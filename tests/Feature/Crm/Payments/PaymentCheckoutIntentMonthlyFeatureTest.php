<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Payments;

use App\Models\LessonPackage;
use App\Models\Payable;
use App\Models\PaymentIntent;
use App\Models\PaymentSystem;
use App\Models\Team;
use App\Models\UserCustomPayment;
use App\Models\UserLessonPackage;
use App\Models\UserPrice;
use App\Services\Payments\PaymentCheckoutIntentSigner;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Payments\Concerns\SignsPaymentCheckout;

/**
 * Обычная оплата месяца: повтор без типа, подмена полей и все три способа
 * остаются месяцем из подписанной страницы.
 *
 * @see /docs/documentation/payments.html#vitrina-payment
 */
final class PaymentCheckoutIntentMonthlyFeatureTest extends CrmTestCase
{
    use SignsPaymentCheckout;
    use FamilyStudentPaymentFixtures;

    private const MONTH = '2026-09-01';

    private const OTHER_MONTH = '2026-10-01';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    public function test_second_submit_without_type_fields_still_pays_the_opened_month(): void
    {
        [$team] = $this->preparePaidMonth();

        foreach ($this->monthInitUrls() as $label => $url) {
            Payable::query()->delete();
            PaymentIntent::query()->delete();

            $response = $this->post($url, [
                'outSum' => '1.00',
                'paymentDate' => 'Клубный взнос',
                'checkout_intent' => $this->signMonthlyCheckout($this->user, self::MONTH, (int) $team->id),
            ]);

            $response->assertRedirect();
            $this->assertNotSame(500, $response->getStatusCode(), $label);
            $this->assertNotSame(200, $response->getStatusCode(), $label);

            $payable = Payable::query()->latest('id')->first();
            $this->assertNotNull($payable, $label);
            $this->assertSame('monthly_fee', (string) $payable->type, $label);
            $this->assertSame(360000, (int) $payable->amount_cents, $label);
            $this->assertStringStartsWith(self::MONTH, (string) $payable->month, $label);
            $this->assertSame((int) $team->id, (int) ($payable->meta['team_id'] ?? 0), $label);
            $this->assertSame((int) $this->user->id, (int) $payable->user_id, $label);
            $this->assertSame(0, Payable::query()->where('type', 'club_fee')->count(), $label);

            $intent = PaymentIntent::query()->latest('id')->first();
            $this->assertNotNull($intent, $label);
            $this->assertSame(360000, (int) $intent->out_sum_cents, $label);
            $this->assertStringStartsWith(self::MONTH, (string) $intent->payment_date, $label);
        }
    }

    public function test_month_submit_with_only_signed_context_still_pays_that_month(): void
    {
        [$team] = $this->preparePaidMonth();

        foreach ($this->monthInitUrls() as $label => $url) {
            Payable::query()->delete();
            PaymentIntent::query()->delete();

            $this->post($url, [
                'checkout_intent' => $this->signMonthlyCheckout($this->user, self::MONTH, (int) $team->id),
            ])->assertRedirect();

            $payable = Payable::query()->latest('id')->first();
            $this->assertNotNull($payable, $label);
            $this->assertSame('monthly_fee', (string) $payable->type, $label);
            $this->assertSame(360000, (int) $payable->amount_cents, $label);
            $this->assertSame(0, Payable::query()->where('type', 'club_fee')->count(), $label);
        }
    }

    public function test_edited_month_team_and_amount_do_not_replace_the_opened_month(): void
    {
        [$teamA, $teamB] = $this->prepareTwoMonths();

        foreach ($this->monthInitUrls() as $label => $url) {
            Payable::query()->delete();
            PaymentIntent::query()->delete();

            $this->post($url, [
                'paymentDate' => 'Октябрь 2026',
                'formatedPaymentDate' => self::OTHER_MONTH,
                'team_id' => (int) $teamB->id,
                'outSum' => '1.00',
                'payment_kind' => 'lesson_package',
                'user_lesson_package_id' => 999999,
                'checkout_intent' => $this->signMonthlyCheckout($this->user, self::MONTH, (int) $teamA->id),
            ])->assertRedirect();

            $payable = Payable::query()->latest('id')->first();
            $this->assertNotNull($payable, $label);
            $this->assertSame('monthly_fee', (string) $payable->type, $label);
            $this->assertSame(360000, (int) $payable->amount_cents, $label);
            $this->assertStringStartsWith(self::MONTH, (string) $payable->month, $label);
            $this->assertSame((int) $teamA->id, (int) ($payable->meta['team_id'] ?? 0), $label);
        }
    }

