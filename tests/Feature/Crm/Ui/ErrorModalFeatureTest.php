<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Ui;

use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Глобальная модалка ошибок (showErrorModal) в admin-layout.
 */
final class ErrorModalFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->asAdmin();
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId('legal_entities.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_admin_layout_includes_show_error_modal_helper(): void
    {
        $this->get(route('admin.legal-entities.index'))
            ->assertOk()
            ->assertSee('function showErrorModal', false)
            ->assertSee('id="errorModal"', false)
            ->assertSee('showModalQueued(\'errorModal\'', false);
    }
}
