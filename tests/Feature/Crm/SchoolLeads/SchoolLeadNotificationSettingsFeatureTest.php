<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Mail\NewSchoolLeadSubmission;
use App\Models\Role;
use App\Models\SchoolLead;
use App\Models\User;
use App\Services\PartnerWidgetService;
use App\Services\SchoolLeadNotificationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Настройки email-уведомлений по заявкам: модалка, API, валидация, отправка.
 */
final class SchoolLeadNotificationSettingsFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asAdmin();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
        $this->partner->refresh();
    }

    public function test_index_renders_statuses_and_notifications_toolbar_buttons(): void
    {
        $html = $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('id="schoolLeadNotificationsModal"', false)
            ->assertSee('id="schoolLeadNotificationEmailsField"', false)
            ->assertSee('id="schoolLeadNotificationEmails"', false)
            ->assertSee('Не получать email-уведомления', false)
            ->assertSee('>Статусы</span>', false)
            ->assertSee('>Уведомления</span>', false)
            ->assertDontSee('>Настройки</span>', false)
            ->getContent();

        $this->assertStringContainsString('data-bs-target="#schoolLeadNotificationsModal"', $html);
        $this->assertSame(1, substr_count($html, 'data-bs-target="#schoolLeadNotificationsModal"'));
        $this->assertMatchesRegularExpression(
            '/id="schoolLeadNotificationsModal"[^>]*>\s*<div class="modal-dialog">/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/id="schoolLeadNotificationsModal"[\s\S]{0,300}modal-(xl|fullscreen)/',
            $html
        );
        $this->assertStringContainsString('name="emails[]"', $html);
        $this->assertStringContainsString('generic-multiselect-field', $html);
        $this->assertStringContainsString('generic-multiselect-field--tags', $html);
        $this->assertStringContainsString('js-generic-multiselect-select', $html);
        $this->assertStringContainsString('KidsCrmGenericMultiselectSelect2.init', $html);
        $this->assertStringContainsString('name="_method"', $html);
        $this->assertStringContainsString('value="PUT"', $html);
        $this->assertStringContainsString('id="schoolLeadEmailNotificationsDisabled"', $html);
        $this->assertStringContainsString('name="email_notifications_disabled"', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/id="schoolLeadEmailNotificationsDisabled"[^>]*\bchecked\b/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/id="schoolLeadNotificationEmails"[^>]*\bdisabled\b/',
            $html
        );

        $selectStart = strpos($html, 'id="schoolLeadNotificationEmails"');
        $this->assertNotFalse($selectStart);
        $selectChunk = substr($html, $selectStart, 500);
        $this->assertStringNotContainsString('<option', $selectChunk);

        $emailsPos = strpos($html, 'id="schoolLeadNotificationEmails"');
        $checkboxPos = strpos($html, 'id="schoolLeadEmailNotificationsDisabled"');
        $this->assertNotFalse($emailsPos);
        $this->assertNotFalse($checkboxPos);
        $this->assertLessThan($emailsPos, $checkboxPos);
    }

    public function test_show_returns_legacy_admin_and_org_emails_until_configured(): void
    {
        $this->partner->email = 'org-leads@example.test';
        $this->partner->save();
        $this->user->email = 'admin-leads@example.test';
        $this->user->save();

        $this->getJson(route('admin.school-leads.notifications.show'))
            ->assertOk()
            ->assertJsonPath('emails_configured', false)
            ->assertJsonPath('email_notifications_disabled', false)
            ->assertJsonFragment(['email' => 'admin-leads@example.test'])
            ->assertJsonFragment(['email' => 'org-leads@example.test']);

        $emails = $this->getJson(route('admin.school-leads.notifications.show'))->json('emails');
        $this->assertContains('admin-leads@example.test', $emails);
        $this->assertContains('org-leads@example.test', $emails);
    }

    public function test_show_omits_admins_and_organization_without_valid_email(): void
    {
        $adminRoleId = Role::query()->where('name', 'admin')->value('id');
        $this->assertNotNull($adminRoleId);

        User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $adminRoleId,
            'name' => 'Безпочты',
            'lastname' => 'Админ',
            'email' => null,
        ]);

        $this->user->email = 'admin-leads@example.test';
        $this->user->save();
        $this->partner->email = '';
        $this->partner->save();

        $payload = $this->getJson(route('admin.school-leads.notifications.show'))
            ->assertOk()
            ->json();

        $suggested = collect($payload['suggested_emails']);
        $this->assertContains('admin-leads@example.test', $suggested->pluck('email')->all());
        foreach ($suggested as $item) {
            $this->assertNotSame('', (string) ($item['email'] ?? ''));
            $this->assertNotFalse(filter_var($item['email'], FILTER_VALIDATE_EMAIL));
            $this->assertStringContainsString($item['email'], $item['label']);
            $this->assertStringNotContainsString('Организация —', $item['label']);
        }
        $this->assertContains('admin-leads@example.test', $payload['emails']);
        $this->assertNotContains('', $payload['emails']);
        $this->assertNotContains(null, $payload['emails']);
        $this->assertNotContains('-', $payload['emails']);

        $this->partner->email = '-';
        $this->partner->save();

        $labels = collect($this->getJson(route('admin.school-leads.notifications.show'))->json('suggested_emails'))
            ->pluck('label')
            ->all();
        foreach ($labels as $label) {
            $this->assertStringNotContainsString('Организация —', $label);
        }
    }

    public function test_update_saves_custom_emails_and_show_returns_them(): void
    {
        $this->putJson(route('admin.school-leads.notifications.update'), [
            'emails' => ['Custom.One@Example.TEST', 'second@example.test'],
            'email_notifications_disabled' => false,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Настройки уведомлений сохранены.')
            ->assertJsonPath('emails', ['custom.one@example.test', 'second@example.test'])
            ->assertJsonPath('email_notifications_disabled', false);

        $this->partner->refresh();
        $this->assertSame(
            ['custom.one@example.test', 'second@example.test'],
            $this->partner->school_leads_notification_emails
        );
        $this->assertFalse((bool) $this->partner->school_leads_email_notifications_disabled);

        $this->getJson(route('admin.school-leads.notifications.show'))
            ->assertOk()
            ->assertJsonPath('emails_configured', true)
            ->assertJsonPath('emails', ['custom.one@example.test', 'second@example.test']);
    }

    public function test_update_requires_at_least_one_email_when_notifications_enabled(): void
    {
        $this->putJson(route('admin.school-leads.notifications.update'), [
            'emails' => [],
            'email_notifications_disabled' => false,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['emails']);

        $response = $this->putJson(route('admin.school-leads.notifications.update'), [
            'emails' => [],
            'email_notifications_disabled' => false,
        ]);
        $this->assertSame(
            'Укажите хотя бы один email для уведомлений.',
            $response->json('errors.emails.0')
        );
    }

    public function test_update_rejects_invalid_email_with_field_error(): void
    {
        $response = $this->putJson(route('admin.school-leads.notifications.update'), [
            'emails' => ['not-an-email'],
            'email_notifications_disabled' => false,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['emails.0']);
        $this->assertSame(
            'Укажите корректный email.',
            $response->json('errors')['emails.0'][0] ?? null
        );
    }

    public function test_update_allows_empty_emails_when_disabled(): void
    {
        $this->putJson(route('admin.school-leads.notifications.update'), [
            'emails' => [],
            'email_notifications_disabled' => true,
        ])
            ->assertOk()
            ->assertJsonPath('email_notifications_disabled', true)
            ->assertJsonPath('emails', []);

        $this->partner->refresh();
        $this->assertTrue((bool) $this->partner->school_leads_email_notifications_disabled);
        $this->assertSame([], $this->partner->school_leads_notification_emails);
    }

    public function test_submit_sends_only_configured_emails(): void
    {
        Mail::fake();

        $this->partner->email = 'org-should-not-receive@example.test';
        $this->partner->school_leads_notification_emails = ['only-this@example.test'];
        $this->partner->school_leads_email_notifications_disabled = false;
        $this->partner->save();

        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Настройка',
            'phone'                 => '+7 900 111-22-33',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        app(SchoolLeadNotificationService::class)->notify($lead);

        Mail::assertSent(NewSchoolLeadSubmission::class, function (NewSchoolLeadSubmission $mail) {
            return $mail->hasTo('only-this@example.test');
        });
        Mail::assertNotSent(NewSchoolLeadSubmission::class, function (NewSchoolLeadSubmission $mail) {
            return $mail->hasTo('org-should-not-receive@example.test')
                || $mail->hasTo((string) $this->user->email);
        });
    }

    public function test_submit_skips_email_when_disabled(): void
    {
        Mail::fake();

        $this->partner->school_leads_notification_emails = ['keep@example.test'];
        $this->partner->school_leads_email_notifications_disabled = true;
        $this->partner->save();

        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Выкл',
            'phone'                 => '+7 900 111-22-34',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        app(SchoolLeadNotificationService::class)->notify($lead);

        Mail::assertNothingSent();
    }

    public function test_disabling_email_does_not_stop_telegram(): void
    {
        Mail::fake();
        config(['services.telegram.bot_token' => 'test-bot-token']);
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $this->partner->school_leads_notification_emails = ['keep@example.test'];
        $this->partner->school_leads_email_notifications_disabled = true;
        $this->partner->school_leads_telegram_chat_id = '-100111222333';
        $this->partner->save();

        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Телеграм',
            'phone'                 => '+7 900 111-22-37',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        app(SchoolLeadNotificationService::class)->notify($lead);

        Mail::assertNothingSent();
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.telegram.org')
                && $request['chat_id'] === '-100111222333'
                && str_contains($request['text'], 'Телеграм');
        });
    }

    public function test_never_saved_settings_still_mail_admins_on_new_lead(): void
    {
        Mail::fake();

        $this->partner->email = 'legacy-org@example.test';
        $this->partner->school_leads_notification_emails = null;
        $this->partner->school_leads_email_notifications_disabled = false;
        $this->partner->save();
        $this->user->email = 'legacy-admin@example.test';
        $this->user->save();

        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Legacy',
            'phone'                 => '+7 900 111-22-35',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        app(SchoolLeadNotificationService::class)->notify($lead);

        Mail::assertSent(NewSchoolLeadSubmission::class, function (NewSchoolLeadSubmission $mail) {
            return $mail->hasTo('legacy-admin@example.test');
        });
        Mail::assertSent(NewSchoolLeadSubmission::class, function (NewSchoolLeadSubmission $mail) {
            return $mail->hasTo('legacy-org@example.test');
        });
    }

    public function test_empty_saved_list_without_legacy_fallback_sends_nobody(): void
    {
        Mail::fake();

        $this->partner->email = 'should-not-fallback@example.test';
        $this->partner->school_leads_notification_emails = [];
        $this->partner->school_leads_email_notifications_disabled = false;
        $this->partner->save();

        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Пустой список',
            'phone'                 => '+7 900 111-22-36',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        app(SchoolLeadNotificationService::class)->notify($lead);

        Mail::assertNothingSent();
    }

    public function test_update_non_ajax_redirects_and_persists(): void
    {
        $response = $this->from(route('admin.school-leads'))
            ->put(route('admin.school-leads.notifications.update'), [
                'emails' => ['non-ajax@example.test'],
                'email_notifications_disabled' => false,
            ]);

        $response->assertRedirect(route('admin.school-leads'));
        $this->assertNotSame(200, $response->getStatusCode());

        $this->partner->refresh();
        $this->assertSame(['non-ajax@example.test'], $this->partner->school_leads_notification_emails);
    }

    public function test_update_non_ajax_validation_failure_redirects_with_errors(): void
    {
        $this->from(route('admin.school-leads'))
            ->put(route('admin.school-leads.notifications.update'), [
                'emails' => [],
                'email_notifications_disabled' => false,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['emails']);
    }

    public function test_notifications_update_route_is_registered_before_school_lead_parameter_route(): void
    {
        $notificationsRoute = Route::getRoutes()->getByName('admin.school-leads.notifications.update');
        $updateRoute = Route::getRoutes()->getByName('admin.school-leads.update');

        $this->assertNotNull($notificationsRoute);
        $this->assertNotNull($updateRoute);
        $this->assertStringContainsString('notifications', $notificationsRoute->uri());
        $this->assertLessThan(
            $this->routeRegistrationIndex('admin.school-leads.update'),
            $this->routeRegistrationIndex('admin.school-leads.notifications.update'),
            'Маршрут notifications должен регистрироваться раньше {schoolLead}, иначе PUT даст 404.'
        );
    }

    public function test_foreign_partner_settings_are_isolated(): void
    {
        $adminRoleId = (int) Role::query()->where('name', 'admin')->value('id');
        $this->foreignUser->role_id = $adminRoleId;
        $this->foreignUser->save();

        $this->putJson(route('admin.school-leads.notifications.update'), [
            'emails' => ['this-partner@example.test'],
            'email_notifications_disabled' => true,
        ])->assertOk();

        $this->asForeignUser();

        $this->putJson(route('admin.school-leads.notifications.update'), [
            'emails' => ['other-partner@example.test'],
            'email_notifications_disabled' => false,
        ])->assertOk();

        $this->partner->refresh();
        $this->foreignPartner->refresh();

        $this->assertSame(['this-partner@example.test'], $this->partner->school_leads_notification_emails);
        $this->assertTrue((bool) $this->partner->school_leads_email_notifications_disabled);
        $this->assertSame(['other-partner@example.test'], $this->foreignPartner->school_leads_notification_emails);
        $this->assertFalse((bool) $this->foreignPartner->school_leads_email_notifications_disabled);
        unset($adminRoleId);
    }

    private function routeRegistrationIndex(string $routeName): int
    {
        $index = 0;
        foreach (Route::getRoutes() as $route) {
            if ($route->getName() === $routeName) {
                return $index;
            }
            $index++;
        }

        $this->fail("Маршрут {$routeName} не найден");
    }
}
