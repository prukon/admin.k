<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Services\PartnerWidgetService;
use App\Services\SchoolLeadNotificationService;
use Tests\Feature\Crm\CrmTestCase;

/**
 * [P1] AJAX-контракт настроек уведомлений заявок: JSON 200/422, errors по полям,
 * 422 не затирает сохранённый список (UX).
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see SchoolLeadsTableColumnsAjaxContractFeatureTest
 */
final class SchoolLeadNotificationSettingsAjaxContractFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asAdmin();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
        $this->user->email = 'admin-leads@example.test';
        $this->user->save();
        $this->partner->email = 'org-leads@example.test';
        $this->partner->save();
    }

    public function test_ajax_show_prefills_admin_and_org_emails_until_user_saves(): void
    {
        $response = $this->getJson(route('admin.school-leads.notifications.show'))
            ->assertOk()
            ->assertJsonPath('emails_configured', false)
            ->assertJsonPath('email_notifications_disabled', false);

        $emails = $response->json('emails');
        $this->assertContains('admin-leads@example.test', $emails);
        $this->assertContains('org-leads@example.test', $emails);

        $suggested = collect($response->json('suggested_emails'))->pluck('email')->all();
        $this->assertContains('admin-leads@example.test', $suggested);
        $this->assertContains('org-leads@example.test', $suggested);

        $this->partner->refresh();
        $this->assertNull($this->partner->school_leads_notification_emails);
    }

    public function test_ajax_show_after_custom_list_does_not_force_admin_emails(): void
    {
        $this->putJson(route('admin.school-leads.notifications.update'), [
            'emails'                       => ['only-custom@example.test'],
            'email_notifications_disabled' => false,
        ])->assertOk();

        $emails = $this->getJson(route('admin.school-leads.notifications.show'))
            ->assertOk()
            ->assertJsonPath('emails_configured', true)
            ->json('emails');

        $this->assertSame(['only-custom@example.test'], $emails);
        $this->assertNotContains('admin-leads@example.test', $emails);
        $this->assertNotContains('org-leads@example.test', $emails);

        $suggested = collect($this->getJson(route('admin.school-leads.notifications.show'))->json('suggested_emails'))
            ->pluck('email')
            ->all();
        $this->assertContains('admin-leads@example.test', $suggested);
        $this->assertContains('org-leads@example.test', $suggested);
    }

    public function test_ajax_show_when_disabled_still_returns_saved_emails(): void
    {
        $this->putJson(route('admin.school-leads.notifications.update'), [
            'emails'                       => ['keep-visible@example.test'],
            'email_notifications_disabled' => true,
        ])
            ->assertOk();

        $this->getJson(route('admin.school-leads.notifications.show'))
            ->assertOk()
            ->assertJsonPath('emails_configured', true)
            ->assertJsonPath('email_notifications_disabled', true)
            ->assertJsonPath('emails', ['keep-visible@example.test']);
    }

    public function test_ajax_save_returns_success_json_and_normalizes_emails(): void
    {
        $this->putJson(route('admin.school-leads.notifications.update'), [
            'emails'                       => ['Custom.One@Example.TEST', 'second@example.test'],
            'email_notifications_disabled' => 0,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Настройки уведомлений сохранены.')
            ->assertJsonPath('emails', ['custom.one@example.test', 'second@example.test'])
            ->assertJsonPath('email_notifications_disabled', false);

        $this->getJson(route('admin.school-leads.notifications.show'))
            ->assertOk()
            ->assertJsonPath('emails_configured', true)
            ->assertJsonPath('emails', ['custom.one@example.test', 'second@example.test']);
    }

    public function test_ajax_save_without_emails_when_enabled_returns_422_under_emails_and_does_not_wipe(): void
    {
        $this->putJson(route('admin.school-leads.notifications.update'), [
            'emails'                       => ['keep-after-422@example.test'],
            'email_notifications_disabled' => false,
        ])->assertOk();

        $response = $this->putJson(route('admin.school-leads.notifications.update'), [
            'emails'                       => [],
            'email_notifications_disabled' => false,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['emails']);
        $this->assertSame(
            'Укажите хотя бы один email для уведомлений.',
            $response->json('errors.emails.0')
        );

        $this->partner->refresh();
        $this->assertSame(['keep-after-422@example.test'], $this->partner->school_leads_notification_emails);
        $this->assertFalse((bool) $this->partner->school_leads_email_notifications_disabled);
    }

    public function test_ajax_save_with_invalid_email_returns_422_under_that_item(): void
    {
        $this->putJson(route('admin.school-leads.notifications.update'), [
            'emails'                       => ['good@example.test'],
            'email_notifications_disabled' => false,
        ])->assertOk();

        $response = $this->putJson(route('admin.school-leads.notifications.update'), [
            'emails'                       => ['not-an-email'],
            'email_notifications_disabled' => false,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['emails.0']);
        $this->assertSame(
            'Укажите корректный email.',
            $response->json('errors')['emails.0'][0] ?? null
        );

        $this->partner->refresh();
        $this->assertSame(['good@example.test'], $this->partner->school_leads_notification_emails);
    }

    public function test_ajax_save_rejects_more_than_max_emails(): void
    {
        $emails = [];
        for ($i = 1; $i <= SchoolLeadNotificationService::MAX_EMAILS + 1; $i++) {
            $emails[] = 'user'.$i.'@example.test';
        }

        $this->putJson(route('admin.school-leads.notifications.update'), [
            'emails'                       => $emails,
            'email_notifications_disabled' => false,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['emails']);

        $this->partner->refresh();
        $this->assertNull($this->partner->school_leads_notification_emails);
    }

    public function test_ajax_save_accepts_max_emails(): void
    {
        $emails = [];
        for ($i = 1; $i <= SchoolLeadNotificationService::MAX_EMAILS; $i++) {
            $emails[] = 'user'.$i.'@example.test';
        }

        $this->putJson(route('admin.school-leads.notifications.update'), [
            'emails'                       => $emails,
            'email_notifications_disabled' => false,
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->partner->refresh();
        $this->assertCount(SchoolLeadNotificationService::MAX_EMAILS, $this->partner->school_leads_notification_emails);
    }

    public function test_ajax_save_keeps_emails_when_user_disables_notifications(): void
    {
        $this->putJson(route('admin.school-leads.notifications.update'), [
            'emails'                       => ['keep-when-off@example.test', 'second@example.test'],
            'email_notifications_disabled' => false,
        ])->assertOk();

        $this->putJson(route('admin.school-leads.notifications.update'), [
            'emails'                       => ['keep-when-off@example.test', 'second@example.test'],
            'email_notifications_disabled' => true,
        ])
            ->assertOk()
            ->assertJsonPath('email_notifications_disabled', true)
            ->assertJsonPath('emails', ['keep-when-off@example.test', 'second@example.test']);

        $this->getJson(route('admin.school-leads.notifications.show'))
            ->assertOk()
            ->assertJsonPath('emails_configured', true)
            ->assertJsonPath('email_notifications_disabled', true)
            ->assertJsonPath('emails', ['keep-when-off@example.test', 'second@example.test']);
    }

    public function test_ajax_save_allows_empty_list_only_when_disabled(): void
    {
        $this->putJson(route('admin.school-leads.notifications.update'), [
            'emails'                       => [],
            'email_notifications_disabled' => true,
        ])
            ->assertOk()
            ->assertJsonPath('emails', [])
            ->assertJsonPath('email_notifications_disabled', true);

        $this->getJson(route('admin.school-leads.notifications.show'))
            ->assertOk()
            ->assertJsonPath('emails_configured', true)
            ->assertJsonPath('emails', [])
            ->assertJsonPath('email_notifications_disabled', true);
    }

    public function test_duplicate_emails_are_collapsed_before_save(): void
    {
        $this->putJson(route('admin.school-leads.notifications.update'), [
            'emails'                       => ['Same@Example.TEST', 'same@example.test'],
            'email_notifications_disabled' => false,
        ])
            ->assertOk()
            ->assertJsonPath('emails', ['same@example.test']);
    }

    public function test_ajax_save_without_emails_key_when_enabled_returns_422(): void
    {
        $response = $this->putJson(route('admin.school-leads.notifications.update'), [
            'email_notifications_disabled' => false,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['emails']);
        $this->assertSame(
            'Укажите хотя бы один email для уведомлений.',
            $response->json('errors.emails.0')
        );

        $this->partner->refresh();
        $this->assertNull($this->partner->school_leads_notification_emails);
    }
}
