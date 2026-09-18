<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LegalEntities;

use App\Models\PartnerLegalEntity;
use App\Models\User;
use App\Services\Tinkoff\SmRegisterClient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Кнопка «Снять блокировку выплат»: доступ, AJAX, non-AJAX safety-net, 422 под полями, UX видимости.
 */
final class LegalEntitiesSmEnableReimbursementFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->asAdmin();
        $this->grantPermissions($this->user, ['legal_entities.view', 'legal_entities.manage', 'legal_entities.sm_register']);
    }

    /** @param list<string> $permissions */
    private function grantPermissions(User $actor, array $permissions): void
    {
        foreach ($permissions as $permission) {
            DB::table('permission_role')->insertOrIgnore([
                'partner_id' => $this->partner->id,
                'role_id' => $actor->role_id,
                'permission_id' => $this->permissionId($permission),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function blockedEntityAttrs(string $shopCode): array
    {
        return [
            'tinkoff_disable_reimbursement' => true,
            'tinkoff_shop_checked_at' => now(),
            'bank_name' => 'ООО Банк Точка',
            'bank_bik' => '044525104',
            'bank_account' => '40802810420000841544',
            'bank_corr_account' => '30101810400000000225',
            'sm_details_template' => 'Выплата по договору',
            'tinkoff_shop_code' => $shopCode,
            'sm_register_status' => 'REGISTERED',
            'registered_at' => now(),
        ];
    }

    private function bindSmMock(): Mockery\MockInterface
    {
        $sm = Mockery::mock(SmRegisterClient::class);
        $this->app->instance(SmRegisterClient::class, $sm);

        return $sm;
    }

    public function test_guest_cannot_enable_reimbursement(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->registered('SC-GUEST')->create(
            $this->blockedEntityAttrs('SC-GUEST')
        );

        Auth::logout();

        $html = $this->from(route('admin.legal-entities.show', $entity))
            ->post(route('admin.legal-entities.sm-enable-reimbursement', $entity));
        $this->assertContains($html->getStatusCode(), [302, 401, 403, 419]);
        $this->assertNotSame(200, $html->getStatusCode());
        $this->assertLessThan(500, $html->getStatusCode());

        $json = $this->postJson(route('admin.legal-entities.sm-enable-reimbursement', $entity));
        $this->assertContains($json->getStatusCode(), [302, 401, 403, 419]);
        $this->assertLessThan(500, $json->getStatusCode());
    }

    public function test_manager_without_manage_permission_gets_403(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->registered('SC-NO-MANAGE')->create(
            $this->blockedEntityAttrs('SC-NO-MANAGE')
        );

        $actor = $this->createUserWithoutPermission('legal_entities.manage', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantPermissions($actor, ['legal_entities.view', 'legal_entities.sm_register']);

        $this->postJson(route('admin.legal-entities.sm-enable-reimbursement', $entity))
            ->assertForbidden();

        $this->from(route('admin.legal-entities.show', $entity))
            ->post(route('admin.legal-entities.sm-enable-reimbursement', $entity))
            ->assertForbidden();
    }

    public function test_viewer_with_sm_register_does_not_see_unblock_button(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->registered('SC-VIEW-BTN')->create(
            $this->blockedEntityAttrs('SC-VIEW-BTN')
        );

        $actor = $this->createUserWithoutPermission('legal_entities.manage', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantPermissions($actor, ['legal_entities.view', 'legal_entities.sm_register']);

        $this->get(route('admin.legal-entities.show', $entity))
            ->assertOk()
            ->assertSee('id="legal-entity-reimbursement-blocked"', false)
            ->assertDontSee('id="legal-entity-enable-reimbursement"', false)
            ->assertDontSee('Снять блокировку выплат', false);
    }

    public function test_ajax_unblocks_payouts_and_returns_json_contract(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create(
            $this->blockedEntityAttrs('SC-AJAX-OK')
        );

        $sm = $this->bindSmMock();
        $sm->shouldReceive('patch')->once()->andReturn(['ok' => true]);
        $sm->shouldReceive('getStatus')->once()->andReturn([
            'bankAccount' => [
                'disableReimbursement' => false,
                'account' => '40802810420000841544',
                'bankName' => 'ООО Банк Точка',
                'bik' => '044525104',
                'details' => 'Выплата по договору',
            ],
        ]);

        $this->postJson(route('admin.legal-entities.sm-enable-reimbursement', $entity))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('disable_reimbursement', false)
            ->assertJsonPath('message', 'Блокировка выплат снята в банке');

        $this->assertFalse($entity->fresh()->tinkoffPayoutsBlocked());
    }

    public function test_ajax_tells_user_when_bank_still_keeps_the_block(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create(
            $this->blockedEntityAttrs('SC-STILL')
        );

        $sm = $this->bindSmMock();
        $sm->shouldReceive('patch')->once()->andReturn(['ok' => true]);
        $sm->shouldReceive('getStatus')->once()->andReturn([
            'bankAccount' => [
                'disableReimbursement' => true,
                'account' => '40802810420000841544',
                'bankName' => 'ООО Банк Точка',
                'bik' => '044525104',
                'details' => 'Выплата по договору',
            ],
        ]);

        $this->postJson(route('admin.legal-entities.sm-enable-reimbursement', $entity))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('disable_reimbursement', true)
            ->assertJsonPath('message', 'Запрос на снятие блокировки отправлен, но банк всё ещё держит флаг disableReimbursement');

        $this->assertTrue($entity->fresh()->tinkoffPayoutsBlocked());
    }

    public function test_non_ajax_redirects_and_clears_block_flag(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create(
            $this->blockedEntityAttrs('SC-NATIVE')
        );

        $sm = $this->bindSmMock();
        $sm->shouldReceive('patch')->once()->andReturn(['ok' => true]);
        $sm->shouldReceive('getStatus')->once()->andReturn([
            'bankAccount' => [
                'disableReimbursement' => false,
                'account' => '40802810420000841544',
                'bankName' => 'ООО Банк Точка',
                'bik' => '044525104',
                'details' => 'Выплата по договору',
            ],
        ]);

        $this->from(route('admin.legal-entities.show', $entity))
            ->post(route('admin.legal-entities.sm-enable-reimbursement', $entity))
            ->assertRedirect(route('admin.legal-entities.show', $entity))
            ->assertSessionHas('ok', 'Блокировка выплат снята в банке');

        $this->assertFalse($entity->fresh()->tinkoffPayoutsBlocked());
    }

    public function test_non_ajax_missing_bank_fields_redirects_with_field_errors_not_empty_200(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->registered('SC-NATIVE-EMPTY')->create([
            'tinkoff_disable_reimbursement' => true,
            'bank_name' => null,
            'bank_bik' => null,
            'bank_account' => null,
        ]);

        $sm = $this->bindSmMock();
        $sm->shouldNotReceive('patch');

        $this->from(route('admin.legal-entities.show', $entity))
            ->post(route('admin.legal-entities.sm-enable-reimbursement', $entity))
            ->assertStatus(302)
            ->assertSessionHasErrors(['bank_account', 'bank_name', 'bank_bik']);

        $this->assertTrue($entity->fresh()->tinkoffPayoutsBlocked());
    }

    public function test_ajax_missing_bank_fields_returns_422_under_fields(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->registered('SC-AJAX-EMPTY')->create([
            'tinkoff_disable_reimbursement' => true,
            'bank_name' => null,
            'bank_bik' => null,
            'bank_account' => null,
        ]);

        $sm = $this->bindSmMock();
        $sm->shouldNotReceive('patch');

        $this->postJson(route('admin.legal-entities.sm-enable-reimbursement', $entity))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['bank_account', 'bank_name', 'bank_bik']);
    }

    public function test_foreign_partner_entity_is_not_unblocked(): void
    {
        $foreign = PartnerLegalEntity::factory()->for($this->foreignPartner)->create(
            $this->blockedEntityAttrs('SC-FOREIGN')
        );

        $sm = $this->bindSmMock();
        $sm->shouldNotReceive('patch');

        $this->postJson(route('admin.legal-entities.sm-enable-reimbursement', $foreign))
            ->assertNotFound();

        $this->assertTrue($foreign->fresh()->tinkoffPayoutsBlocked());
    }

    public function test_entity_without_shop_code_returns_422(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'tinkoff_shop_code' => null,
            'tinkoff_disable_reimbursement' => true,
            'bank_name' => 'ООО Банк Точка',
            'bank_bik' => '044525104',
            'bank_account' => '40802810420000841544',
        ]);

        $sm = $this->bindSmMock();
        $sm->shouldNotReceive('patch');

        $this->postJson(route('admin.legal-entities.sm-enable-reimbursement', $entity))
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_show_does_not_offer_unblock_when_payouts_are_not_blocked(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->registered('SC-OK')->create([
            'tinkoff_disable_reimbursement' => false,
            'tinkoff_shop_checked_at' => now(),
        ]);

        $this->get(route('admin.legal-entities.show', $entity))
            ->assertOk()
            ->assertSee('id="legal-entity-reimbursement-ok"', false)
            ->assertDontSee('id="legal-entity-enable-reimbursement"', false);
    }

    public function test_show_does_not_offer_unblock_before_status_was_requested(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->registered('SC-UNKNOWN')->create([
            'tinkoff_disable_reimbursement' => null,
        ]);

        $this->get(route('admin.legal-entities.show', $entity))
            ->assertOk()
            ->assertSee('id="legal-entity-reimbursement-unknown"', false)
            ->assertDontSee('id="legal-entity-enable-reimbursement"', false)
            ->assertSee('ещё не запрашивался', false);
    }

    public function test_unblock_form_asks_for_confirm_before_submit(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create(
            $this->blockedEntityAttrs('SC-CONFIRM')
        );

        $html = $this->get(route('admin.legal-entities.show', $entity))->assertOk()->getContent();

        $this->assertStringContainsString('id="legal-entity-enable-reimbursement-form"', $html);
        $this->assertStringContainsString('js-sm-action-form', $html);
        $this->assertStringContainsString('data-confirm="Снять блокировку выплат в Т‑Банке?', $html);
        $this->assertStringContainsString('Удержанные холды будут помечены к выплате.', $html);
        $this->assertStringContainsString('js-sm-action-form', $html);
        $this->assertStringNotContainsString('id="legalEntitySmForm"', explode('id="legal-entity-enable-reimbursement-form"', $html)[0] ?? '');
    }

    public function test_non_ajax_tells_user_when_bank_still_keeps_the_block(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create(
            $this->blockedEntityAttrs('SC-STILL-HTML')
        );

        $sm = $this->bindSmMock();
        $sm->shouldReceive('patch')->once()->andReturn(['ok' => true]);
        $sm->shouldReceive('getStatus')->once()->andReturn([
            'bankAccount' => [
                'disableReimbursement' => true,
                'account' => '40802810420000841544',
                'bankName' => 'ООО Банк Точка',
                'bik' => '044525104',
                'details' => 'Выплата по договору',
            ],
        ]);

        $this->from(route('admin.legal-entities.show', $entity))
            ->post(route('admin.legal-entities.sm-enable-reimbursement', $entity))
            ->assertRedirect(route('admin.legal-entities.show', $entity))
            ->assertSessionHas('ok', 'Запрос на снятие блокировки отправлен, но банк всё ещё держит флаг disableReimbursement');

        $this->assertTrue($entity->fresh()->tinkoffPayoutsBlocked());
    }

    public function test_entity_without_shop_code_non_ajax_redirects_with_error_not_empty_200(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'tinkoff_shop_code' => null,
            'tinkoff_disable_reimbursement' => true,
            'bank_name' => 'ООО Банк Точка',
            'bank_bik' => '044525104',
            'bank_account' => '40802810420000841544',
        ]);

        $sm = $this->bindSmMock();
        $sm->shouldNotReceive('patch');

        $this->from(route('admin.legal-entities.show', $entity))
            ->post(route('admin.legal-entities.sm-enable-reimbursement', $entity))
            ->assertStatus(302)
            ->assertSessionHasErrors(['sm']);
    }
}
