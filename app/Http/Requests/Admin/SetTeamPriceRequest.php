<?php

namespace App\Http\Requests\Admin;

use App\Models\TeamPrice;
use App\Support\LessonPackagePostpayPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class SetTeamPriceRequest extends FormRequest
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

        $this->replace($payload);
    }

    public function rules(): array
    {
        $partnerId = (int) (app('current_partner')->id ?? 0);

        return [
            'selectedDate' => ['required', 'string', 'max:255'],
            'teamId' => ['required', 'integer', 'min:1'],
            'lesson_package_id' => [
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
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $teamId = (int) $this->input('teamId');
            $packageId = (int) $this->input('lesson_package_id');
            $monthDate = LessonPackagePostpayPermission::monthStringToDate((string) $this->input('selectedDate', ''));

            $previousId = TeamPrice::query()
                ->where('team_id', $teamId)
                ->whereDate('new_month', $monthDate)
                ->value('lesson_package_id');

            LessonPackagePostpayPermission::rejectUnauthorizedPackageId(
                $v,
                $this->user(),
                $packageId,
                'lesson_package_id',
                $previousId !== null ? (int) $previousId : null,
            );
        });
    }

    public function attributes(): array
    {
        return [
            'selectedDate' => 'месяц',
            'teamId' => 'группа',
            'lesson_package_id' => 'абонемент',
        ];
    }

    public function messages(): array
    {
        return [
            'selectedDate.required' => 'Укажите месяц.',
            'teamId.required' => 'Укажите группу.',
            'lesson_package_id.required' => 'Выберите абонемент для группы.',
            'lesson_package_id.exists' => 'Выбранный абонемент не найден или недоступен.',
        ];
    }
}
