<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Services\PartnerContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlaceScheduleJournalFixedAbonementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $partnerId = (int) app(PartnerContext::class)->partnerId();

        return [
            'user_lesson_package_id' => [
                'required',
                'integer',
                Rule::exists('user_lesson_packages', 'id'),
            ],
            'team_id' => [
                'required',
                'integer',
                Rule::exists('teams', 'id')->where(
                    fn ($q) => $q->where('partner_id', $partnerId)->whereNull('deleted_at')
                ),
            ],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'weekdays' => ['required', 'array', 'min:1'],
            'weekdays.*' => ['integer', 'in:1,2,3,4,5,6,7'],
            'preview' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('preview')) {
            $this->merge([
                'preview' => filter_var($this->input('preview'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }

    public function attributes(): array
    {
        return [
            'user_lesson_package_id' => 'абонемент',
            'team_id' => 'группа',
            'start_date' => 'дата начала',
            'weekdays' => 'дни недели',
            'weekdays.*' => 'день недели',
            'preview' => 'превью',
        ];
    }

    public function messages(): array
    {
        return [
            'user_lesson_package_id.required' => 'Выберите абонемент.',
            'user_lesson_package_id.exists' => 'Абонемент не найден.',
            'team_id.required' => 'Выберите группу.',
            'team_id.exists' => 'Группа не найдена.',
            'start_date.required' => 'Укажите дату начала.',
            'start_date.date_format' => 'Некорректный формат даты начала.',
            'weekdays.required' => 'Выберите хотя бы один день недели.',
            'weekdays.min' => 'Выберите хотя бы один день недели.',
            'weekdays.*.in' => 'Некорректный день недели.',
        ];
    }
}
