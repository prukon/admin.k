<?php

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\ContractEvent;
use App\Models\Team;
use App\Models\User;
use App\Models\UserTableSetting;
use Illuminate\Support\Carbon;
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
                'data' => [['id', 'user_name', 'user_lastname', 'team_title', 'user_phone', 'user_email', 'status_label', 'status_badge_class', 'status', 'creation_mode', 'path_title', 'path_steps', 'updated_at']],
            ]);

        $this->assertSame(1, (int)$resp->json('recordsTotal'));
        $ids = collect($resp->json('data'))->pluck('id')->all();
        $this->assertSame([$c1->id], $ids);
        $this->assertSame(Contract::STATUS_DRAFT, $resp->json('data.0.status'));
        $this->assertSame('Черновик', $resp->json('data.0.status_label'));
        $this->assertSame('Путь с готовым PDF', $resp->json('data.0.path_title'));
        $this->assertSame('active', $resp->json('data.0.path_steps.0.state'));
        $this->assertSame(Contract::STATUS_DRAFT, $resp->json('data.0.path_steps.0.key'));
        $this->assertSame('', $resp->json('data.0.updated_at'));
    }

    /** @test */
    public function data_returns_template_path_steps_starting_from_admin_sent(): void
    {
        $student = User::factory()->create(['partner_id' => $this->partner->id, 'is_enabled' => 1]);

        Contract::create([
            'school_id'      => $this->partner->id,
            'user_id'        => $student->id,
            'creation_mode'  => Contract::CREATION_MODE_TEMPLATE,
            'status'         => Contract::STATUS_AWAITING_CLIENT_FILL,
            'provider'       => 'podpislon',
        ]);

        $row = $this->getJson('/client-contracts/data?draw=1&start=0&length=20')
            ->assertOk()
            ->json('data.0');

        $this->assertSame(Contract::CREATION_MODE_TEMPLATE, $row['creation_mode']);
        $this->assertSame('Путь с формой клиенту', $row['path_title']);
        $this->assertSame('admin_sent', $row['path_steps'][0]['key']);
        $this->assertSame('Админ отправил договор родителю', $row['path_steps'][0]['label']);
        $this->assertSame('done', $row['path_steps'][0]['state']);
        $this->assertSame('active', $row['path_steps'][1]['state']);
        $this->assertStringContainsString('письмо', $row['path_steps'][0]['hint']);
        $this->assertStringContainsString('кабинете', $row['path_steps'][0]['hint']);
    }

    /** @test */
    public function data_returns_school_sms_status_labels_for_sent_and_opened(): void
    {
        $student = User::factory()->create(['partner_id' => $this->partner->id, 'is_enabled' => 1]);

        $sent = Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'source_pdf_path' => 'documents/2026/01/sent.pdf',
            'source_sha256'   => str_repeat('c', 64),
            'provider'        => 'podpislon',
            'status'          => Contract::STATUS_SENT,
        ]);
        $opened = Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'source_pdf_path' => 'documents/2026/01/opened.pdf',
            'source_sha256'   => str_repeat('d', 64),
            'provider'        => 'podpislon',
            'status'          => Contract::STATUS_OPENED,
        ]);

        $rows = collect($this->getJson('/client-contracts/data?draw=1&start=0&length=20')
            ->assertOk()
            ->json('data'))
            ->keyBy('id');

        $this->assertSame('Отправлено СМС', $rows[$sent->id]['status_label']);
        $this->assertSame('Открыто СМС', $rows[$opened->id]['status_label']);
        $this->assertSame('Отправлено', Contract::$STATUS_RU[Contract::STATUS_SENT]);
        $this->assertSame('Открыто', Contract::$STATUS_RU[Contract::STATUS_OPENED]);
    }

    /** @test */
    public function data_updated_at_is_last_contract_event_not_contract_row_updated_at(): void
    {
        $student = User::factory()->create(['partner_id' => $this->partner->id, 'is_enabled' => 1]);

        $contract = Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'source_pdf_path' => 'documents/2026/01/events.pdf',
            'source_sha256'   => str_repeat('e', 64),
            'provider'        => 'podpislon',
            'status'          => Contract::STATUS_DRAFT,
        ]);

        DB::table('contracts')->where('id', $contract->id)->update([
            'updated_at' => '2026-01-01 10:00:00',
        ]);

        $this->createContractEvent($contract->id, 'created', '2026-02-01 12:00:00');
        $this->createContractEvent($contract->id, 'sent', '2026-03-15 18:45:01');

        $row = $this->getJson('/client-contracts/data?draw=1&start=0&length=20')
            ->assertOk()
            ->json('data.0');

        $this->assertSame($contract->id, $row['id']);
        $this->assertSame('15.03.2026 18:45:01', $row['updated_at']);
    }

    /** @test */
    public function data_updated_at_follows_journal_order_by_event_id_not_created_at(): void
    {
        $student = User::factory()->create(['partner_id' => $this->partner->id, 'is_enabled' => 1]);

        $contract = Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'source_pdf_path' => 'documents/2026/01/id-order.pdf',
            'source_sha256'   => str_repeat('f', 64),
            'provider'        => 'podpislon',
            'status'          => Contract::STATUS_DRAFT,
        ]);

        $this->createContractEvent($contract->id, 'created', '2026-04-01 10:00:00');
        $this->createContractEvent($contract->id, 'email_sent', '2026-03-01 09:00:00');

        $row = $this->getJson('/client-contracts/data?draw=1&start=0&length=20')
            ->assertOk()
            ->json('data.0');

        $this->assertSame('01.03.2026 09:00:00', $row['updated_at']);
    }

    /** @test */
    public function data_updated_at_matches_top_journal_row_on_contract_card(): void
    {
        $student = User::factory()->create(['partner_id' => $this->partner->id, 'is_enabled' => 1]);

        $contract = Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'source_pdf_path' => 'documents/2026/01/journal.pdf',
            'source_sha256'   => str_repeat('7', 64),
            'provider'        => 'podpislon',
            'status'          => Contract::STATUS_DRAFT,
        ]);

        $this->createContractEvent($contract->id, 'created', '2026-01-02 08:00:00');
        $this->createContractEvent($contract->id, 'sent', '2026-06-07 14:22:33');

        $listAt = $this->getJson('/client-contracts/data?draw=1&start=0&length=20')
            ->assertOk()
            ->json('data.0.updated_at');

        $this->assertSame('07.06.2026 14:22:33', $listAt);

        $this->get('/client-contracts/'.$contract->id)
            ->assertOk()
            ->assertSee('Журнал событий', false)
            ->assertSee('07.06.2026 14:22:33', false)
            ->assertSee('Отправлено СМС', false);
    }

    /** @test */
    public function data_sorts_updated_at_column_by_last_event_with_missing_events_last(): void
    {
        $student = User::factory()->create(['partner_id' => $this->partner->id, 'is_enabled' => 1]);

        $older = Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'source_pdf_path' => 'documents/2026/01/older.pdf',
            'source_sha256'   => str_repeat('1', 64),
            'provider'        => 'podpislon',
            'status'          => Contract::STATUS_DRAFT,
        ]);
        $newer = Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'source_pdf_path' => 'documents/2026/01/newer.pdf',
            'source_sha256'   => str_repeat('2', 64),
            'provider'        => 'podpislon',
            'status'          => Contract::STATUS_DRAFT,
        ]);
        $empty = Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'source_pdf_path' => 'documents/2026/01/empty.pdf',
            'source_sha256'   => str_repeat('3', 64),
            'provider'        => 'podpislon',
            'status'          => Contract::STATUS_DRAFT,
        ]);

        $this->createContractEvent($older->id, 'created', '2026-01-10 08:00:00');
        $this->createContractEvent($newer->id, 'created', '2026-05-20 11:30:00');

        $descIds = collect($this->getJson('/client-contracts/data?draw=1&start=0&length=20&order[0][column]=7&order[0][dir]=desc')
            ->assertOk()
            ->json('data'))
            ->pluck('id')
            ->all();

        $this->assertSame([$newer->id, $older->id, $empty->id], $descIds);

        $ascIds = collect($this->getJson('/client-contracts/data?draw=1&start=0&length=20&order[0][column]=7&order[0][dir]=asc')
            ->assertOk()
            ->json('data'))
            ->pluck('id')
            ->all();

        $this->assertSame([$older->id, $newer->id, $empty->id], $ascIds);
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

    private function createContractEvent(int $contractId, string $type, string $createdAt): ContractEvent
    {
        return ContractEvent::create([
            'contract_id'  => $contractId,
            'author_id'    => $this->user->id,
            'type'         => $type,
            'payload_json' => null,
            'created_at'   => Carbon::parse($createdAt),
            'updated_at'   => Carbon::parse($createdAt),
        ]);
    }
}

