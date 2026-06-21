<?php

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Договоры: выбор group_id из pivot team_user (несколько групп ученика).
 */
final class ContractGroupPivotFeatureTest extends ContractsFeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['billing.contract_create_fee' => 70.00]);
        $this->partner->wallet_balance = 500;
        $this->partner->save();
    }

    public function test_user_group_endpoint_returns_all_student_teams(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Alpha']);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Beta']);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
            'team_id'    => $teamA->id,
        ]);

        DB::table('team_user')->insert([
            'partner_id' => $this->partner->id,
            'team_id'    => $teamB->id,
            'user_id'    => $student->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson(route('contracts.user.group', ['user_id' => $student->id]))
            ->assertOk()
            ->json();

        $ids = collect($response['groups'])->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $this->assertSame([$teamA->id, $teamB->id], $ids);
    }

    public function test_users_search_includes_groups_array(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Группа поиска',
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
            'name'       => 'УникПоиск',
            'lastname'   => 'Договор',
            'team_id'    => $team->id,
        ]);

        $match = collect(
            $this->getJson(route('contracts.users.search', ['q' => 'УникПоиск']))->json('results')
        )->firstWhere('id', $student->id);

        $this->assertNotNull($match);
        $this->assertSame($team->id, (int) ($match['team_id'] ?? 0));
        $this->assertContains($team->id, $match['team_ids'] ?? []);
        $this->assertSame('Группа поиска', $match['groups'][0]['title'] ?? null);
    }

    public function test_preselected_user_includes_groups_from_pivot(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'A']);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'B']);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
            'team_id'    => null,
        ]);

        foreach ([$teamA, $teamB] as $team) {
            DB::table('team_user')->insert([
                'partner_id' => $this->partner->id,
                'team_id'    => $team->id,
                'user_id'    => $student->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->get(route('contracts.index', ['user_id' => $student->id]))
            ->assertOk()
            ->assertViewHas('preselectedUser', function ($pre) use ($student, $teamA, $teamB) {
                $groupIds = collect($pre['groups'] ?? [])->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();

                return (int) $pre['id'] === $student->id
                    && $groupIds === collect([$teamA->id, $teamB->id])->sort()->values()->all();
            });
    }

    public function test_store_assigns_single_group_automatically(): void
    {
        Storage::fake();

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
            'team_id'    => $team->id,
        ]);

        $pdf = UploadedFile::fake()->create('contract.pdf', 20, 'application/pdf');

        $this->from(route('contracts.index', ['create' => 1]))
            ->post(route('contracts.store'), [
                'creation_mode' => Contract::CREATION_MODE_PDF,
                'user_id'       => $student->id,
                'pdf'           => $pdf,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('contracts', [
            'user_id'  => $student->id,
            'group_id' => $team->id,
        ]);
    }

    public function test_store_requires_group_id_when_student_has_multiple_teams(): void
    {
        Storage::fake();

        $teamA = Team::factory()->create(['partner_id' => $this->partner->id]);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
            'team_id'    => $teamA->id,
        ]);

        DB::table('team_user')->insert([
            'partner_id' => $this->partner->id,
            'team_id'    => $teamB->id,
            'user_id'    => $student->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pdf = UploadedFile::fake()->create('contract.pdf', 20, 'application/pdf');

        $this->from(route('contracts.index', ['create' => 1]))
            ->post(route('contracts.store'), [
                'creation_mode' => Contract::CREATION_MODE_PDF,
                'user_id'       => $student->id,
                'pdf'           => $pdf,
            ])
            ->assertSessionHasErrors(['group_id']);
    }

    public function test_store_saves_selected_group_when_student_has_multiple_teams(): void
    {
        Storage::fake();

        $teamA = Team::factory()->create(['partner_id' => $this->partner->id]);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
            'team_id'    => $teamA->id,
        ]);

        DB::table('team_user')->insert([
            'partner_id' => $this->partner->id,
            'team_id'    => $teamB->id,
            'user_id'    => $student->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pdf = UploadedFile::fake()->create('contract.pdf', 20, 'application/pdf');

        $this->from(route('contracts.index', ['create' => 1]))
            ->post(route('contracts.store'), [
                'creation_mode' => Contract::CREATION_MODE_PDF,
                'user_id'       => $student->id,
                'group_id'      => $teamB->id,
                'pdf'           => $pdf,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('contracts', [
            'user_id'  => $student->id,
            'group_id' => $teamB->id,
        ]);
    }

    public function test_store_rejects_group_not_belonging_to_student(): void
    {
        Storage::fake();

        $teamA = Team::factory()->create(['partner_id' => $this->partner->id]);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);
        $foreignTeam = Team::factory()->create(['partner_id' => $this->partner->id]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
            'team_id'    => $teamA->id,
        ]);

        DB::table('team_user')->insert([
            'partner_id' => $this->partner->id,
            'team_id'    => $teamB->id,
            'user_id'    => $student->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pdf = UploadedFile::fake()->create('contract.pdf', 20, 'application/pdf');

        $this->from(route('contracts.index', ['create' => 1]))
            ->post(route('contracts.store'), [
                'creation_mode' => Contract::CREATION_MODE_PDF,
                'user_id'       => $student->id,
                'group_id'      => $foreignTeam->id,
                'pdf'           => $pdf,
            ])
            ->assertSessionHasErrors(['group_id']);
    }
}
