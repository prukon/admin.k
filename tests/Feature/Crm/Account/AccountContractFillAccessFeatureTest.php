<?php

namespace Tests\Feature\Crm\Account;

use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\ContractTemplateVersion;
use App\Services\Signatures\SignatureProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Tests\Feature\Crm\CrmTestCase;
use ZipArchive;

class AccountContractFillAccessFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['contracts.pdf_converter' => 'fake']);

        $root = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'kidscrm_storage_' . (string) Str::uuid();
        @mkdir($root, 0777, true);
        config(['filesystems.disks.local.root' => $root]);

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    public function test_guest_cannot_access_fill_routes(): void
    {
        $contract = $this->makeAwaitingContract();

        Auth::logout();

        $this->get(route('account.documents.fill', $contract))->assertRedirect(route('login'));
        $this->post(route('account.documents.generate', $contract))->assertRedirect(route('login'));
        $this->post(route('account.documents.sign', $contract))->assertRedirect(route('login'));
    }

    public function test_fill_routes_forbidden_without_documents_view_permission(): void
    {
        $contract = $this->makeAwaitingContract();
        $actor = $this->createUserWithoutPermission('account.documents.view', $this->partner);

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->get(route('account.documents.fill', $contract))
            ->assertStatus(403);

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->post(route('account.documents.generate', $contract), ['fields' => []])
            ->assertStatus(403);

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->post(route('account.documents.sign', $contract), [])
            ->assertStatus(403);
    }

    public function test_fill_page_hides_field_keys_without_show_field_keys_permission(): void
    {
        $contract = $this->makeAwaitingContract();

        $html = $this->getContractFillModalHtml($contract);

        $this->assertStringNotContainsString('&#123;&#123;parent_full_name&#125;&#125;', $html);
    }

    public function test_fill_page_shows_field_keys_with_show_field_keys_permission(): void
    {
        $contract = $this->makeAwaitingContract();
        $this->grantPermissionToRoleForPartner(
            (int) $this->user->role_id,
            $this->partner->id,
            'account.contracts.showFieldKeys',
        );

        $html = $this->getContractFillModalHtml($contract);

        $this->assertStringContainsString('&#123;&#123;parent_lastname&#125;&#125;', $html);
        $this->assertStringNotContainsString('&#123;&#123;parent_full_name&#125;&#125;', $html);
    }

    public function test_fill_endpoints_accessible_for_owner_with_permission(): void
    {
        $contract = $this->makeAwaitingContract();

        $this->get(route('account.documents.index'))->assertOk();
        $this->getContractFillModalHtml($contract);

        $this->post(route('account.documents.generate', $contract), [
            'fields' => [
                'parent_lastname'  => 'Иванов',
                'parent_firstname' => 'Иван',
                'parent_middlename' => 'Иванович',
            ],
        ])
            ->assertRedirect(route('account.documents.index', ['fill' => $contract->id]));

        $contract->refresh();
        $contract->update([
            'status'          => Contract::STATUS_DRAFT,
            'source_pdf_path' => 'documents/access-test.pdf',
            'source_sha256'   => str_repeat('d', 64),
        ]);
        Storage::disk()->put($contract->source_pdf_path, '%PDF-1.4');

        Http::fake(['*' => Http::response([['status' => 15]], 200)]);

        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('send')->once()->andReturnUsing(function (Contract $c) {
            $c->provider_doc_id = 'pkg-access-1';
            $c->save();

            return ['ok' => true];
        });
        $this->app->instance(SignatureProvider::class, $provider);

        $this->post(route('account.documents.sign', $contract), [
            'signer_lastname'   => 'Иванов',
            'signer_firstname'  => 'Иван',
            'signer_middlename' => 'Иванович',
            'signer_phone'      => '+7 (900) 333-44-55',
        ])->assertRedirect(route('account.documents.index'));
    }

    public function test_fill_page_shows_awaiting_contract_on_documents_index(): void
    {
        $contract = $this->makeAwaitingContract();

        $this->get(route('account.documents.index'))
            ->assertOk()
            ->assertSee('data-id="' . $contract->id . '"', false);
    }

    public function test_foreign_user_gets_404_on_generate_and_sign(): void
    {
        $contract = $this->makeAwaitingContract();

        $other = \App\Models\User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        $this->actingAs($other)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->post(route('account.documents.generate', $contract), [
                'fields' => ['parent_full_name' => 'Чужой'],
            ])
            ->assertStatus(404);

        $this->actingAs($other)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->post(route('account.documents.sign', $contract), [
                'signer_lastname'  => 'Чужой',
                'signer_firstname' => 'Пользователь',
                'signer_phone'     => '+7 (900) 000-00-00',
            ])
            ->assertStatus(404);
    }

    private function makeAwaitingContract(): Contract
    {
        $docxPath = $this->createDocxOnDisk();

        $template = ContractTemplate::create([
            'partner_id'  => $this->partner->id,
            'title'       => 'Шаблон access',
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

    private function getContractFillModalHtml(Contract $contract): string
    {
        return (string) $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('account.documents.fill', $contract))
            ->assertOk()
            ->json('html');
    }

    protected function grantPermissionToRoleForPartner(int $roleId, int $partnerId, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $partnerId,
            'role_id'       => $roleId,
            'permission_id' => $this->permissionId($permissionName),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private function createDocxOnDisk(): string
    {
        $rel = 'contract-templates/access-' . uniqid() . '.docx';
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
