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

        $rules = [
            'team_id' => [
                'required',
                'integer',
                Rule::exists('teams', 'id')->where(
                    fn ($q) => $q->where('partner_id', $partnerId)->whereNull('deleted_at')
                ),
                new AllowedActorTeam($partnerId),
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
        ];

        if (is_array($this->input('lessons'))) {
            $rules['lessons'] = ['required', 'array', 'min:1', 'max:400'];
            $rules['lessons.*.user_id'] = ['required', 'integer', 'min:1'];
            $rules['lessons.*.occurrence_date'] = ['required', 'date_format:Y-m-d'];
        } else {
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

            $perDate = [];
            foreach ($this->input('lessons') as $lesson) {
                if (! is_array($lesson)) {
                    continue;
                }
                $date = trim((string) ($lesson['occurrence_date'] ?? ''));
                if ($date === '') {
                    continue;
                }
                $perDate[$date] = ($perDate[$date] ?? 0) + 1;
            }

            foreach ($perDate as $count) {
                if ($count > 100) {
                    $validator->errors()->add(
                        'lessons',
                        'На одну дату можно поставить занятие не больше чем 100 ученикам.'
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
     * @return list<array{user_id: int, occurrence_date: string}>
     */
    public function normalizedLessons(): array
    {
        $validated = $this->validated();
        if (isset($validated['lessons']) && is_array($validated['lessons'])) {
            $rows = [];
            foreach ($validated['lessons'] as $lesson) {
                $rows[] = [
                    'user_id' => (int) $lesson['user_id'],
                    'occurrence_date' => (string) $lesson['occurrence_date'],
                ];
            }

            return $rows;
        }

        $date = (string) $validated['occurrence_date'];
        $rows = [];
        foreach ($validated['user_ids'] as $id) {
            $rows[] = [
                'user_id' => (int) $id,
                'occurrence_date' => $date,
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
            $key = (string) $userId.'|'.(string) $date;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $clean[] = [
                'user_id' => $userId,
                'occurrence_date' => $date,
            ];
        }

        return $clean;
    }
}
