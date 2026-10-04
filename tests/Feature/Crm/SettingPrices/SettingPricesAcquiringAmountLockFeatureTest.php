<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPrice;
use App\Services\Postpay\PostpayUsersPriceSync;
use App\Services\Pricing\UserUnpaidPriceDiscountRecalc;
use App\Services\SettingPrices\FormerMemberMonthChargeService;
use App\Services\TeamUserSyncService;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Ручное «Не оплачено» не открывает сумму месяца, уже оплаченного эквайрингом.
 */
final class SettingPricesAcquiringAmountLockFeatureTest extends CrmTestCase
{
    private const MONTH_LABEL = 'Сентябрь 2026';

    private const MONTH_DATE = '2026-09-01';

    private Team $team;

    private User $student;

    private LessonPackage $package;

    private LessonPackage $otherPackage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->asAdmin();
        $this->grantLessonPackageTypePermissions($this->user, ['fixed', 'flexible', 'no_schedule', 'postpay']);
        $this->grantPartnerRolePermission($this->user, 'setPrices.manualPaid.manage');

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
            'title' => 'Группа эквайринг',
        ]);
        $this->student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'is_enabled' => true,
            'name' => 'Дмитрий',
            'lastname' => 'Васильев',
        ]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($this->student, [(int) $this->team->id]);
        $this->package = LessonPackage::factory()->forPartner((int) $this->partner->id)->flexible(8, 60)->create([
            'name' => '8 занятий',
            'price_cents' => 54000,
            'is_active' => true,
        ]);
        $this->otherPackage = LessonPackage::factory()->forPartner((int) $this->partner->id)->flexible(12, 60)->create([
            'name' => '12 занятий',
            'price_cents' => 360000,
            'is_active' => true,
        ]);
    }

    public function test_manual_unpaid_then_new_amount_is_rejected_and_acquiring_sum_stays(): void
    {
        $row = $this->seedAcquiringPaid(54000);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), $this->statusPayload('unpaid'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user_price.effective_is_paid', false)
            ->assertJsonPath('user_price.price', 540);

        $row->refresh();
        $this->assertSame(0, (int) $row->is_manual_paid);
        $this->assertSame(1, (int) $row->is_paid);
        $this->assertSame(54000, (int) $row->price_cents);

        $paid = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), $this->statusPayload('paid', [
                'lesson_package_id' => $this->otherPackage->id,
                'price' => 3600,
            ]));

        $paid->assertStatus(422);
        $this->assertSame(
            UserPrice::ACQUIRING_AMOUNT_LOCKED_MESSAGE,
            $this->jsonFieldError($paid, 'price')
        );

        $row->refresh();
        $this->assertSame(54000, (int) $row->price_cents);
        $this->assertSame((int) $this->package->id, (int) $row->lesson_package_id);
        $this->assertSame(1, (int) $row->is_paid);
        $this->assertSame(0, (int) $row->is_manual_paid);
        $this->assertFalse($row->effective_is_paid);
    }

    public function test_right_apply_and_year_save_reject_new_amount_after_manual_unpaid(): void
    {
        $row = $this->seedAcquiringPaid(54000);
        $this->markManualUnpaid($row);

        $right = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamId' => $this->team->id,
                'usersPrice' => [[
                    'user_id' => $this->student->id,
                    'price' => 3600,
                    'lesson_package_id' => $this->package->id,
                    'user' => ['name' => 'Дмитрий'],
                ]],
            ]);
        $right->assertStatus(422);
        $this->assertSame(
            UserPrice::ACQUIRING_AMOUNT_LOCKED_MESSAGE,
            $this->jsonFieldError($right, 'usersPrice.0.price')
        );

        $year = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.user-year-prices.save'), [
                'user_id' => $this->student->id,
                'team_id' => $this->team->id,
                'year' => 2026,
                'prices' => [[
                    'new_month' => self::MONTH_DATE,
                    'price' => 3600,
                    'lesson_package_id' => $this->package->id,
                ]],
            ]);
        $year->assertStatus(422);
        $this->assertSame(
            UserPrice::ACQUIRING_AMOUNT_LOCKED_MESSAGE,
            $this->jsonFieldError($year, 'prices.0.price')
        );

        $this->assertSame(54000, (int) $row->fresh()->price_cents);
    }

    public function test_same_amount_can_replace_prepaid_after_manual_unpaid(): void
    {
        $row = $this->seedAcquiringPaid(54000);
        $this->markManualUnpaid($row);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamId' => $this->team->id,
                'usersPrice' => [[
                    'user_id' => $this->student->id,
                    'price' => 540,
                    'lesson_package_id' => $this->otherPackage->id,
                    'user' => ['name' => 'Дмитрий'],
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $row->refresh();
        $this->assertSame(54000, (int) $row->price_cents);
        $this->assertSame((int) $this->otherPackage->id, (int) $row->lesson_package_id);
        $this->assertSame(1, (int) $row->is_paid);
    }

    public function test_team_snapshot_discount_postpay_and_former_clear_keep_acquiring_amount(): void
    {
        $row = $this->seedAcquiringPaid(54000);
        $this->markManualUnpaid($row);

        $this->student->forceFill([
            'discount_percent' => 10,
            'discount_comment' => 'Льгота',
        ])->save();
        $changed = app(UserUnpaidPriceDiscountRecalc::class)->apply($this->student->fresh(), (int) $this->user->id);
        $this->assertSame(0, $changed);
        $this->assertSame(54000, (int) $row->fresh()->price_cents);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setTeamPrice'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamId' => $this->team->id,
                'lesson_package_id' => $this->otherPackage->id,
            ])
            ->assertOk();

        $row->refresh();
        $this->assertSame(54000, (int) $row->price_cents);
        $this->assertSame((int) $this->otherPackage->id, (int) $row->lesson_package_id);

        $postpay = LessonPackage::factory()->forPartner((int) $this->partner->id)->postpay()->create([
            'name' => 'Постоплата',
            'price_cents' => 30000,
            'is_active' => true,
        ]);
        $postpayRow = UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-10-01',
            'price_cents' => 54000,
            'is_paid' => 1,
            'is_manual_paid' => 0,
            'lesson_package_id' => $postpay->id,
        ]);
        $postpayRow->setRelation('lessonPackage', $postpay);
        app(PostpayUsersPriceSync::class)->syncRow($postpayRow);
        $this->assertSame(54000, (int) $postpayRow->fresh()->price_cents);

        $service = app(FormerMemberMonthChargeService::class);
        $assessment = $service->assess($row->fresh());
        $this->assertFalse($assessment['can_clear']);
        $this->assertSame(FormerMemberMonthChargeService::REASON_PAID, $assessment['block_reason']);

        try {
            $service->clear($row->fresh(), (int) $this->user->id);
            $this->fail('Корзина не должна обнулять сумму эквайринга.');
        } catch (ValidationException $e) {
            $this->assertSame(
                FormerMemberMonthChargeService::REASON_PAID,
                $e->errors()['charge'][0] ?? null
            );
        }

        $this->assertSame(54000, (int) $row->fresh()->price_cents);
    }

    public function test_manual_only_month_can_change_amount_after_unpaid(): void
    {
        $row = UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-11-01',
            'price_cents' => 54000,
            'is_paid' => 0,
            'is_manual_paid' => 1,
            'lesson_package_id' => $this->package->id,
        ]);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), [
                'user_id' => $this->student->id,
                'team_id' => $this->team->id,
                'selectedDate' => 'Ноябрь 2026',
                'mode' => 'unpaid',
                'comment' => 'Снять ручную отметку',
            ])
            ->assertOk();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'selectedDate' => 'Ноябрь 2026',
                'teamId' => $this->team->id,
                'usersPrice' => [[
                    'user_id' => $this->student->id,
                    'price' => 3600,
                    'lesson_package_id' => $this->package->id,
                    'user' => ['name' => 'Дмитрий'],
                ]],
            ])
            ->assertOk();

        $this->assertSame(360000, (int) $row->fresh()->price_cents);
        $this->assertSame(0, (int) $row->fresh()->is_paid);
    }

    private function seedAcquiringPaid(int $priceCents): UserPrice
    {
        return UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => self::MONTH_DATE,
            'price_cents' => $priceCents,
            'is_paid' => 1,
            'is_manual_paid' => null,
            'lesson_package_id' => $this->package->id,
        ]);
    }

    private function markManualUnpaid(UserPrice $row): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), $this->statusPayload('unpaid'))
            ->assertOk();

        $row->refresh();
        $this->assertFalse($row->effective_is_paid);
        $this->assertTrue((bool) $row->is_paid);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function statusPayload(string $mode, array $extra = []): array
    {
        return array_merge([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'selectedDate' => self::MONTH_LABEL,
            'mode' => $mode,
            'comment' => $mode === 'paid' ? 'Отметить оплаченным' : 'Снять отметку после эквайринга',
        ], $extra);
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

    private function jsonFieldError(\Illuminate\Testing\TestResponse $response, string $field): string
    {
        $errors = $response->json('errors');
        $this->assertIsArray($errors);
        $this->assertArrayHasKey($field, $errors, 'Нет errors['.$field.']: '.json_encode($errors, JSON_UNESCAPED_UNICODE));

        return (string) ($errors[$field][0] ?? '');
    }
}
