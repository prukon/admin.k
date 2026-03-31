<?php

namespace Tests\Feature\Crm\Payments\TBank\Commissions;

use App\Models\User;
use Tests\Feature\Crm\CrmTestCase;

class TbankCommissionsControllerAccessTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_guest_cannot_access_routes(): void
    {
        auth()->logout();

        $routes = [
            ['GET', route('admin.setting.tbankCommissions')],
            ['GET', route('admin.setting.tbankCommissions.create')],
            ['POST', route('admin.setting.tbankCommissions.store')],
        ];

        foreach ($routes as [$method, $url]) {
            $resp = $this->call($method, $url);
            $this->assertTrue(
                in_array($resp->getStatusCode(), [302, 401, 403], true),
                "Ожидался 302/401/403 для гостя на {$method} {$url}, фактически {$resp->getStatusCode()}"
            );
        }
    }

    public function test_user_without_permission_gets_403(): void
    {
        // Не superadmin без permission settings.commission должен получить 403
        $this->asAdmin();
        $resp = $this->withSession(['current_partner' => $this->partner->id])
            ->get(route('admin.setting.tbankCommissions'));
        $resp->assertStatus(403);
    }

    public function test_setpartner_blocks_user_without_partner_id(): void
    {
        $u = User::factory()->create(['partner_id' => null]);
        $this->actingAs($u);

        // важно: в сессии не должен быть current_partner
        $this->withSession([]);

        $resp = $this->get(route('admin.setting.tbankCommissions'));

        $resp->assertStatus(302);
        $this->assertGuest();
        $resp->assertSessionHasErrors([
            'email' => 'Ваша организация недоступна.',
        ]);
    }

    public function test_setpartner_blocks_when_session_current_partner_points_to_missing_partner(): void
    {
        // Переключение current_partner через session разрешено только superadmin
        $this->asSuperadmin();

        // В сессии ставим несуществующего партнёра
        $resp = $this->withSession(['current_partner' => 999999])
            ->get(route('admin.setting.tbankCommissions'));

        $resp->assertStatus(302);
        $resp->assertSessionHasErrors(['partner']);
    }
}

