<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\User;
use App\Models\UserLessonPackage;
use App\Models\UserPrice;
use App\Services\TeamUserSyncService;
use App\Support\LessonPackageTypePermission;

/**
 * UX: админ выставил сумму без абона, клиент оплатил, затем ставят предоплату.
 * Падает на коде до фикса, где оплаченный месяц без пакета давал 422 / снимок слева молча пропускал.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SettingPricesPaidEmptyPrepaidAttachFeatureTest extends SettingPricesPaidEmptyPrepaidAttachTestCase
{
    public function test_manager_can_attach_prepaid_to_paid_month_without_package_and_price_stays(): void
    {
        $row = $this->seedPaidEmptyMonth(500000);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamId' => $this->team->id,
                'usersPrice' => [
                    $this->rightPayload($this->student, 8000.0, (int) $this->to->id),
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('usersPrice.0.price', 5000)
            ->assertJsonPath('usersPrice.0.lesson_package_id', $this->to->id);

        $row->refresh();
        $ulp = UserLessonPackage::query()->findOrFail($row->user_lesson_package_id);
        $this->assertSame(500000, (int) $row->price_cents);
        $this->assertTrue((bool) $ulp->is_paid);
        $this->assertSame(12, (int) $ulp->lessons_total);
        $this->assertSame(12, (int) $ulp->lessons_remaining);
    }

    public function test_unpaid_month_without_package_takes_catalog_price_when_prepaid_attached(): void
    {
        $row = UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => self::MONTH_DATE,
            'price_cents' => 100000,
            'is_paid' => 0,
            'lesson_package_id' => null,
        ]);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamId' => $this->team->id,
                'usersPrice' => [
                    $this->rightPayload($this->student, 8000.0, (int) $this->to->id),
                ],
            ])
            ->assertOk();

        $row->refresh();
        $this->assertSame(800000, (int) $row->price_cents);
        $this->assertSame((int) $this->to->id, (int) $row->lesson_package_id);
    }

    public function test_attaching_prepaid_does_not_bind_existing_trial_lessons(): void
    {
        $row = $this->seedPaidEmptyMonth();
        $trial = $this->createTrialUtss();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamId' => $this->team->id,
                'usersPrice' => [
                    $this->rightPayload($this->student, 8000.0, (int) $this->from->id),
                ],
            ])
            ->assertOk();

        $trial->refresh();
        $row->refresh();
        $this->assertNull($trial->user_lesson_package_id);
        $ulp = UserLessonPackage::query()->findOrFail($row->user_lesson_package_id);
        $this->assertSame(8, (int) $ulp->lessons_remaining);
        $this->assertFalse($ulp->isLaidOutInSchedule());
    }

    public function test_left_snapshot_attaches_prepaid_to_paid_students_without_package(): void
    {
        $row = $this->seedPaidEmptyMonth(350000);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setTeamPrice'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamId' => $this->team->id,
                'lesson_package_id' => $this->to->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $row->refresh();
        $this->assertSame((int) $this->to->id, (int) $row->lesson_package_id);
        $this->assertSame(350000, (int) $row->price_cents);
        $ulp = UserLessonPackage::query()->findOrFail($row->user_lesson_package_id);
        $this->assertTrue((bool) $ulp->is_paid);
        $this->assertSame(12, (int) $ulp->lessons_total);
    }

    public function test_mass_left_snapshot_attaches_prepaid_across_teams(): void
    {
        $row = $this->seedPaidEmptyMonth(220000);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllTeams'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamsData' => [[
                    'teamId' => $this->team->id,
                    'lesson_package_id' => $this->from->id,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $row->refresh();
        $this->assertSame((int) $this->from->id, (int) $row->lesson_package_id);
        $this->assertSame(220000, (int) $row->price_cents);
        $this->assertNotNull($row->user_lesson_package_id);
    }

    public function test_paid_empty_rejects_fixed_one_off_and_postpay_on_package_field(): void
    {
        foreach ([
            $this->fixed,
            $this->noSchedule,
            $this->postpay,
        ] as $pkg) {
            $row = $this->seedPaidEmptyMonth();

            $response = $this->withHeaders($this->ajaxHeaders())
                ->postJson(route('setPriceAllUsers'), [
                    'selectedDate' => self::MONTH_LABEL,
                    'teamId' => $this->team->id,
                    'usersPrice' => [
                        $this->rightPayload($this->student, 8000.0, (int) $pkg->id),
                    ],
                ]);

            $response
                ->assertStatus(422)
                ->assertJsonValidationErrors(['usersPrice.0.lesson_package_id']);
            $this->assertStringContainsString(
                'только абонемент предоплаты',
                $this->jsonFieldError($response, 'usersPrice.0.lesson_package_id')
            );

            $row->refresh();
            $this->assertNull($row->lesson_package_id);
            $this->assertNull($row->user_lesson_package_id);
            $row->delete();
        }
    }

    public function test_cannot_remove_package_from_paid_month_after_attach(): void
    {
        $row = $this->seedPaidEmptyMonth();
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamId' => $this->team->id,
                'usersPrice' => [
                    $this->rightPayload($this->student, 5000.0, (int) $this->to->id),
                ],
            ])
            ->assertOk();

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamId' => $this->team->id,
                'usersPrice' => [
                    $this->rightPayload($this->student, 5000.0, null),
                ],
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['usersPrice.0.lesson_package_id']);
        $this->assertStringContainsString(
            'Нельзя снять абонемент',
            $this->jsonFieldError($response, 'usersPrice.0.lesson_package_id')
        );

        $row->refresh();
        $this->assertSame((int) $this->to->id, (int) $row->lesson_package_id);
    }

    public function test_repeat_attach_of_same_prepaid_is_idempotent(): void
    {
        $row = $this->seedPaidEmptyMonth();
        $payload = [
            'selectedDate' => self::MONTH_LABEL,
            'teamId' => $this->team->id,
            'usersPrice' => [
                $this->rightPayload($this->student, 8000.0, (int) $this->to->id),
            ],
        ];

        $this->withHeaders($this->ajaxHeaders())->postJson(route('setPriceAllUsers'), $payload)->assertOk();
        $ulpId = (int) $row->fresh()->user_lesson_package_id;

        $this->withHeaders($this->ajaxHeaders())->postJson(route('setPriceAllUsers'), $payload)->assertOk();
        $row->refresh();
        $this->assertSame($ulpId, (int) $row->user_lesson_package_id);
        $this->assertSame(500000, (int) $row->price_cents);
        $this->assertSame(1, UserLessonPackage::query()->where('user_id', $this->student->id)->count());
    }

    public function test_manager_without_prepaid_type_permission_gets_422_on_package_field(): void
    {
        $row = $this->seedPaidEmptyMonth();
        $actor = $this->createUserWithoutPermission('lessonPackages.type.flexible');
        $this->grantPermission($actor, 'setPrices.view');
        $this->actingAs($actor);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamId' => $this->team->id,
                'usersPrice' => [
                    $this->rightPayload($this->student, 8000.0, (int) $this->to->id),
                ],
            ]);
        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['usersPrice.0.lesson_package_id']);
        $this->assertSame(
            LessonPackageTypePermission::denyPackageMessage('flexible'),
            $this->jsonFieldError($response, 'usersPrice.0.lesson_package_id')
        );

        $row->refresh();
        $this->assertNull($row->lesson_package_id);
    }

    public function test_former_member_paid_empty_is_not_updated_by_right_apply(): void
    {
        $former = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'is_enabled' => true,
            'name' => 'Бывший',
        ]);
        $sync = app(TeamUserSyncService::class);
        $sync->syncTeamsForStudent($former, [(int) $this->team->id]);
        $row = UserPrice::forceCreate([
            'user_id' => $former->id,
            'team_id' => $this->team->id,
            'new_month' => self::MONTH_DATE,
            'price_cents' => 300000,
            'is_paid' => 1,
            'lesson_package_id' => null,
        ]);
        $sync->syncTeamsForStudent($former, []);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamId' => $this->team->id,
                'usersPrice' => [
                    $this->rightPayload($former, 8000.0, (int) $this->to->id),
                ],
            ])
            ->assertOk();

        $row->refresh();
        $this->assertNull($row->lesson_package_id);
        $this->assertSame(300000, (int) $row->price_cents);
    }
}
