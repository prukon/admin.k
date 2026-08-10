<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Support\ScheduleOccurrenceTrainerIds;
use Illuminate\Validation\Rule;

trait NormalizesTrainerProfileIds
{
    protected function prepareTrainerProfileIds(): void
    {
        $normalized = ScheduleOccurrenceTrainerIds::normalize(
            $this->input('trainer_profile_ids'),
            $this->exists('trainer_profile_id') ? $this->input('trainer_profile_id') : null
        );

        $this->merge([
            'trainer_profile_ids' => $normalized,
            // Legacy single: первый выбранный (для старых клиентов / empty-cell).
            'trainer_profile_id' => $normalized[0] ?? null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function trainerProfileIdsRules(int $partnerId): array
    {
        return [
            'trainer_profile_ids' => ['nullable', 'array'],
            'trainer_profile_ids.*' => [
                'integer',
                Rule::exists('trainer_profiles', 'id')->where(
                    fn ($query) => $query->where('partner_id', $partnerId)
                ),
            ],
            // Совместимость: одиночный POST из empty-cell / старых клиентов.
            'trainer_profile_id' => [
                'nullable',
                'integer',
                Rule::exists('trainer_profiles', 'id')->where(
                    fn ($query) => $query->where('partner_id', $partnerId)
                ),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function trainerProfileIdsAttributes(): array
    {
        return [
            'trainer_profile_ids' => 'тренеры',
            'trainer_profile_ids.*' => 'тренер',
            'trainer_profile_id' => 'тренер',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function trainerProfileIdsMessages(): array
    {
        return [
            'trainer_profile_ids.array' => 'Некорректный список тренеров.',
            'trainer_profile_ids.*.integer' => 'Некорректный идентификатор тренера.',
            'trainer_profile_ids.*.exists' => 'Выбранный тренер не найден.',
            'trainer_profile_id.exists' => 'Выбранный тренер не найден.',
        ];
    }
}
