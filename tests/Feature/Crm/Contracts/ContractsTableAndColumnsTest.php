<?php

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\Team;
use App\Models\User;
use App\Models\UserTableSetting;
use Illuminate\Support\Facades\DB;

class ContractsTableAndColumnsTest extends ContractsFeatureTestCase
{
    /** @test */
    public function data_returns_only_current_partner_contracts_and_basic_datatables_structure(): void
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'A']);
        $student = User::factory()->create(['partner_id' => $this->partner->id, 'is_enabled' => 1, 'team_id' => $team->id]);

        $c1 = Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'group_id'        => $team->id,
            'source_pdf_path' => 'documents/2026/01/c1.pdf',
            'source_sha256'   => str_repeat('a', 64),
            'provider'        => 'podpislon',
            'status'          => Contract::STATUS_DRAFT,
        ]);

        $foreignStudent = User::factory()->create(['partner_id' => $this->foreignPartner->id, 'is_enabled' => 1]);
        Contract::create([
            'school_id'       => $this->foreignPartner->id,
            'user_id'         => $foreignStudent->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/2026/01/f.pdf',
            'source_sha256'   => str_repeat('b', 64),
            'provider'        => 'podpislon',
            'status'          => Contract::STATUS_DRAFT,
        ]);

        $resp = $this->getJson('/client-contracts/data?draw=1&start=0&length=20');

        $resp->assertStatus(200)
            ->assertJsonStructure([
                'draw',
                'recordsTotal',
                'recordsFiltered',
                'data' => [['id', 'user_name', 'user_lastname', 'team_title', 'user_phone', 'user_email', 'status_label', 'status_badge_class', 'updated_at']],
            ]);

        $this->assertSame(1, (int)$resp->json('recordsTotal'));
        $ids = collect($resp->json('data'))->pluck('id')->all();
        $this->assertSame([$c1->id], $ids);
    }

    /** @test */
    public function columns_settings_get_returns_empty_array_when_missing(): void
    {
        UserTableSetting::where('user_id', $this->user->id)
            ->where('table_key', 'contracts_index')
            ->delete();

        $this->getJson('/client-contracts/columns-settings')
            ->assertStatus(200)
            ->assertExactJson([]);
    }

    /** @test */
    public function columns_settings_post_validates_columns_required_array_with_json_error_format(): void
    {
        $this->postJson('/client-contracts/columns-settings', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['message', 'errors' => ['columns']]);

        $this->postJson('/client-contracts/columns-settings', ['columns' => 'no'])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['message', 'errors' => ['columns']]);
    }

    /** @test */
    public function columns_settings_post_creates_settings_and_normalizes_booleans(): void
    {
        UserTableSetting::where('user_id', $this->user->id)
            ->where('table_key', 'contracts_index')
            ->delete();

        $payload = [
            'columns' => [
                'user_name'  => 'true',
                'user_phone' => 1,
                'user_email' => 'false',
                'updated_at' => 0,
                'any'        => 'on',
                'weird'      => 'abc',
            ],
        ];

        $this->postJson('/client-contracts/columns-settings', $payload)
            ->assertStatus(200)
            ->assertExactJson(['success' => true]);

        $setting = UserTableSetting::where('user_id', $this->user->id)
            ->where('table_key', 'contracts_index')
            ->firstOrFail();

        $this->assertSame([
            'user_name'  => true,
            'user_phone' => true,
            'user_email' => false,
            'updated_at' => false,
            'any'        => true,
            'weird'      => false,
        ], $setting->columns);
    }

    /** @test */
    public function columns_settings_get_returns_empty_array_when_columns_in_db_is_not_array(): void
    {
        DB::table('user_table_settings')->updateOrInsert(
            ['user_id' => $this->user->id, 'table_key' => 'contracts_index'],
            ['columns' => json_encode('not-an-array', JSON_UNESCAPED_UNICODE)]
        );

        $this->getJson('/client-contracts/columns-settings')
            ->assertStatus(200)
            ->assertExactJson([]);
    }
}

