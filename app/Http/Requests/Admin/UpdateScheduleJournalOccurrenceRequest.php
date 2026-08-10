<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\NormalizesTrainerProfileIds;
use App\Services\PartnerContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateScheduleJournalOccurrenceRequest extends FormRequest
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
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'utss_id' => [
                Rule::requiredIf(! $this->boolean('create_postpay')),
                'nullable',
                'integer',
                'exists:user_team_schedule_slots,id',
            ],
            'create_postpay' => ['nullable', 'boolean'],
            'team_id' => [
                'nullable',
                'integer',
                'exists:teams,id',
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

        if ($this->has('create_postpay')) {
            $this->merge([
                'create_postpay' => filter_var($this->input('create_postpay'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        if ($this->input('utss_id') === '' || $this->input('utss_id') === null) {
            $this->merge(['utss_id' => null]);
        }
    }

    public function attributes(): array
    {
        return array_merge([
            'user_id' => 'ученик',
            'utss_id' => 'занятие',
            'team_id' => 'группа',
            'occurrence_date' => 'дата',
            'lesson_occurrence_status_id' => 'статус',
            'comment' => 'комментарий',
        ], $this->trainerProfileIdsAttributes());
    }

    public function messages(): array
    {
        return array_merge([
            'utss_id.required' => 'Выберите занятие.',
            'utss_id.exists' => 'Занятие не найдено.',
            'team_id.required' => 'Выберите группу.',
            'lesson_occurrence_status_id.required' => 'Выберите статус.',
            'lesson_occurrence_status_id.exists' => 'Выбранный статус не найден или неактивен.',
        ], $this->trainerProfileIdsMessages());
    }
}
