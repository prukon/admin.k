<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\NormalizesTrainerProfileIds;
use App\Services\PartnerContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlaceScheduleJournalTrialLessonRequest extends FormRequest
{
    use NormalizesTrainerProfileIds;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $partnerId = (int) app(PartnerContext::class)->partnerId();

        return array_merge([
            'team_id' => [
                'required',
                'integer',
                Rule::exists('teams', 'id')->where(
                    fn ($q) => $q->where('partner_id', $partnerId)->whereNull('deleted_at')
                ),
            ],
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
        ], $this->trainerProfileIdsRules($partnerId));
    }

    protected function prepareForValidation(): void
    {
        $this->prepareTrainerProfileIds();

        if ($this->filled('description') && ! $this->filled('comment')) {
            $this->merge(['comment' => $this->input('description')]);
        }
    }

    public function attributes(): array
    {
        return array_merge([
            'team_id' => 'группа',
            'occurrence_date' => 'дата занятия',
            'lesson_occurrence_status_id' => 'статус',
            'comment' => 'комментарий',
        ], $this->trainerProfileIdsAttributes());
    }

    public function messages(): array
    {
        return array_merge([
            'team_id.required' => 'Выберите группу.',
            'team_id.exists' => 'Группа не найдена.',
            'occurrence_date.required' => 'Укажите дату занятия.',
            'occurrence_date.date_format' => 'Некорректный формат даты занятия.',
            'lesson_occurrence_status_id.required' => 'Выберите статус.',
            'lesson_occurrence_status_id.exists' => 'Выбранный статус не найден или неактивен.',
        ], $this->trainerProfileIdsMessages());
    }
}
