<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\InAppNotification;
use App\Models\User;
use App\Services\Signatures\SignatureProvider;
use Illuminate\Support\Facades\Auth;
use Mockery;

/**
 * Доступ: без sync / чужая школа / гость на sync — без уведомления; webhook без сессии создаёт.
 */
final class ContractSignedInAppNotificationAccessFeatureTest extends ContractSignedInAppNotificationTestCase
{
    public function test_guest_cannot_sync_status_and_does_not_create_notification(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student);
        $this->createUserWithRole('admin');

        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('getStatus')->never();
        $this->app->instance(SignatureProvider::class, $provider);

        Auth::logout();
        $json = $this->getJson(route('contracts.status', $contract));
        $this->assertContains($json->getStatusCode(), [401, 302, 403, 419]);
        $this->assertNotSame(200, $json->getStatusCode());
        $this->assertNotSame(500, $json->getStatusCode());

        $this->assertSame(0, $this->eventNotificationCount());
        $contract->refresh();
        $this->assertSame(Contract::STATUS_SENT, $contract->status);
    }

    public function test_guest_native_get_status_is_denied_and_does_not_create_notification(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student);
        $this->createUserWithRole('admin');

        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('getStatus')->never();
        $this->app->instance(SignatureProvider::class, $provider);

        Auth::logout();
        $web = $this->from(route('login'))->get(route('contracts.status', $contract));
        $this->assertContains($web->getStatusCode(), [401, 302, 403, 419]);
        $this->assertNotSame(200, $web->getStatusCode());
        $this->assertNotSame(500, $web->getStatusCode());

        $this->assertSame(0, $this->eventNotificationCount());
    }

    public function test_trainer_cannot_sync_status_and_does_not_create_notification(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student);
        $trainer = $this->createUserWithRole('trainer');

        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('getStatus')->never();
        $this->app->instance(SignatureProvider::class, $provider);

        $this->actingWith2fa($trainer);
        $this->getJson(route('contracts.status', $contract))->assertForbidden();
        $this->from(route('contracts.show', $contract))
            ->get(route('contracts.status', $contract))
            ->assertForbidden();

        $this->assertSame(0, $this->eventNotificationCount());
    }

    public function test_student_cannot_sync_status_and_does_not_create_notification(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student);

        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('getStatus')->never();
        $this->app->instance(SignatureProvider::class, $provider);

        $this->actingWith2fa($student);
        $this->getJson(route('contracts.status', $contract))->assertForbidden();

        $this->assertSame(0, $this->eventNotificationCount());
    }

    public function test_admin_with_sync_permission_can_sync_and_creates_pending_event(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student);
        $admin = $this->grantSyncToAdmin();

        $this->syncToSigned($contract, $admin);

        $this->assertSame(1, $this->eventNotificationCount());
        $this->assertDatabaseHas('in_app_notifications', [
            'source' => InAppNotification::SOURCE_EVENT,
            'status' => InAppNotification::STATUS_PENDING,
            'title' => 'Договор подписан',
            'created_by' => $student->id,
        ]);
        $contract->refresh();
        $this->assertSame(Contract::STATUS_SIGNED, $contract->status);
    }

    public function test_invalid_webhook_signature_is_403_and_does_not_create_notification(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student);
        $this->createUserWithRole('admin');

        Auth::logout();
        $this->postWebhookFormWithoutValidSignature($contract)
            ->assertStatus(403)
            ->assertJsonPath('error', 'invalid_signature');

        $this->assertSame(0, $this->eventNotificationCount());
        $contract->refresh();
        $this->assertSame(Contract::STATUS_SENT, $contract->status);
    }

    public function test_webhook_for_unknown_file_id_does_not_create_notification(): void
    {
        $this->createUserWithRole('admin');
        $missing = $this->makeSentContract($this->makeStudent(), [
            'provider_doc_id' => 999001,
        ]);
        $missing->delete();

        Auth::logout();
        $rawNoSig = 'EVENT=DOCUMENT_SIGNED&FILE_ID=999001&COMPANY_ID=456';
        $rawBody = $rawNoSig.'&SIGNATURE='.md5($rawNoSig);
        $response = $this->call(
            'POST',
            route('webhooks.podpislon'),
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
            $rawBody
        );

        $response->assertOk()->assertJsonPath('orphan', true);
        $this->assertSame(0, $this->eventNotificationCount());
    }

    public function test_signed_endpoints_never_return_500_or_empty_body(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student);
        $admin = $this->grantSyncToAdmin();

        Auth::logout();
        $webhook = $this->postSignedWebhook($contract);
        $this->assertNotSame(500, $webhook->getStatusCode());
        $this->assertNotSame('', trim((string) $webhook->getContent()));

        $notification = $this->fanOutLatestEvent();

        $this->actingWith2fa($admin);
        foreach ([
            ['GET', route('inAppNotifications.index')],
            ['GET', route('inAppNotifications.bell')],
            ['GET', route('dashboard')],
            ['GET', route('contracts.show', $contract)],
            ['POST', route('inAppNotifications.read', $notification)],
        ] as [$method, $url]) {
            $response = $method === 'GET' ? $this->get($url) : $this->post($url);
            $this->assertNotSame(500, $response->getStatusCode(), "{$method} {$url}");
            $this->assertNotSame('', trim((string) $response->getContent()), "{$method} {$url} пустой");
        }
    }

    public function test_admin_without_sync_permission_gets_403_and_no_notification(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student);
        $admin = $this->createUserWithRole('admin');

        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('getStatus')->never();
        $this->app->instance(SignatureProvider::class, $provider);

        $this->actingWith2fa($admin);
        $this->getJson(route('contracts.status', $contract))->assertForbidden();

        $this->assertSame(0, $this->eventNotificationCount());
    }

    public function test_foreign_partner_contract_sync_is_403_and_no_notification(): void
    {
        $foreignStudent = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => 1,
        ]);
        $foreignContract = $this->makeSentContract($foreignStudent, [
            'school_id' => $this->foreignPartner->id,
        ]);

        $admin = $this->grantSyncToAdmin();
        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('getStatus')->never();
        $this->app->instance(SignatureProvider::class, $provider);

        $this->actingWith2fa($admin);
        $this->getJson(route('contracts.status', $foreignContract))->assertForbidden();

        $this->assertSame(0, $this->eventNotificationCount());
    }

    public function test_unauthenticated_webhook_still_creates_pending_event(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student);
        $this->createUserWithRole('admin');

        Auth::logout();
        $this->postSignedWebhook($contract)
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame(1, $this->eventNotificationCount());
        $this->assertDatabaseHas('in_app_notifications', [
            'source' => InAppNotification::SOURCE_EVENT,
            'status' => InAppNotification::STATUS_PENDING,
            'title' => 'Договор подписан',
        ]);
    }
}
