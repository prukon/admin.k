<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Payments;

use App\Models\LessonPackage;
use App\Models\PartnerLegalEntity;
use App\Models\Team;
use App\Models\User;
use App\Models\UserCustomPayment;
use App\Models\UserLessonPackage;
use App\Models\UserPrice;
use App\Services\Payments\PaymentCheckoutLegalEntityPresenter;
use App\Services\TeamUserSyncService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Витрина оплаты: блок «Поставщик услуг», PaymentCheckoutContext, доступы и контракты endpoint'ов.
 *
 * Endpoint'ы раздела (web, без AJAX):
 * - POST /payment (paying.classes) — рендер payment.paymentUser, 200
 * - GET|POST /payment/club-fee (payment.clubfee) — рендер payment.clubFee, 200
 */
final class PaymentCheckoutServiceProviderFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    private function grantPermission(string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param list<string> $permissions */
    private function grantPermissions(array $permissions): void
    {
        foreach ($permissions as $permission) {
            $this->grantPermission($permission);
        }
    }

    private function grantTbankPermissions(): void
    {
        $this->grantPermissions(['payment.method.tbankCard', 'payment.method.tbankSBP']);
    }

    private function grantPayingClassesAccess(): void
    {
        $this->grantPermission('paying.classes');
    }

    private function grantClubFeeAccess(): void
    {
        $this->grantPermission('payment.clubfee');
    }

    /**
     * @return array<string, mixed>
     */
    private function monthlyPaymentPayload(int $teamId, string $month = '2027-06-01'): array
    {
        return [
            'paymentDate' => 'Июнь 2027',
            'formatedPaymentDate' => $month,
            'team_id' => $teamId,
            'outSum' => '25.00',
        ];
    }

    private function seedMonthlyPrice(int $userId, int $teamId, string $month = '2027-06-01'): void
    {
        UserPrice::factory()->forUserAndMonth($userId, $month, 2500, false, $teamId)->create();
    }

    /**
     * @param  list<string>  $permissions
     */
    private function revokePermissions(array $permissions): void
    {
        $ids = array_map(fn (string $slug) => $this->permissionId($slug), $permissions);

        DB::table('permission_role')
            ->where('partner_id', $this->partner->id)
            ->where('role_id', $this->user->role_id)
            ->whereIn('permission_id', $ids)
            ->delete();

        $this->user->refresh();
        $this->user->unsetRelation('role');
        $this->actingAs($this->user);
    }

    private function seedLessonPackageAssignment(User $user, Team $team, float $feeAmount = 500.0): UserLessonPackage
    {
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'ULP service provider test',
            'schedule_type' => 'no_schedule',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        return UserLessonPackage::query()->create([
            'user_id' => $user->id,
            'lesson_package_id' => $package->id,
            'team_id' => $team->id,
            'starts_at' => '2026-04-01',
            'ends_at' => '2026-05-01',
            'lessons_total' => 8,
            'lessons_remaining' => 8,
            'fee_amount' => number_format($feeAmount, 2, '.', ''),
            'is_paid' => false,
        ]);
    }

    /* ============================================================
     * A. PaymentCheckoutLegalEntityPresenter
     * ============================================================ */

    public function test_presenter_formats_full_label(): void
    {
        $entity = PartnerLegalEntity::factory()
            ->for($this->partner)
            ->create([
                'organization_name' => 'ИП Иванов Иван Иванович',
                'tax_id' => '691408496704',
                'city' => 'Санкт-Петербург',
            ]);

        $label = app(PaymentCheckoutLegalEntityPresenter::class)->formatLabel($entity);

        $this->assertSame(
            'ИП Иванов Иван Иванович, ИНН 691408496704, Санкт-Петербург',
            $label,
        );
    }

    public function test_presenter_omits_empty_inn_and_city(): void
    {
        $entity = PartnerLegalEntity::factory()
            ->for($this->partner)
            ->create([
                'organization_name' => 'ООО Только имя',
                'tax_id' => null,
                'city' => null,
            ]);

        $label = app(PaymentCheckoutLegalEntityPresenter::class)->formatLabel($entity);

        $this->assertSame('ООО Только имя', $label);
    }

    public function test_presenter_label_for_team_id_returns_null_without_binding(): void
    {
        $this->seedGlobalTbank();

        PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-A')
            ->create(['is_default' => true]);

        PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-B')
            ->create(['is_default' => false]);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'legal_entity_id' => null,
        ]);

        $label = app(PaymentCheckoutLegalEntityPresenter::class)
            ->labelForTeamId((int) $this->partner->id, (int) $team->id);

        $this->assertNull($label);
    }

    /* ============================================================
     * B. Access — POST /payment
     * ============================================================ */

    public function test_guest_post_payment_redirects_not_500(): void
    {
        Auth::logout();

        $response = $this->post(route('payment'), $this->monthlyPaymentPayload(1));

        $this->assertContains($response->getStatusCode(), [302, 401, 403, 419]);
        $this->assertNotSame(500, $response->getStatusCode());
    }

    public function test_user_without_paying_classes_gets_403_on_payment_index(): void
    {
        ['team' => $team] = $this->seedTbankTeamChainForStudent();
        $this->seedMonthlyPrice((int) $this->user->id, (int) $team->id);

        $denied = $this->createUserWithoutPermission('paying.classes', $this->partner);
        app(TeamUserSyncService::class)->attachTeamForStudent($denied, (int) $team->id);
        $this->seedMonthlyPrice((int) $denied->id, (int) $team->id);
        $this->actingAs($denied);

        $this->post(route('payment'), $this->monthlyPaymentPayload((int) $team->id))
            ->assertForbidden();
    }

    public function test_authorized_user_with_paying_classes_gets_200_on_payment_index(): void
    {
        ['team' => $team] = $this->seedTbankTeamChainForStudent();
        $this->seedMonthlyPrice((int) $this->user->id, (int) $team->id);
        $this->grantPayingClassesAccess();

        $this->post(route('payment'), $this->monthlyPaymentPayload((int) $team->id))
            ->assertOk()
            ->assertViewIs('payment.paymentUser')
            ->assertViewHas('monthlyTeamId', (int) $team->id);
    }

    public function test_payment_index_non_ajax_post_returns_view_not_empty_200(): void
    {
        ['team' => $team] = $this->seedTbankTeamChainForStudent();
        $this->seedMonthlyPrice((int) $this->user->id, (int) $team->id);
        $this->grantPayingClassesAccess();

        $response = $this->post(route('payment'), $this->monthlyPaymentPayload((int) $team->id));

        $response->assertOk();
        $response->assertViewIs('payment.paymentUser');
        $this->assertNotSame('', trim(strip_tags($response->getContent())));
        $this->assertNotSame(500, $response->getStatusCode());
    }

    /* ============================================================
     * C. Access — GET|POST /payment/club-fee
     * ============================================================ */

    public function test_guest_get_club_fee_redirects_not_500(): void
    {
        Auth::logout();

        $response = $this->get(route('clubFee'));

        $this->assertContains($response->getStatusCode(), [302, 401, 403, 419]);
        $this->assertNotSame(500, $response->getStatusCode());
    }

    public function test_guest_post_club_fee_redirects_not_500(): void
    {
        Auth::logout();

        $response = $this->post(route('clubFee'), ['outSum' => '100']);

        $this->assertContains($response->getStatusCode(), [302, 401, 403, 419]);
        $this->assertNotSame(500, $response->getStatusCode());
    }

    public function test_user_without_clubfee_permission_gets_403_on_club_fee_get_and_post(): void
    {
        $denied = $this->createUserWithoutPermission('payment.clubfee', $this->partner);
        $this->actingAs($denied);

        $this->get(route('clubFee'))->assertForbidden();
        $this->post(route('clubFee'), ['outSum' => '100'])->assertForbidden();
    }

    public function test_authorized_user_gets_200_on_club_fee_get(): void
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        app(TeamUserSyncService::class)->attachTeamForStudent($this->user, (int) $team->id);
        $this->grantClubFeeAccess();

        $this->get(route('clubFee'))
            ->assertOk()
            ->assertViewIs('payment.clubFee')
            ->assertViewHas('clubFeeBlocked', false);
    }

    public function test_club_fee_non_ajax_post_returns_same_view_contract_as_get(): void
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Club POST']);
        app(TeamUserSyncService::class)->attachTeamForStudent($this->user, (int) $team->id);
        $this->grantClubFeeAccess();

        $this->post(route('clubFee'), ['outSum' => '100'])
            ->assertOk()
            ->assertViewIs('payment.clubFee')
            ->assertViewHas('clubFeeRequiresTeamChoice', false)
            ->assertViewHas('clubFeeDefaultTeamId', (int) $team->id);

        $this->assertNotSame(500, $this->post(route('clubFee'), ['outSum' => '100'])->getStatusCode());
    }

    /* ============================================================
     * D. POST /payment — «Поставщик услуг»
     * ============================================================ */

    public function test_payment_page_shows_service_provider_when_tbank_ready(): void
    {
        $this->seedGlobalTbank();
        $this->grantPayingClassesAccess();
        $this->grantTbankPermissions();

        ['team' => $team] = $this->seedTbankTeamChainForStudent(
            entityOverrides: [
                'organization_name' => 'ИП Иванов Иван Иванович',
                'tax_id' => '691408496704',
                'city' => 'Санкт-Петербург',
            ],
        );

        $this->seedMonthlyPrice((int) $this->user->id, (int) $team->id);

        $this->post(route('payment'), $this->monthlyPaymentPayload((int) $team->id))
            ->assertOk()
            ->assertViewHas('showTbankLegalEntityBlock', true)
            ->assertViewHas('tbankAvailable', true)
            ->assertViewHas('tbankSbpAvailable', true)
            ->assertViewHas('serviceProviderLabel', 'ИП Иванов Иван Иванович, ИНН 691408496704, Санкт-Петербург')
            ->assertSee('Поставщик услуг', false)
            ->assertSee('ИП Иванов Иван Иванович, ИНН 691408496704, Санкт-Петербург', false);
    }

    public function test_payment_page_hides_service_provider_block_without_global_tbank(): void
    {
        $this->grantPayingClassesAccess();
        $this->grantTbankPermissions();
        ['team' => $team] = $this->seedTbankTeamChainForStudent();
        $this->seedMonthlyPrice((int) $this->user->id, (int) $team->id);

        $this->post(route('payment'), $this->monthlyPaymentPayload((int) $team->id))
            ->assertOk()
            ->assertViewHas('showTbankLegalEntityBlock', false)
            ->assertDontSee('Поставщик услуг', false);
    }

    public function test_payment_page_hides_service_provider_without_tbank_method_permissions(): void
    {
        $this->seedGlobalTbank();
        $this->grantPayingClassesAccess();
        ['team' => $team] = $this->seedTbankTeamChainForStudent();
        $this->seedMonthlyPrice((int) $this->user->id, (int) $team->id);

        $this->revokePermissions(['payment.method.tbankCard', 'payment.method.tbankSBP']);

        $this->post(route('payment'), $this->monthlyPaymentPayload((int) $team->id))
            ->assertOk()
            ->assertViewHas('showTbankLegalEntityBlock', false)
            ->assertViewHas('tbankAvailable', false)
            ->assertViewHas('tbankSbpAvailable', false)
            ->assertDontSee('Поставщик услуг', false);
    }

    public function test_payment_page_shows_block_with_only_tbank_card_permission(): void
    {
        $this->seedGlobalTbank();
        $this->grantPayingClassesAccess();
        $this->grantTbankPermissions();

        ['team' => $team] = $this->seedTbankTeamChainForStudent(
            entityOverrides: [
                'organization_name' => 'ИП Только карта',
                'tax_id' => '111111111111',
                'city' => 'Москва',
            ],
        );
        $this->seedMonthlyPrice((int) $this->user->id, (int) $team->id);

        $this->revokePermissions(['payment.method.tbankSBP']);

        $this->post(route('payment'), $this->monthlyPaymentPayload((int) $team->id))
            ->assertOk()
            ->assertViewHas('showTbankLegalEntityBlock', true)
            ->assertViewHas('tbankAvailable', true)
            ->assertViewHas('tbankSbpAvailable', false)
            ->assertViewHas('serviceProviderLabel', 'ИП Только карта, ИНН 111111111111, Москва');
    }

    public function test_payment_page_blocks_tbank_and_shows_school_contact_when_entity_missing(): void
    {
        $this->seedGlobalTbank();
        $this->grantPayingClassesAccess();
        $this->grantTbankPermissions();

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'legal_entity_id' => null,
        ]);
        app(TeamUserSyncService::class)->attachTeamForStudent($this->user, (int) $team->id);

        PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-A')
            ->create(['is_default' => true, 'organization_name' => 'ЮЛ A']);

        PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-B')
            ->create(['is_default' => false, 'organization_name' => 'ЮЛ B']);

        $this->seedMonthlyPrice((int) $this->user->id, (int) $team->id);

        $this->post(route('payment'), $this->monthlyPaymentPayload((int) $team->id))
            ->assertOk()
            ->assertViewHas('showTbankLegalEntityBlock', true)
            ->assertViewHas('tbankAvailable', false)
            ->assertViewHas('tbankSbpAvailable', false)
            ->assertViewHas('serviceProviderLabel', null)
            ->assertSee('Обратитесь в школу.', false);
    }

    public function test_custom_payment_page_resolves_service_provider_by_assignment_team(): void
    {
        $this->seedGlobalTbank();
        $this->grantPayingClassesAccess();
        $this->grantTbankPermissions();

        ['team' => $team] = $this->seedTbankTeamChainForStudent(
            entityOverrides: [
                'organization_name' => 'ИП Школа Тест',
                'tax_id' => '123456789012',
                'city' => 'Москва',
            ],
        );

        $upp = UserCustomPayment::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'team_id' => $team->id,
            'date_start' => '2026-09-01',
            'date_end' => '2026-09-30',
            'amount' => '500.00',
            'is_paid' => 0,
        ]);

        $this->post(route('payment'), [
            'payment_kind' => 'custom_payment',
            'custom_payment_id' => $upp->id,
            'paymentDate' => 'Дополнительный платеж',
            'outSum' => '1.00',
        ])
            ->assertOk()
            ->assertViewHas('serviceProviderLabel', 'ИП Школа Тест, ИНН 123456789012, Москва')
            ->assertViewHas('tbankAvailable', true);
    }

    public function test_lesson_package_page_resolves_service_provider_by_assignment_team(): void
    {
        $this->seedGlobalTbank();
        $this->grantPayingClassesAccess();
        $this->grantTbankPermissions();

        ['team' => $team] = $this->seedTbankTeamChainForStudent(
            entityOverrides: [
                'organization_name' => 'ИП Абонемент',
                'tax_id' => '555555555555',
                'city' => 'Новосибирск',
            ],
        );

        $ulp = $this->seedLessonPackageAssignment($this->user, $team, 444.0);

        $this->post(route('payment'), [
            'payment_kind' => 'lesson_package',
            'user_lesson_package_id' => $ulp->id,
            'paymentDate' => 'ignored',
            'outSum' => '1.00',
        ])
            ->assertOk()
            ->assertViewHas('serviceProviderLabel', 'ИП Абонемент, ИНН 555555555555, Новосибирск')
            ->assertViewHas('tbankAvailable', true);
    }

    public function test_payment_index_multi_entity_bound_team_shows_service_provider_label(): void
    {
        $this->seedGlobalTbank();
        $this->grantPayingClassesAccess();
        $this->grantTbankPermissions();

        $entity = PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-BOUND-SP')
            ->create([
                'is_default' => true,
                'organization_name' => 'ИП Привязанное',
                'tax_id' => '770011223344',
                'city' => 'Екатеринбург',
            ]);

        PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-OTHER-SP')
            ->create(['is_default' => false]);

        $teamBound = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'legal_entity_id' => $entity->id,
        ]);
        $teamUnbound = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'legal_entity_id' => null,
        ]);

        $sync = app(TeamUserSyncService::class);
        $sync->attachTeamForStudent($this->user, (int) $teamBound->id);
        $sync->attachTeamForStudent($this->user, (int) $teamUnbound->id);

        $this->seedMonthlyPrice((int) $this->user->id, (int) $teamBound->id, '2027-04-01');
        $this->seedMonthlyPrice((int) $this->user->id, (int) $teamUnbound->id, '2027-04-01');

        $this->post(route('payment'), [
            'paymentDate' => 'Апрель 2027',
            'formatedPaymentDate' => '2027-04-01',
            'team_id' => (int) $teamBound->id,
            'outSum' => '25.00',
        ])
            ->assertOk()
            ->assertViewHas('tbankAvailable', true)
            ->assertViewHas('serviceProviderLabel', 'ИП Привязанное, ИНН 770011223344, Екатеринбург');

        $this->post(route('payment'), [
            'paymentDate' => 'Апрель 2027',
            'formatedPaymentDate' => '2027-04-01',
            'team_id' => (int) $teamUnbound->id,
            'outSum' => '25.00',
        ])
            ->assertOk()
            ->assertViewHas('tbankAvailable', false)
            ->assertViewHas('serviceProviderLabel', null)
            ->assertSee('Обратитесь в школу.', false);
    }

    /* ============================================================
     * E. GET|POST /payment/club-fee — «Поставщик услуг»
     * ============================================================ */

    public function test_club_fee_single_team_shows_service_provider(): void
    {
        $this->seedGlobalTbank();
        $this->grantClubFeeAccess();
        $this->grantTbankPermissions();

        $student = User::factory()->withoutTeam()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
        ]);

        $this->seedTbankTeamChainForStudent(
            user: $student,
            entityOverrides: [
                'organization_name' => 'ИП Клубный',
                'tax_id' => '998877665544',
                'city' => 'Казань',
            ],
        );

        $this->actingAs($student);

        $this->get(route('clubFee'))
            ->assertOk()
            ->assertViewHas('showTbankLegalEntityBlock', true)
            ->assertViewHas('serviceProviderLabel', 'ИП Клубный, ИНН 998877665544, Казань')
            ->assertViewHas('tbankAvailable', true)
            ->assertSee('Поставщик услуг', false)
            ->assertSee('clubFeeTeamTbankCheckout', false)
            ->assertSee('updateClubFeeServiceProvider', false);
    }

    public function test_club_fee_single_team_without_legal_entity_shows_contact_school(): void
    {
        $this->seedGlobalTbank();
        $this->grantClubFeeAccess();
        $this->grantTbankPermissions();

        $student = User::factory()->withoutTeam()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
        ]);

        $onlyUnbound = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'legal_entity_id' => null,
        ]);
        app(TeamUserSyncService::class)->attachTeamForStudent($student, (int) $onlyUnbound->id);

        PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-A')
            ->create(['is_default' => true]);

        PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-B')
            ->create(['is_default' => false]);

        $this->actingAs($student);

        $this->get(route('clubFee'))
            ->assertOk()
            ->assertViewHas('showTbankLegalEntityBlock', true)
            ->assertViewHas('serviceProviderLabel', null)
            ->assertViewHas('tbankAvailable', false)
            ->assertViewHas('teamTbankCheckout', [
                (int) $onlyUnbound->id => [
                    'card' => false,
                    'sbp' => false,
                    'serviceProviderLabel' => null,
                ],
            ])
            ->assertSee('Обратитесь в школу.', false);
    }

    public function test_club_fee_multi_team_exposes_team_checkout_map_with_service_provider_labels(): void
    {
        $this->seedGlobalTbank();
        $this->grantClubFeeAccess();
        $this->grantTbankPermissions();

        $entity = PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-CLUB-A')
            ->create([
                'organization_name' => 'ИП Клуб A',
                'tax_id' => '101010101010',
                'city' => 'Самара',
            ]);

        PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-CLUB-B')
            ->create(['is_default' => false]);

        $teamBound = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Club-Bound',
            'legal_entity_id' => $entity->id,
        ]);
        $teamUnbound = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Club-Unbound',
            'legal_entity_id' => null,
        ]);

        $sync = app(TeamUserSyncService::class);
        $sync->attachTeamForStudent($this->user, (int) $teamBound->id);
        $sync->attachTeamForStudent($this->user, (int) $teamUnbound->id);

        $this->get(route('clubFee'))
            ->assertOk()
            ->assertViewHas('clubFeeRequiresTeamChoice', true)
            ->assertViewHas('showTbankLegalEntityBlock', true)
            ->assertViewHas('serviceProviderLabel', null)
            ->assertViewHas('tbankAvailable', false)
            ->assertViewHas('teamTbankCheckout', [
                (int) $teamBound->id => [
                    'card' => true,
                    'sbp' => true,
                    'serviceProviderLabel' => 'ИП Клуб A, ИНН 101010101010, Самара',
                ],
                (int) $teamUnbound->id => [
                    'card' => false,
                    'sbp' => false,
                    'serviceProviderLabel' => null,
                ],
            ])
            ->assertSee('id="clubFeeServiceProviderRow"', false)
            ->assertSee('clubFeeTeamTbankCheckout', false);
    }

    public function test_club_fee_post_exposes_same_service_provider_contract_as_get(): void
    {
        $this->seedGlobalTbank();
        $this->grantClubFeeAccess();
        $this->grantTbankPermissions();

        ['team' => $team] = $this->seedTbankTeamChainForStudent(
            entityOverrides: [
                'organization_name' => 'ИП POST GET',
                'tax_id' => '121212121212',
                'city' => 'Тула',
            ],
        );

        $student = User::query()->findOrFail($this->user->id);
        app(TeamUserSyncService::class)->attachTeamForStudent($student, (int) $team->id);

        $this->post(route('clubFee'), ['outSum' => '500'])
            ->assertOk()
            ->assertViewHas('serviceProviderLabel', 'ИП POST GET, ИНН 121212121212, Тула')
            ->assertViewHas('tbankAvailable', true);
    }
}
