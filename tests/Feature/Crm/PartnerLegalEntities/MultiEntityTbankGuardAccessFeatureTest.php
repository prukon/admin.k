<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\PartnerLegalEntities;

use App\Models\PartnerLegalEntity;
use App\Models\Payable;
use App\Models\PaymentSystem;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPrice;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Multi-entity T‑Bank guard: доступы, контракты endpoint'ов, non-AJAX safety-net (группы),
 * клубный взнос (динамический T‑Bank), Init с ошибкой «не настроено юр. лицо».
 */
final class MultiEntityTbankGuardAccessFeatureTest extends CrmTestCase
{
    private PartnerLegalEntity $entityA;

    private PartnerLegalEntity $entityB;

    private Team $teamBound;

    private Team $teamUnbound;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->seedMultiEntityWithTeams();
    }

    private function seedMultiEntityWithTeams(): void
    {
        $this->seedGlobalTbank([
            'terminal_key' => 'TERM-MULTI-GUARD',
            'token_password' => 'PWD-MULTI',
            'e2c_terminal_key' => 'E2C-MULTI',
            'e2c_token_password' => 'E2C-PWD',
        ]);

        $this->entityA = PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-MULTI-A')
            ->create(['title' => 'ЮЛ Alpha', 'is_default' => true]);

        $this->entityB = PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-MULTI-B')
            ->create(['title' => 'ЮЛ Beta', 'is_default' => false]);

        $this->teamBound = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Guard-Bound',
            'legal_entity_id' => $this->entityA->id,
        ]);

        $this->teamUnbound = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Guard-Unbound',
            'legal_entity_id' => null,
        ]);

        $sync = app(TeamUserSyncService::class);
        $sync->attachTeamForStudent($this->user, (int) $this->teamBound->id);
        $sync->attachTeamForStudent($this->user, (int) $this->teamUnbound->id);
    }

    /** @param list<string> $permissions */
    private function grantPermissions(array $permissions): void
    {
        foreach ($permissions as $permission) {
            DB::table('permission_role')->insertOrIgnore([
                'partner_id' => $this->partner->id,
                'role_id' => $this->user->role_id,
                'permission_id' => $this->permissionId($permission),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function grantTeamAdminPermissions(): void
    {
        $this->asAdmin();
        $this->user->refresh();
        $this->user->unsetRelation('role');
        $this->actingAs($this->user);
        $this->grantPermissions(['groups.view', 'legal_entities.view']);
    }

    private function grantPaymentPermissions(): void
    {
        $this->user->refresh();
        $this->user->unsetRelation('role');
        $this->actingAs($this->user);
        $this->grantPermissions([
            'paying.classes',
            'payment.method.tbankCard',
            'payment.method.tbankSBP',
            'payment.method.robokassa',
            'payment.clubfee',
        ]);
    }

    private function seedMonthlyPriceForTeam(Team $team, string $month = '2027-04-01', int $amount = 3500): void
    {
        UserPrice::factory()->forUserAndMonth(
            (int) $this->user->id,
            $month,
            $amount,
            false,
            (int) $team->id,
        )->create();
    }

    /* ============================================================
     * A. Admin /teams — guest, forbidden, authorized
     * ============================================================ */

    public function test_guest_get_teams_index_redirects_to_login_not_500(): void
    {
        Auth::logout();

        $response = $this->get(route('admin.team.index'));

        $this->assertContains($response->getStatusCode(), [302, 401]);
        $this->assertNotSame(500, $response->getStatusCode());
    }

    public function test_user_without_groups_view_gets_403_on_teams_index(): void
    {
        $denied = $this->createUserWithoutPermission('groups.view', $this->partner);
        $this->actingAs($denied);

        $this->get(route('admin.team.index'))
            ->assertForbidden()
            ->assertStatus(403);
    }

    public function test_authorized_admin_gets_200_on_teams_index_with_multi_entity_mode(): void
    {
        $this->grantTeamAdminPermissions();

        $this->get(route('admin.team.index'))
            ->assertOk()
            ->assertViewIs('admin.team')
            ->assertViewHas('multiLegalEntityMode', true);
    }

    public function test_authorized_admin_gets_json_200_on_teams_data(): void
    {
        $this->grantTeamAdminPermissions();

        $response = $this->getJson(route('admin.team.data', ['draw' => 1, 'start' => 0, 'length' => 50]));

        $response->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->assertNotSame(500, $response->getStatusCode());
    }

    public function test_guest_get_teams_data_is_unauthorized_not_500(): void
    {
        Auth::logout();

        $response = $this->getJson(route('admin.team.data', ['draw' => 1]));

        $this->assertContains($response->getStatusCode(), [401, 302, 403, 419]);
        $this->assertNotSame(500, $response->getStatusCode());
    }

    public function test_authorized_admin_gets_json_200_on_team_edit(): void
    {
        $this->grantTeamAdminPermissions();

        $this->get(route('admin.team.edit', $this->teamBound->id))
            ->assertOk()
            ->assertJsonFragment([
                'id' => $this->teamBound->id,
                'legal_entity_id' => $this->entityA->id,
            ]);
    }

    /* ============================================================
     * B. Teams store/update — legal_entity_id required in multi-entity
     * ============================================================ */

    public function test_store_ajax_json_creates_team_with_legal_entity_in_multi_entity_mode(): void
    {
        $this->grantTeamAdminPermissions();

        $response = $this->postJson(route('admin.team.store'), [
            'title' => 'Ajax группа с юрлицом',
            'default_duration_minutes' => 60,
            'order_by' => 5,
            'is_enabled' => 1,
            'legal_entity_id' => $this->entityB->id,
        ], ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk()
            ->assertJsonStructure(['message', 'team' => ['id', 'title']])
            ->assertJsonFragment(['message' => 'Группа создана успешно']);

        $this->assertDatabaseHas('teams', [
            'partner_id' => $this->partner->id,
            'title' => 'Ajax группа с юрлицом',
            'legal_entity_id' => $this->entityB->id,
        ]);
    }

    public function test_store_ajax_json_returns_422_without_legal_entity_in_multi_entity_mode(): void
    {
        $this->grantTeamAdminPermissions();

        $this->postJson(route('admin.team.store'), [
            'title' => 'Ajax без юрлица',
            'default_duration_minutes' => 60,
            'order_by' => 6,
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['legal_entity_id']);

        $this->assertDatabaseMissing('teams', [
            'partner_id' => $this->partner->id,
            'title' => 'Ajax без юрлица',
        ]);
    }

    public function test_store_non_ajax_redirects_and_creates_team_with_legal_entity_not_empty_200(): void
    {
        $this->grantTeamAdminPermissions();

        $this->post(route('admin.team.store'), [
            'title' => 'Non-Ajax группа с юрлицом',
            'default_duration_minutes' => 45,
            'order_by' => 7,
            'is_enabled' => 1,
            'legal_entity_id' => $this->entityA->id,
        ])
            ->assertRedirect(route('admin.team.index'))
            ->assertStatus(302);

        $this->assertDatabaseHas('teams', [
            'partner_id' => $this->partner->id,
            'title' => 'Non-Ajax группа с юрлицом',
            'legal_entity_id' => $this->entityA->id,
        ]);
    }

    public function test_store_non_ajax_validation_failure_redirects_back_with_errors_not_empty_200(): void
    {
        $this->grantTeamAdminPermissions();

        $this->from(route('admin.team.index'))
            ->post(route('admin.team.store'), [
                'title' => 'Non-Ajax без юрлица',
                'default_duration_minutes' => 45,
                'order_by' => 8,
                'is_enabled' => 1,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['legal_entity_id']);

        $this->assertDatabaseMissing('teams', [
            'partner_id' => $this->partner->id,
            'title' => 'Non-Ajax без юрлица',
        ]);
    }

    public function test_update_ajax_json_updates_legal_entity_in_multi_entity_mode(): void
    {
        $this->grantTeamAdminPermissions();

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Для PATCH',
            'legal_entity_id' => $this->entityA->id,
        ]);

        $this->patchJson(route('admin.team.update', $team->id), [
            'title' => $team->title,
            'default_duration_minutes' => 60,
            'order_by' => 10,
            'is_enabled' => 1,
            'legal_entity_id' => $this->entityB->id,
        ])
            ->assertOk()
            ->assertJsonFragment(['message' => 'Группа успешно обновлена']);

        $this->assertSame($this->entityB->id, $team->fresh()->legal_entity_id);
    }

    public function test_update_ajax_json_returns_422_when_legal_entity_missing_in_multi_entity_mode(): void
    {
        $this->grantTeamAdminPermissions();

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Для PATCH 422',
            'legal_entity_id' => $this->entityA->id,
        ]);

        $this->patchJson(route('admin.team.update', $team->id), [
            'title' => $team->title,
            'default_duration_minutes' => 60,
            'order_by' => 11,
            'is_enabled' => 1,
            'legal_entity_id' => null,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['legal_entity_id']);

        $this->assertSame($this->entityA->id, $team->fresh()->legal_entity_id);
    }

    public function test_update_non_ajax_patch_updates_team_json_contract_not_empty_200(): void
    {
        $this->grantTeamAdminPermissions();

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Non-Ajax PATCH',
            'legal_entity_id' => $this->entityA->id,
        ]);

        $this->patch(route('admin.team.update', $team->id), [
            'title' => 'Non-Ajax PATCH обновлена',
            'default_duration_minutes' => 50,
            'order_by' => 12,
            'is_enabled' => 1,
            'legal_entity_id' => $this->entityB->id,
        ])
            ->assertOk()
            ->assertJsonFragment(['message' => 'Группа успешно обновлена']);

        $team->refresh();
        $this->assertSame('Non-Ajax PATCH обновлена', $team->title);
        $this->assertSame($this->entityB->id, $team->legal_entity_id);
    }

    public function test_user_without_groups_view_gets_403_on_team_store_and_update(): void
    {
        $denied = $this->createUserWithoutPermission('groups.view', $this->partner);
        $this->actingAs($denied);

        $this->postJson(route('admin.team.store'), [
            'title' => 'Denied store',
            'default_duration_minutes' => 60,
            'order_by' => 1,
            'is_enabled' => 1,
            'legal_entity_id' => $this->entityA->id,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertForbidden();

        $this->patchJson(route('admin.team.update', $this->teamBound->id), [
            'title' => 'Denied update',
            'default_duration_minutes' => 60,
            'order_by' => 1,
            'is_enabled' => 1,
            'legal_entity_id' => $this->entityA->id,
        ])
            ->assertForbidden();
    }

    public function test_update_non_ajax_validation_failure_redirects_with_legal_entity_errors(): void
    {
        $this->grantTeamAdminPermissions();

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'PATCH validation',
            'legal_entity_id' => $this->entityA->id,
        ]);

        $this->from(route('admin.team.index'))
            ->patch(route('admin.team.update', $team->id), [
                'title' => $team->title,
                'default_duration_minutes' => 60,
                'order_by' => 13,
                'is_enabled' => 1,
                'legal_entity_id' => null,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['legal_entity_id']);

        $this->assertSame($this->entityA->id, $team->fresh()->legal_entity_id);
    }

    public function test_single_entity_partner_allows_team_without_legal_entity_binding(): void
    {
        PartnerLegalEntity::query()->where('partner_id', $this->partner->id)->delete();

        $this->seedRegisteredLegalEntityForPartner(shopCode: 'SHOP-SINGLE-ONLY');

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'legal_entity_id' => null,
        ]);

        app(TeamUserSyncService::class)->attachTeamForStudent($this->user, (int) $team->id);
        UserPrice::factory()->forUserAndMonth(
            (int) $this->user->id,
            '2027-05-01',
            2500,
            false,
            (int) $team->id,
        )->create();

        $this->grantPaymentPermissions();

        $this->post(route('payment'), [
            'paymentDate' => 'Май 2027',
            'formatedPaymentDate' => '2027-05-01',
            'team_id' => (int) $team->id,
            'outSum' => '25.00',
        ])
            ->assertOk()
            ->assertViewHas('tbankAvailable', true);

        $resolution = app(\App\Services\PartnerLegalEntities\LegalEntityResolver::class)->forTeam($team);
        $this->assertNotNull($resolution->entity);
        $this->assertSame('SHOP-SINGLE-ONLY', app(\App\Services\PartnerLegalEntities\LegalEntityResolver::class)
            ->shopCode($this->partner->fresh(), $resolution));
    }

    /* ============================================================
     * C. Клубный взнос — доступ и view-контракт (динамический T‑Bank)
     * ============================================================ */

    public function test_guest_get_club_fee_redirects_not_500(): void
    {
        Auth::logout();

        $response = $this->get(route('clubFee'));

        $this->assertContains($response->getStatusCode(), [302, 401, 403, 419]);
        $this->assertNotSame(500, $response->getStatusCode());
    }

    public function test_user_without_clubfee_permission_gets_403_on_club_fee(): void
    {
        $denied = $this->createUserWithoutPermission('payment.clubfee', $this->partner);
        $this->actingAs($denied);

        $this->get(route('clubFee'))->assertForbidden();
        $this->post(route('clubFee'))->assertForbidden();
    }

    public function test_authorized_club_fee_page_exposes_team_tbank_availability_map(): void
    {
        $this->grantPaymentPermissions();

        $this->get(route('clubFee'))
            ->assertOk()
            ->assertViewIs('payment.clubFee')
            ->assertViewHas('clubFeeRequiresTeamChoice', true)
            ->assertViewHas('tbankAvailable', false)
            ->assertViewHas('tbankSbpAvailable', false)
            ->assertViewHas('teamTbankCardAvailable', [
                (int) $this->teamBound->id => true,
                (int) $this->teamUnbound->id => false,
            ])
            ->assertSee('clubFeeTeamTbankCardAvailable', false)
            ->assertSee('updateClubFeeTbankVisibility', false)
            ->assertSee('id="clubFeeTbankCardBlock"', false);
    }

    public function test_club_fee_single_team_without_legal_entity_hides_tbank_in_multi_entity(): void
    {
        $this->grantPaymentPermissions();

        $onlyUnbound = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Единственная без юрлица',
            'legal_entity_id' => null,
        ]);

        $otherUser = User::factory()->withoutTeam()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
        ]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($otherUser, [(int) $onlyUnbound->id]);

        $this->actingAs($otherUser);

        $this->get(route('clubFee'))
            ->assertOk()
            ->assertViewHas('clubFeeRequiresTeamChoice', false)
            ->assertViewHas('clubFeeDefaultTeamId', (int) $onlyUnbound->id)
            ->assertViewHas('tbankAvailable', false)
            ->assertViewHas('teamTbankCardAvailable', [
                (int) $onlyUnbound->id => false,
            ]);
    }

    public function test_authorized_club_fee_post_returns_same_view_contract_as_get(): void
    {
        $this->grantPaymentPermissions();

        $this->post(route('clubFee'), ['outSum' => '100'])
            ->assertOk()
            ->assertViewIs('payment.clubFee')
            ->assertViewHas('clubFeeRequiresTeamChoice', true)
            ->assertViewHas('teamTbankCardAvailable', [
                (int) $this->teamBound->id => true,
                (int) $this->teamUnbound->id => false,
            ]);
    }

    public function test_club_fee_page_hides_tbank_blocks_until_team_selected_in_html(): void
    {
        $this->grantPaymentPermissions();

        $this->get(route('clubFee'))
            ->assertOk()
            ->assertSee('id="clubFeeTbankCardBlock"', false)
            ->assertSee('id="clubFeeTbankSbpBlock"', false)
            ->assertSee('style="display:none"', false);
    }

    public function test_tinkoff_monthly_sbp_init_rejects_unbound_team_not_500(): void
    {
        $this->grantPaymentPermissions();
        $this->seedMonthlyPriceForTeam($this->teamUnbound);

        $response = $this->post(route('payment.tinkoff.sbp'), [
            'paymentDate' => 'Апрель 2027',
            'formatedPaymentDate' => '2027-04-01',
            'team_id' => (int) $this->teamUnbound->id,
            'outSum' => '35.00',
        ]);

        $response->assertRedirect()
            ->assertSessionHasErrors(['tinkoff' => 'Для выбранной группы не настроено юр. лицо']);

        $this->assertNotSame(500, $response->getStatusCode());
    }

    public function test_tinkoff_club_fee_sbp_non_ajax_init_creates_payable_for_bound_team_and_redirects(): void
    {
        $this->grantPaymentPermissions();

        Http::fake([
            'https://rest-api-test.tinkoff.ru/v2/Init' => Http::response([
                'Success' => true,
                'PaymentId' => 880003,
                'PaymentURL' => 'https://example.test/club-sbp',
            ], 200),
            'https://rest-api-test.tinkoff.ru/v2/GetQr' => Http::response([
                'Success' => true,
                'Data' => 'https://example.test/qr',
            ], 200),
        ]);

        $this->post(route('payment.tinkoff.sbp'), [
            'outSum' => '50.00',
            'paymentDate' => 'Клубный взнос',
            'team_id' => (int) $this->teamBound->id,
        ])
            ->assertRedirect()
            ->assertStatus(302);

        $payable = Payable::query()->where('type', 'club_fee')->latest('id')->first();
        $this->assertNotNull($payable);
        $this->assertSame((int) $this->teamBound->id, (int) ($payable->meta['team_id'] ?? 0));
    }

    /* ============================================================
     * D. Init T‑Bank — клубный взнос и месячная оплата
     * ============================================================ */

    public function test_tinkoff_club_fee_init_rejects_unbound_team_with_legal_entity_message(): void
    {
        $this->grantPaymentPermissions();

        $this->post(route('payment.tinkoff.pay'), [
            'outSum' => '500.00',
            'paymentDate' => 'Клубный взнос',
            'team_id' => (int) $this->teamUnbound->id,
        ])
            ->assertRedirect()
            ->assertSessionHasErrors(['tinkoff' => 'Для выбранной группы не настроено юр. лицо']);

        $this->assertSame(0, Payable::query()->where('type', 'club_fee')->count());
    }

    public function test_tinkoff_club_fee_sbp_init_rejects_unbound_team_not_500(): void
    {
        $this->grantPaymentPermissions();

        $response = $this->post(route('payment.tinkoff.sbp'), [
            'outSum' => '50.00',
            'paymentDate' => 'Клубный взнос',
            'team_id' => (int) $this->teamUnbound->id,
        ]);

        $response->assertRedirect()
            ->assertSessionHasErrors(['tinkoff']);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertSame(0, Payable::query()->where('type', 'club_fee')->count());
    }

    public function test_user_without_tbank_card_gets_403_on_club_fee_tinkoff_init(): void
    {
        $this->grantPermissions(['payment.clubfee', 'paying.classes']);

        $denied = $this->createUserWithoutPermission('payment.method.tbankCard', $this->partner);
        app(TeamUserSyncService::class)->attachTeamForStudent($denied, (int) $this->teamBound->id);
        $this->actingAs($denied);

        $this->post(route('payment.tinkoff.pay'), [
            'outSum' => '500.00',
            'paymentDate' => 'Клубный взнос',
            'team_id' => (int) $this->teamBound->id,
        ])->assertForbidden();
    }

    public function test_tinkoff_club_fee_non_ajax_init_creates_payable_for_bound_team_and_redirects(): void
    {
        $this->grantPaymentPermissions();

        Http::fake([
            'https://rest-api-test.tinkoff.ru/v2/Init' => Http::response([
                'Success' => true,
                'PaymentId' => 880001,
                'PaymentURL' => 'https://example.test/club-fee-pay',
            ], 200),
        ]);

        $this->post(route('payment.tinkoff.pay'), [
            'outSum' => '1500.00',
            'paymentDate' => 'Клубный взнос',
            'team_id' => (int) $this->teamBound->id,
        ])
            ->assertRedirect()
            ->assertStatus(302);

        $payable = Payable::query()->where('type', 'club_fee')->latest('id')->first();
        $this->assertNotNull($payable);
        $this->assertSame((int) $this->teamBound->id, (int) ($payable->meta['team_id'] ?? 0));
    }

    public function test_payment_index_hides_tbank_for_unbound_team_in_multi_entity(): void
    {
        $this->grantPaymentPermissions();
        $this->seedMonthlyPriceForTeam($this->teamUnbound);

        $this->post(route('payment'), [
            'paymentDate' => 'Апрель 2027',
            'formatedPaymentDate' => '2027-04-01',
            'team_id' => (int) $this->teamUnbound->id,
            'outSum' => '35.00',
        ])
            ->assertOk()
            ->assertViewHas('tbankAvailable', false)
            ->assertViewHas('tbankSbpAvailable', false);
    }

    public function test_payment_index_shows_tbank_for_bound_team_in_multi_entity(): void
    {
        $this->grantPaymentPermissions();
        $this->seedMonthlyPriceForTeam($this->teamBound);

        $this->post(route('payment'), [
            'paymentDate' => 'Апрель 2027',
            'formatedPaymentDate' => '2027-04-01',
            'team_id' => (int) $this->teamBound->id,
            'outSum' => '35.00',
        ])
            ->assertOk()
            ->assertViewHas('tbankAvailable', true);
    }

    public function test_tinkoff_monthly_init_rejects_unbound_team_with_legal_entity_message(): void
    {
        $this->grantPaymentPermissions();
        $this->seedMonthlyPriceForTeam($this->teamUnbound);

        $this->post(route('payment.tinkoff.pay'), [
            'paymentDate' => 'Апрель 2027',
            'formatedPaymentDate' => '2027-04-01',
            'team_id' => (int) $this->teamUnbound->id,
            'outSum' => '35.00',
        ])
            ->assertRedirect()
            ->assertSessionHasErrors(['tinkoff' => 'Для выбранной группы не настроено юр. лицо']);

        $this->assertSame(0, Payable::query()->where('type', 'monthly_fee')->count());
    }

    public function test_tinkoff_monthly_non_ajax_init_creates_payable_for_bound_team_and_redirects(): void
    {
        $this->grantPaymentPermissions();
        $this->seedMonthlyPriceForTeam($this->teamBound);

        Http::fake([
            'https://rest-api-test.tinkoff.ru/v2/Init' => Http::response([
                'Success' => true,
                'PaymentId' => 880002,
                'PaymentURL' => 'https://example.test/monthly-pay',
            ], 200),
        ]);

        $this->post(route('payment.tinkoff.pay'), [
            'paymentDate' => 'Апрель 2027',
            'formatedPaymentDate' => '2027-04-01',
            'team_id' => (int) $this->teamBound->id,
            'outSum' => '35.00',
        ])
            ->assertRedirect()
            ->assertStatus(302);

        $payable = Payable::query()->where('type', 'monthly_fee')->latest('id')->first();
        $this->assertNotNull($payable);
        $this->assertSame((int) $this->teamBound->id, (int) ($payable->meta['team_id'] ?? 0));
    }

    public function test_guest_post_tinkoff_pay_redirects_to_login_not_500(): void
    {
        Auth::logout();

        $response = $this->post(route('payment.tinkoff.pay'), [
            'outSum' => '100.00',
            'paymentDate' => 'Клубный взнос',
            'team_id' => (int) $this->teamBound->id,
        ]);

        $response->assertRedirect(route('login'));
        $this->assertNotSame(500, $response->getStatusCode());
    }
}