    public function test_month_page_puts_the_same_signed_month_into_every_pay_button(): void
    {
        [$team] = $this->preparePaidMonth();

        $response = $this->post(route('payment'), [
            'paymentDate' => 'Сентябрь 2026',
            'formatedPaymentDate' => self::MONTH,
            'team_id' => (int) $team->id,
            'outSum' => '1.00',
        ]);

        $response->assertOk()->assertViewIs('payment.paymentUser');
        $html = (string) $response->getContent();
        $this->assertNotSame('', trim(strip_tags($html)));
        $this->assertStringNotContainsString('application/json', (string) $response->headers->get('Content-Type'));

        preg_match_all('/name="checkout_intent" value="([^"]*)"/', $html, $matches);
        $this->assertCount(3, $matches[1], 'СБП, карта и Робокасса');
        $this->assertCount(1, array_unique($matches[1]));

        $opened = app(PaymentCheckoutIntentSigner::class)->open(
            html_entity_decode($matches[1][0], ENT_QUOTES),
            $this->user,
            (int) $this->partner->id,
        );
        $this->assertTrue($opened->isMonthly());
        $this->assertSame(self::MONTH, $opened->month);
        $this->assertSame((int) $team->id, $opened->teamId);
        $this->assertSame((int) $this->user->id, $opened->studentUserId);

        $this->assertSame(3, substr_count($html, 'name="formatedPaymentDate" value="'.self::MONTH.'"'));
        $this->assertSame(3, substr_count($html, 'name="outSum" value="3600.00"'));
        $this->assertStringNotContainsString('name="outSum" value="1.00"', $html);
        $this->assertStringNotContainsString('id="checkout-unavailable"', $html);
        $this->assertStringNotContainsString('id="checkout-intent-error"', $html);
        $this->assertStringContainsString((string) $team->title, $html);
    }

    public function test_opened_month_stays_on_that_child_after_the_parent_switches_back(): void
    {
        $this->createSiblingStudents();
        $this->grantPermission($this->brother1, 'payment.method.robokassa');
        $this->grantPermission($this->brother1, 'paying.classes');
        PaymentSystem::factory()->robokassa()->create(['partner_id' => $this->partner->id]);

        $team = $this->makeTeam('Общая');
        $this->attachStudent($this->brother1, $team);
        $this->attachStudent($this->brother2, $team);
        UserPrice::factory()->forUserAndMonth((int) $this->brother2->id, self::MONTH, 250000, false, (int) $team->id)->create();
        UserPrice::factory()->forUserAndMonth((int) $this->brother1->id, self::MONTH, 100000, false, (int) $team->id)->create();

        $this->actingAs($this->brother1);
        $this->switchTo($this->brother2);

        $html = (string) $this->post(route('payment'), [
            'paymentDate' => 'Сентябрь 2026',
            'formatedPaymentDate' => self::MONTH,
            'team_id' => (int) $team->id,
            'outSum' => '1.00',
        ])->assertOk()->assertViewHas('outSum', '2500.00')->getContent();

        preg_match('/name="checkout_intent" value="([^"]+)"/', $html, $match);
        $this->assertNotEmpty($match[1] ?? null);

        $this->switchTo($this->brother1);

        $this->post(route('payment.pay'), [
            'outSum' => '1.00',
            'formatedPaymentDate' => self::OTHER_MONTH,
            'team_id' => (int) $team->id,
            'checkout_intent' => html_entity_decode($match[1], ENT_QUOTES),
        ])->assertRedirect();

        $payable = Payable::query()->latest('id')->first();
        $this->assertNotNull($payable);
        $this->assertSame('monthly_fee', (string) $payable->type);
        $this->assertSame((int) $this->brother2->id, (int) $payable->user_id);
        $this->assertSame(250000, (int) $payable->amount_cents);
        $this->assertStringStartsWith(self::MONTH, (string) $payable->month);
    }

