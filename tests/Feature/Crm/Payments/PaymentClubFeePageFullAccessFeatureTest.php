<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Payments;

use App\Models\Team;
use App\Models\User;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Оплата клубного взноса: право payment.clubfee (группа setPrices).
 *
 * Endpoint'ы раздела: GET|POST /payment/club-fee — рендер страницы (не JSON CRUD).
 * Store/update AJAX и non-AJAX safety-net не применимы (нет создания сущности в БД).
 * Inline JS страницы оплаты — вне scope этого права (BladeInlineJsSyntaxTest при изменении blade).
 *
 * @see PayableTeamPaymentInitFeatureTest
 * @see PaymentCheckoutServiceProviderFeatureTest
 * @see SetPricesPermissionCatalogFeatureTest
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class PaymentClubFeePageFullAccessFeatureTest extends CrmTestCase
{
    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'ClubFee-FullAccess',
        ]);

        app(TeamUserSyncService::class)->attachTeamForStudent($this->user, (int) $this->team->id);
    }

    private function grantClubFee(User $actor): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('payment.clubfee'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>}>
     */
    private function clubFeeEndpoints(): array
    {
        return [
            ['method' => 'GET', 'url' => route('clubFee')],
            ['method' => 'POST', 'url' => route('clubFee'), 'data' => ['outSum' => '500']],
        ];
    }

    public function test_guest_is_denied_on_all_club_fee_endpoints(): void
    {
        Auth::logout();

        foreach ($this->clubFeeEndpoints() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? []
            );

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
            $this->assertNotSame(500, $response->getStatusCode());
            $this->assertNotSame(200, $response->getStatusCode());
        }
    }

    public function test_user_without_payment_clubfee_gets_403_on_all_endpoints(): void
    {
        $denied = $this->createUserWithoutPermission('payment.clubfee', $this->partner);
        $this->actingAs($denied);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        foreach ($this->clubFeeEndpoints() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? []
            );

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без payment.clubfee: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
            $this->assertNotSame(500, $response->getStatusCode());
        }
    }

    public function test_authorized_user_gets_200_on_get_and_post_club_fee(): void
    {
        $this->grantClubFee($this->user);
        $this->actingAs($this->user);

        foreach ($this->clubFeeEndpoints() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? []
            );

            $this->assertSame(
                200,
                $response->getStatusCode(),
                "С payment.clubfee: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
            $this->assertNotSame(500, $response->getStatusCode());
            $this->assertNotSame('', trim((string) $response->getContent()), 'Не пустой 200');
            $response->assertViewIs('payment.clubFee');
        }
    }

    public function test_post_club_fee_without_ajax_headers_still_renders_page_not_empty_200(): void
    {
        $this->grantClubFee($this->user);
        $this->actingAs($this->user);

        $response = $this->post(route('clubFee'), ['outSum' => '750']);

        $response->assertOk()
            ->assertViewIs('payment.clubFee')
            ->assertViewHas('clubFeeBlocked', false)
            ->assertViewHas('clubFeeDefaultTeamId', (int) $this->team->id);

        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertNotSame(500, $response->getStatusCode());
    }
}
