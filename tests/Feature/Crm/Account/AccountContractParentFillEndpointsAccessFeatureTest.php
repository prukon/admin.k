<?php

namespace Tests\Feature\Crm\Account;

use App\Models\Contract;
use App\Models\ContractSignRequest;
use App\Models\User;
use App\Services\Signatures\SignatureProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\Feature\Crm\Account\Concerns\InteractsWithAccountContractFill;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Контроль доступа к «Мои документы» и заполнению договора родителем (fill / generate / sign).
 */
class AccountContractParentFillEndpointsAccessFeatureTest extends CrmTestCase
{
    use InteractsWithAccountContractFill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useAccountContractFillStorage();
    }

    public function test_guest_is_denied_on_all_parent_fill_endpoints(): void
    {
        $awaiting = $this->makeAwaitingFillContract([
            ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
        ]);
        $draft = $this->makeDraftContractWithPdfForAccess();

        Auth::logout();

        $this->get(route('account.documents.index'))->assertRedirect(route('login'));
        $this->get(route('account.documents.fill', $awaiting))->assertRedirect(route('login'));
        $this->post(route('account.documents.generate', $awaiting), ['fields' => []])->assertRedirect(route('login'));
        $this->post(route('account.documents.sign', $draft), [])->assertRedirect(route('login'));
        $this->get(route('account.documents.requests', $draft))->assertRedirect(route('login'));
        $this->get(route('account.documents.downloadOriginal', $draft))->assertRedirect(route('login'));
        $this->get(route('account.documents.downloadSigned', $draft))->assertRedirect(route('login'));

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('account.documents.fill', $awaiting))
            ->assertUnauthorized();
    }

    public function test_user_without_documents_view_gets_403_on_every_endpoint(): void
    {
        $awaiting = $this->makeAwaitingFillContract([
            ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
        ]);
        $draft = $this->makeDraftContractWithPdfForAccess();

        $actor = $this->createUserWithoutPermission('account.documents.view', $this->partner);
        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];

        $this->actingAs($actor)->withSession($session);

        $this->get(route('account.documents.index'))->assertForbidden();
        $this->get(route('account.documents.index', ['fill' => $awaiting->id]))->assertForbidden();
        $this->get(route('account.documents.fill', $awaiting))->assertForbidden();
        $this->post(route('account.documents.generate', $awaiting), ['fields' => []])->assertForbidden();
        $this->post(route('account.documents.sign', $draft), [])->assertForbidden();
        $this->get(route('account.documents.requests', $draft))->assertForbidden();
        $this->get(route('account.documents.downloadOriginal', $draft))->assertForbidden();
        $this->get(route('account.documents.downloadSigned', $draft))->assertForbidden();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('account.documents.fill', $awaiting))
            ->assertForbidden();
    }

    public function test_owner_with_permission_gets_200_on_documents_index_and_fill_json(): void
    {
        $awaiting = $this->makeAwaitingFillContract([
            ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
            ['key' => 'parent_firstname', 'label' => 'Имя', 'required' => true],
        ]);

        $session = $this->accountDocumentsSession();

        $this->withSession($session)
            ->get(route('account.documents.index'))
            ->assertOk()
            ->assertViewIs('account.index')
            ->assertViewHas('activeTab', 'myDocuments');

        $this->withSession($session)
            ->get(route('account.documents.index', ['fill' => $awaiting->id]))
            ->assertOk();

        $this->withSession($session)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->getJson(route('account.documents.fill', $awaiting))
            ->assertOk()
            ->assertJsonStructure(['title', 'html', 'poll']);
    }

    public function test_owner_can_post_generate_and_sign_with_success_responses(): void
    {
        config(['queue.default' => 'sync']);

        $awaiting = $this->makeAwaitingFillContract([
            ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
            ['key' => 'parent_firstname', 'label' => 'Имя', 'required' => true],
        ]);

        $session = $this->accountDocumentsSession();

        $this->withSession($session)
            ->post(route('account.documents.generate', $awaiting), [
                'fields' => [
                    'parent_lastname'  => 'Иванов',
                    'parent_firstname' => 'Иван',
                ],
            ])
            ->assertRedirect(route('account.documents.index', ['fill' => $awaiting->id]))
            ->assertSessionHas('success');

        $awaiting->refresh();
        $awaiting->update([
            'status'          => Contract::STATUS_DRAFT,
            'source_pdf_path' => 'documents/access-sign-' . uniqid() . '.pdf',
            'source_sha256'   => str_repeat('d', 64),
        ]);
        Storage::disk()->put($awaiting->source_pdf_path, '%PDF-1.4');

        Http::fake(['*' => Http::response([['status' => 15]], 200)]);

        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('send')->once()->andReturnUsing(function (Contract $c) {
            $c->provider_doc_id = 'pkg-parent-access';
            $c->save();

            return ['ok' => true];
        });
        $this->app->instance(SignatureProvider::class, $provider);

        $this->withSession($session)
            ->post(route('account.documents.sign', $awaiting), [
                'signer_lastname'   => 'Иванов',
                'signer_firstname'  => 'Иван',
                'signer_middlename' => 'Иванович',
                'signer_phone'      => '+7 (900) 555-66-77',
            ])
            ->assertRedirect(route('account.documents.index'))
            ->assertSessionHas('success');
    }

    public function test_owner_gets_200_on_requests_and_download_original(): void
    {
        $draft = $this->makeDraftContractWithPdfForAccess();
        $session = $this->accountDocumentsSession();

        $this->withSession($session)
            ->get(route('account.documents.requests', $draft))
            ->assertOk()
            ->assertJsonStructure(['requests']);

        $this->withSession($session)
            ->get(route('account.documents.downloadOriginal', $draft))
            ->assertOk();
    }

    public function test_dedicated_documents_view_user_can_access_all_parent_fill_endpoints(): void
    {
        config(['queue.default' => 'sync']);

        $actor = $this->createUserWithoutPermission('account.documents.view', $this->partner);
        $this->grantPermissionToRoleForPartner($actor->role_id, $this->partner->id, 'account.documents.view');

        $awaiting = $this->makeAwaitingFillContract([
            ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
            ['key' => 'parent_firstname', 'label' => 'Имя', 'required' => true],
        ]);
        $awaiting->update(['user_id' => $actor->id]);
        $awaiting->refresh();

        $session = [
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ];

        $this->actingAs($actor)->withSession($session);

        $this->get(route('account.documents.index'))->assertOk();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('account.documents.fill', $awaiting))
            ->assertOk();

        $this->flushHeaders()
            ->post(route('account.documents.generate', $awaiting), [
            'fields' => [
                'parent_lastname'  => 'Петров',
                'parent_firstname' => 'Пётр',
            ],
        ])->assertRedirect(route('account.documents.index', ['fill' => $awaiting->id]));
    }

    public function test_generate_rejects_missing_required_fields_with_validation_errors(): void
    {
        $awaiting = $this->makeAwaitingFillContract([
            ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
            ['key' => 'parent_firstname', 'label' => 'Имя', 'required' => true],
        ]);

        $this->withSession($this->accountDocumentsSession())
            ->from(route('account.documents.index', ['fill' => $awaiting->id]))
            ->post(route('account.documents.generate', $awaiting), [
                'fields' => [
                    'parent_lastname' => 'Иванов',
                ],
            ])
            ->assertRedirect(route('account.documents.index', ['fill' => $awaiting->id]))
            ->assertSessionHasErrors(['fields.parent_firstname']);
    }

    public function test_fill_json_returns_422_when_contract_not_available_for_fill(): void
    {
        $sent = $this->makeAwaitingFillContract([
            ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
        ]);
        $sent->update(['status' => Contract::STATUS_SENT]);

        $this->withSession($this->accountDocumentsSession())
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('account.documents.fill', $sent))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Договор недоступен для заполнения.');
    }

    public function test_non_owner_gets_404_on_fill_generate_and_sign(): void
    {
        $awaiting = $this->makeAwaitingFillContract([
            ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
        ]);

        $other = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];

        $this->actingAs($other)->withSession($session)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('account.documents.fill', $awaiting))
            ->assertNotFound();

        $this->actingAs($other)->withSession($session)
            ->post(route('account.documents.generate', $awaiting), [
                'fields' => ['parent_lastname' => 'Чужой'],
            ])
            ->assertNotFound();

        $this->actingAs($other)->withSession($session)
            ->post(route('account.documents.sign', $awaiting), [
                'signer_lastname'  => 'Чужой',
                'signer_firstname' => 'Пользователь',
                'signer_phone'     => '+7 (900) 000-00-00',
            ])
            ->assertNotFound();
    }

    public function test_non_ajax_fill_redirects_to_documents_with_fill_query(): void
    {
        $awaiting = $this->makeAwaitingFillContract([
            ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
        ]);

        $this->withSession($this->accountDocumentsSession())
            ->get(route('account.documents.fill', $awaiting))
            ->assertRedirect(route('account.documents.index', ['fill' => $awaiting->id]));
    }

    protected function grantPermissionToRoleForPartner(int $roleId, int $partnerId, string $permissionName): void
    {
        \Illuminate\Support\Facades\DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $partnerId,
            'role_id'       => $roleId,
            'permission_id' => $this->permissionId($permissionName),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private function makeDraftContractWithPdfForAccess(): Contract
    {
        $path = 'documents/parent-access-' . uniqid() . '.pdf';
        Storage::disk()->put($path, '%PDF-1.4');

        $contract = Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $this->user->id,
            'group_id'        => null,
            'source_pdf_path' => $path,
            'source_sha256'   => str_repeat('a', 64),
            'provider'        => 'podpislon',
            'status'          => Contract::STATUS_DRAFT,
        ]);

        ContractSignRequest::create([
            'contract_id'       => $contract->id,
            'signer_name'       => 'Тест Тест',
            'signer_phone'      => '79001234567',
            'signer_lastname'   => 'Тест',
            'signer_firstname'  => 'Тест',
            'signer_middlename' => null,
            'ttl_hours'         => 72,
            'status'            => 'sent',
        ]);

        return $contract;
    }
}
