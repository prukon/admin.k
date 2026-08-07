<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Services\PartnerContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlaceScheduleJournalSingleLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $partnerId = (int) app(PartnerContext::class)->partnerId();

        return [
            'team_id' => [
                'required',
                'integer',
                Rule::exists('teams', 'id')->where(
                    fn ($q) => $q->where('partner_id', $partnerId)->whereNull('deleted_at')
                ),
            ],
            'occurrence_date' => ['required', 'date_format:Y-m-d'],
            'user_lesson_package_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('user_lesson_packages', 'id'),
            ],
            'lesson_package_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('lesson_packages', 'id')->where(
                    fn ($q) => $q->where('partner_id', $partnerId)->where('schedule_type', 'no_schedule')
                ),
            ],
            'fee_amount' => [
                'required_with:lesson_package_id',
                'nullable',
                'numeric',
                'min:0',
                'max:999999.99',
            ],
            'lesson_occurrence_status_id' => [
                'required',
                'integer',
                Rule::exists('lesson_occurrence_statuses', 'id')->where(
                    fn ($query) => $query
                        ->where('partner_id', $partnerId)
                        ->where('is_active', true)
                ),
            ],
            'comment' => ['nullable', 'string', 'max:2000'],
            'trainer_profile_id' => [
                'nullable',
                'integer',
                Rule::exists('trainer_profiles', 'id')->where(
                    fn ($query) => $query->where('partner_id', $partnerId)
                ),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $raw = $this->input('trainer_profile_id');

        if ($raw === '' || $raw === 'none' || $raw === '0') {
            $this->merge(['trainer_profile_id' => null]);
        }

        if ($this->filled('description') && ! $this->filled('comment')) {
            $this->merge(['comment' => $this->input('description')]);
        }

        foreach (['user_lesson_package_id', 'lesson_package_id'] as $key) {
            $v = $this->input($key);
            if ($v === '' || $v === '0' || $v === null) {
                $this->merge([$key => null]);
            }
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $ulpId = (int) ($this->input('user_lesson_package_id') ?? 0);
            $templateId = (int) ($this->input('lesson_package_id') ?? 0);

            if ($ulpId > 0 && $templateId > 0) {
                $validator->errors()->add(
                    'user_lesson_package_id',
                    'Укажите либо свободное назначение, либо шаблон разового занятия.'
                );

                return;
            }

            if ($ulpId < 1 && $templateId < 1) {
                $validator->errors()->add(
                    'lesson_package_id',
                    'Укажите назначение или шаблон разового занятия.'
                );
            }
        });
    }

    public function attributes(): array
    {
        return [
            'team_id' => 'группа',
            'occurrence_date' => 'дата занятия',
            'user_lesson_package_id' => 'назначение',
            'lesson_package_id' => 'шаблон',
            'fee_amount' => 'стоимость',
            'lesson_occurrence_status_id' => 'статус',
            'comment' => 'комментарий',
            'trainer_profile_id' => 'тренер',
        ];
    }

    public function messages(): array
    {
        return [
            'team_id.required' => 'Выберите группу.',
            'team_id.exists' => 'Группа не найдена.',
            'occurrence_date.required' => 'Укажите дату занятия.',
            'occurrence_date.date_format' => 'Некорректный формат даты занятия.',
            'user_lesson_package_id.exists' => 'Назначение не найдено.',
            'lesson_package_id.exists' => 'Шаблон разового занятия не найден.',
            'fee_amount.required_with' => 'Укажите стоимость разового занятия.',
            'fee_amount.numeric' => 'Стоимость должна быть числом.',
            'fee_amount.min' => 'Стоимость не может быть отрицательной.',
            'lesson_occurrence_status_id.required' => 'Выберите статус.',
            'lesson_occurrence_status_id.exists' => 'Выбранный статус не найден или неактивен.',
            'trainer_profile_id.exists' => 'Выбранный тренер не найден.',
        ];
    }
}
