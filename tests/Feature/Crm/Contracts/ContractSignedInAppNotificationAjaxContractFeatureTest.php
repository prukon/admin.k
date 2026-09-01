<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Jobs\FanOutInAppNotificationJob;
use App\Models\Contract;
use App\Models\InAppNotification;
use App\Services\Signatures\SignatureProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Mockery;

/**
 * P1: AJAX-контракт signed → колокольчик: JSON status/bell, errors/message, очередь.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ContractSignedInAppNotificationAjaxContractFeatureTest extends ContractSignedInAppNotificationTestCase
{
    public function test_signed_webhook_queues_fanout_job(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student);
        $this->createUserWithRole('admin');

        Queue::fake();
        Auth::logout();
        $this->postSignedWebhook($contract)
            ->assertOk()
            ->assertJsonPath('ok', true);

        Queue::assertPushed(FanOutInAppNotificationJob::class);
        $this->assertSame(1, $this->eventNotificationCount());
    }

    public function test_pending_event_is_hidden_from_bell_until_worker_runs(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student);
        $admin = $this->createUserWithRole('admin');

        Auth::logout();
        $this->postSignedWebhook($contract)->assertOk();

        $this->assertDatabaseHas('in_app_notifications', [
            'source' => InAppNotification::SOURCE_EVENT,
            'status' => InAppNotification::STATUS_PENDING,
        ]);

        $this->actingWith2fa($admin);
        $this->getJson(route('inAppNotifications.bell'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0)
            ->assertJsonCount(0, 'items');

        $this->get(route('inAppNotifications.index'))
            ->assertOk()
            ->assertDontSee('Договор подписан', false);

        $notification = $this->fanOutLatestEvent();

        $this->getJson(route('inAppNotifications.bell'))
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('items.0.id', $notification->id)
            ->assertJsonPath('items.0.title', 'Договор подписан')
            ->assertJsonPath('items.0.category', InAppNotification::CATEGORY_NORMAL)
            ->assertJsonPath('items.0.is_read', false)
            ->assertJsonStructure([
                'unread_count',
                'items' => [[
                    'id',
                    'title',
                    'body',
                    'body_preview',
                    'category',
                    'category_label',
                    'is_read',
                    'created_at',
                    'created_at_human',
                    'page_url',
                ]],
            ]);

        $item = $this->getJson(route('inAppNotifications.bell'))->json('items.0');
        $this->assertStringContainsString('Комарова Ярослав', (string) $item['body']);
        $this->assertStringContainsString('Договор №'.$contract->id, (string) $item['body']);
        $this->assertStringNotContainsString('<a href', (string) $item['body']);
        $this->assertStringNotContainsString(route('contracts.show', $contract, false), (string) $item['body']);
        $this->assertSame(
            route('inAppNotifications.index', ['n' => $notification->id]),
            $item['page_url']
        );
        $this->assertArrayNotHasKey('action_url', $item);
        $this->assertArrayNotHasKey('open_url', $item);
    }

    public function test_bell_preview_keeps_parent_child_line_breaks_without_html(): void
    {
        $student = $this->makeStudent();
        $this->attachParent($student);
        $contract = $this->makeSentContract($student);
        $admin = $this->createUserWithRole('admin');

        Auth::logout();
        $this->postSignedWebhook($contract)->assertOk();
        $this->fanOutLatestEvent();

        $this->actingWith2fa($admin);
        $item = $this->getJson(route('inAppNotifications.bell'))
            ->assertOk()
            ->json('items.0');

        $preview = (string) $item['body_preview'];
        $this->assertStringContainsString("Родитель: Иванов Иван\n", $preview);
        $this->assertStringContainsString('Ребёнок: Комарова Ярослав.', $preview);
        $this->assertStringNotContainsString('<p>', $preview);
        $this->assertStringNotContainsString('<br', $preview);
        $this->assertStringNotContainsString('<a href', $preview);
        $this->assertLessThanOrEqual(63, mb_strlen($preview));
    }

    public function test_ajax_sync_returns_signed_json_and_creates_notification(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student);
        $admin = $this->grantSyncToAdmin();

        $this->mockStatusProvider(['status' => 30, 'status_text' => 'Подписан']);
        $this->actingWith2fa($admin);

        $this->getJson(route('contracts.status', $contract))
            ->assertOk()
            ->assertJsonPath('synced', true)
            ->assertJsonPath('status', Contract::STATUS_SIGNED)
            ->assertJsonStructure(['status', 'synced', 'raw']);

        $this->assertSame(1, $this->eventNotificationCount());
    }

    public function test_ajax_sync_without_provider_doc_id_returns_422_message_and_no_notification(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student, ['provider_doc_id' => null]);
        $admin = $this->grantSyncToAdmin();

        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('getStatus')->never();
        $this->app->instance(SignatureProvider::class, $provider);

        $this->actingWith2fa($admin);
        $this->getJson(route('contracts.status', $contract))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Договор не связан с Подпислоном (нет provider_doc_id). Сначала отправьте договор на подпись.');

        $this->assertSame(0, $this->eventNotificationCount());
    }

    public function test_ajax_sync_unknown_remote_status_does_not_create_notification(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student);
        $admin = $this->grantSyncToAdmin();

        $this->mockStatusProvider(['status' => 'unexpected-xyz'], false);
        $this->actingWith2fa($admin);

        $this->getJson(route('contracts.status', $contract))
            ->assertOk()
            ->assertJsonPath('synced', false);

        $this->assertSame(0, $this->eventNotificationCount());
        $contract->refresh();
        $this->assertSame(Contract::STATUS_SENT, $contract->status);
    }

    public function test_json_webhook_document_signed_also_creates_notification(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student);
        $this->createUserWithRole('admin');

        Queue::fake();
        Auth::logout();
        $this->postSignedWebhookJson($contract)
            ->assertOk()
            ->assertJsonPath('ok', true);

        Queue::assertPushed(FanOutInAppNotificationJob::class);
        $this->assertSame(1, $this->eventNotificationCount());
        $contract->refresh();
        $this->assertSame(Contract::STATUS_SIGNED, $contract->status);
    }

    public function test_student_and_trainer_bell_do_not_count_admin_only_event(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student);
        $trainer = $this->createUserWithRole('trainer');
        $this->createUserWithRole('admin');

        Auth::logout();
        $this->postSignedWebhook($contract)->assertOk();
        $this->fanOutLatestEvent();

        $this->actingWith2fa($student);
        $this->getJson(route('inAppNotifications.bell'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0)
            ->assertJsonCount(0, 'items');

        $this->actingWith2fa($trainer);
        $this->getJson(route('inAppNotifications.bell'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0)
            ->assertJsonCount(0, 'items');
    }

    public function test_ajax_mark_read_decrements_counter_for_event(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student);
        $admin = $this->createUserWithRole('admin');

        Auth::logout();
        $this->postSignedWebhook($contract)->assertOk();
        $notification = $this->fanOutLatestEvent();

        $this->actingWith2fa($admin);
        $this->postJson(route('inAppNotifications.read', $notification), [])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('unread_count', 0);

        $this->assertDatabaseHas('in_app_notification_reads', [
            'in_app_notification_id' => $notification->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_manual_sync_queues_fanout_job(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student);
        $admin = $this->grantSyncToAdmin();

        Queue::fake();
        $this->syncToSigned($contract, $admin);

        Queue::assertPushed(FanOutInAppNotificationJob::class);
        $this->assertSame(1, $this->eventNotificationCount());
    }
}
