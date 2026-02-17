<?php

namespace Tests\Feature\Crm\Account;

use App\Models\Contract;
use App\Models\ContractSignRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\Crm\CrmTestCase;

class AccountDocumentsTest extends CrmTestCase
{
    private function useTempLocalDiskRoot(): void
    {
        $root = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'kidscrm_test_storage_'
            . (string) Str::uuid();

        if (!is_dir($root)) {
            @mkdir($root, 0777, true);
        }
        @chmod($root, 0777);

        config(['filesystems.disks.local.root' => $root]);
    }

    private function makeContract(array $overrides = []): Contract
    {
        return Contract::create(array_merge([
            'school_id'       => $this->partner->id,
            'user_id'         => $this->user->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/2026/02/contract-' . uniqid() . '.pdf',
            'source_sha256'   => str_repeat('a', 64),
            'provider'        => 'podpislon',
            'provider_doc_id' => null,
            'status'          => Contract::STATUS_DRAFT,
            'signed_pdf_path' => null,
            'signed_at'       => null,
        ], $overrides));
    }

    public function test_index_ok_when_has_view_permission(): void
    {
        $resp = $this->get(route('account.documents.index'));

        $resp->assertStatus(200);
        $resp->assertViewIs('account.index');
        $resp->assertViewHas('activeTab', 'myDocuments');
        $resp->assertViewHasAll(['contracts', 'statusMap', 'currentStatus']);
    }

    public function test_index_forbidden_when_missing_view_permission(): void
    {
        $actor = $this->createUserWithoutPermission('account.documents.view', $this->partner);

        $resp = $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id])
            ->get(route('account.documents.index'));

        $resp->assertStatus(403);
    }

    public function test_index_allows_superadmin_even_without_explicit_permission(): void
    {
        $this->asSuperadmin();

        $resp = $this->get(route('account.documents.index'));

        $resp->assertStatus(200);
    }

    public function test_index_shows_only_current_user_contracts(): void
    {
        $mine = $this->makeContract([
            'source_sha256' => str_repeat('b', 64),
        ]);

        $foreign = Contract::create([
            'school_id'       => $this->foreignPartner->id,
            'user_id'         => $this->foreignUser->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/2026/02/foreign.pdf',
            'source_sha256'   => str_repeat('c', 64),
            'provider'        => 'podpislon',
            'status'          => Contract::STATUS_DRAFT,
        ]);

        $resp = $this->get(route('account.documents.index'));

        $resp->assertStatus(200);
        // Проверяем по data-id (кнопка "История отправок") — так не ловим ложные срабатывания на цифрах в верстке.
        $resp->assertSee('data-id="' . $mine->id . '"', false);
        $resp->assertDontSee('data-id="' . $foreign->id . '"', false);
    }

    public function test_index_filters_by_status(): void
    {
        $signed = $this->makeContract([
            'status' => Contract::STATUS_SIGNED,
            'source_sha256' => str_repeat('d', 64),
        ]);
        $draft = $this->makeContract([
            'status' => Contract::STATUS_DRAFT,
            'source_sha256' => str_repeat('e', 64),
        ]);

        $resp = $this->get(route('account.documents.index', ['status' => Contract::STATUS_SIGNED]));

        $resp->assertStatus(200);
        $resp->assertSee('data-id="' . $signed->id . '"', false);
        $resp->assertDontSee('data-id="' . $draft->id . '"', false);
    }

    public function test_requests_ok_for_owner_and_sorted_desc(): void
    {
        $contract = $this->makeContract();

        $r1 = ContractSignRequest::create([
            'contract_id' => $contract->id,
            'signer_name' => 'Иванов Иван',
            'signer_phone' => '79990000000',
            'ttl_hours' => 72,
            'status' => 'created',
            'signer_lastname' => 'Иванов',
            'signer_firstname' => 'Иван',
            'signer_middlename' => null,
        ]);
        $r2 = ContractSignRequest::create([
            'contract_id' => $contract->id,
            'signer_name' => 'Петров Петр',
            'signer_phone' => '79991111111',
            'ttl_hours' => 72,
            'status' => 'sent',
            'signer_lastname' => 'Петров',
            'signer_firstname' => 'Пётр',
            'signer_middlename' => null,
        ]);

        $resp = $this->get(route('account.documents.requests', $contract));

        $resp->assertStatus(200);
        $resp->assertJsonStructure([
            'requests' => [
                ['id', 'signer', 'phone', 'status', 'badge', 'created'],
            ],
        ]);

        // Проверяем сортировку: по убыванию id (последний созданный первым)
        $resp->assertJsonPath('requests.0.id', $r2->id);
        $resp->assertJsonPath('requests.1.id', $r1->id);
    }

    public function test_requests_returns_404_for_foreign_contract(): void
    {
        $foreign = Contract::create([
            'school_id'       => $this->foreignPartner->id,
            'user_id'         => $this->foreignUser->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/2026/02/foreign.pdf',
            'source_sha256'   => str_repeat('f', 64),
            'provider'        => 'podpislon',
            'status'          => Contract::STATUS_DRAFT,
        ]);

        $resp = $this->get(route('account.documents.requests', $foreign));

        $resp->assertStatus(404);
    }

    public function test_download_original_ok_for_owner(): void
    {
        $this->useTempLocalDiskRoot();

        $contract = $this->makeContract([
            'source_pdf_path' => 'documents/2026/02/me.pdf',
        ]);
        Storage::put('documents/2026/02/me.pdf', 'pdf-bytes');

        $resp = $this->get(route('account.documents.downloadOriginal', $contract));

        $resp->assertStatus(200);
        $resp->assertDownload('contract-' . $contract->id . '.pdf');
    }

    public function test_download_original_returns_404_for_foreign_contract(): void
    {
        $this->useTempLocalDiskRoot();

        $foreign = Contract::create([
            'school_id'       => $this->foreignPartner->id,
            'user_id'         => $this->foreignUser->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/2026/02/foreign.pdf',
            'source_sha256'   => str_repeat('g', 64),
            'provider'        => 'podpislon',
            'status'          => Contract::STATUS_DRAFT,
        ]);
        Storage::put('documents/2026/02/foreign.pdf', 'pdf-bytes');

        $resp = $this->get(route('account.documents.downloadOriginal', $foreign));

        $resp->assertStatus(404);
    }

    public function test_download_signed_404_when_missing_signed_path(): void
    {
        $contract = $this->makeContract([
            'signed_pdf_path' => null,
        ]);

        $resp = $this->get(route('account.documents.downloadSigned', $contract));

        $resp->assertStatus(404);
    }

    public function test_download_signed_ok_for_owner(): void
    {
        $this->useTempLocalDiskRoot();

        $contract = $this->makeContract([
            'signed_pdf_path' => 'documents/2026/02/me-signed.pdf',
            'status' => Contract::STATUS_SIGNED,
        ]);
        Storage::put('documents/2026/02/me-signed.pdf', 'signed-pdf-bytes');

        $resp = $this->get(route('account.documents.downloadSigned', $contract));

        $resp->assertStatus(200);
        $resp->assertDownload('contract-' . $contract->id . '-signed.pdf');
    }

    public function test_routes_do_not_require_current_partner_in_session(): void
    {
        // middleware '2fa' может опираться на session; ставим флаг, но partner не задаём
        $this->flushSession();
        $resp = $this->withSession(['2fa:passed' => true])->get(route('account.documents.index'));
        $resp->assertStatus(200);
    }
}

