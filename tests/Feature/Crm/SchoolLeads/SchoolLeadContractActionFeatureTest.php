<?php

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\Contract;
use App\Models\Role;
use App\Models\SchoolLead;
use App\Models\User;
use App\Services\PartnerWidgetService;
use App\Services\SchoolLeads\LatestUserContractLookup;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

final class SchoolLeadContractActionFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asAdmin();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
    }

    private function defaultRoleId(): int
    {
        return (int) Role::query()->where('is_visible', 1)->orderBy('order_by')->value('id');
    }

    public function test_datatable_offers_create_contract_url_when_user_has_no_contracts(): void
    {
        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->defaultRoleId(),
        ]);

        SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Без договора',
            'phone'      => '+7 900 100-00-01',
            'status'     => 'new',
            'user_id'    => $user->id,
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]));

        $response->assertOk();

        $row = collect($response->json('data'))->firstWhere('name', 'Без договора');
        $this->assertNotNull($row);
        $this->assertArrayNotHasKey('latest_contract', $row);
        $this->assertStringContainsString(
            'user_id=' . $user->id,
            (string) $row['create_contract_url']
        );
    }

    public function test_datatable_shows_latest_contract_label_and_link(): void
    {
        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->defaultRoleId(),
        ]);

        $older = Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $user->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/test/old.pdf',
            'source_sha256'   => str_repeat('a', 64),
            'status'          => Contract::STATUS_DRAFT,
            'created_at'      => now()->subDay(),
        ]);

        $latest = Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $user->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/test/new.pdf',
            'source_sha256'   => str_repeat('b', 64),
            'status'          => Contract::STATUS_SENT,
            'created_at'      => now(),
        ]);

        SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'С договором',
            'phone'      => '+7 900 200-00-02',
            'status'     => 'new',
            'user_id'    => $user->id,
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]));

        $response->assertOk();

        $row = collect($response->json('data'))->firstWhere('name', 'С договором');
        $this->assertNotNull($row);
        $this->assertArrayNotHasKey('create_contract_url', $row);
        $this->assertSame($latest->id, (int) $row['latest_contract']['id']);
        $this->assertSame(
            app(LatestUserContractLookup::class)->formatActionLabel($latest),
            $row['latest_contract']['label']
        );
        $this->assertStringContainsString(
            '/client-contracts/' . $latest->id,
            (string) $row['latest_contract']['url']
        );

        $this->assertNotSame($older->id, (int) $row['latest_contract']['id']);
    }

    public function test_contract_create_page_prefills_student_from_query_user_id(): void
    {
        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->defaultRoleId(),
            'name'       => 'Петр',
            'lastname'   => 'Учеников',
        ]);

        $this->get(route('contracts.index', ['user_id' => $user->id]))
            ->assertOk()
            ->assertViewHas('preselectedUser', function ($pre) use ($user) {
                return is_array($pre)
                    && (int) ($pre['id'] ?? 0) === $user->id
                    && str_contains((string) ($pre['text'] ?? ''), 'Петр')
                    && str_contains((string) ($pre['text'] ?? ''), 'Учеников');
            })
            ->assertViewHas('shouldOpenCreateModal', true);

        $this->get(route('contracts.create', ['user_id' => $user->id]))
            ->assertRedirect(route('contracts.index', ['create' => 1, 'user_id' => $user->id]));
    }

    public function test_latest_contract_lookup_picks_newest_by_created_at(): void
    {
        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->defaultRoleId(),
        ]);

        Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $user->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/test/old.pdf',
            'source_sha256'   => str_repeat('a', 64),
            'status'          => Contract::STATUS_DRAFT,
            'created_at'      => now()->subDays(2),
        ]);

        $latest = Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $user->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/test/new.pdf',
            'source_sha256'   => str_repeat('b', 64),
            'status'          => Contract::STATUS_SIGNED,
            'created_at'      => now(),
        ]);

        $map = app(LatestUserContractLookup::class)->forUserIds((int) $this->partner->id, [$user->id]);

        $this->assertSame($latest->id, $map->get($user->id)?->id);
    }

    public function test_datatable_omits_contract_fields_without_contracts_view_permission(): void
    {
        $denied = $this->createUserWithoutPermission('contracts.view', $this->partner);

        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $denied->role_id,
            'permission_id' => $this->permissionId('schoolLeads.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->defaultRoleId(),
        ]);

        SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Нет contracts.view',
            'phone'      => '+7 900 901-01-01',
            'status'     => 'new',
            'user_id'    => $user->id,
        ]);

        $this->actingAs($denied)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $row = collect($this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]))->json('data'))->firstWhere('name', 'Нет contracts.view');

        $this->assertNotNull($row);
        $this->assertArrayNotHasKey('latest_contract', $row);
        $this->assertArrayNotHasKey('create_contract_url', $row);
    }

    public function test_contract_create_page_ignores_foreign_user_id(): void
    {
        $foreignUser = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'role_id'    => $this->defaultRoleId(),
            'name'       => 'Чужой',
            'lastname'   => 'Ученик',
        ]);

        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->get(route('contracts.index', ['user_id' => $foreignUser->id]))
            ->assertOk()
            ->assertViewHas('preselectedUser', null);
    }
}
