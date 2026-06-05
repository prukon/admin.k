<?php

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\ParentProfile;
use App\Models\Team;
use App\Models\User;
use App\Support\RuPhone;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ContractsParentAndAccessTest extends ContractsFeatureTestCase
{
    private function createDraftContract(User $student, ?string $pdfPath = null): Contract
    {
        $path = $pdfPath ?? 'documents/2026/05/parent-test.pdf';

        return Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'group_id'        => $student->team_id,
            'source_pdf_path' => $path,
            'source_sha256'   => str_repeat('p', 64),
            'provider'        => 'podpislon',
            'status'          => Contract::STATUS_DRAFT,
        ]);
    }

    /** @test */
    public function guest_cannot_access_contracts_section(): void
    {
        Auth::logout();

        $this->get('/client-contracts')->assertStatus(302);
        $this->getJson('/client-contracts/data?draw=1')->assertStatus(401);
    }

    /** @test */
    public function contracts_section_forbidden_without_contracts_view(): void
    {
        $actor = $this->createUserWithoutPermission(self::PERM_CONTRACTS_VIEW, $this->partner);

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->get('/client-contracts')
            ->assertStatus(403);
    }

    /** @test */
    public function contracts_section_endpoints_return_ok_with_contracts_view(): void
    {
        Storage::fake();

        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа-A']);
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Петров',
            'firstname'  => 'Пётр',
            'middlename' => 'Петрович',
        ]);
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id'    => $team->id,
            'parent_id'  => $parent->id,
            'is_enabled' => 1,
            'phone'      => '+79991112233',
            'email'      => 'parent@example.com',
        ]);

        $contract = $this->createDraftContract($student);
        Storage::put($contract->source_pdf_path, '%PDF-test');

        $this->get('/client-contracts')->assertOk();
        $this->get('/client-contracts/create')
            ->assertRedirect(route('contracts.index', ['create' => 1]));
        $this->getJson('/client-contracts/data?draw=1&start=0&length=10')->assertOk();
        $this->getJson('/client-contracts/columns-settings')->assertOk();
        $this->postJson('/client-contracts/columns-settings', [
            'columns' => ['user_name' => true],
        ])->assertOk();
        $this->getJson('/client-contracts/users-search?q=Пет')->assertOk();
        $this->getJson('/client-contracts/user-group?user_id=' . $student->id)->assertOk();

        $this->postJson('/client-contracts/check-balance')
            ->assertOk()
            ->assertJsonStructure(['ok', 'fee']);

        $this->get('/client-contracts/' . $contract->id)->assertOk();
        $this->get('/client-contracts/' . $contract->id . '/download-original')->assertOk();

        Mail::fake();

        $this->postJson('/client-contracts/' . $contract->id . '/send-email', [
            'email' => 'notify@example.com',
        ])->assertOk();
    }

    /** @test */
    public function show_displays_parent_full_name_in_main_info_block(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Сидоров',
            'firstname'  => 'Сидор',
            'middlename' => 'Сидорович',
        ]);
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Учеников',
            'name'       => 'Артём',
            'parent_id'  => $parent->id,
            'is_enabled' => 1,
        ]);

        $contract = $this->createDraftContract($student);

        $this->get('/client-contracts/' . $contract->id)
            ->assertOk()
            ->assertSee('>Родитель</dt>', false)
            ->assertSee('Сидоров Сидор Сидорович', false)
            ->assertSee('Учеников Артём', false);
    }

    /** @test */
    public function show_prefills_signer_modal_from_parent_fields_and_phone(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Козлов',
            'firstname'  => 'Козма',
            'middlename' => 'Козьмич',
        ]);
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'parent_id'  => $parent->id,
            'phone'      => '+79990001122',
            'is_enabled' => 1,
        ]);

        $contract = $this->createDraftContract($student);

        $html = $this->get('/client-contracts/' . $contract->id)->getContent();

        $this->assertStringContainsString('id="signerLastname"', $html);
        $this->assertStringContainsString('value="Козлов"', $html);
        $this->assertStringContainsString('id="signerFirstname"', $html);
        $this->assertStringContainsString('value="Козма"', $html);
        $this->assertStringContainsString('id="signerMiddlename"', $html);
        $this->assertStringContainsString('value="Козьмич"', $html);
        $this->assertStringContainsString('id="signerPhone"', $html);
        $this->assertStringContainsString(
            'value="' . RuPhone::formatForInput('+79990001122') . '"',
            $html
        );
    }

    /** @test */
    public function show_uses_parent_profile_when_linked(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Профильный',
            'firstname'  => 'Родитель',
            'middlename' => 'Тестович',
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'parent_id'  => $parent->id,
            'is_enabled' => 1,
        ]);

        $contract = $this->createDraftContract($student);

        $this->get('/client-contracts/' . $contract->id)
            ->assertOk()
            ->assertSee('Профильный Родитель Тестович', false);
    }

    /** @test */
    public function show_signer_prefill_empty_when_parent_fields_missing(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        $contract = $this->createDraftContract($student);

        $response = $this->get('/client-contracts/' . $contract->id);

        $response->assertOk()
            ->assertSee('>Родитель</dt>', false);

        $this->assertStringContainsString(
            '<dd class="col-sm-8">—</dd>',
            $response->getContent()
        );
    }
}
