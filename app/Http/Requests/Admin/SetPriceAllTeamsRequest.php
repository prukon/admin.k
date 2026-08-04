<?php

namespace App\Http\Requests\Admin;

use App\Models\TeamPrice;
use App\Support\LessonPackagePostpayPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class SetPriceAllTeamsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
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

        if (isset($payload['teamsData']) && is_array($payload['teamsData'])) {
            $payload['teamsData'] = array_values(array_filter(
                $payload['teamsData'],
                static function ($row) {
                    if (! is_array($row)) {
                        return false;
                    }
                    $pkg = $row['lesson_package_id'] ?? null;

                    return $pkg !== null && $pkg !== '' && (int) $pkg > 0;
                }
            ));
        }

        $this->replace($payload);
    }

    public function rules(): array
    {
        $partnerId = (int) (app('current_partner')->id ?? 0);

        return [
            'selectedDate' => ['required', 'string', 'max:255'],
            // present|array: пустой список после фильтрации строк без абонемента — no-op (200)
            'teamsData' => ['present', 'array'],
            'teamsData.*.teamId' => ['required', 'integer', 'min:1'],
            'teamsData.*.lesson_package_id' => [
                'required',
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
            if (LessonPackagePostpayPermission::userCanSelect($this->user())) {
                return;
            }

            $rows = $this->input('teamsData', []);
            if (! is_array($rows) || $rows === []) {
                return;
            }

            $monthDate = LessonPackagePostpayPermission::monthStringToDate((string) $this->input('selectedDate', ''));
            $teamIds = [];
            foreach ($rows as $row) {
                if (is_array($row) && isset($row['teamId'])) {
                    $teamIds[] = (int) $row['teamId'];
                }
            }
            $teamIds = array_values(array_unique(array_filter($teamIds)));

            $existingByTeam = [];
            if ($teamIds !== []) {
                $existingByTeam = TeamPrice::query()
                    ->whereDate('new_month', $monthDate)
                    ->whereIn('team_id', $teamIds)
                    ->pluck('lesson_package_id', 'team_id')
                    ->all();
            }

            foreach ($rows as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $packageId = (int) ($row['lesson_package_id'] ?? 0);
                $teamId = (int) ($row['teamId'] ?? 0);
                $previous = array_key_exists($teamId, $existingByTeam)
                    ? ($existingByTeam[$teamId] !== null ? (int) $existingByTeam[$teamId] : null)
                    : null;

                LessonPackagePostpayPermission::rejectUnauthorizedPackageId(
                    $v,
                    $this->user(),
                    $packageId > 0 ? $packageId : null,
                    "teamsData.{$index}.lesson_package_id",
                    $previous,
                );
            }
        });
    }

    public function attributes(): array
    {
        return [
            'selectedDate' => 'месяц',
            'teamsData' => 'группы',
            'teamsData.*.teamId' => 'группа',
            'teamsData.*.lesson_package_id' => 'абонемент',
        ];
    }

    public function messages(): array
    {
        return [
            'selectedDate.required' => 'Укажите месяц.',
            'teamsData.present' => 'Некорректные данные: список групп обязателен.',
            'teamsData.array' => 'Некорректные данные: список групп должен быть массивом.',
            'teamsData.*.lesson_package_id.exists' => 'Выбранный абонемент не найден или недоступен.',
        ];
    }
}
