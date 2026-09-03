<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Services\PartnerWidgetService;
use Tests\Feature\Crm\CrmTestCase;

/**
 * [P1] Non-AJAX safety-net настроек уведомлений заявок: PUT без X-Requested-With → 302,
 * запись в БД; AJAX → JSON. Не допускаем пустой 200.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see SchoolLeadUpdateAndStatusesNonAjaxSafetyNetFeatureTest
 */
final class SchoolLeadNotificationSettingsNonAjaxSafetyNetFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asAdmin();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
    }

    public function test_update_non_ajax_redirects_and_persists_emails(): void
    {
        $response = $this->from(route('admin.school-leads'))
            ->put(route('admin.school-leads.notifications.update'), [
                'emails'                       => ['Non.Ajax@Example.TEST'],
                'email_notifications_disabled' => false,
            ]);

        $response->assertRedirect(route('admin.school-leads'));
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());

        $this->partner->refresh();
        $this->assertSame(['non.ajax@example.test'], $this->partner->school_leads_notification_emails);
        $this->assertFalse((bool) $this->partner->school_leads_email_notifications_disabled);
    }

    public function test_native_form_post_with_method_spoof_put_persists_like_toolbar_form(): void
    {
        $response = $this->from(route('admin.school-leads'))
            ->post(route('admin.school-leads.notifications.update'), [
                '_method'                      => 'PUT',
                'emails'                       => ['spoofed-put@example.test'],
                'email_notifications_disabled' => '0',
            ]);

        $response->assertRedirect(route('admin.school-leads'));
        $this->assertNotSame(200, $response->getStatusCode());

        $this->partner->refresh();
        $this->assertSame(['spoofed-put@example.test'], $this->partner->school_leads_notification_emails);
    }

    public function test_non_ajax_unchecked_checkbox_keeps_notifications_enabled(): void
    {
        $response = $this->from(route('admin.school-leads'))
            ->put(route('admin.school-leads.notifications.update'), [
                'emails' => ['native-unchecked@example.test'],
            ]);

        $response->assertRedirect(route('admin.school-leads'));

        $this->partner->refresh();
        $this->assertSame(['native-unchecked@example.test'], $this->partner->school_leads_notification_emails);
        $this->assertFalse((bool) $this->partner->school_leads_email_notifications_disabled);
    }

    public function test_non_ajax_validation_failure_redirects_with_field_errors_and_does_not_wipe(): void
    {
        $this->partner->school_leads_notification_emails = ['keep-me@example.test'];
        $this->partner->school_leads_email_notifications_disabled = false;
        $this->partner->save();

        $this->from(route('admin.school-leads'))
            ->put(route('admin.school-leads.notifications.update'), [
                'emails'                       => [],
                'email_notifications_disabled' => false,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['emails']);

        $this->partner->refresh();
        $this->assertSame(['keep-me@example.test'], $this->partner->school_leads_notification_emails);
        $this->assertFalse((bool) $this->partner->school_leads_email_notifications_disabled);
    }

    public function test_non_ajax_can_disable_without_emails_when_checkbox_on(): void
    {
        $this->from(route('admin.school-leads'))
            ->put(route('admin.school-leads.notifications.update'), [
                'emails'                       => [],
                'email_notifications_disabled' => true,
            ])
            ->assertRedirect(route('admin.school-leads'));

        $this->partner->refresh();
        $this->assertTrue((bool) $this->partner->school_leads_email_notifications_disabled);
        $this->assertSame([], $this->partner->school_leads_notification_emails);
    }

    public function test_ajax_update_returns_json_success_not_redirect(): void
    {
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->putJson(route('admin.school-leads.notifications.update'), [
                'emails'                       => ['ajax-net@example.test'],
                'email_notifications_disabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Настройки уведомлений сохранены.')
            ->assertJsonPath('emails', ['ajax-net@example.test'])
            ->assertJsonPath('email_notifications_disabled', false);

        $this->partner->refresh();
        $this->assertSame(['ajax-net@example.test'], $this->partner->school_leads_notification_emails);
    }

    public function test_get_show_without_ajax_header_still_returns_json_not_empty_200_html(): void
    {
        $response = $this->get(route('admin.school-leads.notifications.show'), [
            'HTTP_ACCEPT' => 'text/html',
        ]);

        $response->assertOk();
        $this->assertNotSame('', trim((string) $response->getContent()));
        $response->assertJsonStructure([
            'emails',
            'emails_configured',
            'email_notifications_disabled',
            'suggested_emails',
        ]);
    }
}
