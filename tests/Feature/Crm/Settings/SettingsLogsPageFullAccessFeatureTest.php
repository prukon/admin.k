<?php

namespace Tests\Feature\Crm\Settings;

use App\Models\MyLog;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Раздел «Настройки → Логи» (/admin/settings/logs) и GET settings.logs.data:
 * доступ (viewing.all.logs + settings.view для страницы), DataTables, фильтры, SUPERADMIN_ALL_OR_FILTER.
 */
final class SettingsLogsPageFullAccessFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    public function test_guest_cannot_access_any_logs_section_endpoint(): void
    {
        Auth::logout();

        foreach ($this->allSectionRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_without_viewing_all_logs_gets_403_on_all_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('viewing.all.logs', $this->partner);
        $this->grantSettingsView((int) $actor->role_id);
        $this->actingAs($actor);

        foreach ($this->allSectionRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без viewing.all.logs: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_without_settings_view_gets_403_on_page_but_logs_data_ok_with_viewing_all_logs(): void
    {
        $actor = $this->createUserWithoutPermission('settings.view', $this->partner);
        $this->grantViewingAllLogs((int) $actor->role_id);
        $this->actingAs($actor);

        $this->get(route('admin.setting.logs'))->assertForbidden();

        $this->getJson(route('settings.logs.data', $this->baseDataTableParams()))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
    }

    public function test_user_with_viewing_all_logs_and_settings_view_all_section_endpoints_return_200(): void
    {
        $actor = $this->createUserWithoutPermission('viewing.all.logs', $this->partner);
        $this->grantViewingAllLogs((int) $actor->role_id);
        $this->grantSettingsView((int) $actor->role_id);
        $this->actingAs($actor);

        $this->assertAllSectionEndpointsSucceedForAuthorizedUser();
    }

    public function test_superadmin_all_section_endpoints_return_200(): void
    {
        $this->asSuperadmin();
        $this->user->unsetRelation('role');

        $this->assertAllSectionEndpointsSucceedForAuthorizedUser(isSuperadmin: true);
    }

    public function test_index_page_returns_200_with_logs_tab_and_toolbar(): void
    {
        $this->grantPermissionToCurrentRole('settings.view');
        $this->grantPermissionToCurrentRole('viewing.all.logs');

        $this->get(route('admin.setting.logs'))
            ->assertOk()
            ->assertViewIs('admin.setting.index')
            ->assertViewHas('activeTab', 'logs')
            ->assertSee('payments-report-toolbar', false)
            ->assertSee('>Фильтры</span>', false)
            ->assertSee('id="settingsLogsTable"', false)
            ->assertSee('id="settings-logs-filters"', false);
    }

    public function test_superadmin_index_shows_partner_filter_and_table_column(): void
    {
        $this->asSuperadmin();
        $this->user->unsetRelation('role');
        $this->grantPermissionToCurrentRole('viewing.all.logs');

        $this->get(route('admin.setting.logs'))
            ->assertOk()
            ->assertSee('id="settings-logs-filter-partner"', false)
            ->assertSee('value="all"', false)
            ->assertSee('>Партнёр</th>', false);
    }

    public function test_admin_index_hides_partner_filter_and_column(): void
    {
        $this->grantPermissionToCurrentRole('settings.view');
        $this->grantPermissionToCurrentRole('viewing.all.logs');

        $html = $this->get(route('admin.setting.logs'))->assertOk()->getContent();

        $this->assertStringNotContainsString('id="settings-logs-filter-partner"', $html);
        $this->assertStringNotContainsString('>Партнёр</th>', $html);
    }

    public function test_logs_data_returns_datatables_json_structure(): void
    {
        $this->grantPermissionToCurrentRole('viewing.all.logs');

        $this->createPartnerLog($this->partner->id, 'structure-smoke');

        $this->getJson(route('settings.logs.data', $this->baseDataTableParams()))
            ->assertOk()
            ->assertJsonStructure([
                'draw',
                'recordsTotal',
                'recordsFiltered',
                'data' => [
                    '*' => [
                        'id',
                        'created_at',
                        'action',
                        'author',
                        'target_label',
                        'description',
                    ],
                ],
            ]);
    }

    public function test_superadmin_logs_data_includes_partner_title_column(): void
    {
        $this->asSuperadmin();
        $this->user->unsetRelation('role');
        $this->grantPermissionToCurrentRole('viewing.all.logs');

        $this->createPartnerLog($this->partner->id, 'with-partner-col');

        $row = collect($this->getJson(route('settings.logs.data', array_merge(
            $this->baseDataTableParams(),
            ['filter_partner_id' => 'all']
        )))->json('data'))->first();

        $this->assertIsArray($row);
        $this->assertArrayHasKey('partner_title', $row);
        $this->assertSame($this->partner->title, $row['partner_title']);
    }

    public function test_superadmin_logs_data_sees_all_partners_when_filter_all(): void
    {
        $this->asSuperadmin();
        $this->user->unsetRelation('role');
        $this->grantPermissionToCurrentRole('viewing.all.logs');

        $this->createPartnerLog($this->partner->id, 'scope-all-home');
        $this->createPartnerLog($this->foreignPartner->id, 'scope-all-foreign');

        $resp = $this->getJson(route('settings.logs.data', array_merge(
            $this->baseDataTableParams(),
            ['filter_partner_id' => 'all']
        )));

        $resp->assertOk();
        $descriptions = collect($resp->json('data'))->pluck('description')->all();
        $this->assertContains('scope-all-home', $descriptions);
        $this->assertContains('scope-all-foreign', $descriptions);
    }

    public function test_superadmin_logs_data_filters_single_partner(): void
    {
        $this->asSuperadmin();
        $this->user->unsetRelation('role');
        $this->grantPermissionToCurrentRole('viewing.all.logs');

        $this->createPartnerLog($this->partner->id, 'filter-partner-home');
        $this->createPartnerLog($this->foreignPartner->id, 'filter-partner-foreign');

        $resp = $this->getJson(route('settings.logs.data', array_merge(
            $this->baseDataTableParams(),
            ['filter_partner_id' => (string) $this->foreignPartner->id]
        )));

        $resp->assertOk();
        $descriptions = collect($resp->json('data'))->pluck('description')->all();
        $this->assertContains('filter-partner-foreign', $descriptions);
        $this->assertNotContains('filter-partner-home', $descriptions);
    }

    public function test_non_superadmin_logs_data_ignores_filter_all_and_scopes_to_current_partner(): void
    {
        $this->grantPermissionToCurrentRole('viewing.all.logs');

        $this->createPartnerLog($this->partner->id, 'scope-admin-home');
        $this->createPartnerLog($this->foreignPartner->id, 'scope-admin-foreign');

        $resp = $this->getJson(route('settings.logs.data', array_merge(
            $this->baseDataTableParams(),
            ['filter_partner_id' => 'all']
        )));

        $resp->assertOk();
        $descriptions = collect($resp->json('data'))->pluck('description')->all();
        $this->assertContains('scope-admin-home', $descriptions);
        $this->assertNotContains('scope-admin-foreign', $descriptions);
    }

    public function test_logs_data_filters_by_created_date_range(): void
    {
        $this->grantPermissionToCurrentRole('viewing.all.logs');

        $this->createPartnerLog($this->partner->id, 'date-old', '2024-03-01 10:00:00');
        $this->createPartnerLog($this->partner->id, 'date-new', '2025-08-15 12:00:00');

        $resp = $this->getJson(route('settings.logs.data', array_merge(
            $this->baseDataTableParams(),
            [
                'created_from' => '2025-01-01',
                'created_to' => '2025-12-31',
            ]
        )));

        $resp->assertOk();
        $descriptions = collect($resp->json('data'))->pluck('description')->all();
        $this->assertContains('date-new', $descriptions);
        $this->assertNotContains('date-old', $descriptions);
    }

    public function test_logs_data_filters_by_action_author_and_target_label(): void
    {
        $this->grantPermissionToCurrentRole('viewing.all.logs');

        $this->user->forceFill([
            'name' => 'Уникальный',
            'lastname' => 'АвторЛогов',
        ])->save();

        MyLog::query()->create([
            'type' => 1,
            'action' => 31,
            'author_id' => $this->user->id,
            'partner_id' => $this->partner->id,
            'target_type' => 'App\Models\Team',
            'target_id' => 1,
            'target_label' => 'Группа Альфа',
            'description' => 'match-all-filters',
            'created_at' => now(),
        ]);

        MyLog::query()->create([
            'type' => 1,
            'action' => 70,
            'author_id' => $this->user->id,
            'partner_id' => $this->partner->id,
            'target_type' => 'App\Models\Setting',
            'target_id' => $this->partner->id,
            'target_label' => 'Другое',
            'description' => 'no-match-filters',
            'created_at' => now(),
        ]);

        $resp = $this->getJson(route('settings.logs.data', array_merge(
            $this->baseDataTableParams(),
            [
                'filter_action' => '31',
                'filter_author' => 'АвторЛогов',
                'filter_target_label' => 'Альфа',
            ]
        )));

        $resp->assertOk();
        $descriptions = collect($resp->json('data'))->pluck('description')->all();
        $this->assertContains('match-all-filters', $descriptions);
        $this->assertNotContains('no-match-filters', $descriptions);
    }

    public function test_superadmin_logs_data_order_by_partner_title_asc(): void
    {
        $this->asSuperadmin();
        $this->user->unsetRelation('role');
        $this->grantPermissionToCurrentRole('viewing.all.logs');

        $partnerA = Partner::factory()->create(['title' => 'AAA Логи Партнёр']);
        $partnerZ = Partner::factory()->create(['title' => 'ZZZ Логи Партнёр']);

        $this->createPartnerLog($partnerZ->id, 'order-z-log', null, 'OrderPartnerSort');
        $this->createPartnerLog($partnerA->id, 'order-a-log', null, 'OrderPartnerSort');

        $resp = $this->getJson(route('settings.logs.data', array_merge(
            $this->superadminDataTableParams(),
            [
                'filter_partner_id' => 'all',
                'filter_target_label' => 'OrderPartnerSort',
                'order' => [
                    ['column' => 2, 'dir' => 'asc'],
                ],
            ]
        )));

        $resp->assertOk();
        $descriptions = collect($resp->json('data'))->pluck('description')->values()->all();
        $this->assertNotEmpty($descriptions);
        $this->assertSame('order-a-log', $descriptions[0]);
    }

    public function test_datatable_filter_query_variants_return_200(): void
    {
        $this->asSuperadmin();
        $this->user->unsetRelation('role');
        $this->grantPermissionToCurrentRole('viewing.all.logs');

        $this->createPartnerLog($this->partner->id, 'variants-smoke');

        $queries = [
            array_merge($this->baseDataTableParams(), ['filter_partner_id' => 'all']),
            array_merge($this->baseDataTableParams(), [
                'filter_partner_id' => (string) $this->partner->id,
                'created_from' => now()->subYear()->toDateString(),
                'created_to' => now()->toDateString(),
            ]),
            array_merge($this->baseDataTableParams(), [
                'filter_action' => '70',
                'filter_author' => 'test',
                'filter_target_label' => 'Test',
            ]),
            array_merge($this->baseDataTableParams(), [
                'search' => ['value' => 'smoke'],
            ]),
        ];

        foreach ($queries as $params) {
            $this->getJson(route('settings.logs.data', $params))->assertOk();
        }
    }

    private function assertAllSectionEndpointsSucceedForAuthorizedUser(bool $isSuperadmin = false): void
    {
        $this->get(route('admin.setting.logs'))
            ->assertOk()
            ->assertViewIs('admin.setting.index')
            ->assertViewHas('activeTab', 'logs');

        $dataParams = $this->baseDataTableParams();
        if ($isSuperadmin) {
            $dataParams['filter_partner_id'] = 'all';
        }

        $this->getJson(route('settings.logs.data', $dataParams))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->get(route('admin.setting.logs', [
            'created_from' => now()->subMonth()->toDateString(),
            'filter_partner_id' => $isSuperadmin ? 'all' : null,
        ]))->assertOk();

        $filterParams = array_merge($this->baseDataTableParams(), [
            'created_from' => now()->subMonth()->toDateString(),
            'created_to' => now()->toDateString(),
            'filter_action' => '70',
            'filter_author' => 'Иван',
            'filter_target_label' => 'Test',
        ]);
        if ($isSuperadmin) {
            $filterParams['filter_partner_id'] = (string) $this->partner->id;
        }

        $this->getJson(route('settings.logs.data', $filterParams))->assertOk();
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function allSectionRoutesPayload(): array
    {
        return [
            [
                'method' => 'GET',
                'url' => route('admin.setting.logs'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'GET',
                'url' => route('settings.logs.data', $this->baseDataTableParams()),
            ],
            [
                'method' => 'GET',
                'url' => route('settings.logs.data', array_merge(
                    $this->baseDataTableParams(),
                    [
                        'filter_partner_id' => 'all',
                        'created_from' => now()->subYear()->toDateString(),
                        'filter_action' => '70',
                    ]
                )),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function baseDataTableParams(): array
    {
        return [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
        ];
    }

    private function grantViewingAllLogs(int $roleId): void
    {
        DB::table('permission_role')->updateOrInsert(
            [
                'partner_id' => $this->partner->id,
                'role_id' => $roleId,
                'permission_id' => $this->permissionId('viewing.all.logs'),
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function grantSettingsView(int $roleId): void
    {
        DB::table('permission_role')->updateOrInsert(
            [
                'partner_id' => $this->partner->id,
                'role_id' => $roleId,
                'permission_id' => $this->permissionId('settings.view'),
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function grantPermissionToCurrentRole(string $permissionName): void
    {
        DB::table('permission_role')->updateOrInsert(
            [
                'partner_id' => $this->partner->id,
                'role_id' => $this->user->role_id,
                'permission_id' => $this->permissionId($permissionName),
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function superadminDataTableParams(): array
    {
        return array_merge($this->baseDataTableParams(), [
            'columns' => [
                ['name' => 'id'],
                ['name' => 'created_at'],
                ['name' => 'partner_title'],
                ['name' => 'action'],
                ['name' => 'author'],
                ['name' => 'target_label'],
                ['name' => 'description'],
            ],
        ]);
    }

    private function createPartnerLog(
        int $partnerId,
        string $description,
        ?string $createdAt = null,
        string $targetLabel = 'Test',
    ): void {
        MyLog::query()->create([
            'type' => 1,
            'action' => 70,
            'author_id' => $this->user->id,
            'partner_id' => $partnerId,
            'target_type' => 'App\Models\Setting',
            'target_id' => $partnerId,
            'target_label' => $targetLabel,
            'description' => $description,
            'created_at' => $createdAt ? Carbon::parse($createdAt) : now(),
        ]);
    }
}
