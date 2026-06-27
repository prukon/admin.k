<?php

namespace Tests\Feature\Phone;

use App\Models\Contract;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\Account\Concerns\InteractsWithAccountContractFill;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Phone\Concerns\InteractsWithPhoneInput;

/**
 * CRM-страницы с полями телефона: доступ 200 и централизованная маска в HTML/JSON.
 */
final class PhoneFormCrmPagesAccessFeatureTest extends CrmTestCase
{
    use InteractsWithPhoneInput;
    use InteractsWithAccountContractFill;

    private Contract $contract;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asAdmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->grantContractsViewPermission();
        $this->useAccountContractFillStorage();
        $this->contract = $this->makeAwaitingFillContract([
            ['key' => 'parent_phone', 'label' => 'Телефон родителя', 'required' => true],
        ], ['parent_phone']);
    }

    private function grantContractsViewPermission(): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $this->user->role_id,
            'permission_id' => $this->permissionId('contracts.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function test_crm_pages_with_phone_inputs_return_200(): void
    {
        $this->get(route('admin.trainers.index'))->assertOk();
        $this->get(route('admin.user1'))->assertOk();
        $this->get(route('account.user.edit'))->assertOk();
        $this->get(route('admin.cur.company.edit'))->assertOk();
        $this->get(route('account.documents.index'))->assertOk();
        $this->get(route('contracts.show', $this->contract))->assertOk();

        $this->asSuperadmin();
        $this->get(route('admin.partner.index'))->assertOk();
    }

    public function test_crm_pages_include_centralized_phone_mask_assets(): void
    {
        $layoutPages = [
            route('admin.trainers.index'),
            route('admin.user1'),
            route('account.user.edit'),
            route('admin.cur.company.edit'),
            route('account.documents.index'),
        ];

        foreach ($layoutPages as $url) {
            $html = (string) $this->get($url)->assertOk()->getContent();
            $this->assertPhoneMaskScriptsInHtml($html);
        }

        $inlinePhonePages = [
            route('admin.trainers.index'),
            route('account.user.edit'),
            route('admin.cur.company.edit'),
        ];

        foreach ($inlinePhonePages as $url) {
            $html = (string) $this->get($url)->assertOk()->getContent();
            $this->assertCentralizedPhoneMaskAssetsInHtml($html);
        }
    }

    public function test_contract_fill_endpoint_returns_200_with_phone_field_markup(): void
    {
        $html = $this->getContractFillModalHtml($this->contract);

        $this->assertStringContainsString('name="fields[parent_phone]"', $html);
        $this->assertStringContainsString('js-contract-fill-phone', $html);
        $this->assertStringContainsString('type="tel"', $html);
    }

    public function test_tinkoff_partner_page_returns_200_for_authorized_superadmin(): void
    {
        $this->asSuperadmin();

        $this->get('/admin/tinkoff/partners/' . $this->partner->id)
            ->assertOk()
            ->assertSee('Справочник «Юр. лица»', false);
    }

    public function test_admin_user_edit_endpoint_returns_200_json(): void
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $target = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => Role::query()->where('name', 'user')->value('id'),
            'team_id'    => $team->id,
        ]);

        $this->getJson(route('admin.user.edit', $target), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJsonStructure(['user']);
    }

    public function test_partner_edit_endpoint_returns_200_json(): void
    {
        $this->asSuperadmin();

        $this->getJson(route('admin.partner.edit', $this->partner))
            ->assertOk()
            ->assertJsonStructure(['phone', 'ceo']);
    }

    public function test_phone_related_mutation_endpoints_return_success_for_authorized_actor(): void
    {
        $userPhone = $this->randomRuPhoneMasked();
        $trainerPhone = $this->randomRuPhoneMasked();
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $roleId = Role::query()->where('name', 'user')->value('id');

        $this->postJson(route('admin.user.store'), [
            'name'       => 'Access',
            'lastname'   => 'Phone',
            'email'      => 'access-phone-' . uniqid('', true) . '@example.test',
            'phone'      => $userPhone,
            'role_id'    => $roleId,
            'team_id'    => $team->id,
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $this->postJson(route('admin.trainers.store'), [
            'lastname'   => 'Access',
            'name'       => 'Trainer',
            'email'      => 'access-trainer-' . uniqid('', true) . '@example.test',
            'phone'      => $trainerPhone,
            'is_enabled' => 1,
        ])->assertOk();

        $this->patchJson(route('account.user.update'), [
            'name'     => $this->user->name,
            'lastname' => $this->user->lastname,
            'phone'    => $userPhone,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept'           => 'application/json',
        ])->assertOk();
    }
}
