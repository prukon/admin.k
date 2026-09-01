<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Jobs\FanOutInAppNotificationJob;
use App\Models\Contract;
use App\Models\InAppNotification;
use App\Models\ParentProfile;
use App\Models\Role;
use App\Models\User;
use App\Services\InAppNotifications\InAppNotificationAudience;
use App\Services\Signatures\Providers\PodpislonProvider;
use App\Services\Signatures\SignatureProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Monolog\Handler\NullHandler;
use Tests\Feature\Crm\InAppNotifications\InAppNotificationsTestCase;

/**
 * Общие фикстуры: договор → signed → in-app уведомление админам школы.
 */
abstract class ContractSignedInAppNotificationTestCase extends InAppNotificationsTestCase
{
    protected const PERM_CONTRACTS_VIEW = 'contracts.view';

    protected const PERM_CONTRACTS_SYNC = 'contracts.sync';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        config([
            'logging.channels.podpislon' => [
                'driver' => 'monolog',
                'handler' => NullHandler::class,
            ],
        ]);
    }

    protected function actingWith2fa(User $user, ?int $partnerId = null): self
    {
        $this->actingAs($user);
        $this->withSession([
            'current_partner' => $partnerId ?? (int) $user->partner_id,
            '2fa:passed' => true,
        ]);

        return $this;
    }

    protected function fanOutLatestEvent(): InAppNotification
    {
        $notification = InAppNotification::query()
            ->where('source', InAppNotification::SOURCE_EVENT)
            ->latest('id')
            ->first();

        $this->assertNotNull($notification);

        (new FanOutInAppNotificationJob((int) $notification->id))
            ->handle(app(InAppNotificationAudience::class));

        $fresh = $notification->fresh();
        $this->assertNotNull($fresh);

        return $fresh;
    }

    protected function eventNotificationCount(): int
    {
        return InAppNotification::query()
            ->where('source', InAppNotification::SOURCE_EVENT)
            ->count();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeSentContract(User $student, array $overrides = []): Contract
    {
        return Contract::create(array_merge([
            'school_id' => (int) $student->partner_id,
            'user_id' => $student->id,
            'group_id' => null,
            'source_pdf_path' => 'documents/2026/01/source.pdf',
            'source_sha256' => str_repeat('c', 64),
            'provider' => 'podpislon',
            'provider_doc_id' => 8000 + random_int(1, 99999),
            'status' => Contract::STATUS_SENT,
            'signed_pdf_path' => null,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeStudent(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'lastname' => 'Комарова',
            'name' => 'Ярослав',
            'is_enabled' => 1,
        ], $attributes));
    }

    protected function attachParent(User $student, array $parentAttrs = []): ParentProfile
    {
        $parent = ParentProfile::factory()->create(array_merge([
            'partner_id' => (int) $student->partner_id,
            'lastname' => 'Иванов',
            'firstname' => 'Иван',
            'middlename' => null,
        ], $parentAttrs));

        $student->parent_id = $parent->id;
        $student->save();

        return $parent;
    }

    protected function grantPermission(User $actor, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => (int) $actor->partner_id,
            'role_id' => (int) $actor->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function grantSyncToAdmin(): User
    {
        $admin = $this->createUserWithRole('admin', $this->partner, [
            'lastname' => 'Школьный',
            'name' => 'Админ',
            'is_enabled' => 1,
        ]);
        $this->grantPermission($admin, self::PERM_CONTRACTS_VIEW);
        $this->grantPermission($admin, self::PERM_CONTRACTS_SYNC);

        return $admin;
    }

    protected function postSignedWebhook(Contract $contract, string $event = 'DOCUMENT_SIGNED')
    {
        Storage::fake();
        $this->mockSignedPdfDownload();

        $fileId = (int) $contract->provider_doc_id;
        $rawNoSig = 'EVENT='.$event.'&FILE_ID='.$fileId.'&COMPANY_ID=456';
        $signature = md5($rawNoSig);
        $rawBody = $rawNoSig.'&SIGNATURE='.$signature;

        return $this->call(
            'POST',
            route('webhooks.podpislon'),
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
            $rawBody
        );
    }

    /**
     * JSON-тело вебхука (второй вход в тот же handle): подпись = md5(http_build_query без SIGNATURE).
     */
    protected function postSignedWebhookJson(Contract $contract, string $event = 'DOCUMENT_SIGNED')
    {
        Storage::fake();
        $this->mockSignedPdfDownload();

        $fields = [
            'EVENT' => $event,
            'FILE_ID' => (string) $contract->provider_doc_id,
            'COMPANY_ID' => '456',
        ];
        $fields['SIGNATURE'] = md5(http_build_query($fields, '', '&', PHP_QUERY_RFC1738));

        return $this->postJson(route('webhooks.podpislon'), $fields);
    }

    protected function postWebhookFormWithoutValidSignature(Contract $contract)
    {
        $fileId = (int) $contract->provider_doc_id;
        $rawBody = 'EVENT=DOCUMENT_SIGNED&FILE_ID='.$fileId.'&COMPANY_ID=456&SIGNATURE=deadbeef';

        return $this->call(
            'POST',
            route('webhooks.podpislon'),
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
            $rawBody
        );
    }

    protected function mockSignedPdfDownload(): void
    {
        $provider = Mockery::mock(PodpislonProvider::class);
        $provider->shouldReceive('downloadSigned')->zeroOrMoreTimes()->andReturn([
            'filename' => 'signed.pdf',
            'content' => 'PDF-SIGNED',
        ]);
        $this->app->instance(PodpislonProvider::class, $provider);
    }

    /**
     * @param  array<string, mixed>  $statusPayload
     */
    protected function mockStatusProvider(array $statusPayload, bool $downloadSigned = true): void
    {
        Storage::fake();
        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('getStatus')->once()->andReturn($statusPayload);
        if ($downloadSigned) {
            $provider->shouldReceive('downloadSigned')->zeroOrMoreTimes()->andReturn([
                'filename' => 'signed.pdf',
                'content' => 'PDF-BY-SYNC',
            ]);
        }
        $this->app->instance(SignatureProvider::class, $provider);
    }

    protected function syncToSigned(Contract $contract, User $admin): void
    {
        $this->grantPermission($admin, self::PERM_CONTRACTS_VIEW);
        $this->grantPermission($admin, self::PERM_CONTRACTS_SYNC);
        $this->mockStatusProvider(['status' => 30, 'status_text' => 'Подписан']);
        $this->actingWith2fa($admin);

        $this->getJson(route('contracts.status', $contract))
            ->assertOk()
            ->assertJsonPath('status', Contract::STATUS_SIGNED)
            ->assertJsonPath('synced', true);
    }

    protected function customStaffRole(): Role
    {
        return $this->createCustomRole($this->partner, 'Сотрудник школы');
    }
}