    public function test_month_without_signed_context_shows_field_error_and_creates_nothing(): void
    {
        $this->preparePaidMonth();

        foreach ($this->monthInitUrls() as $label => $url) {
            $this->from(route('dashboard'))
                ->post($url, [
                    'outSum' => '3600.00',
                    'paymentDate' => 'Клубный взнос',
                    'formatedPaymentDate' => self::MONTH,
                ])
                ->assertRedirect(route('dashboard'))
                ->assertSessionHasErrors([
                    'checkout_intent' => PaymentCheckoutIntentSigner::MESSAGE_REOPEN,
                ]);

            $json = $this->postJson($url, [
                'outSum' => '3600.00',
                'formatedPaymentDate' => self::MONTH,
            ]);
            $json->assertStatus(422);
            $json->assertJsonValidationErrors([
                'checkout_intent' => PaymentCheckoutIntentSigner::MESSAGE_REOPEN,
            ]);
            $this->assertNotSame(500, $json->getStatusCode(), $label);
        }

        $this->assertSame(0, Payable::query()->count());
    }

    public function test_broken_month_submit_from_the_payment_page_shows_the_error_on_that_page(): void
    {
        [$team] = $this->preparePaidMonth();

        $this->post(route('payment'), [
            'paymentDate' => 'Сентябрь 2026',
            'formatedPaymentDate' => self::MONTH,
            'team_id' => (int) $team->id,
            'outSum' => '1.00',
        ])->assertOk();

        $response = $this->followingRedirects()->post(route('payment.tinkoff.sbp'), [
            'outSum' => '3600.00',
            'paymentDate' => 'Клубный взнос',
        ], ['HTTP_REFERER' => url('/payment')]);

        $response->assertOk();
        $response->assertSee(PaymentCheckoutIntentSigner::MESSAGE_REOPEN, false);
        $response->assertSee('id="checkout-intent-error"', false);
        $response->assertDontSee('Оплатить через СБП', false);
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertSame(0, Payable::query()->count());
    }

    public function test_parent_without_a_pay_method_cannot_start_the_opened_month(): void
    {
        [$team] = $this->seedMonthPrice();
        $token = $this->signMonthlyCheckout($this->user, self::MONTH, (int) $team->id);
        $this->seedProviders();

        $ids = array_map(
            fn (string $name) => $this->permissionId($name),
            ['payment.method.robokassa', 'payment.method.tbankCard', 'payment.method.tbankSBP'],
        );
        DB::table('permission_role')
            ->where('partner_id', $this->partner->id)
            ->where('role_id', $this->user->role_id)
            ->whereIn('permission_id', $ids)
            ->delete();
        $this->user->unsetRelation('role');
        $this->actingAs($this->user);

        foreach ($this->monthInitUrls() as $label => $url) {
            $this->post($url, [
                'formatedPaymentDate' => self::MONTH,
                'team_id' => (int) $team->id,
                'outSum' => '3600.00',
                'checkout_intent' => $token,
            ])->assertForbidden();
            $this->assertSame(0, Payable::query()->count(), $label);
        }
    }

    public function test_guest_cannot_open_or_start_a_month_payment(): void
    {
        [$team] = $this->preparePaidMonth();
        $token = $this->signMonthlyCheckout($this->user, self::MONTH, (int) $team->id);
        Auth::logout();

        foreach (array_merge([route('payment')], array_values($this->monthInitUrls())) as $url) {
            $response = $this->post($url, [
                'formatedPaymentDate' => self::MONTH,
                'outSum' => '3600.00',
                'checkout_intent' => $token,
            ]);
            $this->assertContains($response->getStatusCode(), [302, 401, 403, 419]);
            $this->assertNotSame(500, $response->getStatusCode());
            $this->assertNotSame(200, $response->getStatusCode());
        }

        $this->assertSame(0, Payable::query()->count());
    }

    public function test_parent_without_cabinet_payment_right_cannot_open_the_month_page(): void
    {
        DB::table('permission_role')
            ->where('role_id', $this->user->role_id)
            ->where('partner_id', $this->partner->id)
            ->where('permission_id', $this->permissionId('paying.classes'))
            ->delete();
        $this->user->unsetRelation('role');

        $this->post(route('payment'), [
            'formatedPaymentDate' => self::MONTH,
            'outSum' => '1.00',
        ])->assertForbidden();
    }

