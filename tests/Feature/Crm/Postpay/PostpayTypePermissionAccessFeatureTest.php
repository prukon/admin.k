<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Postpay;

use App\Models\LessonPackage;
use App\Models\UserPrice;
use Illuminate\Support\Facades\Auth;

/**
 * Право lessonPackages.type.postpay: UI, store/update шаблона, назначение в «Установка цен».
 *
 * P1: access (guest / 403 / 422 без права / 200 с правом), AJAX JSON, non-AJAX 302.
 *
 * @see PostpayLessonPackageContractsFeatureTest
 * @see PostpaySettingPricesContractsFeatureTest
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class PostpayTypePermissionAccessFeatureTest extends PostpayTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPostpay(
            withScheduleView: false,
            withSetPricesView: true,
            withLessonPackagesView: true
        );
        $this->grantPermission('setPrices.packageAssignments.view');
    }

    public function test_guest_is_denied_on_lesson_packages_and_setting_prices_endpoints(): void
    {
        Auth::logout();

        $store = $this->post(route('admin.lesson-packages.store'), [
            'name' => 'Guest postpay',
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_POSTPAY,
            'price' => 100,
        ]);
        $this->assertContains($store->getStatusCode(), [302, 401, 403, 419]);
        $this->assertNotSame(200, $store->getStatusCode());
        $this->assertNotSame(500, $store->getStatusCode());

        $prices = $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Август 2026',
            'teamId' => $this->team->id,
            'usersPrice' => [
                [
                    'user_id' => $this->student->id,
                    'price' => 0,
                    'lesson_package_id' => $this->postpayPackage->id,
                ],
            ],
        ]);
        $this->assertContains($prices->getStatusCode(), [401, 403, 419]);
        $this->assertNotSame(200, $prices->getStatusCode());
        $this->assertNotSame(500, $prices->getStatusCode());
    }

    public function test_without_lesson_packages_view_store_returns_403(): void
    {
        $actor = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->grantPermission('lessonPackages.type.postpay', $actor);

        $this->actingAs($actor)->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ])
            ->postJson(route('admin.lesson-packages.store'), [
                'name' => 'No view postpay',
                'schedule_type' => LessonPackage::SCHEDULE_TYPE_POSTPAY,
                'price' => 100,
            ])
            ->assertForbidden();
    }

    public function test_packages_page_hides_postpay_option_without_permission(): void
    {
        $this->revokePermission('lessonPackages.type.postpay');

        $html = $this->get(route('admin.lesson-packages.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('<option value="postpay">Постоплата</option>', $html);
    }

    public function test_packages_page_shows_postpay_option_with_permission(): void
    {
        $html = $this->get(route('admin.lesson-packages.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<option value="postpay">Постоплата</option>', $html);
    }

    public function test_directories_packages_page_hides_postpay_without_permission(): void
    {
        $this->revokePermission('lessonPackages.type.postpay');

        $html = $this->get(route('admin.directories.lesson-packages.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('<option value="postpay">Постоплата</option>', $html);
    }

    public function test_store_postpay_without_permission_ajax_returns_422_on_schedule_type(): void
    {
        $this->revokePermission('lessonPackages.type.postpay');
        $before = LessonPackage::query()->where('partner_id', $this->partner->id)->count();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('admin.lesson-packages.store'), [
                'name' => 'Denied postpay AJAX',
                'schedule_type' => LessonPackage::SCHEDULE_TYPE_POSTPAY,
                'price' => 200,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['schedule_type']);

        $this->assertSame($before, LessonPackage::query()->where('partner_id', $this->partner->id)->count());
    }

    public function test_store_postpay_without_permission_non_ajax_redirects_with_errors_not_empty_200(): void
    {
        $this->revokePermission('lessonPackages.type.postpay');

        $response = $this->from(route('admin.lesson-packages.index'))
            ->post(route('admin.lesson-packages.store'), [
                '_token' => csrf_token(),
                'name' => 'Denied postpay non-AJAX',
                'schedule_type' => LessonPackage::SCHEDULE_TYPE_POSTPAY,
                'price' => 200,
            ]);

        $response->assertRedirect(route('admin.lesson-packages.index'));
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertSessionHasErrors(['schedule_type']);

        $this->assertDatabaseMissing('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Denied postpay non-AJAX',
        ]);
    }

    public function test_store_postpay_with_permission_ajax_returns_200_json(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('admin.lesson-packages.store'), [
                'name' => 'Allowed postpay AJAX',
                'schedule_type' => LessonPackage::SCHEDULE_TYPE_POSTPAY,
                'price' => 300,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Allowed postpay AJAX',
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_POSTPAY,
        ]);
    }

    public function test_store_postpay_with_permission_non_ajax_redirects_and_creates_not_empty_200(): void
    {
        $response = $this->post(route('admin.lesson-packages.store'), [
            '_token' => csrf_token(),
            'name' => 'Allowed postpay non-AJAX',
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_POSTPAY,
            'price' => 310,
        ]);

        $response->assertRedirect(route('admin.lesson-packages.index'));
        $this->assertNotSame(200, $response->getStatusCode());

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Allowed postpay non-AJAX',
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_POSTPAY,
        ]);
    }

    public function test_update_existing_postpay_without_permission_allows_price_change(): void
    {
        $this->revokePermission('lessonPackages.type.postpay');

        $this->withHeaders($this->ajaxHeaders())
            ->putJson(route('admin.lesson-packages.update', $this->postpayPackage), [
                'name' => $this->postpayPackage->name,
                'schedule_type' => LessonPackage::SCHEDULE_TYPE_POSTPAY,
                'price' => 777,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->postpayPackage->refresh();
        $this->assertSame(77700, (int) $this->postpayPackage->price_cents);
    }

    public function test_update_fixed_to_postpay_without_permission_returns_422(): void
    {
        $fixed = LessonPackage::factory()
            ->forPartner((int) $this->partner->id)
            ->create([
                'name' => 'Фикс для смены',
                'schedule_type' => LessonPackage::SCHEDULE_TYPE_FIXED,
                'price_cents' => 10000,
            ]);

        $this->revokePermission('lessonPackages.type.postpay');

        $this->withHeaders($this->ajaxHeaders())
            ->putJson(route('admin.lesson-packages.update', $fixed), [
                'name' => $fixed->name,
                'schedule_type' => LessonPackage::SCHEDULE_TYPE_POSTPAY,
                'price' => 100,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['schedule_type']);

        $fixed->refresh();
        $this->assertSame(LessonPackage::SCHEDULE_TYPE_FIXED, $fixed->schedule_type);
    }

    public function test_packages_data_filter_postpay_without_permission_returns_empty(): void
    {
        $this->revokePermission('lessonPackages.type.postpay');

        $json = $this->getJson(route('admin.lesson-packages.data', [
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_POSTPAY,
        ]))
            ->assertOk()
            ->json();

        $this->assertSame(0, (int) ($json['recordsFiltered'] ?? -1));
        $this->assertSame([], $json['data'] ?? ['x']);
    }

    public function test_packages_data_filter_postpay_with_permission_finds_rows(): void
    {
        $json = $this->getJson(route('admin.lesson-packages.data', [
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_POSTPAY,
        ]))
            ->assertOk()
            ->json();

        $this->assertGreaterThan(0, (int) ($json['recordsFiltered'] ?? 0));
    }

    public function test_assignments_page_excludes_postpay_from_package_select_without_permission(): void
    {
        $this->revokePermission('lessonPackages.type.postpay');

        $html = $this->get(route('admin.lesson-packages.assignments'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(
            'value="'.$this->postpayPackage->id.'"',
            $html
        );
    }

    public function test_set_price_all_users_without_permission_ajax_422_on_package_field(): void
    {
        $this->revokePermission('lessonPackages.type.postpay');

        UserPrice::forceCreate([
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
                        'lesson_package_id' => $this->postpayPackage->id,
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

    public function test_set_price_all_users_without_permission_non_ajax_redirects_with_errors(): void
    {
        $this->revokePermission('lessonPackages.type.postpay');

        UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-08-01',
            'price_cents' => 0,
            'is_paid' => false,
            'lesson_package_id' => null,
        ]);

        $response = $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setPriceAllUsers'), [
                '_token' => csrf_token(),
                'selectedDate' => 'Август 2026',
                'teamId' => $this->team->id,
                'usersPrice' => [
                    [
                        'user_id' => $this->student->id,
                        'price' => 0,
                        'lesson_package_id' => $this->postpayPackage->id,
                    ],
                ],
            ]);

        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertSessionHasErrors(['usersPrice.0.lesson_package_id']);
    }

    public function test_set_price_all_users_keeps_existing_postpay_without_permission(): void
    {
        $this->revokePermission('lessonPackages.type.postpay');
        $this->ensureUserPriceRow(packageId: $this->postpayPackage->id, price: 0);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'selectedDate' => 'Август 2026',
                'teamId' => $this->team->id,
                'usersPrice' => [
                    [
                        'user_id' => $this->student->id,
                        'price' => 0,
                        'lesson_package_id' => $this->postpayPackage->id,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-08-01',
            'lesson_package_id' => $this->postpayPackage->id,
        ]);
    }

    public function test_set_team_price_without_permission_ajax_422(): void
    {
        $this->revokePermission('lessonPackages.type.postpay');

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setTeamPrice'), [
                'selectedDate' => 'Август 2026',
                'teamId' => $this->team->id,
                'lesson_package_id' => $this->postpayPackage->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lesson_package_id']);
    }

    public function test_set_team_price_with_permission_ajax_ok(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setTeamPrice'), [
                'selectedDate' => 'Август 2026',
                'teamId' => $this->team->id,
                'lesson_package_id' => $this->postpayPackage->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_monthly_catalog_hides_unassigned_postpay_without_permission(): void
    {
        $this->revokePermission('lessonPackages.type.postpay');

        $json = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('getTeamPrice'), [
                'teamId' => $this->team->id,
                'selectedDate' => 'Август 2026',
            ])
            ->assertOk()
            ->json();

        $ids = collect($json['lessonPackages'] ?? [])->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertNotContains((int) $this->postpayPackage->id, $ids);
    }

    public function test_monthly_catalog_includes_postpay_with_permission(): void
    {
        $json = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('getTeamPrice'), [
                'teamId' => $this->team->id,
                'selectedDate' => 'Август 2026',
            ])
            ->assertOk()
            ->json();

        $ids = collect($json['lessonPackages'] ?? [])->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertContains((int) $this->postpayPackage->id, $ids);
    }
}
