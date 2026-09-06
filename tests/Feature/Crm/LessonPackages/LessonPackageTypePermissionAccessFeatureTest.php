<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\PaymentNotificationRule;
use App\Models\Team;
use App\Models\TeamPrice;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Models\UserPrice;
use App\Services\SettingPrices\MonthlyPricesProlongReport;
use App\Services\TeamUserSyncService;
use App\Support\LessonPackageTypePermission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Скрытые lessonPackages.type.fixed / flexible / no_schedule:
 * UI, store/update шаблона, фильтр, «Установка цен», уведомления об оплате.
 *
 * Postpay: PostpayTypePermissionAccessFeatureTest.
 *
 * @see LessonPackagesTypePermissionsCatalogFeatureTest
 */
final class LessonPackageTypePermissionAccessFeatureTest extends CrmTestCase
{
    private Team $team;

    private User $student;

    private LessonPackage $fixedPackage;

    private LessonPackage $flexiblePackage;

    private LessonPackage $noSchedulePackage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asAdmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
            'title' => 'Группа типы',
        ]);

        $this->student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
            'name' => 'Type',
            'lastname' => 'Student',
        ]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($this->student, [(int) $this->team->id]);

        $this->fixedPackage = LessonPackage::factory()
            ->forPartner((int) $this->partner->id)
            ->create([
                'name' => 'Фикс доступ',
                'schedule_type' => LessonPackage::SCHEDULE_TYPE_FIXED,
                'price_cents' => 10000,
                'lessons_count' => 8,
            ]);
        $this->flexiblePackage = LessonPackage::factory()
            ->forPartner((int) $this->partner->id)
            ->create([
                'name' => 'Предоплата доступ',
                'schedule_type' => LessonPackage::SCHEDULE_TYPE_FLEXIBLE,
                'price_cents' => 20000,
                'lessons_count' => 8,
            ]);
        $this->noSchedulePackage = LessonPackage::factory()
            ->forPartner((int) $this->partner->id)
            ->create([
                'name' => 'Разовое доступ',
                'schedule_type' => LessonPackage::SCHEDULE_TYPE_NO_SCHEDULE,
                'price_cents' => 30000,
                'lessons_count' => 1,
                'duration_days' => 1,
            ]);
    }

    /**
     * @return list<array{0: string, 1: string, 2: string, 3: LessonPackage}>
     */
    private function typeCases(): array
    {
        return [
            [
                LessonPackage::SCHEDULE_TYPE_FIXED,
                LessonPackageTypePermission::PERMISSION_FIXED,
                'Фиксированный',
                $this->fixedPackage,
            ],
            [
                LessonPackage::SCHEDULE_TYPE_FLEXIBLE,
                LessonPackageTypePermission::PERMISSION_FLEXIBLE,
                'Предоплата',
                $this->flexiblePackage,
            ],
            [
                LessonPackage::SCHEDULE_TYPE_NO_SCHEDULE,
                LessonPackageTypePermission::PERMISSION_NO_SCHEDULE,
                'Разовое занятие',
                $this->noSchedulePackage,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function ajaxHeaders(): array
    {
        return [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ];
    }

    private function grantPermission(string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function storePayload(string $scheduleType, string $name): array
    {
        $payload = [
            'name' => $name,
            'schedule_type' => $scheduleType,
            'price' => 200,
            'lessons_count' => $scheduleType === LessonPackage::SCHEDULE_TYPE_NO_SCHEDULE ? 1 : 8,
        ];
        if ($scheduleType === LessonPackage::SCHEDULE_TYPE_NO_SCHEDULE) {
            $payload['duration_days'] = 1;
        }

        return $payload;
    }

    public function test_packages_page_hides_add_button_and_type_options_without_any_type_permission(): void
    {
        $html = $this->get(route('admin.lesson-packages.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('title="Добавить абонемент"', $html);
        $this->assertStringNotContainsString('payments-report-toolbar-label d-none d-sm-inline">Добавить</span>', $html);
        foreach ($this->typeCases() as [$type, $permission, $label]) {
            $this->assertStringNotContainsString(
                '<option value="'.$type.'">'.$label.'</option>',
                $html,
                "Без {$permission} option «{$label}» быть не должно"
            );
        }
    }

    public function test_packages_page_shows_add_button_and_option_when_one_type_is_granted(): void
    {
        $this->grantLessonPackageTypePermissions($this->user, ['fixed']);

        $html = $this->get(route('admin.lesson-packages.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('title="Добавить абонемент"', $html);
        $this->assertStringContainsString('<option value="fixed">Фиксированный</option>', $html);
        $this->assertStringNotContainsString('<option value="flexible">Предоплата</option>', $html);
        $this->assertStringNotContainsString('<option value="no_schedule">Разовое занятие</option>', $html);
        $this->assertStringNotContainsString('<option value="postpay">Постоплата</option>', $html);
    }

    public function test_directories_packages_page_hides_type_options_without_permission(): void
    {
        $html = $this->get(route('admin.directories.lesson-packages.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('<option value="fixed">Фиксированный</option>', $html);
        $this->assertStringNotContainsString('title="Добавить абонемент"', $html);
    }

    public function test_store_without_permission_ajax_returns_422_on_schedule_type(): void
    {
        foreach ($this->typeCases() as [$type, $permission, $label]) {
            $before = LessonPackage::query()->where('partner_id', $this->partner->id)->count();

            $this->withHeaders($this->ajaxHeaders())
                ->postJson(route('admin.lesson-packages.store'), $this->storePayload($type, 'Denied '.$type))
                ->assertStatus(422)
                ->assertJsonValidationErrors(['schedule_type'])
                ->assertJsonFragment([
                    'schedule_type' => [LessonPackageTypePermission::denyScheduleTypeMessage($type)],
                ]);

            $this->assertSame(
                $before,
                LessonPackage::query()->where('partner_id', $this->partner->id)->count(),
                "Без {$permission} шаблон {$type} не должен создаться"
            );
            unset($label);
        }
    }

    public function test_store_without_permission_non_ajax_redirects_with_errors(): void
    {
        $response = $this->from(route('admin.lesson-packages.index'))
            ->post(route('admin.lesson-packages.store'), array_merge(
                ['_token' => csrf_token()],
                $this->storePayload(LessonPackage::SCHEDULE_TYPE_FIXED, 'Denied fixed non-AJAX')
            ));

        $response->assertRedirect(route('admin.lesson-packages.index'));
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertSessionHasErrors(['schedule_type']);
        $this->assertDatabaseMissing('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Denied fixed non-AJAX',
        ]);
    }

    public function test_store_with_permission_ajax_returns_200(): void
    {
        foreach ($this->typeCases() as [$type, $permission]) {
            $this->grantPermission($permission);

            $this->withHeaders($this->ajaxHeaders())
                ->postJson(route('admin.lesson-packages.store'), $this->storePayload($type, 'Allowed '.$type))
                ->assertOk()
                ->assertJsonPath('success', true);

            $this->assertDatabaseHas('lesson_packages', [
                'partner_id' => $this->partner->id,
                'name' => 'Allowed '.$type,
                'schedule_type' => $type,
            ]);
        }
    }

    public function test_update_existing_without_permission_allows_price_change(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->putJson(route('admin.lesson-packages.update', $this->fixedPackage), [
                'name' => $this->fixedPackage->name,
                'schedule_type' => LessonPackage::SCHEDULE_TYPE_FIXED,
                'price' => 777,
                'lessons_count' => 8,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->fixedPackage->refresh();
        $this->assertSame(77700, (int) $this->fixedPackage->price_cents);
    }

    public function test_update_change_type_without_permission_returns_422(): void
    {
        $this->grantLessonPackageTypePermissions($this->user, ['fixed']);

        $this->withHeaders($this->ajaxHeaders())
            ->putJson(route('admin.lesson-packages.update', $this->fixedPackage), [
                'name' => $this->fixedPackage->name,
                'schedule_type' => LessonPackage::SCHEDULE_TYPE_FLEXIBLE,
                'price' => 100,
                'lessons_count' => 8,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['schedule_type']);

        $this->fixedPackage->refresh();
        $this->assertSame(LessonPackage::SCHEDULE_TYPE_FIXED, $this->fixedPackage->schedule_type);
    }

    public function test_packages_data_filter_without_permission_returns_empty(): void
    {
        foreach ($this->typeCases() as [$type, $permission]) {
            $json = $this->getJson(route('admin.lesson-packages.data', [
                'schedule_type' => $type,
            ]))
                ->assertOk()
                ->json();

            $this->assertSame(
                0,
                (int) ($json['recordsFiltered'] ?? -1),
                "Crafted filter {$type} без {$permission} должен быть пустым"
            );
            $this->assertSame([], $json['data'] ?? ['x']);
        }

        $all = $this->getJson(route('admin.lesson-packages.data'))
            ->assertOk()
            ->json();
        $names = collect($all['data'] ?? [])->pluck('name')->all();
        $this->assertContains('Фикс доступ', $names);
        $this->assertContains('Предоплата доступ', $names);
    }

    public function test_packages_data_filter_with_permission_finds_rows(): void
    {
        $this->grantLessonPackageTypePermissions($this->user, ['fixed']);

        $json = $this->getJson(route('admin.lesson-packages.data', [
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_FIXED,
        ]))
            ->assertOk()
            ->json();

        $this->assertGreaterThan(0, (int) ($json['recordsFiltered'] ?? 0));
        $this->assertContains('Фикс доступ', collect($json['data'] ?? [])->pluck('name')->all());
    }

    public function test_assignments_filter_hides_types_without_permission(): void
    {
        $this->grantPermission('setPrices.packageAssignments.view');

        $html = $this->get(route('admin.lesson-packages.assignments'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('<option value="fixed"', $html);
        $this->assertStringNotContainsString('<option value="flexible"', $html);
        $this->assertStringNotContainsString('<option value="no_schedule"', $html);
    }

    public function test_set_price_all_users_without_permission_ajax_422_on_package_field(): void
    {
        UserPrice::query()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-08-01',
            'price_cents' => 0,
            'is_paid' => false,
            'lesson_package_id' => null,
        ]);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'selectedDate' => 'Август 2026',
                'teamId' => $this->team->id,
                'usersPrice' => [
                    [
                        'user_id' => $this->student->id,
                        'price' => 0,
                        'lesson_package_id' => $this->fixedPackage->id,
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['usersPrice.0.lesson_package_id']);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-08-01',
            'lesson_package_id' => null,
        ]);
    }

    public function test_set_price_all_users_keeps_existing_package_without_permission(): void
    {
        UserPrice::query()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-08-01',
            'price_cents' => 0,
            'is_paid' => false,
            'lesson_package_id' => $this->fixedPackage->id,
        ]);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'selectedDate' => 'Август 2026',
                'teamId' => $this->team->id,
                'usersPrice' => [
                    [
                        'user_id' => $this->student->id,
                        'price' => 0,
                        'lesson_package_id' => $this->fixedPackage->id,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-08-01',
            'lesson_package_id' => $this->fixedPackage->id,
        ]);
    }

    public function test_monthly_catalog_hides_unassigned_types_and_keeps_assigned(): void
    {
        UserPrice::query()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-08-01',
            'price_cents' => 0,
            'is_paid' => false,
            'lesson_package_id' => $this->flexiblePackage->id,
        ]);

        $json = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('getTeamPrice'), [
                'teamId' => $this->team->id,
                'selectedDate' => 'Август 2026',
            ])
            ->assertOk()
            ->json();

        $ids = collect($json['lessonPackages'] ?? [])->pluck('id')->map(static fn ($id) => (int) $id)->all();
        $this->assertNotContains((int) $this->fixedPackage->id, $ids);
        $this->assertNotContains((int) $this->noSchedulePackage->id, $ids);
        $this->assertContains((int) $this->flexiblePackage->id, $ids);
    }

    public function test_payment_notifications_page_hides_type_checkboxes_without_permission(): void
    {
        $this->grantPermission('setPrices.paymentNotifications.manage');

        $html = $this->get(route('admin.settingPrices.paymentNotifications'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('id="pn-type-fixed"', $html);
        $this->assertStringNotContainsString('id="pn-type-flexible"', $html);
        $this->assertStringNotContainsString('id="pn-type-postpay"', $html);
    }

    public function test_payment_notifications_store_without_permission_returns_422(): void
    {
        $this->grantPermission('setPrices.paymentNotifications.manage');

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('admin.settingPrices.paymentNotifications.rules.store'), [
                'name' => 'Denied types',
                'trigger_type' => PaymentNotificationRule::TRIGGER_DAY_OF_MONTH,
                'trigger_value' => 5,
                'billing_month_offset' => 0,
                'schedule_types' => ['fixed'],
                'subject_template' => 'Тема',
                'body_html_template' => '<p>Тело</p>',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['schedule_types']);
    }

    public function test_payment_notifications_update_keeps_existing_types_without_permission(): void
    {
        $this->grantPermission('setPrices.paymentNotifications.manage');

        $rule = PaymentNotificationRule::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Уже fixed',
            'is_enabled' => true,
            'trigger_type' => PaymentNotificationRule::TRIGGER_DAY_OF_MONTH,
            'trigger_value' => 5,
            'billing_month_offset' => 0,
            'schedule_types' => ['fixed'],
            'subject_template' => 'Тема',
            'body_html_template' => '<p>Тело</p>',
            'sort_order' => 0,
        ]);

        $this->withHeaders($this->ajaxHeaders())
            ->putJson(route('admin.settingPrices.paymentNotifications.rules.update', ['id' => $rule->id]), [
                'name' => 'Уже fixed (upd)',
                'is_enabled' => true,
                'trigger_type' => PaymentNotificationRule::TRIGGER_DAY_OF_MONTH,
                'trigger_value' => 5,
                'billing_month_offset' => 0,
                'schedule_types' => ['fixed'],
                'subject_template' => 'Тема',
                'body_html_template' => '<p>Тело</p>',
            ])
            ->assertOk()
            ->assertJsonPath('rule.name', 'Уже fixed (upd)');

        $this->withHeaders($this->ajaxHeaders())
            ->putJson(route('admin.settingPrices.paymentNotifications.rules.update', ['id' => $rule->id]), [
                'name' => 'Уже fixed (upd2)',
                'is_enabled' => true,
                'trigger_type' => PaymentNotificationRule::TRIGGER_DAY_OF_MONTH,
                'trigger_value' => 5,
                'billing_month_offset' => 0,
                'schedule_types' => ['fixed', 'flexible'],
                'subject_template' => 'Тема',
                'body_html_template' => '<p>Тело</p>',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['schedule_types']);
    }

    public function test_guest_is_denied_on_store(): void
    {
        Auth::logout();

        $store = $this->post(route('admin.lesson-packages.store'), $this->storePayload(
            LessonPackage::SCHEDULE_TYPE_FIXED,
            'Guest fixed'
        ));
        $this->assertContains($store->getStatusCode(), [302, 401, 403, 419]);
        $this->assertNotSame(200, $store->getStatusCode());
        $this->assertNotSame(500, $store->getStatusCode());
    }

    public function test_guest_cannot_open_pages_or_mutate_type_gated_endpoints(): void
    {
        Auth::logout();

        foreach ($this->typeGatedEndpoints() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'text/html']
            );

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 405, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
            $this->assertNotSame(500, $response->getStatusCode());
            $this->assertNotSame(200, $response->getStatusCode());
        }
    }

    public function test_guest_json_requests_are_denied_and_not_server_error(): void
    {
        Auth::logout();

        foreach ($this->typeGatedEndpoints() as $item) {
            $response = $this->json($item['method'], $item['url'], $item['data'] ?? []);
            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 405, 419],
                "Гость JSON {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
            $this->assertNotSame(500, $response->getStatusCode());
            $this->assertNotSame(200, $response->getStatusCode());
        }
    }

    public function test_manager_without_lesson_packages_view_gets_403_even_with_type_permission(): void
    {
        $actor = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->grantLessonPackageTypePermissions($actor, ['fixed', 'flexible', 'no_schedule']);
        $this->actingAs($actor);

        $this->get(route('admin.lesson-packages.index'))->assertForbidden();
        $this->get(route('admin.directories.lesson-packages.index'))->assertForbidden();
        $this->postJson(route('admin.lesson-packages.store'), $this->storePayload(
            LessonPackage::SCHEDULE_TYPE_FIXED,
            'No view fixed'
        ))->assertForbidden();
        $this->getJson(route('admin.lesson-packages.data'))->assertForbidden();
        $this->putJson(
            route('admin.lesson-packages.update', $this->fixedPackage),
            $this->storePayload(LessonPackage::SCHEDULE_TYPE_FIXED, $this->fixedPackage->name)
        )->assertForbidden();
        $this->assertDatabaseMissing('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'No view fixed',
        ]);
    }

    public function test_manager_without_set_prices_view_gets_403_on_price_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.view', $this->partner);
        $this->grantLessonPackageTypePermissions($actor, ['fixed']);
        $this->actingAs($actor);

        $this->postJson(route('setTeamPrice'), [
            'teamId' => $this->team->id,
            'lesson_package_id' => $this->fixedPackage->id,
            'selectedDate' => 'Август 2026',
        ])->assertForbidden();

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Август 2026',
            'teamId' => $this->team->id,
            'usersPrice' => [[
                'user_id' => $this->student->id,
                'price' => 0,
                'lesson_package_id' => $this->fixedPackage->id,
            ]],
        ])->assertForbidden();

        $this->postJson(route('setting-prices.prolong-month.preview'), [
            'selectedDate' => 'Сентябрь 2026',
        ])->assertForbidden();
    }

    public function test_manager_with_view_and_type_permission_can_open_pages(): void
    {
        $this->grantLessonPackageTypePermissions($this->user, ['fixed']);
        $this->grantPermission('setPrices.packageAssignments.view');
        $this->grantPermission('setPrices.paymentNotifications.manage');

        foreach ([
            route('admin.lesson-packages.index'),
            route('admin.directories.lesson-packages.index'),
            route('admin.lesson-packages.assignments'),
            route('admin.settingPrices.paymentNotifications'),
            route('admin.settingPrices.indexMenu'),
        ] as $url) {
            $page = $this->get($url);
            $page->assertOk();
            $this->assertNotSame('', trim((string) $page->getContent()));
            $this->assertNotSame(500, $page->getStatusCode());
        }
    }

    public function test_wrong_http_methods_on_type_gated_endpoints_are_not_server_error(): void
    {
        $this->grantLessonPackageTypePermissions($this->user, ['fixed']);

        foreach ([
            ['GET', route('admin.lesson-packages.store'), $this->storePayload('fixed', 'Get store')],
            ['PATCH', route('admin.lesson-packages.store'), $this->storePayload('fixed', 'Patch store')],
            ['DELETE', route('admin.lesson-packages.store')],
            ['GET', route('setTeamPrice'), ['teamId' => $this->team->id, 'lesson_package_id' => $this->fixedPackage->id, 'selectedDate' => 'Август 2026']],
            ['PUT', route('setTeamPrice'), ['teamId' => $this->team->id, 'lesson_package_id' => $this->fixedPackage->id, 'selectedDate' => 'Август 2026']],
            ['PATCH', route('admin.lesson-packages.update', $this->fixedPackage), $this->storePayload('fixed', $this->fixedPackage->name)],
        ] as $item) {
            $response = $this->call($item[0], $item[1], $item[2] ?? []);
            $this->assertNotSame(500, $response->getStatusCode(), "{$item[0]} {$item[1]}");
            if ($response->getStatusCode() === 200) {
                $this->assertNotSame('', trim((string) $response->getContent()));
            }
        }
    }

    public function test_school_calendar_page_opens_without_type_permission(): void
    {
        $page = $this->get(route('admin.lesson-packages.school-schedule'));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));
    }

    public function test_assignments_package_select_hides_types_without_permission(): void
    {
        $this->grantPermission('setPrices.packageAssignments.view');

        $html = $this->get(route('admin.lesson-packages.assignments'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('value="'.$this->fixedPackage->id.'"', $html);
        $this->assertStringNotContainsString('value="'.$this->flexiblePackage->id.'"', $html);
        $this->assertStringNotContainsString('value="'.$this->noSchedulePackage->id.'"', $html);
    }

    public function test_assignments_package_select_shows_granted_type_only(): void
    {
        $this->grantPermission('setPrices.packageAssignments.view');
        $this->grantLessonPackageTypePermissions($this->user, ['fixed']);

        $html = $this->get(route('admin.lesson-packages.assignments'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('value="'.$this->fixedPackage->id.'"', $html);
        $this->assertStringNotContainsString('value="'.$this->flexiblePackage->id.'"', $html);
    }

    public function test_assignments_datatable_filter_without_permission_returns_empty(): void
    {
        $this->grantPermission('setPrices.packageAssignments.view');

        $assignment = UserLessonPackage::query()->create([
            'user_id' => $this->student->id,
            'lesson_package_id' => $this->fixedPackage->id,
            'lessons_total' => 8,
            'lessons_remaining' => 8,
            'fee_amount_cents' => 10000,
            'is_paid' => 0,
            'created_by' => $this->user->id,
        ]);

        $json = $this->getJson(route('admin.lesson-packages.assignments.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'filter_schedule_type' => LessonPackage::SCHEDULE_TYPE_FIXED,
        ]))
            ->assertOk()
            ->json();

        $this->assertSame(0, (int) ($json['recordsFiltered'] ?? -1));
        $this->assertSame([], $json['data'] ?? ['x']);

        $all = $this->getJson(route('admin.lesson-packages.assignments.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
        ]))->assertOk()->json();
        $this->assertContains($assignment->id, collect($all['data'] ?? [])->pluck('id')->all());
    }

    public function test_assignments_store_without_permission_ajax_returns_422_on_package_field(): void
    {
        $this->grantPermission('setPrices.packageAssignments.view');

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('admin.lesson-packages.assignments.store'), [
                'user_id' => $this->student->id,
                'lesson_package_id' => $this->fixedPackage->id,
                'fee_amount' => '100.00',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lesson_package_id'])
            ->assertJsonFragment([
                'lesson_package_id' => [LessonPackageTypePermission::denyPackageMessage('fixed')],
            ]);

        $this->assertDatabaseMissing('user_lesson_packages', [
            'user_id' => $this->student->id,
            'lesson_package_id' => $this->fixedPackage->id,
        ]);
    }

    public function test_assignments_store_with_permission_creates_record(): void
    {
        $this->grantPermission('setPrices.packageAssignments.view');
        $this->grantLessonPackageTypePermissions($this->user, ['fixed']);

        $this->post(route('admin.lesson-packages.assignments.store'), [
            'user_id' => $this->student->id,
            'lesson_package_id' => $this->fixedPackage->id,
            'fee_amount' => '100.00',
        ])->assertRedirect(route('admin.lesson-packages.assignments'));

        $this->assertDatabaseHas('user_lesson_packages', [
            'user_id' => $this->student->id,
            'lesson_package_id' => $this->fixedPackage->id,
            'fee_amount_cents' => 10000,
        ]);
    }

    public function test_set_team_price_without_permission_ajax_422_on_package_field(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setTeamPrice'), [
                'teamId' => $this->team->id,
                'lesson_package_id' => $this->fixedPackage->id,
                'selectedDate' => 'Август 2026',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lesson_package_id'])
            ->assertJsonFragment([
                'lesson_package_id' => [LessonPackageTypePermission::denyPackageMessage('fixed')],
            ]);
    }

    public function test_set_team_price_keeps_existing_package_without_permission(): void
    {
        TeamPrice::query()->create([
            'team_id' => $this->team->id,
            'new_month' => '2026-08-01',
            'price_cents' => 10000,
            'lesson_package_id' => $this->fixedPackage->id,
        ]);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setTeamPrice'), [
                'teamId' => $this->team->id,
                'lesson_package_id' => $this->fixedPackage->id,
                'selectedDate' => 'Август 2026',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('team_prices', [
            'team_id' => $this->team->id,
            'new_month' => '2026-08-01',
            'lesson_package_id' => $this->fixedPackage->id,
        ]);
    }

    public function test_set_price_all_teams_without_permission_ajax_422_on_package_field(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllTeams'), [
                'selectedDate' => 'Август 2026',
                'teamsData' => [[
                    'teamId' => $this->team->id,
                    'lesson_package_id' => $this->noSchedulePackage->id,
                ]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['teamsData.0.lesson_package_id']);
    }

    public function test_year_prices_save_without_permission_ajax_422_on_package_field(): void
    {
        UserPrice::query()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-08-01',
            'price_cents' => 0,
            'is_paid' => false,
            'lesson_package_id' => null,
        ]);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.user-year-prices.save'), [
                'user_id' => $this->student->id,
                'team_id' => $this->team->id,
                'year' => 2026,
                'prices' => [[
                    'new_month' => '2026-08-01',
                    'price' => 100,
                    'lesson_package_id' => $this->flexiblePackage->id,
                ]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['prices.0.lesson_package_id']);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-08-01',
            'lesson_package_id' => null,
        ]);
    }

    public function test_year_prices_save_keeps_existing_package_without_permission(): void
    {
        UserPrice::query()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-08-01',
            'price_cents' => 15000,
            'is_paid' => false,
            'lesson_package_id' => $this->flexiblePackage->id,
        ]);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.user-year-prices.save'), [
                'user_id' => $this->student->id,
                'team_id' => $this->team->id,
                'year' => 2026,
                'prices' => [[
                    'new_month' => '2026-08-01',
                    'price' => 150,
                    'lesson_package_id' => $this->flexiblePackage->id,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-08-01',
            'lesson_package_id' => $this->flexiblePackage->id,
        ]);
    }

    public function test_year_prices_catalog_hides_unassigned_types_and_keeps_assigned(): void
    {
        UserPrice::query()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-03-01',
            'price_cents' => 0,
            'is_paid' => false,
            'lesson_package_id' => $this->noSchedulePackage->id,
        ]);

        $json = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.user-year-prices'), [
                'user_id' => $this->student->id,
                'team_id' => $this->team->id,
                'year' => 2026,
            ])
            ->assertOk()
            ->json();

        $ids = collect($json['lessonPackages'] ?? [])->pluck('id')->map(static fn ($id) => (int) $id)->all();
        $this->assertContains((int) $this->noSchedulePackage->id, $ids);
        $this->assertNotContains((int) $this->fixedPackage->id, $ids);
        $this->assertNotContains((int) $this->flexiblePackage->id, $ids);
    }

    public function test_month_prolong_skips_type_without_permission_and_copies_when_granted(): void
    {
        TeamPrice::query()->create([
            'team_id' => $this->team->id,
            'new_month' => '2026-09-01',
            'price_cents' => 10000,
            'lesson_package_id' => $this->fixedPackage->id,
        ]);
        UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-09-01',
            'price_cents' => 10000,
            'lesson_package_id' => $this->fixedPackage->id,
            'is_paid' => 1,
            'is_manual_paid' => 1,
        ]);

        $preview = $this->postJson(route('setting-prices.prolong-month.preview'), [
            'selectedDate' => 'Сентябрь 2026',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $denied = collect($preview->json('skip_reasons'))
            ->firstWhere('reason', MonthlyPricesProlongReport::REASON_TYPE_DENIED);
        $this->assertIsArray($denied);
        $this->assertGreaterThan(0, (int) ($denied['students'] ?? 0));

        $this->postJson(route('setting-prices.prolong-month.apply'), [
            'selectedDate' => 'Сентябрь 2026',
        ])
            ->assertOk()
            ->assertJsonPath('counts.students_create', 0);

        $this->assertDatabaseMissing('users_prices', [
            'user_id' => $this->student->id,
            'new_month' => '2026-10-01',
            'lesson_package_id' => $this->fixedPackage->id,
        ]);

        $this->grantLessonPackageTypePermissions($this->user, ['fixed']);

        $this->postJson(route('setting-prices.prolong-month.apply'), [
            'selectedDate' => 'Сентябрь 2026',
        ])
            ->assertOk()
            ->assertJsonPath('counts.students_create', 1);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-10-01',
            'lesson_package_id' => $this->fixedPackage->id,
        ]);
    }

    public function test_show_json_returns_schedule_type_without_type_permission_so_edit_can_keep_value(): void
    {
        $this->getJson(route('admin.lesson-packages.show', $this->flexiblePackage), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('lesson_package.schedule_type', LessonPackage::SCHEDULE_TYPE_FLEXIBLE)
            ->assertJsonPath('lesson_package.id', $this->flexiblePackage->id);
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function typeGatedEndpoints(): array
    {
        return [
            ['method' => 'GET', 'url' => route('admin.lesson-packages.index')],
            ['method' => 'GET', 'url' => route('admin.directories.lesson-packages.index')],
            ['method' => 'GET', 'url' => route('admin.lesson-packages.data')],
            ['method' => 'GET', 'url' => route('admin.lesson-packages.show', $this->fixedPackage)],
            [
                'method' => 'POST',
                'url' => route('admin.lesson-packages.store'),
                'data' => $this->storePayload(LessonPackage::SCHEDULE_TYPE_FIXED, 'Guest matrix store'),
            ],
            [
                'method' => 'PUT',
                'url' => route('admin.lesson-packages.update', $this->fixedPackage),
                'data' => $this->storePayload(LessonPackage::SCHEDULE_TYPE_FIXED, $this->fixedPackage->name),
            ],
            ['method' => 'DELETE', 'url' => route('admin.lesson-packages.destroy', $this->fixedPackage)],
            ['method' => 'GET', 'url' => route('admin.lesson-packages.assignments')],
            [
                'method' => 'POST',
                'url' => route('admin.lesson-packages.assignments.store'),
                'data' => [
                    'user_id' => $this->student->id,
                    'lesson_package_id' => $this->fixedPackage->id,
                    'fee_amount' => '100.00',
                ],
            ],
            ['method' => 'GET', 'url' => route('admin.settingPrices.paymentNotifications')],
            [
                'method' => 'POST',
                'url' => route('setTeamPrice'),
                'data' => [
                    'teamId' => $this->team->id,
                    'lesson_package_id' => $this->fixedPackage->id,
                    'selectedDate' => 'Август 2026',
                ],
            ],
            [
                'method' => 'POST',
                'url' => route('setPriceAllUsers'),
                'data' => [
                    'selectedDate' => 'Август 2026',
                    'teamId' => $this->team->id,
                    'usersPrice' => [[
                        'user_id' => $this->student->id,
                        'price' => 0,
                        'lesson_package_id' => $this->fixedPackage->id,
                    ]],
                ],
            ],
            [
                'method' => 'POST',
                'url' => route('setting-prices.user-year-prices.save'),
                'data' => [
                    'user_id' => $this->student->id,
                    'team_id' => $this->team->id,
                    'year' => 2026,
                    'prices' => [[
                        'new_month' => '2026-08-01',
                        'price' => 100,
                        'lesson_package_id' => $this->fixedPackage->id,
                    ]],
                ],
            ],
        ];
    }
}
