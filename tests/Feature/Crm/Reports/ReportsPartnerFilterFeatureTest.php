<?php

namespace Tests\Feature\Crm\Reports;

use App\Models\FiscalReceipt;
use App\Models\OutgoingEmailLog;
use App\Models\Partner;
use App\Models\PaymentIntent;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Фильтр «Партнер» в отчётах: emails / fiscal-receipts / payment-intents.
 *
 * Scope: superadmin — все партнёры + опциональный partner_id, UI-фильтр виден;
 * не-superadmin с правом — только свой партнёр, UI-фильтр скрыт, query partner_id игнорируется.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ReportsPartnerFilterFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    // -----------------------------------------------------------------------
    // Исходящие письма
    // -----------------------------------------------------------------------

    public function test_emails_superadmin_sees_partner_filter_and_column_ui(): void
    {
        $this->asSuperadmin();

        $html = $this->get(route('reports.emails.index'))
            ->assertOk()
            ->assertViewHas('emailsCanFilterPartner', true)
            ->assertSee('em-filter-partner', false)
            ->assertSee('data-column-key="partner"', false)
            ->assertSee('/admin/reports/emails/partners-search', false)
            ->getContent();

        $this->assertStringContainsString("key: 'partner'", $html);
        $this->assertStringContainsString('partner_title', $html);
    }

    public function test_emails_non_superadmin_with_permission_hides_partner_filter(): void
    {
        $actor = $this->grantEmailsViewToAdmin();
        $this->actingAs($actor);

        $this->get(route('reports.emails.index'))
            ->assertOk()
            ->assertViewHas('emailsCanFilterPartner', false)
            ->assertDontSee('em-filter-partner', false)
            ->assertDontSee('/admin/reports/emails/partners-search', false);
    }

    public function test_emails_guest_and_unauthorized_cannot_access_partners_search(): void
    {
        $url = route('reports.emails.partners.search', ['q' => 'x']);

        $guestStatus = $this->get($url)->getStatusCode();
        $this->assertContains(
            $guestStatus,
            [302, 401, 403],
            "Гость partners-search → {$guestStatus}"
        );

        $denied = $this->createUserWithoutPermission('reports.emails.view', $this->partner);
        $this->actingAs($denied);
        $this->get($url)->assertForbidden();
    }

    public function test_emails_partners_search_returns_results_contract_for_authorized(): void
    {
        $this->asSuperadmin();
        $this->partner->update(['title' => 'Emails Filter Partner Alpha']);

        $json = $this->get(route('reports.emails.partners.search', ['q' => 'Emails Filter']))
            ->assertOk()
            ->assertJsonStructure(['results'])
            ->json();

        $ids = collect($json['results'])->pluck('id')->all();
        $this->assertContains($this->partner->id, $ids);

        $row = collect($json['results'])->firstWhere('id', $this->partner->id);
        $this->assertIsArray($row);
        $this->assertSame('Emails Filter Partner Alpha', $row['text'] ?? null);
    }

    public function test_emails_partners_search_validation_rejects_too_long_q_with_422(): void
    {
        $this->asSuperadmin();

        $this->getJson(route('reports.emails.partners.search', [
            'q' => str_repeat('a', 256),
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }

    public function test_emails_superadmin_data_and_total_respect_partner_id_filter(): void
    {
        $this->asSuperadmin();

        $own = OutgoingEmailLog::create([
            'partner_id' => $this->partner->id,
            'status'     => OutgoingEmailLog::STATUS_SENT,
            'subject'    => 'PartnerFilterOwn',
        ]);
        $foreign = OutgoingEmailLog::create([
            'partner_id' => $this->foreignPartner->id,
            'status'     => OutgoingEmailLog::STATUS_FAILED,
            'subject'    => 'PartnerFilterForeign',
            'error_message' => 'x',
        ]);

        $all = $this->ajaxGetEmailsData([]);
        $allIds = collect($all['data'])->pluck('id')->all();
        $this->assertContains($own->id, $allIds);
        $this->assertContains($foreign->id, $allIds);

        $this->get(route('reports.emails.total'))
            ->assertOk()
            ->assertJsonPath('total_raw', 2);

        $filtered = $this->ajaxGetEmailsData(['partner_id' => $this->partner->id]);
        $ids = collect($filtered['data'])->pluck('id')->all();
        $this->assertSame([$own->id], array_values($ids));

        $row = collect($filtered['data'])->firstWhere('id', $own->id);
        $this->assertSame($this->partner->id, (int) ($row['partner_id'] ?? 0));
        $this->assertArrayHasKey('partner_title', $row);

        $this->get(route('reports.emails.total', ['partner_id' => $this->partner->id]))
            ->assertOk()
            ->assertJson([
                'total_raw'  => 1,
                'sent_raw'   => 1,
                'failed_raw' => 0,
            ]);
    }

    public function test_emails_non_superadmin_ignores_partner_id_query_and_stays_isolated(): void
    {
        $actor = $this->grantEmailsViewToAdmin();
        $this->actingAs($actor);

        $own = OutgoingEmailLog::create([
            'partner_id' => $this->partner->id,
            'status'     => OutgoingEmailLog::STATUS_SENT,
            'subject'    => 'AdminOwn',
        ]);
        $foreign = OutgoingEmailLog::create([
            'partner_id' => $this->foreignPartner->id,
            'status'     => OutgoingEmailLog::STATUS_SENT,
            'subject'    => 'AdminForeign',
        ]);

        // Попытка подставить чужой partner_id не должна открыть чужие записи.
        $json = $this->ajaxGetEmailsData(['partner_id' => $this->foreignPartner->id]);
        $ids = collect($json['data'])->pluck('id')->all();
        $this->assertContains($own->id, $ids);
        $this->assertNotContains($foreign->id, $ids);

        $this->get(route('reports.emails.total', ['partner_id' => $this->foreignPartner->id]))
            ->assertOk()
            ->assertJsonPath('total_raw', 1);

        $this->get(route('reports.emails.show', ['log' => $foreign->id]))->assertForbidden();
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.emails.show', ['log' => $foreign->id, 'modal' => 1]))
            ->assertForbidden();
    }

    public function test_emails_superadmin_can_show_foreign_partner_log(): void
    {
        $this->asSuperadmin();

        $foreign = OutgoingEmailLog::create([
            'partner_id' => $this->foreignPartner->id,
            'status'     => OutgoingEmailLog::STATUS_SENT,
            'subject'    => 'SA Foreign Show',
            'html_body'  => '<p>x</p>',
        ]);

        $this->get(route('reports.emails.show', ['log' => $foreign->id]))
            ->assertOk()
            ->assertViewIs('admin.report.outgoing_email_show');

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.emails.show', ['log' => $foreign->id, 'modal' => 1]))
            ->assertOk()
            ->assertSee('outgoing-email-show-content', false);
    }

    public function test_emails_mailable_classes_search_respects_partner_id_for_superadmin(): void
    {
        $this->asSuperadmin();

        OutgoingEmailLog::create([
            'partner_id'     => $this->partner->id,
            'status'         => OutgoingEmailLog::STATUS_SENT,
            'mailable_class' => 'App\\Mail\\PartnerFilterOwnMail',
            'subject'        => 's',
        ]);
        OutgoingEmailLog::create([
            'partner_id'          => $this->foreignPartner->id,
            'status'              => OutgoingEmailLog::STATUS_SENT,
            'notification_class'  => 'App\\Notifications\\PartnerFilterForeignNote',
            'subject'             => 's',
        ]);

        $all = $this->get(route('reports.emails.mailable.classes.search', ['q' => 'PartnerFilter']))
            ->assertOk()
            ->json('results');
        $allIds = collect($all)->pluck('id')->all();
        $this->assertContains('App\\Mail\\PartnerFilterOwnMail', $allIds);
        $this->assertContains('App\\Notifications\\PartnerFilterForeignNote', $allIds);

        $filtered = $this->get(route('reports.emails.mailable.classes.search', [
            'q'          => 'PartnerFilter',
            'partner_id' => $this->partner->id,
        ]))
            ->assertOk()
            ->json('results');
        $ids = collect($filtered)->pluck('id')->all();
        $this->assertContains('App\\Mail\\PartnerFilterOwnMail', $ids);
        $this->assertNotContains('App\\Notifications\\PartnerFilterForeignNote', $ids);
    }

    public function test_emails_index_with_partner_id_query_preserves_select2_option_for_superadmin(): void
    {
        $this->asSuperadmin();
        $p = Partner::factory()->create(['title' => 'Preselected Emails Partner']);

        $this->get(route('reports.emails.index', ['partner_id' => $p->id]))
            ->assertOk()
            ->assertViewHas('emailsFilterPartner', function ($label) use ($p) {
                return is_array($label)
                    && (int) ($label['id'] ?? 0) === $p->id
                    && ($label['text'] ?? '') === 'Preselected Emails Partner';
            })
            ->assertSee('Preselected Emails Partner', false);
    }

    // -----------------------------------------------------------------------
    // Чеки
    // -----------------------------------------------------------------------

    public function test_fiscal_superadmin_sees_partner_filter_non_superadmin_hides(): void
    {
        $this->asSuperadmin();
        $this->get(route('reports.fiscal-receipts.index'))
            ->assertOk()
            ->assertViewHas('frCanFilterPartner', true)
            ->assertSee('fr-filter-partner', false);

        $actor = $this->grantFiscalViewToAdmin();
        $this->actingAs($actor);
        $this->get(route('reports.fiscal-receipts.index'))
            ->assertOk()
            ->assertViewHas('frCanFilterPartner', false)
            ->assertDontSee('fr-filter-partner', false);
    }

    public function test_fiscal_superadmin_partner_id_filter_on_total_and_data(): void
    {
        $this->asSuperadmin();

        FiscalReceipt::query()->create([
            'partner_id' => $this->partner->id,
            'amount'     => 100,
            'type'       => 'income',
            'status'     => 'processed',
        ]);
        FiscalReceipt::query()->create([
            'partner_id' => $this->foreignPartner->id,
            'amount'     => 500,
            'type'       => 'income',
            'status'     => 'processed',
        ]);

        $this->get(route('reports.fiscal-receipts.total'))
            ->assertOk()
            ->assertJsonPath('total_raw', 600);

        $this->get(route('reports.fiscal-receipts.total', ['partner_id' => $this->partner->id]))
            ->assertOk()
            ->assertJsonPath('total_raw', 100);

        $json = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('reports.fiscal-receipts.data', [
                'draw'       => 1,
                'start'      => 0,
                'length'     => 50,
                'partner_id' => $this->partner->id,
            ]))
            ->assertOk()
            ->json();

        foreach ($json['data'] as $row) {
            $this->assertSame($this->partner->id, (int) ($row['partner_id'] ?? 0));
        }
    }

    public function test_fiscal_non_superadmin_ignores_foreign_partner_id_query(): void
    {
        $actor = $this->grantFiscalViewToAdmin();
        $this->actingAs($actor);

        FiscalReceipt::query()->create([
            'partner_id' => $this->partner->id,
            'amount'     => 50,
            'type'       => 'income',
            'status'     => 'processed',
        ]);
        FiscalReceipt::query()->create([
            'partner_id' => $this->foreignPartner->id,
            'amount'     => 999,
            'type'       => 'income',
            'status'     => 'processed',
        ]);

        $this->get(route('reports.fiscal-receipts.total', [
            'partner_id' => $this->foreignPartner->id,
        ]))
            ->assertOk()
            ->assertJsonPath('total_raw', 50);
    }

    // -----------------------------------------------------------------------
    // Платежные запросы
    // -----------------------------------------------------------------------

    public function test_payment_intents_superadmin_sees_partner_filter_non_superadmin_hides(): void
    {
        $this->asSuperadmin();
        $this->get(route('reports.payment-intents.index'))
            ->assertOk()
            ->assertViewHas('piCanFilterPartner', true)
            ->assertSee('pi-filter-partner', false);

        $actor = $this->grantPaymentIntentsViewToAdmin();
        $this->actingAs($actor);
        $this->get(route('reports.payment-intents.index'))
            ->assertOk()
            ->assertViewHas('piCanFilterPartner', false)
            ->assertDontSee('pi-filter-partner', false);
    }

    public function test_payment_intents_superadmin_partner_id_filter_on_total_and_data(): void
    {
        $this->asSuperadmin();

        $own = PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'out_sum'    => 111,
        ]);
        $foreign = PaymentIntent::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'out_sum'    => 222,
        ]);

        $this->get(route('reports.payment-intents.total'))
            ->assertOk()
            ->assertJsonPath('total_raw', 333);

        $this->get(route('reports.payment-intents.total', ['partner_id' => $this->partner->id]))
            ->assertOk()
            ->assertJsonPath('total_raw', 111);

        $ids = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->getJson(route('reports.payment-intents.data', [
                    'draw'       => 1,
                    'start'      => 0,
                    'length'     => 50,
                    'partner_id' => $this->partner->id,
                ]))
                ->assertOk()
                ->json('data')
        )->pluck('id')->all();

        $this->assertContains($own->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_payment_intents_non_superadmin_ignores_foreign_partner_id_query(): void
    {
        $actor = $this->grantPaymentIntentsViewToAdmin();
        $this->actingAs($actor);

        PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'out_sum'    => 40,
        ]);
        PaymentIntent::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'out_sum'    => 800,
        ]);

        $this->get(route('reports.payment-intents.total', [
            'partner_id' => $this->foreignPartner->id,
        ]))
            ->assertOk()
            ->assertJsonPath('total_raw', 40);
    }

    // -----------------------------------------------------------------------
    // helpers
    // -----------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function ajaxGetEmailsData(array $extra): array
    {
        return $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('reports.emails.data', array_merge([
                'draw'   => 1,
                'start'  => 0,
                'length' => 50,
            ], $extra)))
            ->assertOk()
            ->json();
    }

    private function grantEmailsViewToAdmin(): \App\Models\User
    {
        $actor = $this->createUserWithoutPermission('reports.emails.view', $this->partner);
        $this->grantPermissionToRole((int) $actor->role_id, 'reports.emails.view');

        return $actor;
    }

    private function grantFiscalViewToAdmin(): \App\Models\User
    {
        $actor = $this->createUserWithoutPermission('reports.fiscal.receipts.view', $this->partner);
        $this->grantPermissionToRole((int) $actor->role_id, 'reports.fiscal.receipts.view');

        return $actor;
    }

    private function grantPaymentIntentsViewToAdmin(): \App\Models\User
    {
        $actor = $this->createUserWithoutPermission('reports.payment.intents.view', $this->partner);
        $this->grantPermissionToRole((int) $actor->role_id, 'reports.payment.intents.view');

        return $actor;
    }

    private function grantPermissionToRole(int $roleId, string $permissionName): void
    {
        DB::table('permission_role')->updateOrInsert(
            [
                'partner_id'    => $this->partner->id,
                'role_id'       => $roleId,
                'permission_id' => $this->permissionId($permissionName),
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
