<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Школьный UI /client-contracts: sent/opened как в памятке.
 * $STATUS_RU и кабинет не меняются.
 */
final class ContractSchoolStatusLabelFeatureTest extends ContractsFeatureTestCase
{
    public function test_guest_cannot_see_contracts_index_or_data(): void
    {
        Auth::logout();

        $this->get(route('contracts.index'))->assertStatus(302);
        $this->getJson('/client-contracts/data?draw=1&start=0&length=20')->assertStatus(401);
    }

    public function test_without_contracts_view_index_and_data_are_forbidden(): void
    {
        $actor = $this->createUserWithoutPermission(self::PERM_CONTRACTS_VIEW, $this->partner);

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('contracts.index'))->assertForbidden();
        $this->getJson('/client-contracts/data?draw=1&start=0&length=20')->assertForbidden();
    }

    public function test_index_filter_uses_sms_labels_and_keeps_other_short_labels(): void
    {
        $html = $this->get(route('contracts.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('value="sent">Отправлено СМС', $html);
        $this->assertStringContainsString('value="opened">Открыто СМС', $html);
        $this->assertStringContainsString('value="signed">Подписан', $html);
        $this->assertStringContainsString('value="revoked">Отозван', $html);
        $this->assertStringContainsString('value="awaiting_client_fill">Ожидает заполнения', $html);
        $this->assertStringContainsString('value="draft">Черновик', $html);
        $this->assertStringNotContainsString('value="generating_pdf"', $html);
    }

    public function test_data_returns_school_status_labels_for_sent_and_opened(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        $sent = $this->makeContract($student, Contract::STATUS_SENT);
        $opened = $this->makeContract($student, Contract::STATUS_OPENED);
        $draft = $this->makeContract($student, Contract::STATUS_DRAFT);

        $rows = collect($this->getJson('/client-contracts/data?draw=1&start=0&length=20')
            ->assertOk()
            ->json('data'))
            ->keyBy('id');

        $this->assertSame('Отправлено СМС', $rows[$sent->id]['status_label']);
        $this->assertSame('Открыто СМС', $rows[$opened->id]['status_label']);
        $this->assertSame('Черновик', $rows[$draft->id]['status_label']);
        $this->assertSame(Contract::STATUS_SENT, $rows[$sent->id]['status']);
        $this->assertSame(Contract::STATUS_OPENED, $rows[$opened->id]['status']);
    }

    public function test_show_page_uses_school_status_label_for_sent_and_opened(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        $sent = $this->makeContract($student, Contract::STATUS_SENT);
        $opened = $this->makeContract($student, Contract::STATUS_OPENED);

        $this->get(route('contracts.show', $sent))
            ->assertOk()
            ->assertSee('Отправлено СМС', false);

        $this->get(route('contracts.show', $opened))
            ->assertOk()
            ->assertSee('Открыто СМС', false);

        $this->assertSame('Отправлено', $sent->status_ru);
        $this->assertSame('Открыто', $opened->status_ru);
    }

    public function test_foreign_partner_contract_show_is_forbidden(): void
    {
        $foreignStudent = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'is_enabled' => 1,
        ]);

        $foreign = Contract::create([
            'school_id'       => $this->foreignPartner->id,
            'user_id'         => $foreignStudent->id,
            'source_pdf_path' => 'documents/foreign/sms-label.pdf',
            'source_sha256'   => str_repeat('c', 64),
            'provider'        => 'podpislon',
            'status'          => Contract::STATUS_SENT,
        ]);

        $this->get(route('contracts.show', $foreign))->assertForbidden();
    }

    private function makeContract(User $student, string $status): Contract
    {
        return Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'group_id'        => $student->team_id,
            'source_pdf_path' => 'documents/sms-label/'.uniqid('', true).'.pdf',
            'source_sha256'   => str_repeat('d', 64),
            'provider'        => 'podpislon',
            'status'          => $status,
        ]);
    }
}
