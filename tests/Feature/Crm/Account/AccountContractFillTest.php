<?php

namespace Tests\Feature\Crm\Account;

use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\ContractTemplateVersion;
use App\Models\User;
use App\Services\Signatures\SignatureProvider;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Tests\Feature\Crm\CrmTestCase;
use ZipArchive;

class AccountContractFillTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['contracts.pdf_converter' => 'fake']);
        config(['queue.default' => 'sync']);

        $root = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'kidscrm_storage_' . (string) Str::uuid();
        @mkdir($root, 0777, true);
        config(['filesystems.disks.local.root' => $root]);
    }

    public function test_fill_page_ok_for_awaiting_contract(): void
    {
        $contract = $this->makeAwaitingContract();

        $html = $this->getContractFillModalHtml($contract);

        $this->assertStringContainsString('Сформировать договор', $html);
        $this->assertStringContainsString('contract-fill-panel--parent', $html);
        $this->assertStringContainsString('name="fields[parent_lastname]"', $html);
        $this->assertStringContainsString('name="fields[parent_firstname]"', $html);
        $this->assertStringNotContainsString('name="fields[parent_full_name]"', $html);
        $this->assertStringNotContainsString('&#123;&#123;parent_full_name&#125;&#125;', $html);
    }

    public function test_fill_direct_url_redirects_to_documents_with_fill_query(): void
    {
        $contract = $this->makeAwaitingContract();

        $this->get(route('account.documents.fill', $contract))
            ->assertRedirect(route('account.documents.index', ['fill' => $contract->id]));
    }

    public function test_generate_creates_pdf_and_sets_draft(): void
    {
        $contract = $this->makeAwaitingContract();

        $resp = $this->post(route('account.documents.generate', $contract), [
            'fields' => [
                'parent_lastname'  => 'Иванов',
                'parent_firstname' => 'Иван',
            ],
        ]);

        $resp->assertRedirect(route('account.documents.index', ['fill' => $contract->id]));
        $resp->assertSessionHas('success');

        $contract->refresh();
        $this->assertSame(Contract::STATUS_DRAFT, $contract->status);
        $this->assertNotNull($contract->source_pdf_path);
        $this->assertSame('Иванов Иван', $contract->filled_data['parent_full_name'] ?? null);
        Storage::disk()->assertExists($contract->source_pdf_path);
    }

    public function test_client_sign_sends_sms_via_provider(): void
    {
        Http::fake(['*' => Http::response([['status' => 15, 'status_text' => 'sent']], 200)]);

        $contract = $this->makeAwaitingContract();
        $contract->update([
            'status'          => Contract::STATUS_DRAFT,
            'source_pdf_path' => 'documents/test.pdf',
            'source_sha256'   => str_repeat('b', 64),
        ]);
        Storage::disk()->put($contract->source_pdf_path, '%PDF-1.4');

        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('send')->once()->andReturnUsing(function (Contract $c) {
            $c->provider_doc_id = 'pkg-client-1';
            $c->save();
            return ['ok' => true];
        });
        $this->app->instance(SignatureProvider::class, $provider);

        $this->post(route('account.documents.sign', $contract), [
            'signer_lastname'   => 'Петров',
            'signer_firstname'  => 'Пётр',
            'signer_middlename' => 'Петрович',
            'signer_phone'      => '+7 (900) 111-22-33',
        ])
            ->assertRedirect(route('account.documents.index'))
            ->assertSessionHas('success');

        $contract->refresh();
        $this->assertSame(Contract::STATUS_SENT, $contract->status);
    }

    public function test_foreign_user_gets_404_on_fill(): void
    {
        $contract = $this->makeAwaitingContract();

        $other = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        $this->actingAs($other)
            ->withSession(['current_partner' => $this->partner->id])
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('account.documents.fill', $contract))
            ->assertStatus(404);
    }

    private function getContractFillModalHtml(Contract $contract): string
    {
        return (string) $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('account.documents.fill', $contract))
            ->assertOk()
            ->json('html');
    }

    private function makeAwaitingContract(): Contract
    {
        $docxPath = $this->createDocxOnDisk();

        $template = ContractTemplate::create([
            'partner_id'  => $this->partner->id,
            'title'       => 'Шаблон для кабинета',
            'is_archived' => false,
        ]);

        $version = ContractTemplateVersion::create([
            'contract_template_id' => $template->id,
            'version'              => 1,
            'docx_path'            => $docxPath,
            'docx_sha256'          => str_repeat('c', 64),
            'fields_schema'        => [
                [
                    'key'            => 'parent_full_name',
                    'label'          => 'ФИО родителя',
                    'required'       => true,
                    'prefill_source' => null,
                ],
            ],
        ]);
        $template->current_version_id = $version->id;
        $template->save();

        return Contract::create([
            'school_id'                    => $this->partner->id,
            'user_id'                      => $this->user->id,
            'group_id'                     => null,
            'creation_mode'                => Contract::CREATION_MODE_TEMPLATE,
            'contract_template_version_id' => $version->id,
            'source_pdf_path'              => null,
            'source_sha256'                => null,
            'status'                       => Contract::STATUS_AWAITING_CLIENT_FILL,
            'fill_expires_at'              => now()->addDays(7),
            'provider'                     => 'podpislon',
        ]);
    }

    private function createDocxOnDisk(): string
    {
        $rel = 'contract-templates/test-' . uniqid() . '.docx';
        $abs = Storage::disk()->path($rel);
        @mkdir(dirname($abs), 0775, true);

        $zip = new ZipArchive();
        $zip->open($abs, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"></Types>');
        $zip->addFromString(
            'word/document.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body><w:p><w:r><w:t>Договор {{parent_full_name}}</w:t></w:r></w:p></w:body></w:document>'
        );
        $zip->close();

        return $rel;
    }
}
