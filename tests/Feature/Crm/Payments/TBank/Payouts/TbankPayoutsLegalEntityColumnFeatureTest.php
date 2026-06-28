<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Payments\TBank\Payouts;

use App\Models\PartnerLegalEntity;
use App\Models\TinkoffPayout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Колонка «Организация» (юр. лицо) в разделе выплат T‑Bank + AJAX/non-AJAX columns-settings.
 */
final class TbankPayoutsLegalEntityColumnFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->grantPayoutsManage((int) $this->user->role_id);
        $this->actingAs($this->user);
    }

    public function test_guest_cannot_access_payouts_data_with_legal_entity_column(): void
    {
        Auth::logout();

        $response = $this->getJson('/admin/tinkoff/payouts/data?draw=1&start=0&length=10');

        $this->assertContains($response->getStatusCode(), [302, 401, 403, 419]);
    }

    public function test_user_without_permission_gets_403_on_payouts_data(): void
    {
        $actor = $this->createUserWithoutPermission('tbank.payouts.manage', $this->partner);
        $this->actingAs($actor);

        $this->getJson('/admin/tinkoff/payouts/data?draw=1&start=0&length=10')
            ->assertForbidden();
    }

    public function test_index_renders_organization_column_in_table_and_settings(): void
    {
        $this->get(route('admin.tinkoff.payouts.index'))
            ->assertOk()
            ->assertSee('>Организация</th>', false)
            ->assertSee('legal_entity_organization', false)
            ->assertSee('data-column-key="legal_entity_organization"', false);
    }

    public function test_datatable_data_includes_legal_entity_organization_from_snapshot(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'title' => 'Fallback Title',
            'organization_name' => 'ООО Колонка Выплат',
        ]);

        TinkoffPayout::query()->create([
            'payment_id' => null,
            'partner_id' => $this->partner->id,
            'legal_entity_id' => $entity->id,
            'deal_id' => 'le-col-' . uniqid(),
            'amount' => 5000,
            'is_final' => true,
            'status' => 'COMPLETED',
            'completed_at' => now(),
        ]);

        $resp = $this->getJson('/admin/tinkoff/payouts/data?draw=1&start=0&length=50');
        $resp->assertOk()
            ->assertJsonStructure([
                'draw',
                'recordsTotal',
                'recordsFiltered',
                'data' => [
                    '*' => [
                        'id',
                        'status',
                        'source',
                        'partner',
                        'legal_entity_organization',
                        'payer',
                        'initiator',
                        'payment_id',
                        'provider_inv_id',
                        'deal_id',
                        'gross',
                        'bank_accept_fee',
                        'bank_payout_fee',
                        'platform_fee',
                        'net',
                        'when_to_run',
                        'created_at',
                        'completed_at',
                        'tinkoff_payout_payment_id',
                    ],
                ],
            ]);

        $row = collect($resp->json('data'))->firstWhere('legal_entity_organization', 'ООО Колонка Выплат');
        $this->assertNotNull($row);
        $this->assertSame('ООО Колонка Выплат', $row['legal_entity_organization']);
        $this->assertNotEmpty($resp->json('recordsTotal'));
    }

    public function test_datatable_legal_entity_organization_falls_back_to_title(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'title' => 'ИП Иванов',
            'organization_name' => null,
        ]);

        TinkoffPayout::query()->create([
            'payment_id' => null,
            'partner_id' => $this->partner->id,
            'legal_entity_id' => $entity->id,
            'deal_id' => 'le-title-' . uniqid(),
            'amount' => 100,
            'is_final' => false,
            'status' => 'NEW',
        ]);

        $resp = $this->getJson('/admin/tinkoff/payouts/data?draw=1&start=0&length=50');
        $resp->assertOk();

        $row = collect($resp->json('data'))->firstWhere('legal_entity_organization', 'ИП Иванов');
        $this->assertNotNull($row);
    }

    public function test_datatable_legal_entity_organization_is_dash_without_snapshot(): void
    {
        TinkoffPayout::query()->create([
            'payment_id' => null,
            'partner_id' => $this->partner->id,
            'legal_entity_id' => null,
            'deal_id' => 'no-le-' . uniqid(),
            'amount' => 100,
            'is_final' => false,
            'status' => 'NEW',
        ]);

        $resp = $this->getJson('/admin/tinkoff/payouts/data?draw=1&start=0&length=50');
        $resp->assertOk();

        $this->assertSame('—', $resp->json('data.0.legal_entity_organization'));
    }

    public function test_columns_settings_ajax_contract_accepts_legal_entity_organization_key(): void
    {
        $this->postJson('/admin/tinkoff/payouts/columns-settings', [
            'columns' => [
                'partner' => true,
                'legal_entity_organization' => true,
                'net' => false,
            ],
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $saved = $this->getJson('/admin/tinkoff/payouts/columns-settings')
            ->assertOk()
            ->json();

        $this->assertTrue($saved['legal_entity_organization'] ?? false);
        $this->assertFalse($saved['net'] ?? true);
    }

    public function test_columns_settings_non_ajax_post_persists_and_returns_json_success_not_empty(): void
    {
        $this->post('/admin/tinkoff/payouts/columns-settings', [
            'columns' => [
                'legal_entity_organization' => '1',
                'status' => '1',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $saved = $this->getJson('/admin/tinkoff/payouts/columns-settings')->json();
        $this->assertTrue($saved['legal_entity_organization'] ?? false);
    }

    public function test_columns_settings_validation_failure_returns_422_not_500(): void
    {
        $this->postJson('/admin/tinkoff/payouts/columns-settings', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['columns']);
    }

    private function grantPayoutsManage(int $roleId): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $roleId,
            'permission_id' => $this->permissionId('tbank.payouts.manage'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
