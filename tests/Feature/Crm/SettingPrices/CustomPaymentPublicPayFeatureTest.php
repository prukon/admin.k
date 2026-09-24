<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\Partner;
use App\Models\PartnerLegalEntity;
use App\Models\Payable;
use App\Models\Team;
use App\Models\User;
use App\Models\UserCustomPayment;
use App\Models\UserCustomPaymentPublicPayLink;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Crm\CrmTestCase;

final class CustomPaymentPublicPayFeatureTest extends CrmTestCase
{
    private Team $team;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа публичной оплаты',
            'deleted_at' => null,
        ]);

        $this->student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => true,
            'lastname' => 'Плательщиков',
            'name' => 'Пётр',
        ]);

        app(TeamUserSyncService::class)->attachTeamForStudent($this->student, (int) $this->team->id);
        $this->grantCustomPaymentsView($this->user);
        $this->actingAs($this->user);
    }

    private function grantCustomPaymentsView(User $actor): void
    {
        foreach (['setPrices.view', 'setPrices.customPayments.view'] as $permission) {
            DB::table('permission_role')->insertOrIgnore([
                'partner_id' => $this->partner->id,
                'role_id' => $actor->role_id,
                'permission_id' => $this->permissionId($permission),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedTbankForPartner(): void
    {
        $this->seedGlobalTbank([
            'terminal_key' => 'TERM_UCP_PUBLIC_PAY',
            'token_password' => 'PWD_UCP_PUBLIC_PAY',
            'e2c_terminal_key' => 'E2C_TERM',
            'e2c_token_password' => 'E2C_PWD',
        ]);

        PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-UCP-PUBLIC-PAY')
            ->create(['is_default' => true]);

        Partner::query()->whereKey($this->partner->id)->update([
            'tinkoff_partner_id' => null,
            'tax_id' => null,
        ]);
        $this->partner->refresh();
    }

    private function createPayment(array $overrides = []): UserCustomPayment
    {
        return UserCustomPayment::query()->create(array_merge([
            'partner_id' => $this->partner->id,
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'amount_cents' => 50000,
            'note' => 'Интенсив',
            'is_paid' => false,
        ], $overrides));
    }

    private function fakeTbankInit(): void
    {
        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $url = $request->url();
            if (str_contains($url, '/v2/Init')) {
                $body = $request->data();

                return Http::response([
                    'Success' => true,
                    'PaymentId' => '8800112233',
                    'PaymentURL' => 'https://pay.example.test/',
                    'OrderId' => (string) ($body['OrderId'] ?? 'order-ucp'),
                    'ErrorCode' => '0',
                ], 200);
            }
            if (str_contains($url, '/v2/GetState')) {
                return Http::response([
                    'Success' => true,
                    'ErrorCode' => '0',
                    'Status' => 'NEW',
                    'Amount' => 50000,
                ], 200);
            }
            if (str_contains($url, '/v2/Cancel')) {
                return Http::response([
                    'Success' => true,
                    'ErrorCode' => '0',
                    'Status' => 'CANCELED',
                ], 200);
            }

            return Http::response(['Success' => false], 500);
        });
    }

    public function test_unknown_public_token_returns_404(): void
    {
        $this->get(route('ucp.public.pay', ['token' => str_repeat('b', 64)]))
            ->assertNotFound();
    }

    public function test_guest_cannot_issue_public_pay_link(): void
    {
        $payment = $this->createPayment();
        Auth::logout();

        $response = $this->postJson(route('admin.settingPrices.customPayments.public-pay-link', ['id' => $payment->id]));
        $this->assertContains($response->getStatusCode(), [302, 401, 403, 419]);
    }

    public function test_issue_returns_422_when_tbank_is_not_configured(): void
    {
        $payment = $this->createPayment();

        $this->postJson(route('admin.settingPrices.customPayments.public-pay-link', ['id' => $payment->id]))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Оплата T‑Bank не подключена для этого клуба');
    }

    public function test_issue_returns_422_when_already_paid_or_amount_is_below_ten_rubles(): void
    {
        $this->seedTbankForPartner();

        $paid = $this->createPayment(['is_paid' => true]);
        $this->postJson(route('admin.settingPrices.customPayments.public-pay-link', ['id' => $paid->id]))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Дополнительный платеж уже оплачен');

        $small = $this->createPayment(['amount_cents' => 900, 'is_paid' => false]);
        $this->postJson(route('admin.settingPrices.customPayments.public-pay-link', ['id' => $small->id]))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Оплата по СБП доступна для суммы от 10 ₽ до 1 000 000 ₽.');
    }

    public function test_issue_returns_short_url_and_public_page_inits_custom_payment_fee(): void
    {
        $this->seedTbankForPartner();
        $this->fakeTbankInit();
        $payment = $this->createPayment();

        $issue = $this->postJson(route('admin.settingPrices.customPayments.public-pay-link', ['id' => $payment->id]))
            ->assertOk();

        $url = (string) $issue->json('url');
        $this->assertStringContainsString('/pc/', $url);
        $this->assertStringNotContainsString('/pay/ucp/', $url);

        $code = substr($url, strrpos($url, '/') + 1);
        $this->assertSame(10, strlen($code));

        $link = UserCustomPaymentPublicPayLink::query()
            ->where('user_custom_payment_id', $payment->id)
            ->first();
        $this->assertNotNull($link);
        $this->assertSame($code, (string) $link->short_code);
        $this->assertSame(64, strlen((string) $link->token));

        $this->get(route('ucp.public.pay.short', ['code' => $code]))
            ->assertOk()
            ->assertViewIs('payment.ulp-public-pay')
            ->assertSee('Оплата через СБП', false)
            ->assertSee('Описание:', false)
            ->assertSee('Интенсив', false);

        $payable = Payable::query()->where('type', 'custom_payment_fee')->first();
        $this->assertNotNull($payable);
        $this->assertSame($payment->id, (int) ($payable->meta['user_period_price_id'] ?? 0));
        $this->assertSame((int) $this->team->id, (int) ($payable->meta['team_id'] ?? 0));
        $this->assertSame(50000, (int) $payable->amount_cents);
    }

    public function test_amount_change_rotates_short_code_and_old_url_returns_404(): void
    {
        $this->seedTbankForPartner();
        $payment = $this->createPayment();

        $issue = $this->postJson(route('admin.settingPrices.customPayments.public-pay-link', ['id' => $payment->id]))
            ->assertOk();
        $oldCode = substr((string) $issue->json('url'), strrpos((string) $issue->json('url'), '/') + 1);

        $this->putJson(route('admin.settingPrices.customPayments.update', ['id' => $payment->id]), [
            'amount' => 700,
            'note' => 'Интенсив',
            'is_paid' => false,
        ])->assertOk();

        $this->get(route('ucp.public.pay.short', ['code' => $oldCode]))
            ->assertNotFound();

        $link = UserCustomPaymentPublicPayLink::query()
            ->where('user_custom_payment_id', $payment->id)
            ->first();
        $this->assertNotNull($link);
        $this->assertNotSame($oldCode, (string) $link->short_code);
    }

    public function test_foreign_partner_payment_returns_404(): void
    {
        $this->seedTbankForPartner();
        $foreign = UserCustomPayment::query()->create([
            'partner_id' => $this->foreignPartner->id,
            'user_id' => $this->foreignUser->id,
            'team_id' => null,
            'amount_cents' => 50000,
            'is_paid' => false,
        ]);

        $this->postJson(route('admin.settingPrices.customPayments.public-pay-link', ['id' => $foreign->id]))
            ->assertNotFound();
    }

    public function test_datatable_exposes_pay_link_available_only_when_payable(): void
    {
        $ready = $this->createPayment(['note' => 'Можно копировать', 'amount_cents' => 15000]);
        $small = $this->createPayment(['note' => 'Мало', 'amount_cents' => 500]);
        $paid = $this->createPayment(['note' => 'Уже оплачен', 'amount_cents' => 15000, 'is_paid' => true]);

        $withoutTbank = $this->datatableRowByNote('Можно копировать');
        $this->assertFalse($withoutTbank['pay_link_available']);

        $this->seedTbankForPartner();

        $rows = $this->datatableRows();
        $this->assertTrue($rows->firstWhere('note', 'Можно копировать')['pay_link_available']);
        $this->assertFalse($rows->firstWhere('note', 'Мало')['pay_link_available']);
        $this->assertFalse($rows->firstWhere('note', 'Уже оплачен')['pay_link_available']);
        $this->assertSame($ready->id, (int) $rows->firstWhere('note', 'Можно копировать')['id']);
        $this->assertSame($small->id, (int) $rows->firstWhere('note', 'Мало')['id']);
        $this->assertSame($paid->id, (int) $rows->firstWhere('note', 'Уже оплачен')['id']);
    }

    public function test_js_renders_copy_icon_with_payment_link_tooltip(): void
    {
        foreach ([
            resource_path('js/setting-prices-custom-payments.js'),
            public_path('js/setting-prices-custom-payments.js'),
        ] as $path) {
            $js = (string) file_get_contents($path);
            $this->assertStringContainsString('js-custom-payment-copy-pay-link', $js);
            $this->assertStringContainsString('Ссылка на оплату', $js);
            $this->assertStringContainsString('Скопировано', $js);
            $this->assertStringContainsString('fa-check', $js);
            $this->assertStringContainsString("data-custom-payment-action=\"edit\"", $js);
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function datatableRows()
    {
        $response = $this->getJson(route('admin.settingPrices.customPayments.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
        ]))->assertOk();

        return collect($response->json('data'));
    }

    /**
     * @return array<string, mixed>
     */
    private function datatableRowByNote(string $note): array
    {
        $row = $this->datatableRows()->firstWhere('note', $note);
        $this->assertIsArray($row);

        return $row;
    }
}
