<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
