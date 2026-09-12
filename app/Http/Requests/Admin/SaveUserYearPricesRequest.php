<?php

namespace App\Http\Requests\Admin;

use App\Models\UserPrice;
use App\Support\LessonPackagePostpayPermission;
use App\Support\LessonPackageTypePermission;
use App\Support\SettingPricesRequirePackageForPositivePrice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveUserYearPricesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $payload = $this->all();
        if ($payload === [] && $this->getContent() !== '') {
            $decoded = json_decode($this->getContent(), true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        if (isset($payload['prices']) && is_array($payload['prices'])) {
            $payload['prices'] = array_map(static function ($row) {
                if (! is_array($row)) {
                    return $row;
                }

                if (array_key_exists('lesson_package_id', $row)) {
                    $pkg = $row['lesson_package_id'];
                    if ($pkg === '' || $pkg === false) {
                        $row['lesson_package_id'] = null;
                    }
                }

                return $row;
            }, $payload['prices']);
        }

        $this->replace($payload);
    }

    public function rules(): array
    {
        $partnerId = (int) (app('current_partner')->id ?? 0);

        return [
            'user_id' => ['required', 'integer', 'min:1'],
            'team_id' => ['required', 'integer', 'min:1'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'prices' => ['required', 'array'],
            'prices.*.new_month' => ['required', 'date_format:Y-m-d'],
            'prices.*.price' => ['required', 'numeric', 'min:0'],
            'prices.*.lesson_package_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('lesson_packages', 'id')->where(
                    fn ($q) => $q->where('partner_id', $partnerId)
                ),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $rows = $this->input('prices', []);
            if (! is_array($rows) || $rows === []) {
                return;
            }

            $userId = (int) $this->input('user_id');
            $teamId = (int) $this->input('team_id');
            $months = [];
            foreach ($rows as $row) {
                if (is_array($row) && ! empty($row['new_month'])) {
                    $months[] = (string) $row['new_month'];
                }
            }
            $months = array_values(array_unique($months));

            $existingByMonth = [];
            if ($userId > 0 && $teamId > 0 && $months !== []) {
                $existingByMonth = UserPrice::query()
                    ->where('user_id', $userId)
                    ->where('team_id', $teamId)
                    ->whereIn('new_month', $months)
                    ->get(['new_month', 'lesson_package_id', 'price_cents', 'is_paid', 'is_manual_paid'])
                    ->mapWithKeys(static function (UserPrice $row) {
                        $key = $row->new_month instanceof \DateTimeInterface
                            ? $row->new_month->format('Y-m-d')
                            : substr((string) $row->new_month, 0, 10);

                        return [$key => $row];
                    })
                    ->all();
            }

            foreach ($rows as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $packageId = isset($row['lesson_package_id']) ? (int) $row['lesson_package_id'] : 0;
                $monthKey = substr((string) ($row['new_month'] ?? ''), 0, 10);
                $existing = $existingByMonth[$monthKey] ?? null;
                $previous = $existing && $existing->lesson_package_id !== null
                    ? (int) $existing->lesson_package_id
                    : null;

                LessonPackageTypePermission::rejectUnauthorizedPackageId(
                    $v,
                    $this->user(),
                    $packageId > 0 ? $packageId : null,
                    "prices.{$index}.lesson_package_id",
                    $previous,
                );

                if (! ($existing && $existing->effective_is_paid)) {
                    SettingPricesRequirePackageForPositivePrice::rejectIfMissingPackage(
                        $v,
                        $row['price'] ?? null,
                        array_key_exists('lesson_package_id', $row),
                        $row['lesson_package_id'] ?? null,
                        $existing !== null ? (int) $existing->price_cents : null,
                        $previous,
                        "prices.{$index}.lesson_package_id",
                    );
                }
            }
        });
    }

    public function attributes(): array
    {
        return [
            'user_id' => 'ученик',
            'team_id' => 'группа',
            'year' => 'год',
            'prices' => 'цены',
            'prices.*.new_month' => 'месяц',
            'prices.*.price' => 'цена',
            'prices.*.lesson_package_id' => 'абонемент',
        ];
    }

    public function messages(): array
    {
        return [
            'team_id.required' => 'Выберите группу для сохранения цен.',
            'prices.*.lesson_package_id.exists' => 'Выбранный абонемент не найден или недоступен.',
            'prices.*.lesson_package_id.required' => SettingPricesRequirePackageForPositivePrice::MESSAGE,
        ];
    }
}
