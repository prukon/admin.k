<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\LessonOccurrenceStatus;
use App\Services\PartnerContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateScheduleJournalOccurrenceRequest extends FormRequest
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
            'utss_id' => ['required', 'integer', 'exists:user_team_schedule_slots,id'],
            'occurrence_date' => ['required', 'date_format:Y-m-d'],
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
    }

    public function attributes(): array
    {
        return [
            'user_id' => 'ученик',
            'utss_id' => 'занятие',
            'occurrence_date' => 'дата',
            'lesson_occurrence_status_id' => 'статус',
            'comment' => 'комментарий',
            'trainer_profile_id' => 'тренер',
        ];
    }

    public function messages(): array
    {
        return [
            'utss_id.required' => 'Выберите занятие.',
            'utss_id.exists' => 'Занятие не найдено.',
            'lesson_occurrence_status_id.required' => 'Выберите статус.',
            'lesson_occurrence_status_id.exists' => 'Выбранный статус не найден или неактивен.',
            'trainer_profile_id.exists' => 'Выбранный тренер не найден.',
        ];
    }
}
