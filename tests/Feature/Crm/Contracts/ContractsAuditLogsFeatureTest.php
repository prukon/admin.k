<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Enums\AuditEvent;
use App\Models\Contract;
use App\Models\ContractSignRequest;
use App\Models\MyLog;
use App\Models\User;
use App\Services\Signatures\SignatureProvider;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Mockery;

final class ContractsAuditLogsFeatureTest extends ContractsFeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['billing.contract_create_fee' => 70.00]);
        $this->partner->wallet_balance = 500;
        $this->partner->save();
    }

    private function latestLog(AuditEvent $event): ?MyLog
    {
        return MyLog::query()
            ->where('partner_id', $this->partner->id)
            ->where('event', $event->value)
            ->latest('id')
            ->first();
    }

    /**
     * @return array<string, string>
     */
    private function validSendPayload(): array
    {
        return [
            'signer_lastname'   => 'Иванов',
            'signer_firstname'  => 'Иван',
            'signer_middlename' => 'Иванович',
            'signer_phone'      => '+7 (900) 111-22-33',
        ];
    }

    public function test_logs_data_returns_200_with_contracts_view(): void
    {
        $this->getJson(route('logs.data.contract', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
    }

    public function test_logs_data_returns_403_without_contracts_view(): void
    {
        $actor = $this->createUserWithoutPermission(self::PERM_CONTRACTS_VIEW, $this->partner);
        $this->actingAs($actor);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->getJson(route('logs.data.contract', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertStatus(403);
    }

    public function test_index_renders_history_button_with_contracts_view(): void
    {
        $this->get(route('contracts.index'))
            ->assertOk()
            ->assertSee('historyModal', false)
            ->assertSee('История', false)
            ->assertSee('showLogModal', false);
    }

    public function test_store_writes_contract_created_log(): void
    {
        Storage::fake();

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        $pdf = UploadedFile::fake()->create('audit-contract.pdf', 20, 'application/pdf');

        $this->post(route('contracts.store'), [
            'creation_mode' => Contract::CREATION_MODE_PDF,
            'user_id'       => $student->id,
            'pdf'           => $pdf,
        ])->assertRedirect();

        $contractId = (int) Contract::query()->where('user_id', $student->id)->value('id');
        $this->assertGreaterThan(0, $contractId);

        $log = $this->latestLog(AuditEvent::ContractCreated);

        $this->assertNotNull($log);
        $this->assertSame(AuditEvent::ContractCreated->level(), $log->level);
        $this->assertStringContainsString('Договор создан: № '.$contractId, (string) $log->description);
        $this->assertSame((int) $student->id, (int) $log->user_id);
    }

    public function test_revoke_awaiting_client_fill_writes_contract_revoked_log_with_refund(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        $contract = Contract::create([
            'school_id'                    => $this->partner->id,
            'user_id'                      => $student->id,
            'group_id'                     => null,
            'creation_mode'                => Contract::CREATION_MODE_TEMPLATE,
            'contract_template_version_id' => null,
            'source_pdf_path'              => null,
            'source_sha256'                => null,
            'status'                       => Contract::STATUS_AWAITING_CLIENT_FILL,
            'fill_expires_at'              => now()->addDays(7),
            'provider'                     => 'podpislon',
        ]);

        $this->postJson(route('contracts.revoke', $contract))
            ->assertOk()
            ->assertJsonPath('status', 'revoked');

        $log = $this->latestLog(AuditEvent::ContractRevoked);

        $this->assertNotNull($log);
        $this->assertSame(AuditEvent::ContractRevoked->level(), $log->level);
        $this->assertStringContainsString('Возврат 70 ₽: Да', (string) $log->description);
    }

    public function test_revoke_sent_contract_writes_contract_revoked_log_without_refund(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        $contract = Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/audit/revoke-sent.pdf',
            'source_sha256'   => str_repeat('r', 64),
            'provider'        => 'podpislon',
            'provider_doc_id' => 'pkg-revoke-sent',
            'status'          => Contract::STATUS_SENT,
        ]);

        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('revoke')->once()->andReturn(['ok' => true]);
        $this->app->instance(SignatureProvider::class, $provider);

        $this->postJson(route('contracts.revoke', $contract))
            ->assertOk()
            ->assertJsonPath('status', 'revoked');

        $log = $this->latestLog(AuditEvent::ContractRevoked);

        $this->assertNotNull($log);
        $this->assertStringContainsString('Возврат 70 ₽: Нет', (string) $log->description);
    }

    public function test_send_email_writes_contract_email_sent_log(): void
    {
        Storage::fake();

        $contract = $this->createDraftContractForAudit();
        Storage::put($contract->source_pdf_path, $this->minimalDocxBytes());

        Mail::fake();

        $this->postJson(route('contracts.sendEmail', $contract), [
            'email'  => 'audit-contract@example.test',
            'signed' => false,
        ])->assertOk();

        $log = $this->latestLog(AuditEvent::ContractEmailSent);

        $this->assertNotNull($log);
        $this->assertStringContainsString('audit-contract@example.test', (string) $log->description);
        $this->assertStringContainsString('исходный PDF', (string) $log->description);
    }

    public function test_send_writes_contract_sign_request_created_and_sign_sent_success_logs(): void
    {
        Storage::fake();

        $contract = $this->createDraftContractForAudit();
        Storage::put($contract->source_pdf_path, $this->minimalDocxBytes());

        $this->mockSuccessfulSend();

        $this->postJson(route('contracts.send', $contract), $this->validSendPayload())
            ->assertOk();

        $requestLog = MyLog::query()
            ->where('partner_id', $this->partner->id)
            ->where('event', AuditEvent::ContractSignRequestCreated->value)
            ->latest('id')
            ->first();
        $this->assertNotNull($requestLog);
        $this->assertStringContainsString('Запрос на подпись создан', (string) $requestLog->description);
        $this->assertStringContainsString('Иванов', (string) $requestLog->description);

        $successLog = $this->latestLog(AuditEvent::ContractSignSentSuccess);
        $this->assertNotNull($successLog);
        $this->assertStringContainsString('created" → "sent"', (string) $successLog->description);
    }

    public function test_send_failure_writes_contract_sign_sent_failed_log(): void
    {
        Storage::fake();

        $contract = $this->createDraftContractForAudit();
        Storage::put($contract->source_pdf_path, $this->minimalDocxBytes());

        Http::fake([
            '*' => Http::response([], 200),
        ]);

        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('send')
            ->andReturnUsing(function (Contract $c) {
                $c->provider_doc_id = 'pkg-audit-fail';
                $c->save();

                return ['ok' => true];
            });
        $this->app->instance(SignatureProvider::class, $provider);

        $this->postJson(route('contracts.send', $contract), $this->validSendPayload())
            ->assertStatus(422);

        $log = $this->latestLog(AuditEvent::ContractSignSentFailed);

        $this->assertNotNull($log);
        $this->assertStringContainsString('created" → "failed"', (string) $log->description);
    }

    public function test_resend_success_writes_contract_sign_resent_success_log(): void
    {
        Http::fake([
            '*repeat-send*' => Http::response(['status' => true], 200),
            '*'             => Http::response([
                [
                    'status'      => 15,
                    'status_text' => 'sent',
                ],
            ], 200),
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        $contract = Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/audit/resend.pdf',
            'source_sha256'   => str_repeat('s', 64),
            'provider'        => 'podpislon',
            'provider_doc_id' => 'pkg-resend-audit',
            'status'          => Contract::STATUS_SENT,
        ]);

        ContractSignRequest::create([
            'contract_id'  => $contract->id,
            'signer_name'  => 'Иванов Иван',
            'signer_phone' => '79001112233',
            'ttl_hours'    => 72,
            'status'       => 'sent',
        ]);

        $this->postJson(route('contracts.resend', $contract), [])
            ->assertOk();

        $log = $this->latestLog(AuditEvent::ContractSignResentSuccess);

        $this->assertNotNull($log);
        $this->assertStringContainsString('sent', (string) $log->description);
    }

    public function test_logs_data_returns_written_contract_event_in_table(): void
    {
        Storage::fake();

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        $contract = Contract::create([
            'school_id'                    => $this->partner->id,
            'user_id'                      => $student->id,
            'group_id'                     => null,
            'creation_mode'                => Contract::CREATION_MODE_TEMPLATE,
            'contract_template_version_id' => null,
            'source_pdf_path'              => null,
            'source_sha256'                => null,
            'status'                       => Contract::STATUS_AWAITING_CLIENT_FILL,
            'fill_expires_at'              => now()->addDays(7),
            'provider'                     => 'podpislon',
        ]);

        $this->postJson(route('contracts.revoke', $contract))->assertOk();

        $descriptions = collect($this->getJson(route('logs.data.contract', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 50,
        ]))->json('data'))->pluck('description')->all();

        $this->assertTrue(
            collect($descriptions)->contains(fn (string $d): bool => str_contains($d, 'Возврат 70 ₽: Да')),
            'Ожидалась запись contract.revoked в logs-data.'
        );
    }

    private function createDraftContractForAudit(): Contract
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        return Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'group_id'        => $student->team_id,
            'source_pdf_path' => 'documents/audit/' . uniqid('', true) . '.pdf',
            'source_sha256'   => str_repeat('a', 64),
            'provider'        => 'podpislon',
            'status'          => Contract::STATUS_DRAFT,
        ]);
    }

    private function mockSuccessfulSend(): void
    {
        Http::fake([
            '*' => Http::response([
                [
                    'status'      => 15,
                    'status_text' => 'sent',
                ],
            ], 200),
        ]);

        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('send')
            ->andReturnUsing(function (Contract $contract) {
                $contract->provider_doc_id = 'pkg-audit-send';
                $contract->save();

                return ['ok' => true];
            });
        $this->app->instance(SignatureProvider::class, $provider);
    }
}
