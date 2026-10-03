<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\NormalizesTrainerProfileIds;
use App\Rules\AllowedActorTeam;
use App\Services\PartnerContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlaceScheduleJournalBulkLessonsRequest extends FormRequest
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
                new AllowedActorTeam($partnerId),
            ],
            'occurrence_date' => ['required', 'date_format:Y-m-d'],
            'user_ids' => ['required', 'array', 'min:1', 'max:100'],
            'user_ids.*' => ['integer', 'min:1'],
            'lesson_occurrence_status_id' => [
                'required',
                'integer',
                Rule::exists('lesson_occurrence_statuses', 'id')->where(
                    fn ($query) => $query
                        ->where('partner_id', $partnerId)
                        ->where('is_active', true)
                ),
            ],
        ], $this->trainerProfileIdsRules($partnerId));
    }

    protected function prepareForValidation(): void
    {
        $this->prepareTrainerProfileIds();

        $ids = $this->input('user_ids', []);
        if (! is_array($ids)) {
            return;
        }

        $clean = [];
        foreach ($ids as $id) {
            $n = (int) $id;
            if ($n > 0) {
                $clean[$n] = $n;
            }
        }

        $this->merge(['user_ids' => array_values($clean)]);
    }

    public function attributes(): array
    {
        return array_merge([
            'team_id' => 'группа',
            'occurrence_date' => 'дата занятия',
            'user_ids' => 'ученики',
            'user_ids.*' => 'ученик',
            'lesson_occurrence_status_id' => 'статус',
        ], $this->trainerProfileIdsAttributes());
    }

    public function messages(): array
    {
        return array_merge([
            'team_id.required' => 'Выберите группу.',
            'team_id.integer' => 'Выберите группу.',
            'team_id.exists' => 'Группа не найдена.',
            'occurrence_date.required' => 'Укажите дату занятия.',
            'occurrence_date.date_format' => 'Некорректный формат даты занятия.',
            'user_ids.required' => 'Выберите учеников.',
            'user_ids.array' => 'Выберите учеников.',
            'user_ids.min' => 'Выберите учеников.',
            'user_ids.max' => 'За один раз можно поставить занятие не больше чем 100 ученикам.',
            'user_ids.*.integer' => 'Ученик не найден.',
            'user_ids.*.min' => 'Ученик не найден.',
            'lesson_occurrence_status_id.required' => 'Выберите статус.',
            'lesson_occurrence_status_id.integer' => 'Выберите статус.',
            'lesson_occurrence_status_id.exists' => 'Выбранный статус не найден или неактивен.',
        ], $this->trainerProfileIdsMessages());
    }
}
