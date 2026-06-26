<?php

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\SchoolLead;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Сайдбар: счётчик лидов со статусом «Новый» у пункта «Лиды».
 */
final class SchoolLeadsSidebarCounterFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    public function test_sidebar_shows_badge_with_new_leads_count(): void
    {
        $this->asAdmin();

        SchoolLead::factory()
            ->forPartner((int) $this->partner->id)
            ->count(2)
            ->create();

        $sidebar = $this->sidebarChunk(
            $this->get(route('admin.school-leads'))->assertOk()->getContent()
        );

        $this->assertMatchesRegularExpression(
            '/<p>Лиды<span class="badge badge-info right">2<\/span><\/p>/',
            $sidebar
        );
    }

    public function test_sidebar_hides_badge_when_no_new_leads(): void
    {
        $this->asAdmin();

        SchoolLead::factory()
            ->forPartner((int) $this->partner->id)
            ->withStatus($this->schoolLeadProcessingStatusId())
            ->create();

        $sidebar = $this->sidebarChunk(
            $this->get(route('admin.school-leads'))->assertOk()->getContent()
        );

        $this->assertStringContainsString('<p>Лиды</p>', $sidebar);
        $this->assertStringNotContainsString('<p>Лиды<span class="badge badge-info right">', $sidebar);
    }

    public function test_sidebar_counts_only_current_partner_new_leads(): void
    {
        $this->asAdmin();

        SchoolLead::factory()
            ->forPartner((int) $this->partner->id)
            ->create();

        SchoolLead::factory()
            ->forPartner((int) $this->foreignPartner->id)
            ->count(3)
            ->create();

        $sidebar = $this->sidebarChunk(
            $this->get(route('admin.school-leads'))->assertOk()->getContent()
        );

        $this->assertMatchesRegularExpression(
            '/<p>Лиды<span class="badge badge-info right">1<\/span><\/p>/',
            $sidebar
        );
    }

    private function sidebarChunk(string $html): string
    {
        $sidebarStart = strpos($html, 'nav-sidebar');
        $this->assertNotFalse($sidebarStart, 'Sidebar not found in response');

        return substr($html, (int) $sidebarStart, 5000);
    }
}
