<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\InAppNotification;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Договор signed → in-app уведомление админам этой школы (ядро).
 */
final class ContractSignedInAppNotificationFeatureTest extends ContractSignedInAppNotificationTestCase
{
    public function test_webhook_notifies_school_admin_not_trainer_student_or_foreign(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student);

        $admin = $this->createUserWithRole('admin', $this->partner, [
            'lastname' => 'Школьный',
            'name' => 'Админ',
            'is_enabled' => 1,
        ]);
        $trainer = $this->createUserWithRole('trainer', $this->partner, [
            'lastname' => 'Школьный',
            'name' => 'Тренер',
            'is_enabled' => 1,
        ]);
        $foreignAdmin = $this->createUserWithRole('admin', $this->foreignPartner);
        $disabledAdmin = $this->createUserWithRole('admin', $this->partner, [
            'lastname' => 'Выключен',
            'name' => 'Админ',
            'is_enabled' => 0,
        ]);
        $customRole = $this->customStaffRole();
        $customStaff = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $customRole->id,
            'is_enabled' => 1,
        ]);

        Auth::logout();
        $this->postSignedWebhook($contract)
            ->assertOk()
            ->assertJsonPath('ok', true);

        $contract->refresh();
        $this->assertSame(Contract::STATUS_SIGNED, $contract->status);

        $notification = $this->fanOutLatestEvent();

        $this->assertSame(InAppNotification::SOURCE_EVENT, $notification->source);
        $this->assertSame(InAppNotification::CATEGORY_NORMAL, $notification->category);
        $this->assertSame(InAppNotification::TTL_7D, $notification->ttl_preset);
        $this->assertSame('Договор подписан', $notification->title);
        $this->assertSame((int) $student->id, (int) $notification->created_by);
        $this->assertFalse((bool) $notification->is_global);
        $this->assertTrue($notification->partners()->where('partners.id', $this->partner->id)->exists());
        $this->assertFalse($notification->partners()->where('partners.id', $this->foreignPartner->id)->exists());
        $this->assertStringContainsString('Комарова Ярослав. Договор №'.$contract->id.' подписан.', (string) $notification->body);
        $this->assertStringNotContainsString('Родитель:', (string) $notification->body);
        $this->assertStringNotContainsString('<a href', (string) $notification->body);
        $this->assertStringNotContainsString('/client-contracts/', (string) $notification->body);

        $this->assertDatabaseHas('in_app_notification_recipients', [
            'in_app_notification_id' => $notification->id,
            'user_id' => $admin->id,
        ]);
        $this->assertDatabaseMissing('in_app_notification_recipients', [
            'in_app_notification_id' => $notification->id,
            'user_id' => $trainer->id,
        ]);
        $this->assertDatabaseMissing('in_app_notification_recipients', [
            'in_app_notification_id' => $notification->id,
            'user_id' => $student->id,
        ]);
        $this->assertDatabaseMissing('in_app_notification_recipients', [
            'in_app_notification_id' => $notification->id,
            'user_id' => $foreignAdmin->id,
        ]);
        $this->assertDatabaseMissing('in_app_notification_recipients', [
            'in_app_notification_id' => $notification->id,
            'user_id' => $disabledAdmin->id,
        ]);
        $this->assertDatabaseMissing('in_app_notification_recipients', [
            'in_app_notification_id' => $notification->id,
            'user_id' => $customStaff->id,
        ]);

        $this->actingWith2fa($admin);
        $this->get(route('inAppNotifications.index'))
            ->assertOk()
            ->assertSee('Договор подписан', false)
            ->assertSee('Комарова Ярослав. Договор №'.$contract->id.' подписан.', false);

        $this->actingWith2fa($trainer);
        $this->get(route('inAppNotifications.index'))
            ->assertOk()
            ->assertDontSee('Договор подписан', false);

        $this->actingWith2fa($foreignAdmin, (int) $this->foreignPartner->id);
        $this->get(route('inAppNotifications.index'))
            ->assertOk()
            ->assertDontSee('Договор подписан', false);
    }

    public function test_manual_sync_notifies_school_admin(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student);
        $admin = $this->grantSyncToAdmin();
        $trainer = $this->createUserWithRole('trainer');

        $this->syncToSigned($contract, $admin);

        $notification = $this->fanOutLatestEvent();
        $this->assertSame('Договор подписан', $notification->title);
        $this->assertSame(InAppNotification::TTL_7D, $notification->ttl_preset);
        $this->assertDatabaseHas('in_app_notification_recipients', [
            'in_app_notification_id' => $notification->id,
            'user_id' => $admin->id,
        ]);
        $this->assertDatabaseMissing('in_app_notification_recipients', [
            'in_app_notification_id' => $notification->id,
            'user_id' => $trainer->id,
        ]);
        $this->assertDatabaseMissing('in_app_notification_recipients', [
            'in_app_notification_id' => $notification->id,
            'user_id' => $student->id,
        ]);
    }

    public function test_body_with_parent_uses_three_lines(): void
    {
        $student = $this->makeStudent();
        $this->attachParent($student);
        $contract = $this->makeSentContract($student);
        $this->createUserWithRole('admin');

        Auth::logout();
        $this->postSignedWebhook($contract)->assertOk();

        $notification = $this->fanOutLatestEvent();
        $this->assertStringContainsString('Родитель: Иванов Иван', (string) $notification->body);
        $this->assertStringContainsString('Ребёнок: Комарова Ярослав.', (string) $notification->body);
        $this->assertStringContainsString('Договор №'.$contract->id.' подписан.', (string) $notification->body);
        $this->assertStringNotContainsString('Комарова Ярослав. Договор', (string) $notification->body);
        $this->assertStringNotContainsString('<a href', (string) $notification->body);
    }

    public function test_body_with_parent_id_but_empty_name_keeps_single_sentence(): void
    {
        $student = $this->makeStudent();
        $this->attachParent($student, [
            'lastname' => null,
            'firstname' => null,
            'middlename' => null,
        ]);
        $contract = $this->makeSentContract($student);
        $this->createUserWithRole('admin');

        Auth::logout();
        $this->postSignedWebhook($contract)->assertOk();

        $notification = $this->fanOutLatestEvent();
        $this->assertStringContainsString(
            'Комарова Ярослав. Договор №'.$contract->id.' подписан.',
            (string) $notification->body
        );
        $this->assertStringNotContainsString('Родитель:', (string) $notification->body);
        $this->assertStringNotContainsString('Ребёнок:', (string) $notification->body);
    }

    public function test_empty_student_name_falls_back_to_id(): void
    {
        $student = $this->makeStudent([
            'lastname' => '',
            'name' => '',
        ]);
        $contract = $this->makeSentContract($student);
        $this->createUserWithRole('admin');

        Auth::logout();
        $this->postSignedWebhook($contract)->assertOk();

        $notification = $this->fanOutLatestEvent();
        $this->assertStringContainsString(
            'Ученик #'.$student->id.'. Договор №'.$contract->id.' подписан.',
            (string) $notification->body
        );
    }

    public function test_body_escapes_html_in_student_and_parent_names(): void
    {
        $student = $this->makeStudent([
            'lastname' => '<script>alert(1)</script>',
            'name' => 'Иван',
        ]);
        $this->attachParent($student, [
            'lastname' => '<img src=x onerror=alert(1)>',
            'firstname' => 'Иван',
            'middlename' => null,
        ]);
        $contract = $this->makeSentContract($student);
        $this->createUserWithRole('admin');

        Auth::logout();
        $this->postSignedWebhook($contract)->assertOk();

        $notification = $this->fanOutLatestEvent();
        $this->assertStringNotContainsString('<script>', (string) $notification->body);
        $this->assertStringNotContainsString('<img', (string) $notification->body);
        $this->assertStringNotContainsString('<a href', (string) $notification->body);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', (string) $notification->body);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', (string) $notification->body);
        $this->assertStringContainsString('Родитель:', (string) $notification->body);
        $this->assertStringContainsString('Договор №'.$contract->id.' подписан.', (string) $notification->body);
    }

    public function test_second_signed_webhook_does_not_duplicate_notification(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student);
        $this->createUserWithRole('admin');

        Auth::logout();
        $this->postSignedWebhook($contract)->assertOk();
        $this->assertSame(1, $this->eventNotificationCount());

        $this->postSignedWebhook($contract)->assertOk();
        $this->assertSame(1, $this->eventNotificationCount());
    }

    public function test_opened_webhook_does_not_create_notification(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student);
        $this->createUserWithRole('admin');

        Auth::logout();
        $this->postSignedWebhook($contract, 'DOCUMENT_OPENED')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $contract->refresh();
        $this->assertSame(Contract::STATUS_OPENED, $contract->status);
        $this->assertSame(0, $this->eventNotificationCount());
    }

    public function test_pdf_recovery_for_already_signed_does_not_notify(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student, [
            'status' => Contract::STATUS_SIGNED,
            'signed_pdf_path' => null,
        ]);
        $admin = $this->grantSyncToAdmin();

        $this->mockStatusProvider(['status' => '30']);
        $this->actingWith2fa($admin);
        $this->getJson(route('contracts.status', $contract))
            ->assertOk()
            ->assertJsonPath('synced', true);

        $this->assertSame(0, $this->eventNotificationCount());
    }

    public function test_dispatcher_failure_does_not_block_signing(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student);
        config(['in_app_notifications.events.contract_signed.ttl_preset' => 'bogus']);

        Auth::logout();
        $this->postSignedWebhook($contract)
            ->assertOk()
            ->assertJsonPath('ok', true);

        $contract->refresh();
        $this->assertSame(Contract::STATUS_SIGNED, $contract->status);
        $this->assertSame(0, $this->eventNotificationCount());
    }

    public function test_ttl_is_seven_days(): void
    {
        Carbon::setTestNow('2026-09-01 12:00:00');

        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student);
        $this->createUserWithRole('admin');

        Auth::logout();
        $this->postSignedWebhook($contract)->assertOk();

        $notification = InAppNotification::query()
            ->where('source', InAppNotification::SOURCE_EVENT)
            ->latest('id')
            ->first();
        $this->assertNotNull($notification);
        $this->assertSame(InAppNotification::TTL_7D, $notification->ttl_preset);
        $this->assertTrue(
            $notification->expires_at?->equalTo(now()->addDays(7)) ?? false
        );

        Carbon::setTestNow();
    }

    public function test_superadmin_sees_event_for_current_school_only(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student);

        Auth::logout();
        $this->postSignedWebhook($contract)->assertOk();
        $this->fanOutLatestEvent();

        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->get(route('inAppNotifications.index'))
            ->assertOk()
            ->assertSee('Договор подписан', false);

        $this->withSession([
            'current_partner' => $this->foreignPartner->id,
            '2fa:passed' => true,
        ]);
        $this->get(route('inAppNotifications.index'))
            ->assertOk()
            ->assertDontSee('Договор подписан', false);
    }
}