    public function test_wrong_http_methods_on_month_payment_are_not_a_blank_success(): void
    {
        $this->preparePaidMonth();

        $this->get(route('payment'))
            ->assertRedirect(route('dashboard'));

        foreach (array_merge([route('payment')], array_values($this->monthInitUrls())) as $url) {
            foreach (['GET', 'PATCH', 'PUT', 'DELETE'] as $method) {
                if ($url === route('payment') && $method === 'GET') {
                    continue;
                }
                $response = $this->call($method, $url, [
                    'formatedPaymentDate' => self::MONTH,
                    'outSum' => '1.00',
                ]);
                $this->assertNotSame(500, $response->getStatusCode(), $method.' '.$url);
                $this->assertNotSame(200, $response->getStatusCode(), $method.' '.$url);
            }
        }

        $this->assertSame(0, Payable::query()->count());
    }

    public function test_already_paid_month_does_not_start_another_payment(): void
    {
        [$team] = $this->preparePaidMonth(true);

        foreach ($this->monthInitUrls() as $label => $url) {
            $response = $this->post($url, [
                'formatedPaymentDate' => self::MONTH,
                'team_id' => (int) $team->id,
                'checkout_intent' => $this->signMonthlyCheckout($this->user, self::MONTH, (int) $team->id),
            ]);

            $this->assertContains($response->getStatusCode(), [302, 422], $label);
            $this->assertNotSame(500, $response->getStatusCode(), $label);
            $this->assertNotSame(200, $response->getStatusCode(), $label);
        }

        $this->assertSame(0, Payable::query()->count());
    }

    public function test_month_without_a_charge_does_not_create_a_payment(): void
    {
        $this->grantMonthPermissions();
        $this->seedProviders();
        $team = $this->attachTeam('Пустой месяц');

        foreach ($this->monthInitUrls() as $label => $url) {
            $response = $this->post($url, [
                'formatedPaymentDate' => self::MONTH,
                'team_id' => (int) $team->id,
                'outSum' => '3600.00',
                'checkout_intent' => $this->signMonthlyCheckout($this->user, self::MONTH, (int) $team->id),
            ]);

            $this->assertContains($response->getStatusCode(), [302, 403, 422], $label);
            $this->assertNotSame(500, $response->getStatusCode(), $label);
            $this->assertNotSame(200, $response->getStatusCode(), $label);
        }

        $this->assertSame(0, Payable::query()->count());
    }

