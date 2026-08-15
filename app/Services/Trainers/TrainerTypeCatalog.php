<?php

declare(strict_types=1);

namespace App\Services\Trainers;

use App\Models\TrainerProfile;
use App\Models\TrainerSalaryDraftLine;
use App\Models\TrainerType;
use App\Services\Schedule\TrainerSalary\Schemes\Kansas\KansasTrainerSalaryScheme;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TrainerTypeCatalog
{
    public function ensureSystemType(int $partnerId): TrainerType
    {
        if ($partnerId <= 0) {
            throw new \InvalidArgumentException('partner_id обязателен для системного типа тренера.');
        }

        return DB::transaction(function () use ($partnerId) {
            $partner = DB::table('partners')->where('id', $partnerId)->lockForUpdate()->first();
            if ($partner === null) {
                throw new \InvalidArgumentException('Партнёр не найден для системного типа тренера.');
            }

            $existing = TrainerType::query()
                ->where('partner_id', $partnerId)
                ->where('is_system', true)
                ->first();

            if ($existing !== null) {
                if (! $existing->is_enabled) {
                    $existing->is_enabled = true;
                    $existing->save();
                }

                return $existing;
            }

            try {
                return TrainerType::query()->create([
                    'partner_id' => $partnerId,
                    'name' => TrainerType::SYSTEM_DEFAULT_NAME,
                    'sort_order' => 0,
                    'is_enabled' => true,
                    'is_system' => true,
                    'rate_per_training_cents' => 0,
                    'base_premium_cents' => 0,
                ]);
            } catch (QueryException $e) {
                if ((int) ($e->errorInfo[1] ?? 0) !== 1062) {
                    throw $e;
                }

                return TrainerType::query()->create([
                    'partner_id' => $partnerId,
                    'name' => TrainerType::SYSTEM_DEFAULT_NAME . ' #' . $partnerId,
                    'sort_order' => 0,
                    'is_enabled' => true,
                    'is_system' => true,
                    'rate_per_training_cents' => 0,
                    'base_premium_cents' => 0,
                ]);
            }
        });
    }

    /**
     * @return Collection<int, TrainerType>
     */
    public function typesForPartner(int $partnerId, bool $onlyEnabled = false): Collection
    {
        $this->ensureSystemType($partnerId);

        $query = TrainerType::query()
            ->where('partner_id', $partnerId)
            ->withCount([
                'trainerProfiles as trainer_profiles_count' => static function ($query): void {
                    $query->withTrashed();
                },
            ])
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($onlyEnabled) {
            $query->where('is_enabled', true);
        }

        return $query->get();
    }

    /**
     * @param  array{
     *     name: string,
     *     sort_order?: int,
     *     is_enabled?: bool,
     *     rate_per_training?: mixed,
     *     base_premium?: mixed
     * }  $data
     */
    public function create(int $partnerId, array $data): TrainerType
    {
        $this->ensureSystemType($partnerId);

        try {
            $type = TrainerType::query()->create([
                'partner_id' => $partnerId,
                'name' => trim((string) $data['name']),
                'sort_order' => (int) ($data['sort_order'] ?? 10),
                'is_enabled' => (bool) ($data['is_enabled'] ?? true),
                'is_system' => false,
                'rate_per_training_cents' => $this->centsFromRequest($data['rate_per_training'] ?? 0, 'rate_per_training'),
                'base_premium_cents' => $this->centsFromRequest($data['base_premium'] ?? 0, 'base_premium'),
            ]);
        } catch (QueryException $e) {
            $this->throwDuplicateNameIfNeeded($e);
            throw $e;
        }

        $this->recomputeKansasDraftsForType($type);

        return $type;
    }

    /**
     * @param  array{
     *     name?: string,
     *     sort_order?: int,
     *     is_enabled?: bool,
     *     rate_per_training?: mixed,
     *     base_premium?: mixed
     * }  $data
     */
    public function update(TrainerType $type, array $data): TrainerType
    {
        if (array_key_exists('is_enabled', $data) && $type->is_system && ! (bool) $data['is_enabled']) {
            throw ValidationException::withMessages([
                'is_enabled' => ['Системный тип нельзя отключить.'],
            ]);
        }

        $type->name = trim((string) ($data['name'] ?? $type->name));
        $type->sort_order = (int) ($data['sort_order'] ?? $type->sort_order);
        if (! $type->is_system) {
            $type->is_enabled = (bool) ($data['is_enabled'] ?? $type->is_enabled);
        } else {
            $type->is_enabled = true;
        }
        $type->rate_per_training_cents = $this->centsFromRequest(
            $data['rate_per_training'] ?? Money::fromCents((int) $type->rate_per_training_cents),
            'rate_per_training',
        );
        $type->base_premium_cents = $this->centsFromRequest(
            $data['base_premium'] ?? Money::fromCents((int) $type->base_premium_cents),
            'base_premium',
        );

        try {
            $type->save();
        } catch (QueryException $e) {
            $this->throwDuplicateNameIfNeeded($e);
            throw $e;
        }

        $this->recomputeKansasDraftsForType($type);

        return $type->refresh();
    }

    public function delete(TrainerType $type): void
    {
        if ($type->is_system) {
            throw ValidationException::withMessages([
                'name' => ['Системный тип удалить нельзя.'],
            ]);
        }

        $assigned = TrainerProfile::withTrashed()
            ->where('trainer_type_id', $type->id)
            ->exists();

        if ($assigned) {
            throw ValidationException::withMessages([
                'name' => ['Тип назначен тренерам. Сначала смените тип у этих тренеров.'],
            ]);
        }

        $type->delete();
    }

    public function assignToProfile(TrainerProfile $profile, TrainerType $type): void
    {
        if ((int) $type->partner_id !== (int) $profile->partner_id) {
            throw ValidationException::withMessages([
                'trainer_type_id' => ['Выберите тип тренера из списка.'],
            ]);
        }

        $changed = (int) $profile->trainer_type_id !== (int) $type->id;
        $profile->trainer_type_id = (int) $type->id;
        $profile->save();

        if ($changed) {
            $this->recomputeKansasDraftsForProfile($profile);
        }
    }

    public function ensureProfileHasType(TrainerProfile $profile): TrainerType
    {
        $partnerId = (int) $profile->partner_id;
        $type = $profile->trainerType;
        if ($type !== null && (int) $type->partner_id === $partnerId) {
            return $type;
        }

        $type = $this->ensureSystemType($partnerId);
        $profile->forceFill(['trainer_type_id' => $type->id])->save();

        return $type;
    }

    /**
     * @return array{rate_per_training_cents: int, base_premium_cents: int}
     */
    public function ratesForProfile(TrainerProfile $profile): array
    {
        $type = $this->ensureProfileHasType($profile);

        return [
            'rate_per_training_cents' => (int) $type->rate_per_training_cents,
            'base_premium_cents' => (int) $type->base_premium_cents,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(TrainerType $type): array
    {
        $trainersCount = array_key_exists('trainer_profiles_count', $type->getAttributes())
            ? (int) $type->trainer_profiles_count
            : (int) $type->trainerProfiles()->withTrashed()->count();

        return [
            'id' => (int) $type->id,
            'name' => (string) $type->name,
            'sort_order' => (int) $type->sort_order,
            'is_enabled' => (int) $type->is_enabled,
            'is_system' => (int) $type->is_system,
            'rate_per_training' => Money::fromCents((int) $type->rate_per_training_cents),
            'base_premium' => Money::fromCents((int) $type->base_premium_cents),
            'trainers_count' => $trainersCount,
            'can_delete' => ! $type->is_system && $trainersCount === 0,
        ];
    }

    public function recomputeKansasDraftsForType(TrainerType $type): void
    {
        $profileIds = TrainerProfile::query()
            ->where('trainer_type_id', $type->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->recomputeKansasDraftsForProfileIds($profileIds);
    }

    public function recomputeKansasDraftsForProfile(TrainerProfile $profile): void
    {
        $this->recomputeKansasDraftsForProfileIds([(int) $profile->id]);
    }

    /**
     * @param  list<int>  $profileIds
     */
    private function recomputeKansasDraftsForProfileIds(array $profileIds): void
    {
        $profileIds = array_values(array_filter($profileIds, fn (int $id) => $id > 0));
        if ($profileIds === []) {
            return;
        }

        $drafts = TrainerSalaryDraftLine::query()
            ->whereIn('trainer_profile_id', $profileIds)
            ->whereHas('period', fn ($q) => $q->where('scheme_code', KansasTrainerSalaryScheme::CODE))
            ->with(['period', 'trainerProfile.trainerType'])
            ->get();

        if ($drafts->isEmpty()) {
            return;
        }

        $scheme = app(KansasTrainerSalaryScheme::class);
        foreach ($drafts as $draft) {
            $scheme->compute($draft);
            $draft->save();
        }
    }

    private function centsFromRequest(mixed $value, string $field): int
    {
        try {
            return Money::toCentsOrFail($value);
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages([
                $field => ['Некорректная денежная сумма.'],
            ]);
        }
    }

    private function throwDuplicateNameIfNeeded(QueryException $e): void
    {
        $code = $e->errorInfo[1] ?? null;
        if ((int) $code === 1062) {
            throw ValidationException::withMessages([
                'name' => ['Тип тренера с таким названием уже существует.'],
            ]);
        }
    }
}
