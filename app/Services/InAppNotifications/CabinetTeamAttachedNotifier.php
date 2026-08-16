<?php

declare(strict_types=1);

namespace App\Services\InAppNotifications;

use App\Models\InAppNotification;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * In-app уведомление админам и тренерам школы: ученик добавил себе группу в ЛК.
 */
final class CabinetTeamAttachedNotifier
{
    public function __construct(
        private readonly InAppNotificationDispatcher $dispatcher,
        private readonly InAppNotificationAudience $audience,
    ) {}

    public function notify(User $actor, User $student, Team $team): void
    {
        try {
            $this->dispatch($actor, $student, $team);
        } catch (Throwable $e) {
            Log::error('[CabinetTeamAttachedNotifier] failed', [
                'actor_id' => (int) $actor->id,
                'student_id' => (int) $student->id,
                'team_id' => (int) $team->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function dispatch(User $actor, User $student, Team $team): void
    {
        $config = (array) config('in_app_notifications.events.cabinet_team_attached', []);
        $roleNames = array_values(array_filter(array_map('strval', (array) ($config['role_names'] ?? []))));
        $roleIds = $this->audience->systemRoleIdsByNames($roleNames);
        $partnerId = (int) $student->partner_id;

        if ($roleIds === [] || $partnerId <= 0) {
            return;
        }

        $this->dispatcher->dispatchEvent([
            'title' => (string) ($config['title'] ?? 'Ученик добавил группу'),
            'body' => $this->buildBody($student, $team),
            'category' => (string) ($config['category'] ?? InAppNotification::CATEGORY_NORMAL),
            'partner_ids' => [$partnerId],
            'role_ids' => $roleIds,
            'ttl_preset' => (string) ($config['ttl_preset'] ?? InAppNotification::TTL_30D),
        ], $actor);
    }

    private function buildBody(User $student, Team $team): string
    {
        $team->loadMissing('location:id,name');
        $student->loadMissing('parentProfile');

        $childName = trim((string) ($student->full_name ?: ''));
        if ($childName === '') {
            $childName = 'Ученик #'.$student->id;
        }

        $locationName = trim((string) ($team->location?->name ?? ''));
        if ($locationName === '') {
            $locationId = (int) ($team->location_id ?? 0);
            $locationName = $locationId > 0 ? ('Объект #'.$locationId) : 'объект не указан';
        }

        $parentName = trim((string) ($student->parent_full_name ?: ''));
        if ($parentName !== '') {
            return sprintf(
                '<p>Родитель: %s<br>Ребёнок: %s.<br>Добавлена группа «%s» (объект «%s»).</p>',
                e($parentName),
                e($childName),
                e((string) $team->title),
                e($locationName)
            );
        }

        return sprintf(
            '<p>%s добавил группу «%s» (объект «%s»).</p>',
            e($childName),
            e((string) $team->title),
            e($locationName)
        );
    }
}
