<?php

namespace App\Http\Requests\Admin;

use App\Models\LessonOccurrenceStatus;
use App\Services\PartnerContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateScheduleCellRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $partnerId = (int) app(PartnerContext::class)->partnerId();

        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'lesson_occurrence_status_id' => [
                'required',
                'integer',
                Rule::exists('lesson_occurrence_statuses', 'id')->where(
                    fn ($query) => $query
                        ->where('partner_id', $partnerId)
                        ->where('is_active', true)
                ),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
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

        // Совместимость со старым именем поля в клиенте (если осталось).
        if (! $this->filled('lesson_occurrence_status_id') && $this->filled('status_id')) {
            $this->merge([
                'lesson_occurrence_status_id' => $this->input('status_id'),
            ]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $partnerId = (int) app(PartnerContext::class)->partnerId();
            $attendedId = LessonOccurrenceStatus::attendedIdForPartner($partnerId);
            $statusId = (int) $this->input('lesson_occurrence_status_id');

            if ($attendedId === null || $statusId !== $attendedId) {
                return;
            }

            $trainerId = $this->input('trainer_profile_id');
            if ($trainerId !== null && $trainerId !== '') {
                return;
            }

            // «Без тренера» допустимо для «Посетил»
        });
    }

    public function attributes(): array
    {
        return [
            'user_id' => 'ученик',
            'date' => 'дата',
            'lesson_occurrence_status_id' => 'статус',
            'description' => 'комментарий',
            'trainer_profile_id' => 'тренер',
        ];
    }

    public function messages(): array
    {
        return [
            'lesson_occurrence_status_id.required' => 'Выберите статус.',
            'lesson_occurrence_status_id.exists' => 'Выбранный статус не найден или неактивен.',
            'trainer_profile_id.exists' => 'Выбранный тренер не найден.',
        ];
    }
}
