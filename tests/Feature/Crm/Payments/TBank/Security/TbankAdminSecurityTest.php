<?php

namespace Tests\Feature\Crm\Payments\TBank\Security;

use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

class TbankAdminSecurityTest extends CrmTestCase
{
    private function grantPayoutsManagePermissionForCurrentUser(): void
    {
        $permId = $this->permissionId('tbank.payouts.manage');

        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $this->user->role_id,
            'permission_id' => $permId,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function test_user_with_only_payouts_permission_cannot_access_debug_or_sm_register_routes(): void
    {
        $this->grantPayoutsManagePermissionForCurrentUser();

        // debug (под manage.payment.method.tbank)
        $this->get('/tinkoff/debug/tpay-status')->assertStatus(403);

        // sm-register (под manage.payment.method.tbank)
        $this->post(route('tinkoff.partners.smRegister', ['id' => $this->partner->id]), [])->assertStatus(403);
    }
}