    public function test_package_and_extra_payment_keep_their_type_when_the_form_fields_are_stripped(): void
    {
        $this->grantMonthPermissions();
        $this->seedProviders();
        $team = $this->attachTeam('Абонемент');

        $custom = UserCustomPayment::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'team_id' => $team->id,
            'date_start' => '2026-10-01',
            'date_end' => '2026-10-31',
            'amount_cents' => 32100,
            'is_paid' => 0,
        ]);
        $package = $this->makeLessonAssignment($team, 44400);
        $other = $this->makeLessonAssignment($team, 11100);

        foreach ($this->monthInitUrls() as $label => $url) {
            Payable::query()->delete();
            PaymentIntent::query()->delete();
            $this->post($url, [
                'outSum' => '1.00',
                'paymentDate' => 'Клубный взнос',
                'payment_kind' => 'lesson_package',
                'user_lesson_package_id' => (int) $other->id,
                'checkout_intent' => $this->signCustomCheckout($this->user, (int) $custom->id),
            ])->assertRedirect();
            $payable = Payable::query()->latest('id')->first();
            $this->assertNotNull($payable, $label);
            $this->assertSame('custom_payment_fee', (string) $payable->type, $label);
            $this->assertSame(32100, (int) $payable->amount_cents, $label);
            $this->assertSame((int) $custom->id, (int) ($payable->meta['user_period_price_id'] ?? 0), $label);

            Payable::query()->delete();
            PaymentIntent::query()->delete();
            $this->post($url, [
                'outSum' => '9.00',
                'checkout_intent' => $this->signLessonCheckout($this->user, (int) $package->id),
            ])->assertRedirect();
            $payable = Payable::query()->latest('id')->first();
            $this->assertNotNull($payable, $label);
            $this->assertSame('lesson_package_fee', (string) $payable->type, $label);
            $this->assertSame(44400, (int) $payable->amount_cents, $label);
            $this->assertSame((int) $package->id, (int) ($payable->meta['user_lesson_package_id'] ?? 0), $label);
            $this->assertSame(0, Payable::query()->where('type', 'club_fee')->count(), $label);
        }
    }

    public function test_unknown_payment_page_hides_buttons_instead_of_offering_a_club_fee(): void
    {
        $this->grantMonthPermissions();
        $this->seedProviders();
        $this->attachTeam('Без месяца');

        $html = (string) $this->post(route('payment'), [
            'paymentDate' => '',
            'outSum' => '500.00',
        ])->assertOk()->getContent();

        $this->assertStringContainsString('id="checkout-unavailable"', $html);
        $this->assertStringContainsString('Не удалось определить оплату', $html);
        $this->assertStringNotContainsString('name="checkout_intent"', $html);
        $this->assertStringNotContainsString('Оплатить через СБП', $html);
        $this->assertStringNotContainsString('Оплатить картой', $html);
        $this->assertStringNotContainsString('Клубный взнос', $html);
    }

    /**
     * @return array<string, string>
     */
    private function monthInitUrls(): array
    {
        return [
            'robokassa' => route('payment.pay'),
            'card' => route('payment.tinkoff.pay'),
            'sbp' => route('payment.tinkoff.sbp'),
        ];
    }

    /**
     * @return array{0: Team, 1: Team}
     */
    private function prepareTwoMonths(): array
    {
        $this->grantMonthPermissions();
        $this->seedProviders();
        $teamA = $this->attachTeam('Сентябрь');
        $teamB = $this->attachTeam('Октябрь');
        UserPrice::factory()->forUserAndMonth((int) $this->user->id, self::MONTH, 360000, false, (int) $teamA->id)->create();
        UserPrice::factory()->forUserAndMonth((int) $this->user->id, self::OTHER_MONTH, 100000, false, (int) $teamB->id)->create();

        return [$teamA, $teamB];
    }

    /**
     * @return array{0: Team}
     */
    private function preparePaidMonth(bool $paid = false): array
    {
        $this->grantMonthPermissions();
        $this->seedProviders();

        return $this->seedMonthPrice($paid);
    }

    /**
     * @return array{0: Team}
     */
    private function seedMonthPrice(bool $paid = false): array
    {
        $team = $this->attachTeam('Сентябрь');
        UserPrice::factory()->forUserAndMonth((int) $this->user->id, self::MONTH, 360000, $paid, (int) $team->id)->create();

        return [$team];
    }

    private function grantMonthPermissions(): void
    {
        foreach ([
            'paying.classes',
            'payment.method.robokassa',
            'payment.method.tbankCard',
            'payment.method.tbankSBP',
        ] as $permission) {
            DB::table('permission_role')->insertOrIgnore([
                'partner_id' => $this->partner->id,
                'role_id' => $this->user->role_id,
                'permission_id' => $this->permissionId($permission),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $this->user->unsetRelation('role');
        $this->actingAs($this->user);
    }

    private function seedProviders(): void
    {
        PaymentSystem::factory()->robokassa()->create(['partner_id' => $this->partner->id]);
        $this->seedGlobalTbank([
            'terminal_key' => 'TERM-MONTH',
            'token_password' => 'PWD-MONTH',
            'e2c_terminal_key' => 'E2C-MONTH',
            'e2c_token_password' => 'E2C-PWD',
        ]);
        $paymentId = 910000;
        Http::fake(function () use (&$paymentId) {
            $paymentId++;

            return Http::response([
                'Success' => true,
                'PaymentId' => $paymentId,
                'PaymentURL' => 'https://example.test/month-pay',
                'Data' => 'https://example.test/month-qr',
            ], 200);
        });
    }

    private function attachTeam(string $title): Team
    {
        $chain = $this->seedTbankTeamChainForStudent(shopCode: 'SHOP-'.substr(md5($title), 0, 10));
        $chain['team']->update(['title' => $title]);

        return $chain['team']->fresh();
    }

    private function makeLessonAssignment(Team $team, int $feeCents): UserLessonPackage
    {
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Пакет '.$feeCents,
            'schedule_type' => 'no_schedule',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => $feeCents,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        return UserLessonPackage::query()->create([
            'user_id' => $this->user->id,
            'lesson_package_id' => $package->id,
            'team_id' => $team->id,
            'starts_at' => '2026-09-01',
            'ends_at' => '2026-09-30',
            'lessons_total' => 8,
            'lessons_remaining' => 8,
            'fee_amount_cents' => $feeCents,
            'is_paid' => false,
        ]);
    }
}
