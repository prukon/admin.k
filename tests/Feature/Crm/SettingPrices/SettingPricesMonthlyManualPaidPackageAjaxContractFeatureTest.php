<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPrice;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * JSON-контракт POST …/manual-paid с абонементом/ценой из карточки.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SettingPricesMonthlyManualPaidPackageAjaxContractFeatureTest extends CrmTestCase
{
    private Team $team;

    private User $student;

    private LessonPackage $packageA;

    private LessonPackage $packageB;

    private UserPrice $row;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->asAdmin();
        $this->grantPermission('setPrices.manualPaid.manage');
        $this->grantLessonPackageTypePermissions($this->user, ['fixed', 'flexible', 'no_schedule']);

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
        ]);
        $this->student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'is_enabled' => true,
        ]);
        $this->packageA = LessonPackage::factory()->forPartner((int) $this->partner->id)->create([
            'name' => 'Рубин ajax',
            'price_cents' => 500000,
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_FLEXIBLE,
            'is_active' => true,
        ]);
        $this->packageB = LessonPackage::factory()->forPartner((int) $this->partner->id)->create([
            'name' => 'Ромашка ajax',
            'price_cents' => 700000,
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_FLEXIBLE,
            'is_active' => true,
        ]);
        $this->row = UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-10-01',
            'price_cents' => 500000,
            'is_paid' => 0,
            'lesson_package_id' => $this->packageA->id,
        ]);
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

    public function test_ajax_paid_with_card_package_returns_json_user_price(): void
    {
        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), [
                'user_id' => $this->student->id,
                'team_id' => $this->team->id,
                'selectedDate' => 'Октябрь 2024',
                'mode' => 'paid',
                'comment' => 'Оплата с ромашкой из селекта',
                'lesson_package_id' => $this->packageB->id,
                'price' => 7000.0,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'user_price' => [
                    'id',
                    'user_id',
                    'team_id',
                    'lesson_package_id',
                    'price',
                    'is_manual_paid',
                ],
            ])
            ->assertJsonPath('user_price.lesson_package_id', $this->packageB->id)
            ->assertJsonPath('user_price.user_id', $this->student->id);

        $this->assertNotSame('', trim($response->getContent()));
        $this->assertNotSame('{}', trim($response->getContent()));
    }

    public function test_ajax_missing_comment_returns_422_on_comment(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), [
                'user_id' => $this->student->id,
                'team_id' => $this->team->id,
                'selectedDate' => 'Октябрь 2024',
                'mode' => 'paid',
                'lesson_package_id' => $this->packageB->id,
                'price' => 7000.0,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['comment'])
            ->assertJsonPath('errors.comment.0', 'Укажите комментарий к ручному изменению.');

        $this->assertDatabaseHas('users_prices', [
            'id' => $this->row->id,
            'lesson_package_id' => $this->packageA->id,
            'is_manual_paid' => null,
        ]);
    }

    public function test_ajax_short_comment_returns_422_on_comment(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), [
                'user_id' => $this->student->id,
                'team_id' => $this->team->id,
                'selectedDate' => 'Октябрь 2024',
                'mode' => 'paid',
                'comment' => 'аб',
                'lesson_package_id' => $this->packageB->id,
                'price' => 7000.0,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['comment'])
            ->assertJsonPath('errors.comment.0', 'Комментарий должен содержать не менее 3 символов.');

        $this->assertDatabaseHas('users_prices', [
            'id' => $this->row->id,
            'is_manual_paid' => null,
        ]);
    }

    public function test_ajax_missing_team_returns_422_on_team_id(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), [
                'user_id' => $this->student->id,
                'selectedDate' => 'Октябрь 2024',
                'mode' => 'paid',
                'comment' => 'Нет группы',
                'lesson_package_id' => $this->packageB->id,
                'price' => 7000.0,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_id'])
            ->assertJsonPath('errors.team_id.0', 'Выберите группу для изменения статуса оплаты.');
    }

    public function test_ajax_invalid_mode_returns_422_on_mode(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), [
                'user_id' => $this->student->id,
                'team_id' => $this->team->id,
                'selectedDate' => 'Октябрь 2024',
                'mode' => 'clear',
                'comment' => 'Недопустимый режим',
                'lesson_package_id' => $this->packageB->id,
                'price' => 7000.0,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['mode'])
            ->assertJsonPath('errors.mode.0', 'Некорректный режим ручной отметки оплаты.');
    }

    public function test_ajax_unknown_package_returns_422_on_lesson_package_id(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), [
                'user_id' => $this->student->id,
                'team_id' => $this->team->id,
                'selectedDate' => 'Октябрь 2024',
                'mode' => 'paid',
                'comment' => 'Нет такого абонемента',
                'lesson_package_id' => 999999,
                'price' => 7000.0,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lesson_package_id'])
            ->assertJsonPath('errors.lesson_package_id.0', 'Выбранный абонемент не найден или недоступен.');

        $this->assertDatabaseHas('users_prices', [
            'id' => $this->row->id,
            'lesson_package_id' => $this->packageA->id,
            'is_manual_paid' => null,
        ]);
    }

    public function test_ajax_negative_price_returns_422_on_price(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), [
                'user_id' => $this->student->id,
                'team_id' => $this->team->id,
                'selectedDate' => 'Октябрь 2024',
                'mode' => 'paid',
                'comment' => 'Отрицательная сумма',
                'lesson_package_id' => $this->packageB->id,
                'price' => -1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['price'])
            ->assertJsonPath('errors.price.0', 'Цена не может быть отрицательной.');

        $this->assertDatabaseHas('users_prices', [
            'id' => $this->row->id,
            'lesson_package_id' => $this->packageA->id,
            'is_manual_paid' => null,
        ]);
    }

    public function test_ajax_foreign_partner_package_returns_422_on_lesson_package_id(): void
    {
        $foreign = LessonPackage::factory()->forPartner((int) $this->foreignPartner->id)->create([
            'name' => 'Чужой пакет',
            'price_cents' => 100000,
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_FLEXIBLE,
            'is_active' => true,
        ]);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), [
                'user_id' => $this->student->id,
                'team_id' => $this->team->id,
                'selectedDate' => 'Октябрь 2024',
                'mode' => 'paid',
                'comment' => 'Пакет другого партнёра',
                'lesson_package_id' => $foreign->id,
                'price' => 1000.0,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lesson_package_id'])
            ->assertJsonPath('errors.lesson_package_id.0', 'Выбранный абонемент не найден или недоступен.');

        $this->assertDatabaseHas('users_prices', [
            'id' => $this->row->id,
            'lesson_package_id' => $this->packageA->id,
            'is_manual_paid' => null,
        ]);
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
}
