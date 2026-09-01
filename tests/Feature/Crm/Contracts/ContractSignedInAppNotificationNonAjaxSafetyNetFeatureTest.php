<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\InAppNotification;
use App\Services\Signatures\SignatureProvider;
use Illuminate\Support\Facades\Auth;
use Mockery;

/**
 * P1: native GET sync без X-Requested-With тоже создаёт уведомление (кнопка на карточке — $.ajax GET).
 *
 * UX-регресс: если notify повесить только на ajax-ветку, открытие/синк без заголовка
 * обновит статус, а колокольчик админа останется пустым.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ContractSignedInAppNotificationNonAjaxSafetyNetFeatureTest extends ContractSignedInAppNotificationTestCase
{
    public function test_native_get_status_signs_contract_and_notifies_school_admin(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student);
        $admin = $this->grantSyncToAdmin();
        $this->createUserWithRole('trainer');

        $this->mockStatusProvider(['status' => 30, 'status_text' => 'Подписан']);
        $this->actingWith2fa($admin);

        $response = $this->from(route('contracts.show', $contract))
            ->get(route('contracts.status', $contract));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame('', trim((string) $response->getContent()));
        $response->assertOk()
            ->assertJsonPath('synced', true)
            ->assertJsonPath('status', Contract::STATUS_SIGNED);

        $contract->refresh();
        $this->assertSame(Contract::STATUS_SIGNED, $contract->status);

        $notification = $this->fanOutLatestEvent();
        $this->assertSame(InAppNotification::SOURCE_EVENT, $notification->source);
        $this->assertDatabaseHas('in_app_notification_recipients', [
            'in_app_notification_id' => $notification->id,
            'user_id' => $admin->id,
        ]);
        $this->assertDatabaseMissing('in_app_notification_recipients', [
            'in_app_notification_id' => $notification->id,
            'user_id' => $student->id,
        ]);
    }

    public function test_native_get_status_without_provider_doc_id_returns_422_and_no_notification(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student, ['provider_doc_id' => null]);
        $admin = $this->grantSyncToAdmin();

        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('getStatus')->never();
        $this->app->instance(SignatureProvider::class, $provider);

        $this->actingWith2fa($admin);
        $response = $this->from(route('contracts.show', $contract))
            ->get(route('contracts.status', $contract));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertStatus(422)
            ->assertJsonPath('message', 'Договор не связан с Подпислоном (нет provider_doc_id). Сначала отправьте договор на подпись.');

        $this->assertSame(0, $this->eventNotificationCount());
        $contract->refresh();
        $this->assertSame(Contract::STATUS_SENT, $contract->status);
    }

    public function test_native_html_accept_status_still_notifies_and_is_not_empty(): void
    {
        $student = $this->makeStudent();
        $contract = $this->makeSentContract($student);
        $admin = $this->grantSyncToAdmin();

        $this->mockStatusProvider(['status' => 30, 'status_text' => 'Подписан']);
        $this->actingWith2fa($admin);

        $response = $this->withHeaders(['Accept' => 'text/html'])
            ->get(route('contracts.status', $contract));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame('', trim((string) $response->getContent()));
        $response->assertOk();
        $this->assertSame(Contract::STATUS_SIGNED, $response->json('status'));
        $this->assertSame(1, $this->eventNotificationCount());
    }

    public function test_form_urlencoded_webhook_notifies_like_json_webhook(): void
    {
        $student = $this->makeStudent();
        $formContract = $this->makeSentContract($student, ['provider_doc_id' => 81001]);
        $jsonContract = $this->makeSentContract($student, ['provider_doc_id' => 81002]);
        $this->createUserWithRole('admin');

        Auth::logout();
        $this->postSignedWebhook($formContract)->assertOk()->assertJsonPath('ok', true);
        $this->postSignedWebhookJson($jsonContract)->assertOk()->assertJsonPath('ok', true);

        $this->assertSame(2, $this->eventNotificationCount());
        $formContract->refresh();
        $jsonContract->refresh();
        $this->assertSame(Contract::STATUS_SIGNED, $formContract->status);
        $this->assertSame(Contract::STATUS_SIGNED, $jsonContract->status);
    }
}
