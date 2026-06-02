<?php

namespace Tests\Feature\Crm\Audit\Concerns;

use App\Enums\AuditEvent;
use App\Enums\AuditLevel;
use App\Models\MyLog;
use Illuminate\Support\Facades\DB;

trait InteractsWithMyLogsAudit
{
    /**
     * @return array<string, mixed>
     */
    protected function auditLogsDataTableParams(): array
    {
        return [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
        ];
    }

    protected function grantPermissionToRoleOnPartner(string $permissionName, int $roleId, ?int $partnerId = null): void
    {
        DB::table('permission_role')->updateOrInsert(
            [
                'partner_id' => $partnerId ?? $this->partner->id,
                'role_id' => $roleId,
                'permission_id' => $this->permissionId($permissionName),
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    protected function grantViewingAllLogs(?int $roleId = null, ?int $partnerId = null): void
    {
        $this->grantPermissionToRoleOnPartner(
            'viewing.all.logs',
            $roleId ?? (int) $this->user->role_id,
            $partnerId
        );
    }

    protected function grantSettingsView(?int $roleId = null): void
    {
        $this->grantPermissionToRoleOnPartner(
            'settings.view',
            $roleId ?? (int) $this->user->role_id
        );
    }

    protected function seedAuditSession(): void
    {
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    protected function createAuditLog(array $attributes): MyLog
    {
        return MyLog::query()->create(array_merge([
            'author_id' => $this->user->id,
            'partner_id' => $this->partner->id,
            'created_at' => now(),
        ], $attributes));
    }

    protected function createEventLog(
        AuditEvent $event,
        string $description,
        array $extra = [],
    ): MyLog {
        return $this->createAuditLog(array_merge([
            'event' => $event->value,
            'level' => $event->level()->value,
            'description' => $description,
        ], $extra));
    }

    /**
     * Строка только с legacy type/action (без event) — не должна участвовать в runtime-фильтрах.
     */
    protected function createLegacyOnlyLog(int $type, int $action, string $description, array $extra = []): MyLog
    {
        return $this->createAuditLog(array_merge([
            'type' => $type,
            'action' => $action,
            'description' => $description,
        ], $extra));
    }

    /**
     * Все комбинации query-параметров вкладки «Настройки → Логи» для smoke HTTP 200.
     *
     * @return list<array<string, mixed>>
     */
    protected function settingsLogsPageFilterVariants(bool $isSuperadmin = false): array
    {
        $defaults = array_merge($this->auditLogsDataTableParams(), [
            'hide_superadmin' => '1',
            'hide_authorizations' => '0',
            'hide_integrations' => '0',
        ]);

        if ($isSuperadmin) {
            $defaults['filter_partner_id'] = 'all';
        }

        $variants = [
            $defaults,
            array_merge($defaults, ['hide_superadmin' => '0']),
            array_merge($defaults, ['hide_authorizations' => '1']),
            array_merge($defaults, ['hide_integrations' => '1']),
            array_merge($defaults, [
                'hide_superadmin' => '0',
                'hide_authorizations' => '1',
                'hide_integrations' => '1',
            ]),
            array_merge($defaults, ['filter_action' => 'unknown']),
            array_merge($defaults, ['filter_level' => AuditLevel::Info->value]),
            array_merge($defaults, ['filter_level' => AuditLevel::Security->value]),
            array_merge($defaults, ['filter_level' => AuditLevel::Integration->value]),
            array_merge($defaults, [
                'filter_action' => AuditEvent::SettingsUpdated->value,
                'filter_author' => 'smoke',
                'filter_target_label' => 'SmokeTarget',
            ]),
            array_merge($defaults, [
                'filter_action' => AuditEvent::AuthLogin->value,
            ]),
            array_merge($defaults, [
                'filter_action' => AuditEvent::TeamCreated->value,
            ]),
            array_merge($defaults, [
                'created_from' => now()->subYear()->toDateString(),
                'created_to' => now()->toDateString(),
            ]),
            array_merge($defaults, [
                'search' => ['value' => 'audit-smoke'],
            ]),
        ];

        foreach (array_slice(AuditEvent::labelsForUi(), 0, 8, true) as $eventValue => $label) {
            $variants[] = array_merge($defaults, ['filter_action' => $eventValue]);
        }

        if ($isSuperadmin) {
            $variants[] = array_merge($defaults, [
                'filter_partner_id' => (string) $this->partner->id,
            ]);
            $variants[] = array_merge($defaults, [
                'filter_partner_id' => (string) $this->foreignPartner->id,
            ]);
        } else {
            $variants[] = array_merge($defaults, ['filter_partner_id' => 'all']);
            $variants[] = array_merge($defaults, [
                'filter_partner_id' => (string) $this->foreignPartner->id,
            ]);
        }

        return $variants;
    }

    /**
     * @return list<array{method: string, url: string, headers?: array<string, string>}>
     */
    protected function settingsLogsSectionRoutes(bool $isSuperadmin = false): array
    {
        $dataParams = $this->auditLogsDataTableParams();
        if ($isSuperadmin) {
            $dataParams['filter_partner_id'] = 'all';
        }

        return [
            [
                'method' => 'GET',
                'url' => route('admin.setting.logs'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'GET',
                'url' => route('settings.logs.data', $dataParams),
            ],
            [
                'method' => 'GET',
                'url' => route('settings.logs.data', array_merge($dataParams, [
                    'filter_action' => AuditEvent::SettingsUpdated->value,
                    'filter_level' => AuditLevel::Security->value,
                    'hide_authorizations' => '1',
                    'hide_integrations' => '1',
                ])),
            ],
            [
                'method' => 'GET',
                'url' => route('settings.logs.data', array_merge($dataParams, [
                    'filter_action' => 'unknown',
                    'hide_superadmin' => '0',
                ])),
            ],
            [
                'method' => 'GET',
                'url' => route('admin.setting.logs', [
                    'created_from' => now()->subMonth()->toDateString(),
                    'filter_action' => AuditEvent::SettingsUpdated->value,
                    'hide_superadmin' => '1',
                ]),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
        ];
    }
}
