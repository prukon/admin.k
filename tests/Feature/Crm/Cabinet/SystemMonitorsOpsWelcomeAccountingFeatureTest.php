<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Cabinet;

use App\Mail\ClientWelcomeCredentialsMail;
use App\Models\OutgoingEmailLog;
use App\Models\SchoolLead;
use App\Models\User;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\Auth;

/**
 * Пульт Welcome: лид→клиент за 24 ч. Ложный «нет письма», когда SMTP принял,
 * а в журнале пустой тип (как 9 клиентов «Будущее» 2026‑08‑31).
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SystemMonitorsOpsWelcomeAccountingFeatureTest extends SystemMonitorsTestCase
{
    public function test_nine_converted_clients_with_sent_letters_without_type_are_not_shown_as_missing(): void
    {
        $this->asSuperadmin();

        $ids = [];
        for ($i = 1; $i <= 9; $i++) {
            $ids[] = $this->seedConvertedClient(
                email: "future-legacy-{$i}@example.test",
                log: $this->legacySentWelcomeLog("future-legacy-{$i}@example.test"),
            )->id;
        }

        $response = $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('welcome.missing_count', 0)
            ->assertJsonPath('welcome.last_user_id', null);

        $raw = (string) $response->getContent();
        $this->assertArrayNotHasKey('email', $response->json('welcome'));
        foreach ($ids as $id) {
            $this->assertStringNotContainsString('#'.$id, $raw);
        }
    }

    public function test_operator_sees_nine_missing_when_letters_were_not_sent(): void
    {
        $this->asSuperadmin();

        $lastId = null;
        for ($i = 1; $i <= 9; $i++) {
            $lastId = $this->seedConvertedClient(
                email: "future-missing-{$i}@example.test",
                log: null,
            )->id;
        }

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('welcome.missing_count', 9)
            ->assertJsonPath('welcome.last_user_id', $lastId);
    }

    public function test_letter_accepted_by_smtp_without_user_id_in_log_is_not_missing(): void
    {
        $this->asSuperadmin();
        $student = $this->seedConvertedClient(
            email: 'welcome-to-summary-only@example.test',
            log: [
                'status' => OutgoingEmailLog::STATUS_SENT,
                'mailable_class' => ClientWelcomeCredentialsMail::class,
                'notifiable_type' => null,
                'notifiable_id' => null,
                'to_summary' => 'welcome-to-summary-only@example.test',
                'subject' => ClientWelcomeCredentialsMail::SUBJECT_PREFIX.' — '.$this->partner->title,
                'sent_at' => now(),
            ],
        );

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('welcome.missing_count', 0)
            ->assertJsonPath('welcome.last_user_id', null);

        $this->assertNotNull($student->id);
    }

    public function test_letter_to_named_address_still_counts_as_delivered(): void
    {
        $this->asSuperadmin();
        $this->seedConvertedClient(
            email: 'named-welcome@example.test',
            log: $this->legacySentWelcomeLog('Родитель <named-welcome@example.test>'),
        );

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('welcome.missing_count', 0)
            ->assertJsonPath('welcome.last_user_id', null);
    }

    public function test_letter_stuck_in_sending_still_counts_as_missing(): void
    {
        $this->asSuperadmin();
        $student = $this->seedConvertedClient(
            email: 'welcome-sending@example.test',
            log: [
                'status' => OutgoingEmailLog::STATUS_SENDING,
                'mailable_class' => null,
                'notifiable_id' => null,
                'to_summary' => 'welcome-sending@example.test',
                'subject' => ClientWelcomeCredentialsMail::SUBJECT_PREFIX.' — '.$this->partner->title,
                'sent_at' => null,
            ],
        );

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('welcome.missing_count', 1)
            ->assertJsonPath('welcome.last_user_id', $student->id);
    }

    public function test_newest_converted_client_without_letter_is_shown_as_last_id(): void
    {
        $this->asSuperadmin();
        $older = $this->seedConvertedClient('welcome-older-missing@example.test', null);
        $newer = $this->seedConvertedClient('welcome-newer-missing@example.test', null);
        $this->seedConvertedClient(
            'welcome-newer-sent@example.test',
            $this->legacySentWelcomeLog('welcome-newer-sent@example.test'),
        );

        $this->assertGreaterThan($older->id, $newer->id);

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('welcome.missing_count', 2)
            ->assertJsonPath('welcome.last_user_id', $newer->id);
    }

    public function test_converted_client_of_another_school_is_counted_for_superadmin(): void
    {
        $this->asSuperadmin();
        $other = $this->seedConvertedClient(
            email: 'other-school-missing@example.test',
            log: null,
            partnerId: (int) $this->foreignPartner->id,
        );

        $this->actingAs($this->user)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('welcome.missing_count', 1)
            ->assertJsonPath('welcome.last_user_id', $other->id);
    }

    public function test_creating_client_from_lead_clears_welcome_row_after_real_send(): void
    {
        config(['mail.default' => 'array', 'queue.default' => 'sync']);
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        $this->asAdmin();
        $this->grantPermissionToActor($this->user, 'users.view');
        $this->grantPermissionToActor($this->user, 'schoolLeads.view');

        $email = 'ops-e2e-welcome@example.test';
        $lead = SchoolLead::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Е2Е',
            'phone' => '+7 900 101-01-01',
            'parent_email' => $email,
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $store = $this->actingAs($this->user)
            ->postJson(route('admin.user.store'), [
                'name' => 'Е2Е',
                'lastname' => 'Клиент',
                'role_id' => $this->roleId('user'),
                'is_enabled' => 1,
                'school_lead_id' => $lead->id,
                'parent_email' => $email,
                'parent_lastname' => 'Родитель',
                'parent_firstname' => 'Тест',
            ], $this->ajaxHeaders());

        $store->assertOk()
            ->assertJsonPath('welcome_email_sent', true)
            ->assertJsonPath('user.email', $email);

        $this->asSuperadmin();

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('welcome.missing_count', 0)
            ->assertJsonPath('welcome.last_user_id', null);
    }

    public function test_native_create_from_lead_still_writes_letter_and_clears_welcome_row(): void
    {
        config(['mail.default' => 'array', 'queue.default' => 'sync']);
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        $this->asAdmin();
        $this->grantPermissionToActor($this->user, 'users.view');
        $this->grantPermissionToActor($this->user, 'schoolLeads.view');

        $email = 'ops-native-welcome@example.test';
        $lead = SchoolLead::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Native',
            'phone' => '+7 900 101-01-02',
            'parent_email' => $email,
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $this->from(route('admin.school-leads'))
            ->actingAs($this->user)
            ->post(route('admin.user.store'), [
                'name' => 'Native',
                'lastname' => 'Клиент',
                'role_id' => $this->roleId('user'),
                'is_enabled' => 1,
                'school_lead_id' => $lead->id,
                'parent_email' => $email,
                'parent_lastname' => 'Родитель',
                'parent_firstname' => 'Тест',
            ])
            ->assertRedirect(route('admin.user1'));

        $this->assertNotNull(User::query()->where('email', $email)->first());

        $this->asSuperadmin();
        $this->actingAs($this->user)
            ->get($this->opsUrl())
            ->assertOk()
            ->assertJsonPath('welcome.missing_count', 0)
            ->assertJsonPath('welcome.last_user_id', null);
    }

    public function test_admin_without_permission_does_not_see_missing_client_id(): void
    {
        $this->asSuperadmin();
        $this->seedConvertedClient('welcome-forbidden-leak@example.test', null);

        $this->asAdmin();
        $this->user->forceFill(['system_monitors' => true])->save();

        $json = $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders());
        $json->assertForbidden();
        $this->assertNotSame(200, $json->getStatusCode());
        $this->assertNotSame('', trim((string) $json->getContent()));
        $this->assertArrayNotHasKey('welcome', $json->json() ?? []);
        $this->assertArrayNotHasKey('last_user_id', $json->json() ?? []);
        $this->assertStringNotContainsString('welcome-forbidden-leak@example.test', (string) $json->getContent());
    }

    public function test_guest_cannot_read_welcome_counts(): void
    {
        $this->asSuperadmin();
        $this->seedConvertedClient('welcome-guest-leak@example.test', null);
        Auth::logout();

        $json = $this->getJson($this->opsUrl());
        $this->assertNotSame(500, $json->getStatusCode());
        $this->assertNotSame(200, $json->getStatusCode());
        $this->assertTrue($json->isRedirect() || $json->status() === 401);
        $this->assertStringNotContainsString('welcome-guest-leak@example.test', (string) $json->getContent());
        $this->assertArrayNotHasKey('welcome', $json->json() ?? []);
    }

    /**
     * @param  array<string, mixed>|null  $log
     */
    private function seedConvertedClient(string $email, ?array $log, ?int $partnerId = null): User
    {
        $partnerId ??= (int) $this->partner->id;
        $partner = $partnerId === (int) $this->partner->id ? $this->partner : $this->foreignPartner;

        $student = $this->createUserWithRole('user', $partner, [
            'email' => $email,
            'created_at' => now()->subMinutes(15),
        ]);

        SchoolLead::query()->create([
            'partner_id' => $partnerId,
            'name' => 'Лид',
            'phone' => '+7 9'.str_pad((string) $student->id, 9, '0', STR_PAD_LEFT),
            'parent_email' => $email,
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'user_id' => $student->id,
        ]);

        if ($log !== null) {
            OutgoingEmailLog::query()->create(array_merge([
                'partner_id' => $partnerId,
            ], $log));
        }

        return $student;
    }

    /**
     * @return array<string, mixed>
     */
    private function legacySentWelcomeLog(string $toSummary): array
    {
        return [
            'status' => OutgoingEmailLog::STATUS_SENT,
            'mailable_class' => null,
            'notifiable_type' => null,
            'notifiable_id' => null,
            'to_summary' => $toSummary,
            'subject' => ClientWelcomeCredentialsMail::SUBJECT_PREFIX.' — '.$this->partner->title,
            'sent_at' => now(),
        ];
    }
}
