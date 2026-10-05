<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Payments;

use App\Models\Payable;
use App\Models\PaymentSystem;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPrice;
use App\Services\Payments\PaymentCheckoutIntent;
use App\Services\Payments\PaymentCheckoutIntentException;
use App\Services\Payments\PaymentCheckoutIntentSigner;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Payments\Concerns\SignsPaymentCheckout;

/**
 * Init берёт вид платежа только из подписанного checkout_intent.
 * Голый outSum без контекста страницы не становится клубным взносом.
 */
final class PaymentCheckoutIntentFeatureTest extends CrmTestCase
{
    use SignsPaymentCheckout;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    public function test_monthly_token_stays_monthly_when_type_fields_are_stripped(): void
    {
        $this->grantPermission('payment.method.robokassa');
        $this->seedRobokassa();
        $team = $this->attachTeam();
        UserPrice::factory()
            ->forUserAndMonth((int) $this->user->id, '2026-09-01', 360000, false, (int) $team->id)
            ->create();

        $response = $this->post(route('payment.pay'), [
            'outSum' => '3600.00',
            'checkout_intent' => $this->signMonthlyCheckout($this->user, '2026-09-01', (int) $team->id),
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('OutSum=3600.00', (string) $response->headers->get('Location'));

        $payable = Payable::query()->latest('id')->first();
        $this->assertNotNull($payable);
        $this->assertSame('monthly_fee', (string) $payable->type);
        $this->assertSame(360000, (int) $payable->amount_cents);
        $this->assertStringStartsWith('2026-09-01', (string) $payable->month);
        $this->assertSame(0, Payable::query()->where('type', 'club_fee')->count());
    }

    public function test_bare_out_sum_does_not_create_club_fee(): void
    {
        $this->grantPermission('payment.method.robokassa');
        $this->seedRobokassa();
        $this->attachTeam();

        $this->from(route('dashboard'))
            ->post(route('payment.pay'), [
                'outSum' => '3600.00',
                'paymentDate' => 'Клубный взнос',
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasErrors([
                'checkout_intent' => PaymentCheckoutIntentSigner::MESSAGE_REOPEN,
            ]);

        $this->assertSame(0, Payable::query()->count());
    }

    public function test_club_token_without_permission_does_not_create_payable(): void
    {
        $this->grantPermission('payment.method.robokassa');
        $this->seedRobokassa();
        $this->attachTeam();

        $this->from(route('clubFee'))
            ->post(route('payment.pay'), [
                'outSum' => '500.00',
                'checkout_intent' => $this->signClubCheckout($this->user),
            ])
            ->assertRedirect(route('clubFee'))
            ->assertSessionHasErrors([
                'checkout_intent' => PaymentCheckoutIntentSigner::MESSAGE_CLUB_FORBIDDEN,
            ]);

        $this->assertSame(0, Payable::query()->count());
    }

    public function test_club_token_with_permission_creates_club_fee(): void
    {
        $this->grantPermission('payment.method.robokassa');
        $this->grantPermission('payment.clubfee');
        $this->seedRobokassa();
        $team = $this->attachTeam();

        $response = $this->post(route('payment.pay'), [
            'outSum' => '500.00',
            'team_id' => (int) $team->id,
            'checkout_intent' => $this->signClubCheckout($this->user),
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('OutSum=500.00', (string) $response->headers->get('Location'));

        $payable = Payable::query()->latest('id')->first();
        $this->assertNotNull($payable);
        $this->assertSame('club_fee', (string) $payable->type);
        $this->assertSame(50000, (int) $payable->amount_cents);
        $this->assertSame((int) $this->user->id, (int) $payable->user_id);
    }

    public function test_tampered_and_foreign_tokens_do_not_create_payable(): void
    {
        $this->grantPermission('payment.method.robokassa');
        $this->seedRobokassa();
        $team = $this->attachTeam();
        UserPrice::factory()
            ->forUserAndMonth((int) $this->user->id, '2026-09-01', 360000, false, (int) $team->id)
            ->create();

        $token = $this->signMonthlyCheckout($this->user, '2026-09-01', (int) $team->id);

        $this->from(route('payment'))
            ->post(route('payment.pay'), [
                'outSum' => '3600.00',
                'checkout_intent' => $token.'x',
            ])
            ->assertSessionHasErrors([
                'checkout_intent' => PaymentCheckoutIntentSigner::MESSAGE_STALE,
            ]);

        $this->from(route('payment'))
            ->post(route('payment.pay'), [
                'outSum' => '3600.00',
                'checkout_intent' => $this->signMonthlyCheckout($this->foreignUser, '2026-09-01', (int) $team->id),
            ])
            ->assertSessionHasErrors([
                'checkout_intent' => PaymentCheckoutIntentSigner::MESSAGE_STALE,
            ]);

        $this->assertSame(0, Payable::query()->count());
    }

    public function test_expired_token_does_not_create_payable(): void
    {
        $this->grantPermission('payment.method.robokassa');
        $this->seedRobokassa();
        $team = $this->attachTeam();

        $token = $this->signMonthlyCheckout($this->user, '2026-09-01', (int) $team->id);
        $payload = json_decode(Crypt::decryptString($token), true);
        $this->assertIsArray($payload);
        $payload['exp'] = time() - 5;
        $expired = Crypt::encryptString(json_encode($payload, JSON_UNESCAPED_UNICODE));

        $this->from(route('payment'))
            ->post(route('payment.pay'), [
                'outSum' => '3600.00',
                'formatedPaymentDate' => '2026-09-01',
                'checkout_intent' => $expired,
            ])
            ->assertSessionHasErrors([
                'checkout_intent' => PaymentCheckoutIntentSigner::MESSAGE_STALE,
            ]);

        $this->assertSame(0, Payable::query()->count());
    }

    public function test_payment_and_club_fee_pages_render_checkout_intent(): void
    {
        $this->grantPermission('payment.method.robokassa');
        $this->grantPermission('payment.clubfee');
        $this->seedRobokassa();
        $team = $this->attachTeam();
        UserPrice::factory()
            ->forUserAndMonth((int) $this->user->id, '2026-09-01', 360000, false, (int) $team->id)
            ->create();

        $this->post(route('payment'), [
            'paymentDate' => 'Сентябрь 2026',
            'formatedPaymentDate' => '2026-09-01',
            'team_id' => (int) $team->id,
            'outSum' => '1.00',
        ])
            ->assertOk()
            ->assertSee('name="checkout_intent"', false)
            ->assertDontSee('id="checkout-unavailable"', false);

        $this->post(route('payment'), [
            'paymentDate' => '',
            'outSum' => '500.00',
        ])
            ->assertOk()
            ->assertSee('id="checkout-unavailable"', false)
            ->assertDontSee('name="checkout_intent"', false);

        $this->get(route('clubFee'))
            ->assertOk()
            ->assertSee('name="checkout_intent"', false);
    }

    public function test_signer_roundtrip_and_rejects_other_student(): void
    {
        $signer = app(PaymentCheckoutIntentSigner::class);
        $token = $signer->issue(
            PaymentCheckoutIntent::KIND_MONTHLY,
            (int) $this->partner->id,
            (int) $this->user->id,
            (int) $this->user->id,
            month: '2026-09-01',
            teamId: 31,
        );

        $opened = $signer->open($token, $this->user, (int) $this->partner->id);
        $this->assertSame(PaymentCheckoutIntent::KIND_MONTHLY, $opened->kind);
        $this->assertSame('2026-09-01', $opened->month);
        $this->assertSame(31, $opened->teamId);
        $this->assertTrue($opened->isMonthly());

        $other = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
        ]);
        $foreignStudent = $signer->issue(
            PaymentCheckoutIntent::KIND_MONTHLY,
            (int) $this->partner->id,
            (int) $this->user->id,
            (int) $other->id,
            month: '2026-09-01',
        );

        try {
            $signer->open($foreignStudent, $this->user, (int) $this->partner->id);
            $this->fail('Чужой ученик должен быть отклонён.');
        } catch (PaymentCheckoutIntentException $e) {
            $this->assertSame(PaymentCheckoutIntentSigner::MESSAGE_STUDENT, $e->getMessage());
        }
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
        $this->user->unsetRelation('role');
    }

    private function seedRobokassa(): void
    {
        PaymentSystem::factory()->robokassa()->create(['partner_id' => $this->partner->id]);
    }

    private function attachTeam(): Team
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        app(TeamUserSyncService::class)->attachTeamForStudent($this->user, (int) $team->id);

        return $team;
    }
}
