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

        $teamRules = [
            'integer',
            Rule::exists('teams', 'id')->where(
                fn ($q) => $q->where('partner_id', $partnerId)->whereNull('deleted_at')
            ),
            new AllowedActorTeam($partnerId),
        ];

        $rules = [
            'lesson_occurrence_status_id' => [
                'required',
                'integer',
                Rule::exists('lesson_occurrence_statuses', 'id')->where(
                    fn ($query) => $query
                        ->where('partner_id', $partnerId)
                        ->where('is_active', true)
                ),
            ],
        ];

        if (is_array($this->input('lessons'))) {
            $rules['team_id'] = array_merge(['nullable'], $teamRules);
            $rules['lessons'] = ['required', 'array', 'min:1', 'max:400'];
            $rules['lessons.*.user_id'] = ['required', 'integer', 'min:1'];
            $rules['lessons.*.occurrence_date'] = ['required', 'date_format:Y-m-d'];
            $rules['lessons.*.team_id'] = array_merge(['nullable'], $teamRules);
        } else {
            $rules['team_id'] = array_merge(['required'], $teamRules);
            $rules['occurrence_date'] = ['required', 'date_format:Y-m-d'];
            $rules['user_ids'] = ['required', 'array', 'min:1', 'max:100'];
            $rules['user_ids.*'] = ['integer', 'min:1'];
        }

        return array_merge($rules, $this->trainerProfileIdsRules($partnerId));
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! is_array($this->input('lessons'))) {
                return;
            }

            $fallbackTeam = $this->input('team_id');
            $perGroupDate = [];
            foreach ($this->input('lessons') as $index => $lesson) {
                if (! is_array($lesson)) {
                    continue;
                }
                $teamId = $lesson['team_id'] ?? null;
                if ($teamId === null || $teamId === '') {
                    $teamId = $fallbackTeam;
                }
                if ($teamId === null || $teamId === '') {
                    $validator->errors()->add('lessons.'.$index.'.team_id', 'Выберите группу.');

                    continue;
                }
                $date = trim((string) ($lesson['occurrence_date'] ?? ''));
                if ($date === '') {
                    continue;
                }
                $key = (string) $teamId.'|'.$date;
                $perGroupDate[$key] = ($perGroupDate[$key] ?? 0) + 1;
            }

            foreach ($perGroupDate as $count) {
                if ($count > 100) {
                    $validator->errors()->add(
                        'lessons',
                        'В одной группе на одну дату можно поставить занятие не больше чем 100 ученикам.'
                    );

                    return;
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->prepareTrainerProfileIds();

        if (is_array($this->input('lessons'))) {
            $this->merge(['lessons' => $this->dedupedLessons($this->input('lessons'))]);

            return;
        }

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

    /**
     * Пары «ученик + дата» из lessons[] либо из прежнего occurrence_date + user_ids[].
     *
     * @return list<array{user_id: int, occurrence_date: string, team_id: int}>
     */
    public function normalizedLessons(): array
    {
        $validated = $this->validated();
        if (isset($validated['lessons']) && is_array($validated['lessons'])) {
            $fallbackTeamId = (int) ($validated['team_id'] ?? 0);
            $rows = [];
            foreach ($validated['lessons'] as $lesson) {
                $rows[] = [
                    'user_id' => (int) $lesson['user_id'],
                    'occurrence_date' => (string) $lesson['occurrence_date'],
                    'team_id' => (int) ($lesson['team_id'] ?? $fallbackTeamId),
                ];
            }

            return $rows;
        }

        $date = (string) $validated['occurrence_date'];
        $teamId = (int) $validated['team_id'];
        $rows = [];
        foreach ($validated['user_ids'] as $id) {
            $rows[] = [
                'user_id' => (int) $id,
                'occurrence_date' => $date,
                'team_id' => $teamId,
            ];
        }

        return $rows;
    }

    public function attributes(): array
    {
        return array_merge([
            'team_id' => 'группа',
            'occurrence_date' => 'дата занятия',
            'user_ids' => 'ученики',
            'user_ids.*' => 'ученик',
            'lessons' => 'занятия',
            'lessons.*.user_id' => 'ученик',
            'lessons.*.occurrence_date' => 'дата занятия',
            'lessons.*.team_id' => 'группа',
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
            'lessons.required' => 'Выберите занятия.',
            'lessons.array' => 'Выберите занятия.',
            'lessons.min' => 'Выберите занятия.',
            'lessons.max' => 'За один раз можно поставить не больше 400 занятий.',
            'lessons.*.user_id.required' => 'Ученик не найден.',
            'lessons.*.user_id.integer' => 'Ученик не найден.',
            'lessons.*.user_id.min' => 'Ученик не найден.',
            'lessons.*.occurrence_date.required' => 'Укажите дату занятия.',
            'lessons.*.occurrence_date.date_format' => 'Некорректный формат даты занятия.',
            'lessons.*.team_id.integer' => 'Выберите группу.',
            'lessons.*.team_id.exists' => 'Группа не найдена.',
            'lesson_occurrence_status_id.required' => 'Выберите статус.',
            'lesson_occurrence_status_id.integer' => 'Выберите статус.',
            'lesson_occurrence_status_id.exists' => 'Выбранный статус не найден или неактивен.',
        ], $this->trainerProfileIdsMessages());
    }

    /**
     * @param  array<mixed>  $lessons
     * @return list<mixed>
     */
    private function dedupedLessons(array $lessons): array
    {
        $clean = [];
        $seen = [];
        foreach ($lessons as $lesson) {
            if (! is_array($lesson)) {
                $clean[] = $lesson;

                continue;
            }

            $userId = $lesson['user_id'] ?? null;
            $date = $lesson['occurrence_date'] ?? null;
            $teamId = $lesson['team_id'] ?? $this->input('team_id');
            $key = (string) $userId.'|'.(string) $date.'|'.(string) $teamId;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $row = [
                'user_id' => $userId,
                'occurrence_date' => $date,
            ];
            if (array_key_exists('team_id', $lesson) && $lesson['team_id'] !== null && $lesson['team_id'] !== '') {
                $row['team_id'] = $lesson['team_id'];
            }
            $clean[] = $row;
        }

        return $clean;
    }
}
