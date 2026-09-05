<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\ContractEvent;
use App\Models\InAppNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class ContractAnnulRevokedSignedWebhookFeatureTest extends ContractSignedInAppNotificationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $storage = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'kidscrm_storage_annul_'
            . (string) Str::uuid();

        if (!is_dir($storage)) {
            @mkdir($storage, 0777, true);
        }
        @chmod($storage, 0777);

        $appStorage = $storage . DIRECTORY_SEPARATOR . 'app';
        if (!is_dir($appStorage)) {
            @mkdir($appStorage, 0777, true);
        }
        @chmod($appStorage, 0777);

        $this->app->useStoragePath($storage);
        config(['filesystems.disks.local.root' => $appStorage]);
    }
    public function test_signed_webhook_on_revoked_keeps_status_saves_pdf_and_skips_bell(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student, [
            'status' => Contract::STATUS_REVOKED,
            'signed_pdf_path' => null,
            'signed_at' => null,
        ]);
        $this->createUserWithRole('admin', $this->partner, [
            'lastname' => 'Школьный',
            'name' => 'Админ',
            'is_enabled' => 1,
        ]);

        Auth::logout();
        $this->postSignedWebhook($contract)
            ->assertOk()
            ->assertJsonPath('ok', true);

        $contract->refresh();
        $this->assertSame(Contract::STATUS_REVOKED, $contract->status);
        $this->assertNotNull($contract->signed_pdf_path);
        $this->assertNotNull($contract->signed_at);
        Storage::assertExists($contract->signed_pdf_path);

        $this->assertSame(0, $this->eventNotificationCount());
        $this->assertSame(0, (int) InAppNotification::query()->count());

        $this->assertDatabaseHas('contract_events', [
            'contract_id' => $contract->id,
            'type'        => 'signed_after_revoke',
        ]);
        $this->assertDatabaseHas('contract_events', [
            'contract_id' => $contract->id,
            'type'        => 'signed_pdf_saved',
        ]);
    }

    public function test_opened_webhook_on_revoked_does_not_change_status(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student, [
            'status' => Contract::STATUS_REVOKED,
        ]);

        Auth::logout();
        $this->postSignedWebhook($contract, 'DOCUMENT_OPENED')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $contract->refresh();
        $this->assertSame(Contract::STATUS_REVOKED, $contract->status);
        $this->assertSame(0, $this->eventNotificationCount());
    }

    public function test_manual_sync_signed_on_revoked_saves_pdf_keeps_status_skips_bell(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student, [
            'status' => Contract::STATUS_REVOKED,
            'signed_pdf_path' => null,
        ]);
        $admin = $this->grantSyncToAdmin();

        $this->mockStatusProvider(['status' => 30, 'status_text' => 'Подписан']);
        $this->actingWith2fa($admin);

        $this->getJson(route('contracts.status', $contract))
            ->assertOk()
            ->assertJsonPath('status', Contract::STATUS_REVOKED)
            ->assertJsonPath('synced', true);

        $contract->refresh();
        $this->assertSame(Contract::STATUS_REVOKED, $contract->status);
        $this->assertNotNull($contract->signed_pdf_path);
        Storage::assertExists($contract->signed_pdf_path);
        $this->assertSame(0, $this->eventNotificationCount());

        $this->assertTrue(
            ContractEvent::query()
                ->where('contract_id', $contract->id)
                ->where('type', 'signed_after_revoke')
                ->exists()
        );
    }
}
