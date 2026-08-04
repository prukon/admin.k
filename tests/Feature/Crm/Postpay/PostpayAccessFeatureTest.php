<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Postpay;

use App\Models\LessonPackage;
use App\Models\UserPrice;
use Illuminate\Support\Facades\Auth;

/**
 * Контроль доступа к endpoint'ам раздела постоплаты (журнал, цены, шаблоны).
 *
 * @see \Tests\Feature\Crm\Schedule\ScheduleJournalAccessFeatureTest
 * @see \Tests\Feature\Crm\SettingPrices\SettingPricesMonthlyPackageAccessFeatureTest
 */
final class PostpayAccessFeatureTest extends PostpayTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPostpay(
            withScheduleView: true,
            withSetPricesView: true,
            withLessonPackagesView: true
        );
        $this->ensureUserPriceRow();
    }

    public function test_authorized_with_permissions_can_use_postpay_endpoints(): void
    {
        $this->get(route('schedule.index', ['year' => 2026, 'month' => '08']))
            ->assertOk()
            ->assertSee('data-postpay="1"', false);

        $this->getJson(route('schedule.cell-context', [
            'user_id' => $this->student->id,
            'date' => '2026-08-10',
        ]))
            ->assertOk()
            ->assertJsonStructure(['postpay_teams', 'postpay_team_id'])
            ->assertJsonPath('postpay_team_id', $this->team->id);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $this->student->id,
                'create_postpay' => 1,
                'team_id' => $this->team->id,
                'occurrence_date' => '2026-08-10',
                'lesson_occurrence_status_id' => $this->attendedStatusId,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('getTeamPrice'), [
                'teamId' => $this->team->id,
                'selectedDate' => 'Август 2026',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

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

        $this->postJson(route('admin.lesson-packages.store'), [
            'name' => 'Постоплата access ok',
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_POSTPAY,
            'price' => 200,
        ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_guest_is_denied_on_postpay_endpoints(): void
    {
        Auth::logout();

        $this->get(route('schedule.index'))->assertRedirect();
        $this->getJson(route('schedule.cell-context', [
            'user_id' => $this->student->id,
            'date' => '2026-08-10',
        ]))->assertUnauthorized();

        $this->postJson(route('schedule.update'), [
            'user_id' => $this->student->id,
            'create_postpay' => 1,
            'team_id' => $this->team->id,
            'occurrence_date' => '2026-08-10',
            'lesson_occurrence_status_id' => $this->attendedStatusId,
        ])->assertUnauthorized();

        $this->post(route('schedule.update'), [
            '_token' => csrf_token(),
            'user_id' => $this->student->id,
            'create_postpay' => 1,
            'team_id' => $this->team->id,
            'occurrence_date' => '2026-08-10',
            'lesson_occurrence_status_id' => $this->attendedStatusId,
        ])->assertRedirect();

        $this->postJson(route('getTeamPrice'), [
            'teamId' => $this->team->id,
            'selectedDate' => 'Август 2026',
        ])->assertUnauthorized();

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Август 2026',
            'teamId' => $this->team->id,
            'usersPrice' => [
                [
                    'user_id' => $this->student->id,
                    'price' => 0,
                    'lesson_package_id' => $this->postpayPackage->id,
                ],
            ],
        ])->assertUnauthorized();

        $this->post(route('setPriceAllUsers'), [
            'selectedDate' => 'Август 2026',
            'teamId' => $this->team->id,
            'usersPrice' => [
                [
                    'user_id' => $this->student->id,
                    'price' => 0,
                    'lesson_package_id' => $this->postpayPackage->id,
                ],
            ],
        ])->assertRedirect();

        $this->postJson(route('admin.lesson-packages.store'), [
            'name' => 'Guest postpay',
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_POSTPAY,
            'price' => 100,
        ])->assertUnauthorized();
    }

    public function test_without_schedule_view_postpay_journal_returns_403(): void
    {
        $actor = $this->createUserWithoutPermission('schedule.view', $this->partner);
        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];

        $this->actingAs($actor)->withSession($session)
            ->get(route('schedule.index'))
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->getJson(route('schedule.cell-context', [
                'user_id' => $this->student->id,
                'date' => '2026-08-10',
            ]))
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->postJson(route('schedule.update'), [
                'user_id' => $this->student->id,
                'create_postpay' => 1,
                'team_id' => $this->team->id,
                'occurrence_date' => '2026-08-10',
                'lesson_occurrence_status_id' => $this->attendedStatusId,
            ])
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->post(route('schedule.update'), [
                '_token' => csrf_token(),
                'user_id' => $this->student->id,
                'create_postpay' => 1,
                'team_id' => $this->team->id,
                'occurrence_date' => '2026-08-10',
                'lesson_occurrence_status_id' => $this->attendedStatusId,
            ])
            ->assertForbidden();
    }

    public function test_without_set_prices_view_postpay_price_endpoints_return_403(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.view', $this->partner);
        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];

        $this->actingAs($actor)->withSession($session)
            ->postJson(route('getTeamPrice'), [
                'teamId' => $this->team->id,
                'selectedDate' => 'Август 2026',
            ])
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
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
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
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
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-08-01',
            'lesson_package_id' => $this->postpayPackage->id,
        ]);
    }

    public function test_without_lesson_packages_view_postpay_template_store_returns_403(): void
    {
        $actor = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];

        $this->actingAs($actor)->withSession($session)
            ->postJson(route('admin.lesson-packages.store'), [
                'name' => 'Forbidden postpay',
                'schedule_type' => LessonPackage::SCHEDULE_TYPE_POSTPAY,
                'price' => 100,
            ])
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->post(route('admin.lesson-packages.store'), [
                '_token' => csrf_token(),
                'name' => 'Forbidden postpay non-ajax',
                'schedule_type' => LessonPackage::SCHEDULE_TYPE_POSTPAY,
                'price' => 100,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Forbidden postpay',
        ]);
    }
}
