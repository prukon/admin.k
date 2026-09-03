<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#school-leads-email-notifications-index совпадает с CRM-модалкой
 * и school-leads-widget §5.1.5 / §7.
 */
final class SchoolLeadEmailNotificationsDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_school_lead_email_notifications(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="school-leads-email-notifications-index"', $html);
        $start = strpos($html, 'id="school-leads-email-notifications-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="ops-day-kpi-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('«Уведомления»', $chunk);
        $this->assertStringContainsString('«Статусы»', $chunk);
        $this->assertStringContainsString('#schoolLeadNotificationsModal', $chunk);
        $this->assertStringContainsString('school_leads_notification_emails', $chunk);
        $this->assertStringContainsString('school_leads_email_notifications_disabled', $chunk);
        $this->assertStringContainsString('emails_configured', $chunk);
        $this->assertStringContainsString('suggested_emails', $chunk);
        $this->assertStringContainsString('/admin/school-leads/notifications', $chunk);
        $this->assertStringContainsString('schoolLeads.view', $chunk);
        $this->assertStringContainsString('PUT …/{schoolLead}', $chunk);
        $this->assertStringContainsString('shown.bs.modal', $chunk);
        $this->assertStringContainsString('KidsCrmGenericMultiselectSelect2.init', $chunk);
        $this->assertStringContainsString('generic-multiselect', $chunk);
        $this->assertStringContainsString('$select.val()', $chunk);
        $this->assertStringContainsString('Не получать email-уведомления', $chunk);
        $this->assertStringContainsString('SchoolLeadNotificationService', $chunk);
        $this->assertStringContainsString('school-leads-widget#school-leads-email-notifications', $chunk);
        $this->assertStringContainsString('school-leads-widget#7', $chunk);
        $this->assertStringContainsString('SchoolLeadNotificationSettingsFeatureTest', $chunk);
        $this->assertStringContainsString('SchoolLeadNotificationSettingsFullAccessFeatureTest', $chunk);
        $this->assertStringContainsString('SchoolLeadNotificationSettingsNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('SchoolLeadNotificationSettingsAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('SchoolLeadNotificationTest', $chunk);
        $this->assertStringContainsString('SchoolLeadLandingNotificationFeatureTest', $chunk);
        $this->assertStringContainsString('SchoolLeadEmailNotificationsDocumentationContractTest', $chunk);
        $this->assertStringContainsString('TELEGRAM_CHAT_ID', $chunk);
        $this->assertStringContainsString('/doc#school-leads-email-notifications-index', $html);
        $this->assertStringContainsString('кнопка «Уведомления»', $html);
    }

    public function test_widget_doc_matches_notification_settings_behavior(): void
    {
        $html = $this->docFile('school-leads-widget.html');

        $this->assertStringContainsString('id="school-leads-email-notifications"', $html);
        $this->assertStringContainsString('/doc#school-leads-email-notifications-index', $html);
        $this->assertStringContainsString('#schoolLeadNotificationsModal', $html);
        $this->assertStringContainsString('KidsCrmGenericMultiselectSelect2', $html);
        $this->assertStringContainsString('generic-multiselect', $html);
        $this->assertStringContainsString('js-generic-multiselect-select', $html);
        $this->assertStringContainsString('school_leads_notification_emails', $html);
        $this->assertStringContainsString('school_leads_email_notifications_disabled', $html);
        $this->assertStringContainsString('UpdateSchoolLeadNotificationSettingsRequest', $html);
        $this->assertStringContainsString('shown.bs.modal', $html);
        $this->assertStringContainsString('$select.val()', $html);
        $this->assertStringContainsString('Не получать email-уведомления', $html);
        $this->assertStringContainsString('кнопкой «Уведомления»', $html);
        $this->assertStringContainsString('id="7"', $html);
        $this->assertStringContainsString('SchoolLeadNotificationSettingsFeatureTest', $html);
        $this->assertStringContainsString('SchoolLeadLandingNotificationFeatureTest', $html);
        $this->assertStringNotContainsString('Email отправляется всем пользователям с ролью', $html);
    }

    public function test_landing_and_admin_users_point_to_the_same_announcement(): void
    {
        $landing = $this->docFile('school-leads-landing.html');
        $this->assertStringContainsString('/doc#school-leads-email-notifications-index', $landing);
        $this->assertStringContainsString('кнопка «Уведомления»', $landing);
        $this->assertStringContainsString('Не зависит от галочки', $landing);

        $users = $this->docFile('admin-users.html');
        $this->assertStringContainsString('/doc#school-leads-email-notifications-index', $users);
        $this->assertStringContainsString('кнопкой «Уведомления»', $users);
        $this->assertStringNotContainsString('уведомлением админам о <b>новой заявке</b>', $users);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
