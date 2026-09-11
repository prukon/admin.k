<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\User;
use App\Models\UserLessonPackage;
use App\Models\UserPrice;
use App\Services\TeamUserSyncService;

/**
 * JSON-контракт AJAX: постановка предоплаты на оплаченный месяц без абона.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SettingPricesPaidEmptyPrepaidAttachAjaxContractFeatureTest extends SettingPricesPaidEmptyPrepaidAttachTestCase
{
    public function test_ajax_right_apply_returns_json_with_frozen_price_and_new_package(): void
    {
        $this->seedPaidEmptyMonth();

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamId' => $this->team->id,
                'usersPrice' => [
                    $this->rightPayload($this->student, 8000.0, (int) $this->to->id),
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'usersPrice',
                'selectedDate',
                'lessonPackages',
            ]);
        $this->assertNotSame('', trim($response->getContent()));
        $this->assertNotSame('{}', trim($response->getContent()));
        $this->assertSame(5000, (int) $response->json('usersPrice.0.price'));
        $this->assertSame((int) $this->to->id, (int) $response->json('usersPrice.0.lesson_package_id'));
    }

    public function test_ajax_year_save_returns_json_success_and_keeps_price(): void
    {
        $row = $this->seedPaidEmptyMonth(410000);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.user-year-prices.save'), [
                'user_id' => $this->student->id,
                'team_id' => $this->team->id,
                'year' => self::YEAR,
                'prices' => [[
                    'new_month' => self::MONTH_DATE,
                    'price' => 8000,
                    'lesson_package_id' => (int) $this->from->id,
                ]],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true);
        $this->assertNotSame('', trim($response->getContent()));

        $row->refresh();
        $this->assertSame((int) $this->from->id, (int) $row->lesson_package_id);
        $this->assertSame(410000, (int) $row->price_cents);
    }

    public function test_ajax_team_snapshot_returns_json_and_attaches_prepaid(): void
    {
        $row = $this->seedPaidEmptyMonth(330000);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setTeamPrice'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamId' => $this->team->id,
                'lesson_package_id' => $this->to->id,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('lesson_package_id', $this->to->id);
        $this->assertNotSame('{}', trim($response->getContent()));

        $row->refresh();
        $this->assertSame(330000, (int) $row->price_cents);
        $this->assertNotNull($row->user_lesson_package_id);
    }

    public function test_ajax_missing_month_returns_422_on_selected_date(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'teamId' => $this->team->id,
                'usersPrice' => [
                    $this->rightPayload($this->student, 8000.0, (int) $this->to->id),
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['selectedDate'])
            ->assertJsonPath('errors.selectedDate.0', 'Укажите месяц для установки цен.');
    }

    public function test_ajax_fixed_on_paid_empty_returns_422_on_users_price_package_field(): void
    {
        $this->seedPaidEmptyMonth();

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
            ->assertJsonValidationErrors(['usersPrice.0.lesson_package_id'])
            ->assertJsonPath('success', false);
        $this->assertStringContainsString(
            'только абонемент предоплаты',
            $this->jsonFieldError($response, 'usersPrice.0.lesson_package_id')
        );
    }

    public function test_ajax_year_save_fixed_returns_422_on_prices_package_field(): void
    {
        $this->seedPaidEmptyMonth();

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.user-year-prices.save'), [
                'user_id' => $this->student->id,
                'team_id' => $this->team->id,
                'year' => self::YEAR,
                'prices' => [[
                    'new_month' => self::MONTH_DATE,
                    'price' => 9000,
                    'lesson_package_id' => (int) $this->fixed->id,
                ]],
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['prices.0.lesson_package_id']);
        $this->assertStringContainsString(
            'только абонемент предоплаты',
            $this->jsonFieldError($response, 'prices.0.lesson_package_id')
        );
    }

    public function test_ajax_partial_save_attaches_other_student_when_one_row_is_rejected(): void
    {
        $blocked = $this->seedLaidOutPrepaid(remaining: 2, paid: false);
        $okStudent = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'is_enabled' => true,
            'name' => 'Ок',
        ]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($okStudent, [(int) $this->team->id]);
        $okRow = UserPrice::forceCreate([
            'user_id' => $okStudent->id,
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
                    $this->rightPayload($okStudent, 8000.0, (int) $this->to->id),
                ],
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['usersPrice.0.lesson_package_id'])
            ->assertJsonStructure(['success', 'message', 'errors', 'usersPrice', 'selectedDate']);

        $saved = collect($response->json('usersPrice'))->firstWhere('user_id', $okStudent->id);
        $this->assertIsArray($saved);
        $this->assertSame((int) $this->to->id, (int) $saved['lesson_package_id']);

        $okRow->refresh();
        $this->assertSame((int) $this->to->id, (int) $okRow->lesson_package_id);
        $this->assertSame(250000, (int) $okRow->price_cents);
        $this->assertNotNull($okRow->user_lesson_package_id);
        $this->assertSame(1, UserLessonPackage::query()->where('user_id', $okStudent->id)->count());

        $blocked['row']->refresh();
        $this->assertSame((int) $this->from->id, (int) $blocked['row']->lesson_package_id);
    }
}
