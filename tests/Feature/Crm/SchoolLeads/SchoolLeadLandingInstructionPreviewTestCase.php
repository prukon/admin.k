<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\PartnerWidget;
use App\Models\User;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Общий сетап CRM-модалки «Инструкция для родителей».
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
abstract class SchoolLeadLandingInstructionPreviewTestCase extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    protected function actingAsLandingViewer(): User
    {
        $actor = $this->createUserWithoutPermission('schoolLeadLanding.view', $this->partner);
        $this->grantLandingView($actor);
        $this->actingAs($actor);

        return $actor;
    }

    protected function grantLandingView(User $actor): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => (int) $actor->partner_id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('schoolLeadLanding.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function widgetWithSlug(string $slug = 'crm-instr-school'): PartnerWidget
    {
        $widget = app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
        $widget->update(['landing_slug' => $slug]);

        return $widget->fresh();
    }

    protected function previewUrl(): string
    {
        return route('admin.school-leads.instruction-preview');
    }

    /**
     * @return array<string, string>
     */
    protected function ajaxHeaders(): array
    {
        return [
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            'HTTP_ACCEPT' => 'application/json',
        ];
    }
}
