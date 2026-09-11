<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\User;
use App\Models\UserLessonPackage;
use App\Models\UserPrice;
use App\Services\TeamUserSyncService;

/**
 * UX-баги замены предоплаты: оплаченный месяц меняет шаблон и не переписывает сумму;
 * объём меньше списанного — 422 под полем; частичный массовый save.
 *
 * Падает на коде до фикса, где оплаченный месяц пропускали целиком
 * и селект абонемента был disabled.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SettingPricesFlexibleReplaceFeatureTest extends SettingPricesFlexibleReplaceTestCase
{
    public function test_apply_right_replaces_paid_prepaid_and_keeps_frozen_price(): void
    {
        $seed = $this->seedLaidOutPrepaid(remaining: 5, paid: true);

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
            ->assertJsonPath('usersPrice.0.lesson_package_id', $this->to->id)
            ->assertJsonPath('usersPrice.0.price', 5000)
            ->assertJsonPath('usersPrice.0.effective_is_paid', true);

        $seed['row']->refresh();
        $seed['ulp']->refresh();
        $this->assertSame((int) $this->to->id, (int) $seed['row']->lesson_package_id);
        $this->assertSame(500000, (int) $seed['row']->price_cents);
        $this->assertSame(500000, (int) $seed['ulp']->fee_amount_cents);
        $this->assertSame(12, (int) $seed['ulp']->lessons_total);
        $this->assertSame(9, (int) $seed['ulp']->lessons_remaining);
        $this->assertSame((int) $seed['ulp']->id, (int) $seed['row']->user_lesson_package_id);
    }

    public function test_apply_right_unpaid_prepaid_takes_new_catalog_price(): void
    {
        $seed = $this->seedLaidOutPrepaid(remaining: 5, paid: false);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamId' => $this->team->id,
                'usersPrice' => [
                    $this->rightPayload($this->student, 8000.0, (int) $this->to->id),
                ],
            ])
            ->assertOk()
            ->assertJsonPath('usersPrice.0.price', 8000)
            ->assertJsonPath('usersPrice.0.lesson_package_id', $this->to->id);

        $seed['row']->refresh();
        $this->assertSame(800000, (int) $seed['row']->price_cents);
    }

    public function test_apply_right_rejects_paid_fixed_with_error_on_package_field(): void
    {
        UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => self::MONTH_DATE,
            'price_cents' => 900000,
            'is_paid' => 1,
            'lesson_package_id' => $this->fixed->id,
        ]);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamId' => $this->team->id,
                'usersPrice' => [
                    $this->rightPayload($this->student, 8000.0, (int) $this->to->id),
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['usersPrice.0.lesson_package_id']);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => self::MONTH_DATE,
            'lesson_package_id' => $this->fixed->id,
            'price_cents' => 900000,
        ]);
    }

    public function test_apply_right_blocked_when_new_volume_below_consumed_returns_422_on_package(): void
    {
        $seed = $this->seedLaidOutPrepaid(remaining: 2, paid: false);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamId' => $this->team->id,
                'usersPrice' => [
                    $this->rightPayload($this->student, 3000.0, (int) $this->small->id),
                ],
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['usersPrice.0.lesson_package_id'])
            ->assertJsonPath('success', false);
        $this->assertArrayHasKey('usersPrice', $response->json());
        $msg = $this->jsonFieldError($response, 'usersPrice.0.lesson_package_id');
        $this->assertStringContainsString('списано', $msg);
        $this->assertStringContainsString('6', $msg);
        $this->assertStringContainsString('4', $msg);

        $seed['row']->refresh();
        $seed['ulp']->refresh();
        $this->assertSame((int) $this->from->id, (int) $seed['row']->lesson_package_id);
        $this->assertSame(8, (int) $seed['ulp']->lessons_total);
        $this->assertSame(2, (int) $seed['ulp']->lessons_remaining);
    }

    public function test_mass_apply_saves_other_student_when_one_replace_is_blocked(): void
    {
        $okStudent = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'is_enabled' => true,
            'name' => 'Ок',
            'lastname' => 'Ученик',
        ]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($okStudent, [(int) $this->team->id]);

        $blocked = $this->seedLaidOutPrepaid(remaining: 2, paid: false);
        $okRow = $this->assignFromPackage($okStudent);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamId' => $this->team->id,
                'usersPrice' => [
                    $this->rightPayload($this->student, 3000.0, (int) $this->small->id),
                    $this->rightPayload($okStudent, 3000.0, (int) $this->small->id),
                ],
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['usersPrice.0.lesson_package_id']);

        $okRow->refresh();
        $okUlp = UserLessonPackage::query()->findOrFail($okRow->user_lesson_package_id);
        $this->assertSame((int) $this->small->id, (int) $okRow->lesson_package_id);
        $this->assertSame((int) $this->small->id, (int) $okUlp->lesson_package_id);
        $this->assertSame(300000, (int) $okRow->price_cents);

        $blocked['row']->refresh();
        $this->assertSame((int) $this->from->id, (int) $blocked['row']->lesson_package_id);
    }

    public function test_year_save_replaces_paid_prepaid_and_keeps_price(): void
    {
        $seed = $this->seedLaidOutPrepaid(remaining: 5, paid: true);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.user-year-prices.save'), [
                'user_id' => $this->student->id,
                'team_id' => $this->team->id,
                'year' => self::YEAR,
                'prices' => [[
                    'new_month' => self::MONTH_DATE,
                    'price' => 8000,
                    'lesson_package_id' => (int) $this->to->id,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $seed['row']->refresh();
        $seed['ulp']->refresh();
        $this->assertSame((int) $this->to->id, (int) $seed['row']->lesson_package_id);
        $this->assertSame(500000, (int) $seed['row']->price_cents);
        $this->assertSame(12, (int) $seed['ulp']->lessons_total);
        $this->assertSame(9, (int) $seed['ulp']->lessons_remaining);
    }

    public function test_team_snapshot_replaces_paid_prepaid_and_keeps_student_price(): void
    {
        $seed = $this->seedLaidOutPrepaid(remaining: 5, paid: true);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setTeamPrice'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamId' => $this->team->id,
                'lesson_package_id' => $this->to->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('lesson_package_id', $this->to->id);

        $seed['row']->refresh();
        $seed['ulp']->refresh();
        $this->assertSame((int) $this->to->id, (int) $seed['row']->lesson_package_id);
        $this->assertSame(500000, (int) $seed['row']->price_cents);
        $this->assertSame(12, (int) $seed['ulp']->lessons_total);
        $this->assertSame(9, (int) $seed['ulp']->lessons_remaining);
    }

    public function test_apply_right_attaches_prepaid_to_paid_empty_month_and_keeps_frozen_price(): void
    {
        $row = $this->seedPaidEmptyMonth();

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
            ->assertJsonPath('usersPrice.0.lesson_package_id', $this->to->id)
            ->assertJsonPath('usersPrice.0.price', 5000)
            ->assertJsonPath('usersPrice.0.effective_is_paid', true);

        $row->refresh();
        $this->assertSame((int) $this->to->id, (int) $row->lesson_package_id);
        $this->assertSame(500000, (int) $row->price_cents);
        $this->assertNotNull($row->user_lesson_package_id);
        $ulp = UserLessonPackage::query()->findOrFail($row->user_lesson_package_id);
        $this->assertSame((int) $this->to->id, (int) $ulp->lesson_package_id);
        $this->assertSame(500000, (int) $ulp->fee_amount_cents);
        $this->assertTrue((bool) $ulp->is_paid);
        $this->assertSame(12, (int) $ulp->lessons_total);
        $this->assertSame(12, (int) $ulp->lessons_remaining);
        $this->assertSame(self::MONTH_DATE, $ulp->billing_month?->format('Y-m-d'));
        $this->assertSame(self::MONTH_DATE, $ulp->starts_at?->format('Y-m-d'));
        $this->assertSame('2026-03-31', $ulp->ends_at?->format('Y-m-d'));
    }

    public function test_apply_right_attaches_prepaid_to_manual_paid_empty_month(): void
    {
        $row = $this->seedPaidEmptyMonth(400000, true);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamId' => $this->team->id,
                'usersPrice' => [
                    $this->rightPayload($this->student, 8000.0, (int) $this->from->id),
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $row->refresh();
        $this->assertSame((int) $this->from->id, (int) $row->lesson_package_id);
        $this->assertSame(400000, (int) $row->price_cents);
        $ulp = UserLessonPackage::query()->findOrFail($row->user_lesson_package_id);
        $this->assertTrue((bool) $ulp->is_paid);
        $this->assertSame(8, (int) $ulp->lessons_total);
        $this->assertSame(8, (int) $ulp->lessons_remaining);
        $this->assertSame(400000, (int) $ulp->fee_amount_cents);
    }

    public function test_apply_right_paid_empty_keeps_trial_cells_and_gives_full_volume(): void
    {
        $row = $this->seedPaidEmptyMonth();
        $trial = $this->createTrialUtss();

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
        $trial->refresh();
        $this->assertNull($trial->user_lesson_package_id);
        $this->assertTrue((bool) $trial->is_trial_lesson);
        $ulp = UserLessonPackage::query()->findOrFail($row->user_lesson_package_id);
        $this->assertSame(12, (int) $ulp->lessons_total);
        $this->assertSame(12, (int) $ulp->lessons_remaining);
        $this->assertFalse($ulp->isLaidOutInSchedule());
    }

    public function test_apply_right_rejects_paid_empty_fixed_with_error_on_package_field(): void
    {
        $row = $this->seedPaidEmptyMonth();

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamId' => $this->team->id,
                'usersPrice' => [
                    $this->rightPayload($this->student, 9000.0, (int) $this->fixed->id),
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
        $this->assertSame(500000, (int) $row->price_cents);
    }

    public function test_apply_right_rejects_removing_prepaid_from_paid_month(): void
    {
        $seed = $this->seedLaidOutPrepaid(remaining: 5, paid: true);

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

        $seed['row']->refresh();
        $this->assertSame((int) $this->from->id, (int) $seed['row']->lesson_package_id);
        $this->assertSame(500000, (int) $seed['row']->price_cents);
    }

    public function test_year_save_attaches_prepaid_to_paid_empty_and_keeps_price(): void
    {
        $row = $this->seedPaidEmptyMonth();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.user-year-prices.save'), [
                'user_id' => $this->student->id,
                'team_id' => $this->team->id,
                'year' => self::YEAR,
                'prices' => [[
                    'new_month' => self::MONTH_DATE,
                    'price' => 8000,
                    'lesson_package_id' => (int) $this->to->id,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $row->refresh();
        $this->assertSame((int) $this->to->id, (int) $row->lesson_package_id);
        $this->assertSame(500000, (int) $row->price_cents);
        $ulp = UserLessonPackage::query()->findOrFail($row->user_lesson_package_id);
        $this->assertTrue((bool) $ulp->is_paid);
        $this->assertSame(12, (int) $ulp->lessons_total);
        $this->assertSame(12, (int) $ulp->lessons_remaining);
    }

    public function test_team_snapshot_attaches_prepaid_to_paid_empty_and_keeps_student_price(): void
    {
        $row = $this->seedPaidEmptyMonth(350000);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setTeamPrice'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamId' => $this->team->id,
                'lesson_package_id' => $this->to->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('lesson_package_id', $this->to->id);

        $row->refresh();
        $this->assertSame((int) $this->to->id, (int) $row->lesson_package_id);
        $this->assertSame(350000, (int) $row->price_cents);
        $ulp = UserLessonPackage::query()->findOrFail($row->user_lesson_package_id);
        $this->assertTrue((bool) $ulp->is_paid);
        $this->assertSame(350000, (int) $ulp->fee_amount_cents);
        $this->assertSame(12, (int) $ulp->lessons_total);
        $this->assertSame(12, (int) $ulp->lessons_remaining);
    }

    public function test_team_snapshot_still_skips_paid_fixed(): void
    {
        UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => self::MONTH_DATE,
            'price_cents' => 900000,
            'is_paid' => 1,
            'lesson_package_id' => $this->fixed->id,
        ]);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setTeamPrice'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamId' => $this->team->id,
                'lesson_package_id' => $this->to->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => self::MONTH_DATE,
            'lesson_package_id' => $this->fixed->id,
            'price_cents' => 900000,
        ]);
    }

    public function test_mass_apply_attaches_prepaid_to_paid_empty_when_other_replace_is_blocked(): void
    {
        $emptyPaid = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'is_enabled' => true,
            'name' => 'Пустой',
            'lastname' => 'Оплачен',
        ]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($emptyPaid, [(int) $this->team->id]);

        $blocked = $this->seedLaidOutPrepaid(remaining: 2, paid: false);
        $emptyRow = UserPrice::forceCreate([
            'user_id' => $emptyPaid->id,
            'team_id' => $this->team->id,
            'new_month' => self::MONTH_DATE,
            'price_cents' => 250000,
            'is_paid' => 1,
            'lesson_package_id' => null,
        ]);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamId' => $this->team->id,
                'usersPrice' => [
                    $this->rightPayload($this->student, 3000.0, (int) $this->small->id),
                    $this->rightPayload($emptyPaid, 8000.0, (int) $this->to->id),
                ],
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['usersPrice.0.lesson_package_id']);

        $emptyRow->refresh();
        $this->assertSame((int) $this->to->id, (int) $emptyRow->lesson_package_id);
        $this->assertSame(250000, (int) $emptyRow->price_cents);
        $ulp = UserLessonPackage::query()->findOrFail($emptyRow->user_lesson_package_id);
        $this->assertSame(12, (int) $ulp->lessons_total);
        $this->assertSame(12, (int) $ulp->lessons_remaining);

        $blocked['row']->refresh();
        $this->assertSame((int) $this->from->id, (int) $blocked['row']->lesson_package_id);
    }

    public function test_get_team_price_and_year_json_expose_effective_paid_for_js(): void
    {
        $this->seedLaidOutPrepaid(remaining: 5, paid: true);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('getTeamPrice'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamId' => $this->team->id,
            ])
            ->assertOk()
            ->assertJsonPath('usersPrice.0.effective_is_paid', true)
            ->assertJsonPath('usersPrice.0.lesson_package_id', $this->from->id)
            ->assertJsonPath('usersPrice.0.price', 5000);

        $year = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.user-year-prices'), [
                'user_id' => $this->student->id,
                'team_id' => $this->team->id,
                'year' => self::YEAR,
            ])
            ->assertOk();

        $march = collect($year->json('months'))->firstWhere('new_month', self::MONTH_DATE);
        $this->assertIsArray($march);
        $this->assertTrue((bool) $march['effective_is_paid']);
        $this->assertSame((int) $this->from->id, (int) $march['lesson_package_id']);
        $this->assertEquals(5000, $march['price']);
    }
}
