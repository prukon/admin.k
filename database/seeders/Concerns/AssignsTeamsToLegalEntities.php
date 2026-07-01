<?php

namespace Database\Seeders\Concerns;

use App\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

trait AssignsTeamsToLegalEntities
{
    /**
     * Явная привязка групп к активным юр. лицам партнёра (dev/test fixtures).
     *
     * Single-entity: default (или единственное) юр. лицо.
     * Multi-entity: чередование по id группы между активными юр. лицами партнёра.
     */
    protected function assignLegalEntitiesToAllTeams(): void
    {
        /** @var Collection<int, Collection<int, object>> $entitiesByPartner */
        $entitiesByPartner = DB::table('partner_legal_entities')
            ->whereNull('deleted_at')
            ->where('is_enabled', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get(['id', 'partner_id', 'is_default'])
            ->groupBy('partner_id');

        if ($entitiesByPartner->isEmpty()) {
            return;
        }

        Team::query()->each(function (Team $team) use ($entitiesByPartner): void {
            $partnerId = (int) $team->partner_id;
            /** @var Collection<int, object>|null $entities */
            $entities = $entitiesByPartner->get($partnerId);

            if ($entities === null || $entities->isEmpty()) {
                return;
            }

            $entityId = $this->resolveLegalEntityIdForTeam((int) $team->id, $entities);
            if ($entityId === null) {
                return;
            }

            if ((int) $team->legal_entity_id !== $entityId) {
                $team->update(['legal_entity_id' => $entityId]);
            }
        });
    }

    /**
     * @param  Collection<int, object>  $entities
     */
    protected function resolveLegalEntityIdForTeam(int $teamId, Collection $entities): ?int
    {
        if ($entities->isEmpty()) {
            return null;
        }

        if ($entities->count() === 1) {
            return (int) $entities->first()->id;
        }

        $index = max(0, $teamId - 1) % $entities->count();

        return (int) $entities->values()[$index]->id;
    }
}
