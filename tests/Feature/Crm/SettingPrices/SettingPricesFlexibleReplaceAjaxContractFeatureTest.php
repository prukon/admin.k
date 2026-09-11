<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

/**
 * JSON-контракт AJAX apply/save замены предоплаты: 200 структура, 422 errors по полям.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SettingPricesFlexibleReplaceAjaxContractFeatureTest extends SettingPricesFlexibleReplaceTestCase
{
    public function test_ajax_paid_replace_returns_json_users_price_with_frozen_amount(): void
    {
        $this->seedLaidOutPrepaid(remaining: 5, paid: true);

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

    public function test_ajax_missing_team_returns_422_on_team_id(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'selectedDate' => self::MONTH_LABEL,
                'usersPrice' => [
                    $this->rightPayload($this->student, 8000.0, (int) $this->to->id),
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['teamId'])
            ->assertJsonPath('errors.teamId.0', 'Укажите группу.');
    }

    public function test_ajax_unknown_package_returns_422_on_lesson_package_id(): void
    {
        $this->assignFromPackage();

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamId' => $this->team->id,
                'usersPrice' => [
                    $this->rightPayload($this->student, 8000.0, 999999),
                ],
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['usersPrice.0.lesson_package_id']);
        $this->assertSame(
            'Выбранный абонемент не найден или недоступен.',
            $this->jsonFieldError($response, 'usersPrice.0.lesson_package_id')
        );
    }

    public function test_ajax_negative_price_returns_422_on_price(): void
    {
        $this->assignFromPackage();

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamId' => $this->team->id,
                'usersPrice' => [
                    $this->rightPayload($this->student, -1, (int) $this->to->id),
                ],
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['usersPrice.0.price']);
        $this->assertSame(
            'Цена не может быть отрицательной.',
            $this->jsonFieldError($response, 'usersPrice.0.price')
        );
    }

    public function test_ajax_foreign_package_returns_422_on_lesson_package_id(): void
    {
        $this->assignFromPackage();
        $foreign = \App\Models\LessonPackage::factory()
            ->forPartner((int) $this->foreignPartner->id)
            ->flexible(8, 60)
            ->create([
                'name' => 'Чужой пакет',
                'price_cents' => 100000,
                'is_active' => true,
            ]);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamId' => $this->team->id,
                'usersPrice' => [
                    $this->rightPayload($this->student, 1000.0, (int) $foreign->id),
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['usersPrice.0.lesson_package_id']);
    }

    public function test_ajax_year_save_volume_error_returns_422_on_prices_package(): void
    {
        $this->seedLaidOutPrepaid(remaining: 2, paid: false);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.user-year-prices.save'), [
                'user_id' => $this->student->id,
                'team_id' => $this->team->id,
                'year' => self::YEAR,
                'prices' => [[
                    'new_month' => self::MONTH_DATE,
                    'price' => 3000,
                    'lesson_package_id' => (int) $this->small->id,
                ]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['prices.0.lesson_package_id']);
    }

    public function test_ajax_year_save_missing_team_returns_422_on_team_id(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.user-year-prices.save'), [
                'user_id' => $this->student->id,
                'year' => self::YEAR,
                'prices' => [[
                    'new_month' => self::MONTH_DATE,
                    'price' => 8000,
                    'lesson_package_id' => (int) $this->to->id,
                ]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_id'])
            ->assertJsonPath('errors.team_id.0', 'Выберите группу для сохранения цен.');
    }

    public function test_ajax_team_snapshot_volume_error_returns_422_on_lesson_package_id(): void
    {
        $this->seedLaidOutPrepaid(remaining: 2, paid: false);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setTeamPrice'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamId' => $this->team->id,
                'lesson_package_id' => $this->small->id,
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lesson_package_id']);
        $first = (string) ($response->json('errors.lesson_package_id.0') ?? '');
        $this->assertStringContainsString('списано', $first);
    }

    public function test_ajax_partial_save_422_includes_users_price_of_saved_student(): void
    {
        $okStudent = \App\Models\User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'is_enabled' => true,
            'name' => 'Ок',
            'lastname' => 'Ajax',
        ]);
        app(\App\Services\TeamUserSyncService::class)->syncTeamsForStudent($okStudent, [(int) $this->team->id]);
        $okRow = $this->assignFromPackage($okStudent);
        $this->seedLaidOutPrepaid(remaining: 2, paid: false);

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
            ->assertJsonValidationErrors(['usersPrice.0.lesson_package_id'])
            ->assertJsonStructure(['success', 'message', 'errors', 'usersPrice', 'selectedDate']);

        $saved = collect($response->json('usersPrice'))
            ->firstWhere('user_id', $okStudent->id);
        $this->assertIsArray($saved);
        $this->assertSame((int) $this->small->id, (int) $saved['lesson_package_id']);
        $this->assertEquals(3000, (float) $saved['price']);

        $okRow->refresh();
        $this->assertSame((int) $this->small->id, (int) $okRow->lesson_package_id);
    }
}
