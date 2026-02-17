<?php

namespace Tests\Feature\Crm\Account;

use App\Models\Contract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountDocumentsGuestTest extends TestCase
{
    use RefreshDatabase;

    private function makeAnyContract(): Contract
    {
        return Contract::create([
            'school_id'       => 1,
            'user_id'         => 1,
            'group_id'        => null,
            'source_pdf_path' => 'documents/2026/02/any.pdf',
            'source_sha256'   => str_repeat('a', 64),
            'provider'        => 'podpislon',
            'status'          => Contract::STATUS_DRAFT,
        ]);
    }

    public function test_guest_redirected_to_login_for_index(): void
    {
        $resp = $this->get(route('account.documents.index'));
        $resp->assertRedirect(route('login'));
    }

    public function test_guest_redirected_to_login_for_requests(): void
    {
        $contract = $this->makeAnyContract();

        $resp = $this->get(route('account.documents.requests', $contract));
        $resp->assertRedirect(route('login'));
    }

    public function test_guest_redirected_to_login_for_download_original(): void
    {
        $contract = $this->makeAnyContract();

        $resp = $this->get(route('account.documents.downloadOriginal', $contract));
        $resp->assertRedirect(route('login'));
    }

    public function test_guest_redirected_to_login_for_download_signed(): void
    {
        $contract = $this->makeAnyContract();

        $resp = $this->get(route('account.documents.downloadSigned', $contract));
        $resp->assertRedirect(route('login'));
    }
}

