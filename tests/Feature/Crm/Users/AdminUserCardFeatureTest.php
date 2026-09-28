<?php

namespace Tests\Feature\Crm\Users;

use App\Enums\AuditEvent;
use App\Models\Contract;
use App\Models\MyLog;
use App\Models\OutgoingEmailLog;
use App\Models\ParentProfile;
use App\Models\Payment;
use App\Models\Team;
use App\Models\User;
use App\Models\UserField;
use App\Models\UserFieldValue;
use App\Services\TeamUserSyncService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Feature\Crm\CrmTestCase;

final class AdminUserCardFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    public function test_guest_is_redirected_from_student_card(): void
    {
        $student = $this->makeStudent(['lastname' => 'ГостьКарточка']);

        Auth::logout();

        $this->get(route('admin.user.show', $student))->assertRedirect();
    }

    public function test_user_without_users_view_gets_403(): void
    {
        $student = $this->makeStudent();
        $actor = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->actingAs($actor);

        $this->get(route('admin.user.show', $student))->assertForbidden();
    }

    public function test_card_shows_student_family_and_hides_tabs_without_permission(): void
    {
        $actor = $this->actAsRole([
            'users.view',
            'users.sex',
            'users.comment',
            'users.phone.update',
            'users.discount.manage',
            'groups.view',
        ]);

        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Карточкин',
            'firstname' => 'Павел',
            'middlename' => 'Сергеевич',
            'passport' => '4510 123456',
            'phone' => '79110000001',
        ]);

        $student = $this->makeStudent([
            'lastname' => 'Карточкин',
            'name' => 'Илья',
            'middlename' => 'Павлович',
            'parent_id' => $parent->id,
            'sex' => 'male',
            'comment' => 'Комментарий карточки',
            'address' => 'Улица Карточки, 1',
            'is_individual_traits' => 1,
            'is_on_medical_register' => null,
            'is_with_disability' => 0,
            'discount_percent' => 10,
            'discount_comment' => 'Скидка карточки',
            'birthday' => '2014-05-02',
        ]);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа Карточки',
        ]);
        app(TeamUserSyncService::class)->attachTeamForStudent($student, (int) $team->id);

        $inactive = $this->makeStudent([
            'lastname' => 'Карточкин',
            'name' => 'Неактивный',
            'parent_id' => $parent->id,
            'is_enabled' => 0,
        ]);
        $deleted = $this->makeStudent([
            'lastname' => 'Карточкин',
            'name' => 'УдалённыйБрат',
            'parent_id' => $parent->id,
        ]);
        $deleted->delete();

        $field = UserField::factory()->forPartner($this->partner->id)->create([
            'name' => 'Любимый спорт',
        ]);
        DB::table('user_field_role')->insert([
            'user_field_id' => $field->id,
            'role_id' => $actor->role_id,
        ]);
        UserFieldValue::factory()->create([
            'user_id' => $student->id,
            'field_id' => $field->id,
            'value' => 'Плавание',
        ]);

        $html = $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('admin.user.show', $student))
            ->assertOk()
            ->assertSee('Анкетные данные', false)
            ->assertSee('Данные о здоровье', false)
            ->assertSee('Скидка', false)
            ->assertSeeInOrder([
                'Адрес проживания',
                'Комментарий',
                'Любимый спорт',
                'Данные о здоровье',
                'Скидка',
                'Активность',
            ])
            ->assertSee('js-phone-mask', false)
            ->assertSee('id="user-card-phone"', false)
            ->assertDontSee('Остальное', false)
            ->assertSee('Мужской', false)
            ->assertSee('Комментарий карточки', false)
            ->assertSee('Улица Карточки, 1', false)
            ->assertSee('Инд. особенности (физические, психологические)', false)
            ->assertSee('id="user-card-is_individual_traits" disabled checked', false)
            ->assertSee('id="user-card-is_on_medical_register" disabled', false)
            ->assertDontSee('name="is_individual_traits"', false)
            ->assertSee('Не указано', false)
            ->assertSee('name="discount_percent"', false)
            ->assertSee('value="10"', false)
            ->assertSee('Скидка карточки', false)
            ->assertSee('Группа Карточки', false)
            ->assertSee('Любимый спорт', false)
            ->assertSee('Плавание', false)
            ->assertSee('4510 123456', false)
            ->assertSee('name="parent_lastname"', false)
            ->assertSee('Карточкин Неактивный', false)
            ->assertSee('Неактивен', false)
            ->assertDontSee('УдалённыйБрат', false)
            ->assertDontSee('Абонементы', false)
            ->assertDontSee('Посещаемость', false)
            ->assertSee('id="user-card-pane-family"', false)
            ->assertSee('js-parent-phone', false)
            ->assertSee('id="card-parent-phone"', false)
            ->assertSee('Из справочника', false)
            ->assertSee('Новый родитель', false)
            ->assertSee('id="card-parent-id"', false)
            ->assertDontSee('class="row g-3"', false)
            ->assertSee('class="col-md-4"', false)
            ->assertSee('id="user-card-save"', false)
            ->assertSee('Отмена', false)
            ->assertSee('id="user-card-change-password"', false)
            ->assertSee('id="user-card-change-pass" class="d-none w-100"', false)
            ->assertSee('Отправить новый пароль по почте', false)
            ->assertSee('id="user-card-delete"', false)
            ->assertSee('data-password-url="'.route('admin.user.password.update', $student).'"', false)
            ->assertSee('data-welcome-url="'.route('admin.user.send-welcome-credentials', $student).'"', false)
            ->assertSee('data-delete-url="'.route('admin.user.delete', $student).'"', false)
            ->assertSee('aria-disabled="true"', false)
            ->assertSee('Скрыть входы', false)
            ->assertDontSee('id="user-card-tab-payments"', false)
            ->assertDontSee('id="user-card-tab-contracts"', false)
            ->assertSee('id="user-card-tab-emails"', false)
            ->assertSee('Писем не было', false)
            ->assertDontSee('id="user-card-emails-table"', false)
            ->assertSee('>Офлайн<', false)
            ->getContent();

        $this->assertStringContainsString(route('admin.user.show', $inactive), $html);
        $this->assertStringContainsString(route('admin.team.index'), $html);
    }

    public function test_name_column_links_to_card_and_edit_button_stays(): void
    {
        $blade = (string) file_get_contents(resource_path('views/admin/user.blade.php'));
        $card = (string) file_get_contents(resource_path('views/admin/users/_user_card_shell.blade.php'));

        $this->assertStringContainsString("@json(url('/admin/users')) + '/' + row.id", $blade);
        $this->assertStringContainsString("linkClass: 'js-open-user-card'", $blade);
        $this->assertStringContainsString("@include('admin.users._user_card_shell')", $blade);
        $this->assertStringContainsString('id="userCardModal"', $card);
        $this->assertStringContainsString('#userCardModal .modal-dialog', $card);
        $this->assertStringContainsString('max-width: 640px;', $card);
        $this->assertStringContainsString('PhoneInputMask.initIn(content)', $card);
        $this->assertStringContainsString('#user-card-change-password', $card);
        $this->assertStringContainsString('#user-card-apply-password', $card);
        $this->assertStringContainsString('#user-card-send-password', $card);
        $this->assertStringContainsString('#user-card-delete', $card);
        $this->assertStringContainsString('userCardHideIsTemporary', $card);
        $this->assertStringContainsString('restoreUserCardTeamsSelect', $card);
        $this->assertStringContainsString('#trainers-table a.js-open-user-card', $card);
        $this->assertStringContainsString('#role-staff-table a.js-open-user-card', $card);
        $this->assertDoesNotMatchRegularExpression('/id="userCardModal"[^>]*>\s*<div class="modal-dialog modal-lg/', $card);
        $this->assertStringContainsString('#userCardModal #user-card-form > .modal-footer', $card);
        $this->assertSame(1, substr_count($blade, 'edit-user-link'));
        $this->assertStringContainsString('data-bs-target="#editUserModal"', $blade);

        $trainersBlade = (string) file_get_contents(resource_path('views/admin/trainers/index.blade.php'));
        $this->assertStringContainsString("@include('admin.users._user_card_shell')", $trainersBlade);
        $this->assertStringContainsString("linkClass: 'js-open-user-card'", $trainersBlade);
        $this->assertStringContainsString("@json(url('/admin/users')) + '/' + row.user_id", $trainersBlade);

        $staffBlade = (string) file_get_contents(resource_path('views/admin/role_staff/index.blade.php'));
        $this->assertStringContainsString("@include('admin.users._user_card_shell')", $staffBlade);
        $this->assertStringContainsString("linkClass: 'js-open-user-card'", $staffBlade);
        $this->assertStringContainsString("@json(url('/admin/users')) + '/' + row.id", $staffBlade);
    }

    public function test_browser_get_opens_the_list_with_card_query(): void
    {
        $this->asAdmin();
        $this->grant('users.view');
        $student = $this->makeStudent();

        $this->get(route('admin.user.show', $student))
            ->assertRedirect(route('admin.user1', ['card' => $student->id]));
    }

    public function test_card_save_updates_info_and_family(): void
    {
        $this->actAsRole([
            'users.view',
            'users.name.update',
            'users.group.update',
            'users.other.update',
        ]);

        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'СтараяФамилия',
            'firstname' => 'Павел',
        ]);
        $student = $this->makeStudent([
            'lastname' => 'Карточкин',
            'name' => 'Илья',
            'parent_id' => $parent->id,
            'address' => 'Старый адрес',
        ]);

        $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('admin.user.show', $student))
            ->assertOk()
            ->assertSee('action="'.route('admin.user.update', $student).'"', false)
            ->assertSee('name="address"', false)
            ->assertSee('name="parent_lastname"', false)
            ->assertSee('js-generic-multiselect-select', false)
            ->assertSee('data-placeholder="Выберите группы"', false)
            ->assertSee('name="is_individual_traits" value="0"', false)
            ->assertSee('id="user-card-is_individual_traits" name="is_individual_traits" value="1"', false)
            ->assertDontSee('удерживайте Ctrl', false);

        $this->patchJson(route('admin.user.update', $student), [
            'lastname' => 'НоваяФамилия',
            'name' => 'Илья',
            'address' => 'Новый адрес карточки',
            'parent_id' => $parent->id,
            'parent_lastname' => 'РодительНовый',
            'parent_firstname' => 'Павел',
            'is_individual_traits' => '1',
            'is_on_medical_register' => '0',
            'is_with_disability' => '0',
        ])->assertOk()
            ->assertJsonPath('message', 'Клиент успешно обновлён');

        $student->refresh();
        $parent->refresh();
        $this->assertSame('НоваяФамилия', $student->lastname);
        $this->assertSame('Новый адрес карточки', $student->address);
        $this->assertTrue((bool) $student->is_individual_traits);
        $this->assertFalse((bool) $student->is_on_medical_register);
        $this->assertSame('РодительНовый', $parent->lastname);
    }

    public function test_staff_card_omits_family_payments_and_contracts_and_foreign_user_is_not_found(): void
    {
        $this->asAdmin();
        $this->grant('users.view');
        $this->grant('reports.view');
        $this->grant('contracts.view');

        $trainer = $this->createUserWithRole('trainer', $this->partner, [
            'lastname' => 'ТренерКарточка',
            'name' => 'Олег',
        ]);

        $this->get(route('admin.user.show', $this->foreignUser))->assertNotFound();

        $admin = $this->createUserWithRole('admin', $this->partner, [
            'lastname' => 'АдминКарточка',
            'name' => 'Ольга',
        ]);

        foreach ([$trainer, $admin] as $person) {
            $this->withHeader('X-Requested-With', 'XMLHttpRequest')
                ->get(route('admin.user.show', $person))
                ->assertOk()
                ->assertSee('id="user-card-tab-info"', false)
                ->assertSee('id="user-card-tab-emails"', false)
                ->assertSee('id="user-card-tab-logs"', false)
                ->assertSee('Изменить пароль', false)
                ->assertDontSee('id="user-card-tab-family"', false)
                ->assertDontSee('id="user-card-tab-payments"', false)
                ->assertDontSee('id="user-card-tab-contracts"', false)
                ->assertDontSee('id="user-card-send-password"', false)
                ->assertDontSee('Данные о здоровье', false)
                ->assertDontSee('name="team_ids[]"', false);

            $this->getJson(route('admin.user.payments-data', $person).'?draw=1')->assertNotFound();
            $this->getJson(route('admin.user.contracts-data', $person).'?draw=1')->assertNotFound();
            $this->getJson(route('admin.user.logs-data', $person).'?draw=1&start=0&length=10')->assertOk();
        }
    }

    public function test_payments_and_contracts_tabs_require_their_permissions_and_list_all_rows(): void
    {
        $this->asAdmin();
        $this->grant('users.view');
        $this->grant('reports.view');
        $this->grant('contracts.view');

        $student = $this->makeStudent(['lastname' => 'Плательщик', 'name' => 'Карты']);
        $other = $this->makeStudent(['lastname' => 'Чужой', 'name' => 'Платёж']);

        Payment::factory()->forUser($student)->create([
            'payment_status' => 'REJECTED',
            'payment_month' => '2026-03-01',
            'team_title' => 'Группа платежа',
            'summ_cents' => 150000,
            'operation_date' => '2026-03-04 10:00:00',
        ]);
        Payment::factory()->forUser($student)->create([
            'payment_status' => 'CANCELED',
            'payment_month' => 'Абонемент',
            'summ_cents' => 50000,
            'operation_date' => '2026-03-05 11:00:00',
        ]);
        Payment::factory()->forUser($other)->create([
            'payment_status' => 'CONFIRMED',
            'payment_month' => 'ЧужойПлатёжТип',
            'summ_cents' => 100,
        ]);

        Contract::query()->create([
            'school_id' => $this->partner->id,
            'user_id' => $student->id,
            'group_id' => null,
            'source_pdf_path' => 'documents/card/draft.pdf',
            'source_sha256' => str_repeat('b', 64),
            'provider' => 'podpislon',
            'status' => Contract::STATUS_DRAFT,
        ]);
        Contract::query()->create([
            'school_id' => $this->partner->id,
            'user_id' => $student->id,
            'group_id' => null,
            'source_pdf_path' => 'documents/card/revoked.pdf',
            'source_sha256' => str_repeat('c', 64),
            'provider' => 'podpislon',
            'status' => Contract::STATUS_REVOKED,
        ]);

        $page = $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('admin.user.show', $student))
            ->assertOk();
        $page->assertSee('id="user-card-tab-payments"', false);
        $page->assertSee('id="user-card-tab-contracts"', false);
        $page->assertSee('id="user-card-payments-table"', false);
        $page->assertSee('id="user-card-contracts-table"', false);
        $page->assertDontSee('Платежей нет', false);
        $page->assertDontSee('Договоров нет', false);

        $payments = collect($this->getJson(route('admin.user.payments-data', $student).'?draw=1&start=0&length=20')->assertOk()->json('data'));
        $this->assertTrue($payments->contains(fn (array $row) => ($row['status_label'] ?? '') === 'Отклонён' && ($row['type_label'] ?? '') === 'Ежемесячный платеж'));
        $this->assertTrue($payments->contains(fn (array $row) => ($row['status_label'] ?? '') === 'Отменён' && ($row['type_label'] ?? '') === 'Абонемент'));
        $this->assertFalse($payments->contains(fn (array $row) => ($row['type_label'] ?? '') === 'ЧужойПлатёжТип'));

        $contracts = collect($this->getJson(route('admin.user.contracts-data', $student).'?draw=1&start=0&length=20')->assertOk()->json('data'));
        $labels = $contracts->pluck('status_label')->all();
        $this->assertContains('Черновик', $labels);
        $this->assertContains('Отозвано', $labels);
    }

    public function test_header_presence_follows_chat_card_rules(): void
    {
        $this->asAdmin();
        $this->grant('users.view');
        $online = $this->makeStudent(['last_seen_at' => now()]);
        $seen = now()->subDay()->startOfMinute();
        $offline = $this->makeStudent(['last_seen_at' => $seen]);

        $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('admin.user.show', $online))
            ->assertOk()
            ->assertSee('>Онлайн<', false)
            ->assertSee('user-card-avatar', false)
            ->assertSee('user-card-identity', false)
            ->assertSee('user-card-presence', false);

        $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('admin.user.show', $offline))
            ->assertOk()
            ->assertSee('>Офлайн<', false)
            ->assertSee($seen->timezone((string) config('app.timezone'))->format('d.m.Y H:i'), false);
    }

    public function test_empty_emails_are_text_without_datatables(): void
    {
        $this->asAdmin();
        $this->grant('users.view');
        $this->grant('reports.emails.view');
        $student = $this->makeStudent(['email' => 'empty-mail@example.test']);

        $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('admin.user.show', $student))
            ->assertOk()
            ->assertSee('id="user-card-tab-emails"', false)
            ->assertSee('Писем не было', false)
            ->assertDontSee('id="user-card-emails-table"', false);
    }

    public function test_emails_tab_lists_only_messages_to_this_student(): void
    {
        $this->asAdmin();
        $this->grant('users.view');
        $this->grant('reports.emails.view');
        $student = $this->makeStudent(['email' => 'card-mail@example.test']);
        $other = $this->makeStudent(['email' => 'other-mail@example.test']);

        $own = OutgoingEmailLog::create([
            'partner_id' => $this->partner->id,
            'status' => OutgoingEmailLog::STATUS_SENT,
            'to_addresses' => [['address' => 'card-mail@example.test', 'name' => null]],
            'to_summary' => 'card-mail@example.test',
            'subject' => 'Письмо карточки',
            'send_attempts' => 2,
            'error_message' => null,
            'sent_at' => '2026-03-04 10:00:00',
        ]);
        OutgoingEmailLog::create([
            'partner_id' => $this->partner->id,
            'status' => OutgoingEmailLog::STATUS_FAILED,
            'to_addresses' => [['address' => 'other-mail@example.test', 'name' => null]],
            'to_summary' => 'other-mail@example.test',
            'subject' => 'Чужое письмо',
            'send_attempts' => 1,
            'error_message' => 'smtp down',
        ]);
        OutgoingEmailLog::create([
            'partner_id' => $this->foreignPartner->id,
            'status' => OutgoingEmailLog::STATUS_SENT,
            'to_addresses' => [['address' => 'card-mail@example.test', 'name' => null]],
            'to_summary' => 'card-mail@example.test',
            'subject' => 'Письмо другой школы',
            'send_attempts' => 1,
        ]);

        $page = $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('admin.user.show', $student))
            ->assertOk();
        $page->assertSee('id="user-card-emails-table"', false);
        $page->assertDontSee('Писем не было', false);

        $rows = collect($this->getJson(route('admin.user.emails-data', $student).'?draw=1&start=0&length=20')->assertOk()->json('data'));
        $this->assertTrue($rows->contains(fn (array $row) => ($row['subject'] ?? '') === 'Письмо карточки' && (int) ($row['send_attempts'] ?? 0) === 2));
        $this->assertTrue($rows->contains(fn (array $row) => str_contains((string) ($row['show_url'] ?? ''), '/'. $own->id)));
        $this->assertFalse($rows->contains(fn (array $row) => ($row['subject'] ?? '') === 'Чужое письмо'));
        $this->assertFalse($rows->contains(fn (array $row) => ($row['subject'] ?? '') === 'Письмо другой школы'));

        $this->getJson(route('admin.user.emails-data', $other).'?draw=1&start=0&length=20')
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1);
    }

    public function test_email_show_rejects_a_message_for_another_student(): void
    {
        $this->actAsRole(['users.view']);
        $student = $this->makeStudent(['email' => 'own-mail@example.test']);
        $foreign = OutgoingEmailLog::create([
            'partner_id' => $this->partner->id,
            'status' => OutgoingEmailLog::STATUS_SENT,
            'to_addresses' => [['address' => 'stranger@example.test', 'name' => null]],
            'to_summary' => 'stranger@example.test',
            'subject' => 'Не его письмо',
            'send_attempts' => 1,
        ]);

        $this->get(route('admin.user.email-show', ['user' => $student, 'log' => $foreign]))
            ->assertNotFound();
    }

    public function test_empty_payments_and_contracts_are_text_without_datatables(): void
    {
        $this->asAdmin();
        $this->grant('users.view');
        $this->grant('reports.view');
        $this->grant('contracts.view');
        $student = $this->makeStudent();

        $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('admin.user.show', $student))
            ->assertOk()
            ->assertSee('Платежей нет', false)
            ->assertSee('Договоров нет', false)
            ->assertDontSee('id="user-card-payments-table"', false)
            ->assertDontSee('id="user-card-contracts-table"', false);
    }

    public function test_header_shows_login_flag_and_second_flag_when_activity_country_differs(): void
    {
        $this->app->instance(\App\Services\Geo\IpCountryResolver::class, new class implements \App\Services\Geo\IpCountryResolver
        {
            public function resolve(?string $ip): ?\App\Services\Geo\IpCountry
            {
                return match ($ip) {
                    '203.0.113.10' => new \App\Services\Geo\IpCountry('DE', 'Германия'),
                    '203.0.113.20' => new \App\Services\Geo\IpCountry('RU', 'Россия'),
                    default => null,
                };
            }
        });

        $this->asAdmin();
        $this->grant('users.view');
        $student = $this->makeStudent([
            'last_activity_ip' => '203.0.113.20',
            'last_activity_user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Version/17.0 Mobile/15E148 Safari/604.1',
        ]);

        MyLog::query()->create([
            'event' => AuditEvent::AuthLogin->value,
            'level' => AuditEvent::AuthLogin->level()->value,
            'description' => "Логин: Ученик, IP: 203.0.113.10\nПлатформа: Windows 10, Браузер: Chrome 120, \nУстройство: WebKit,ПК: Нет, Моб. устройство: Да, Планшет: Нет",
            'partner_id' => $this->partner->id,
            'author_id' => $student->id,
            'created_at' => now(),
        ]);

        $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('admin.user.show', $student))
            ->assertOk()
            ->assertSee('flag-icon-de', false)
            ->assertSee('flag-icon-ru', false)
            ->assertSee('Германия, Chrome 120', false)
            ->assertSee('fa-mobile-screen', false)
            ->assertSee('title="Телефон"', false);
    }

    public function test_activity_request_stores_client_ip_when_it_changes(): void
    {
        $student = $this->makeStudent();
        $this->actingAs($student);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->get(route('admin.user1'));
        $this->assertSame('203.0.113.10', $student->fresh()->last_activity_ip);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.20'])
            ->get(route('admin.user1'));
        $this->assertSame('203.0.113.20', $student->fresh()->last_activity_ip);
    }

    public function test_payments_endpoint_is_forbidden_without_reports_view(): void
    {
        $this->actAsRole(['users.view']);
        $student = $this->makeStudent();

        $this->getJson(route('admin.user.payments-data', $student).'?draw=1')->assertForbidden();
    }

    public function test_logs_include_subject_and_target_and_hide_logins_by_default(): void
    {
        $this->asAdmin();
        $this->grant('users.view');
        $student = $this->makeStudent(['lastname' => 'Логов', 'name' => 'Пётр']);

        $this->writeLog($student, AuditEvent::UserUpdated, 'правка-карточки', userId: (int) $student->id);
        $this->writeLog($student, AuditEvent::ScheduleDayUpdated, 'отметка-журнала', userId: null, asTarget: true);
        $this->writeLog($student, AuditEvent::AuthLogin, 'вход-ученика', userId: (int) $student->id);
        MyLog::query()->create([
            'event' => AuditEvent::UserUpdated->value,
            'level' => AuditEvent::UserUpdated->level()->value,
            'description' => 'чужая-школа',
            'partner_id' => $this->foreignPartner->id,
            'user_id' => $student->id,
            'created_at' => now(),
        ]);

        $hidden = collect($this->getJson(route('admin.user.logs-data', [
            'user' => $student,
            'draw' => 1,
            'start' => 0,
            'length' => 20,
        ]))->assertOk()->json('data'))->pluck('description');

        $this->assertTrue($hidden->contains(fn ($text) => str_contains((string) $text, 'правка-карточки')));
        $this->assertTrue($hidden->contains(fn ($text) => str_contains((string) $text, 'отметка-журнала')));
        $this->assertFalse($hidden->contains(fn ($text) => str_contains((string) $text, 'вход-ученика')));
        $this->assertFalse($hidden->contains(fn ($text) => str_contains((string) $text, 'чужая-школа')));

        $shown = collect($this->getJson(route('admin.user.logs-data', [
            'user' => $student,
            'draw' => 1,
            'start' => 0,
            'length' => 20,
            'hide_authorizations' => 0,
        ]))->assertOk()->json('data'))->pluck('description');

        $this->assertTrue($shown->contains(fn ($text) => str_contains((string) $text, 'вход-ученика')));
    }

    public function test_guest_is_turned_away_from_every_card_endpoint(): void
    {
        $student = $this->makeStudent();
        $log = OutgoingEmailLog::create([
            'partner_id' => $this->partner->id,
            'status' => OutgoingEmailLog::STATUS_SENT,
            'to_summary' => 'guest-card@example.test',
            'subject' => 'Письмо гостя',
            'send_attempts' => 1,
        ]);

        Auth::logout();

        $urls = [
            route('admin.user.show', $student),
            route('admin.user.payments-data', $student),
            route('admin.user.contracts-data', $student),
            route('admin.user.emails-data', $student),
            route('admin.user.email-show', ['user' => $student, 'log' => $log]),
            route('admin.user.logs-data', $student),
        ];

        foreach ($urls as $url) {
            $response = $this->get($url);
            $this->assertContains(
                $response->status(),
                [301, 302, 303, 307, 401],
                $url.' вернул '.$response->status()
            );
        }
    }

    public function test_manager_without_users_view_gets_403_on_card_endpoints(): void
    {
        $student = $this->makeStudent();
        $log = OutgoingEmailLog::create([
            'partner_id' => $this->partner->id,
            'status' => OutgoingEmailLog::STATUS_SENT,
            'to_summary' => 'noperm-card@example.test',
            'subject' => 'Письмо без права',
            'send_attempts' => 1,
        ]);
        $actor = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->actingAs($actor);

        $this->get(route('admin.user.show', $student))->assertForbidden();
        $this->getJson(route('admin.user.emails-data', $student).'?draw=1')->assertForbidden();
        $this->get(route('admin.user.email-show', ['user' => $student, 'log' => $log]))->assertForbidden();
        $this->getJson(route('admin.user.logs-data', $student).'?draw=1')->assertForbidden();
        $this->getJson(route('admin.user.payments-data', $student).'?draw=1')->assertForbidden();
        $this->getJson(route('admin.user.contracts-data', $student).'?draw=1')->assertForbidden();
        $this->patchJson(route('admin.user.update', $student), ['name' => 'Чужой'])->assertForbidden();
    }

    public function test_manager_without_contracts_view_cannot_list_student_contracts(): void
    {
        $this->actAsRole(['users.view', 'reports.view']);
        $student = $this->makeStudent();

        $this->getJson(route('admin.user.contracts-data', $student).'?draw=1')->assertForbidden();
    }

    public function test_card_fragment_is_not_stored_by_the_browser(): void
    {
        $this->asAdmin();
        $this->grant('users.view');
        $student = $this->makeStudent();

        $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('admin.user.show', $student))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_saving_the_card_from_the_browser_form_redirects_and_updates_the_student(): void
    {
        $this->actAsRole(['users.view', 'users.name.update']);
        $student = $this->makeStudent([
            'lastname' => 'СтараяФамилия',
            'name' => 'Илья',
        ]);

        $this->from(route('admin.user1'))
            ->patch(route('admin.user.update', $student), [
                'lastname' => 'НоваяФамилия',
                'name' => 'Илья',
            ])
            ->assertRedirect(route('admin.user1'));

        $this->assertSame('НоваяФамилия', $student->fresh()->lastname);
    }

    public function test_saving_the_card_with_an_empty_lastname_reports_the_field_error(): void
    {
        $this->actAsRole(['users.view', 'users.name.update']);
        $student = $this->makeStudent([
            'lastname' => 'СтараяФамилия',
            'name' => 'Илья',
        ]);

        $this->patchJson(route('admin.user.update', $student), [
            'lastname' => '',
            'name' => 'Илья',
        ])->assertStatus(422)
            ->assertJsonPath('errors.lastname.0', 'Поле "Фамилия" обязательно для заполнения.');

        $this->from(route('admin.user1'))
            ->patch(route('admin.user.update', $student), [
                'lastname' => '',
                'name' => 'Илья',
            ])
            ->assertRedirect(route('admin.user1'))
            ->assertSessionHasErrors(['lastname']);

        $this->assertSame('СтараяФамилия', $student->fresh()->lastname);
    }

    public function test_header_puts_name_and_age_on_the_left_and_presence_on_the_right(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-28 12:00:00', (string) config('app.timezone')));
        try {
            $this->asAdmin();
            $this->grant('users.view');
            $birthday = Carbon::parse('2015-01-15', (string) config('app.timezone'));
            $student = $this->makeStudent([
                'lastname' => 'Иванов',
                'name' => 'Пётр',
                'middlename' => 'Сергеевич',
                'birthday' => $birthday->toDateString(),
                'image_crop' => null,
            ]);

            $html = $this->withHeader('X-Requested-With', 'XMLHttpRequest')
                ->get(route('admin.user.show', $student))
                ->assertOk()
                ->assertSeeInOrder([
                    'user-card-identity',
                    'user-card-avatar',
                    'id="userCardModalLabel">Иванов Пётр<',
                    '15.01.2015 · 11 лет',
                    'user-card-presence',
                    '>Офлайн<',
                ], false)
                ->assertSee('src="'.asset('img/default-avatar.png').'"', false)
                ->assertDontSee('id="userCardModalLabel">Иванов Пётр Сергеевич<', false)
                ->getContent();

            $this->assertDoesNotMatchRegularExpression(
                '/user-card-presence[\s\S]{0,400}\d{2}\.\d{2}\.\d{4}/',
                $html
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_chat_presence_stays_online_for_two_minutes_and_then_shows_the_clock_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-28 12:00:00', (string) config('app.timezone')));
        try {
            $this->asAdmin();
            $this->grant('users.view');
            $stillOnline = $this->makeStudent(['last_seen_at' => now()->subSeconds(120)]);
            $justOffline = $this->makeStudent(['last_seen_at' => now()->subSeconds(121)]);

            $onlineHtml = $this->withHeader('X-Requested-With', 'XMLHttpRequest')
                ->get(route('admin.user.show', $stillOnline))
                ->assertOk()
                ->assertSee('>Онлайн<', false)
                ->getContent();
            $this->assertDoesNotMatchRegularExpression(
                '/user-card-presence[\s\S]{0,400}\d{2}\.\d{2}\.\d{4} \d{2}:\d{2}/',
                $onlineHtml
            );

            $this->withHeader('X-Requested-With', 'XMLHttpRequest')
                ->get(route('admin.user.show', $justOffline))
                ->assertOk()
                ->assertSee('>Офлайн<', false)
                ->assertSee('28.09.2026 11:57', false);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_same_country_after_login_does_not_add_a_second_flag(): void
    {
        $this->app->instance(\App\Services\Geo\IpCountryResolver::class, new class implements \App\Services\Geo\IpCountryResolver
        {
            public function resolve(?string $ip): ?\App\Services\Geo\IpCountry
            {
                return match ($ip) {
                    '203.0.113.10', '198.51.100.20' => new \App\Services\Geo\IpCountry('DE', 'Германия'),
                    '10.0.0.8' => null,
                    default => null,
                };
            }
        });

        $this->asAdmin();
        $this->grant('users.view');
        $sameCountry = $this->makeStudent([
            'last_activity_ip' => '198.51.100.20',
            'last_activity_user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0',
        ]);
        MyLog::query()->create([
            'event' => AuditEvent::AuthLogin->value,
            'level' => AuditEvent::AuthLogin->level()->value,
            'description' => "Логин: Ученик, IP: 203.0.113.10\nПлатформа: Windows 10, Браузер: Chrome 120, \nУстройство: WebKit,ПК: Да, Моб. устройство: Нет, Планшет: Нет",
            'partner_id' => $this->partner->id,
            'author_id' => $sameCountry->id,
            'created_at' => now(),
        ]);

        $html = $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('admin.user.show', $sameCountry))
            ->assertOk()
            ->assertSee('flag-icon-de', false)
            ->assertSee('fa-desktop', false)
            ->getContent();
        $this->assertSame(1, substr_count($html, 'flag-icon-de'));

        $local = $this->makeStudent();
        MyLog::query()->create([
            'event' => AuditEvent::AuthLogin->value,
            'level' => AuditEvent::AuthLogin->level()->value,
            'description' => "Логин: Ученик, IP: 127.0.0.1\nПлатформа: Windows 10, Браузер: Chrome 120, \nУстройство: WebKit,ПК: Да, Моб. устройство: Нет, Планшет: Нет",
            'partner_id' => $this->partner->id,
            'author_id' => $local->id,
            'created_at' => now(),
        ]);

        $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('admin.user.show', $local))
            ->assertOk()
            ->assertSee('fa-desktop', false)
            ->assertDontSee('flag-icon-', false);
    }

    public function test_activity_checkbox_is_saved_only_when_the_manager_may_change_it(): void
    {
        $editor = $this->actAsRole(['users.view', 'users.activity.update', 'users.name.update']);
        $student = $this->makeStudent([
            'lastname' => 'Активный',
            'name' => 'Ученик',
            'is_enabled' => 1,
        ]);

        $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('admin.user.show', $student))
            ->assertOk()
            ->assertSee('name="is_enabled" value="0"', false)
            ->assertSee('id="user-card-enabled" name="is_enabled" value="1"', false);

        $this->patchJson(route('admin.user.update', $student), [
            'lastname' => 'Активный',
            'name' => 'Ученик',
            'is_enabled' => '0',
        ])->assertOk();
        $this->assertFalse((bool) $student->fresh()->is_enabled);

        $viewer = $this->actAsRole(['users.view']);
        $this->assertNotSame($editor->id, $viewer->id);
        $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('admin.user.show', $student))
            ->assertOk()
            ->assertDontSee('name="is_enabled"', false)
            ->assertSee('Неактивен', false);
    }

    public function test_groups_stay_read_only_without_the_group_update_permission(): void
    {
        $this->actAsRole(['users.view', 'groups.view']);
        $student = $this->makeStudent();
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Только чтение группы',
        ]);
        app(TeamUserSyncService::class)->attachTeamForStudent($student, (int) $team->id);

        $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('admin.user.show', $student))
            ->assertOk()
            ->assertSee('Только чтение группы', false)
            ->assertDontSee('name="team_ids[]"', false)
            ->assertDontSee('js-generic-multiselect-select', false);
    }

    public function test_letters_in_copy_and_a_different_letter_case_still_belong_to_the_student(): void
    {
        $this->actAsRole(['users.view']);
        $student = $this->makeStudent(['email' => 'Card.User@Example.TEST']);
        $copy = OutgoingEmailLog::create([
            'partner_id' => $this->partner->id,
            'status' => OutgoingEmailLog::STATUS_FAILED,
            'to_addresses' => [['address' => 'other@example.test', 'name' => null]],
            'cc_addresses' => [['address' => 'card.user@example.test', 'name' => null]],
            'to_summary' => 'other@example.test',
            'subject' => 'Копия ученику',
            'send_attempts' => 3,
            'error_message' => 'smtp down',
            'sent_at' => '2026-04-01 09:30:00',
        ]);
        OutgoingEmailLog::create([
            'partner_id' => $this->partner->id,
            'status' => OutgoingEmailLog::STATUS_SENT,
            'to_addresses' => [],
            'bcc_addresses' => [['address' => 'card.user@example.test', 'name' => null]],
            'to_summary' => 'скрытая копия',
            'subject' => 'Скрытая копия ученику',
            'send_attempts' => 1,
        ]);
        OutgoingEmailLog::create([
            'partner_id' => $this->partner->id,
            'status' => OutgoingEmailLog::STATUS_SENT,
            'to_addresses' => [['address' => 'CARD.USER@example.test', 'name' => null]],
            'to_summary' => 'CARD.USER@example.test',
            'subject' => 'Тема другим регистром',
            'send_attempts' => 1,
        ]);

        $rows = collect($this->getJson(route('admin.user.emails-data', $student).'?draw=1&start=0&length=20')->assertOk()->json('data'));
        $this->assertTrue($rows->contains(fn (array $row) => ($row['subject'] ?? '') === 'Копия ученику' && ($row['error_excerpt'] ?? '') === 'smtp down' && (int) ($row['send_attempts'] ?? 0) === 3));
        $this->assertTrue($rows->contains(fn (array $row) => ($row['subject'] ?? '') === 'Скрытая копия ученику'));
        $this->assertTrue($rows->contains(fn (array $row) => ($row['subject'] ?? '') === 'Тема другим регистром'));

        $this->get(route('admin.user.email-show', ['user' => $student, 'log' => $copy]))
            ->assertOk()
            ->assertSee('Копия ученику', false)
            ->assertSee('smtp down', false);
    }

    public function test_student_without_an_email_address_has_no_letters(): void
    {
        $this->actAsRole(['users.view']);
        $student = $this->makeStudent(['email' => '']);
        OutgoingEmailLog::create([
            'partner_id' => $this->partner->id,
            'status' => OutgoingEmailLog::STATUS_SENT,
            'to_addresses' => [['address' => 'someone@example.test', 'name' => null]],
            'to_summary' => 'someone@example.test',
            'subject' => 'Чужое при пустом email',
            'send_attempts' => 1,
        ]);

        $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('admin.user.show', $student))
            ->assertOk()
            ->assertSee('Писем не было', false)
            ->assertDontSee('id="user-card-emails-table"', false);

        $this->getJson(route('admin.user.emails-data', $student).'?draw=1&start=0&length=20')
            ->assertOk()
            ->assertJsonPath('recordsTotal', 0);
    }

    public function test_a_short_new_password_is_rejected_and_the_same_password_is_not_saved(): void
    {
        $this->actAsRole(['users.view', 'users.password.update']);
        $student = $this->makeStudent();
        $student->password = Hash::make('same-pass-1');
        $student->save();

        $this->postJson(route('admin.user.password.update', $student), [
            'password' => 'short',
        ])->assertStatus(422)
            ->assertJsonStructure(['errors' => ['password']]);

        $this->postJson(route('admin.user.password.update', $student), [
            'password' => 'same-pass-1',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Новый пароль совпадает с текущим.');

        $this->assertTrue(Hash::check('same-pass-1', (string) $student->fresh()->password));
    }

    public function test_welcome_letter_is_refused_when_the_student_has_no_email(): void
    {
        $this->actAsRole(['users.view']);
        $student = $this->makeStudent(['email' => '']);

        $this->postJson(route('admin.user.send-welcome-credentials', $student))
            ->assertStatus(422)
            ->assertJsonPath('message', 'У ученика не указан email.');
    }

    public function test_card_endpoints_reject_the_wrong_http_method(): void
    {
        $this->asAdmin();
        $this->grant('users.view');
        $student = $this->makeStudent();

        $this->put(route('admin.user.show', $student))->assertStatus(405);
        $this->delete(route('admin.user.show', $student))->assertStatus(405);
        $this->post(route('admin.user.emails-data', $student))->assertStatus(405);
        $this->put(route('admin.user.update', $student), ['name' => 'Имя'])->assertStatus(405);
    }

    public function test_logs_reject_invalid_hide_authorizations_flag(): void
    {
        $this->asAdmin();
        $this->grant('users.view');
        $student = $this->makeStudent();

        $this->getJson(route('admin.user.logs-data', [
            'user' => $student,
            'hide_authorizations' => 'maybe',
        ]))
            ->assertStatus(422)
            ->assertJsonPath('errors.hide_authorizations.0', 'Поле «Скрыть входы» должно быть да или нет.');
    }

    private function makeStudent(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => 1,
        ], $attributes));
    }

    /**
     * @param  list<string>  $permissions
     */
    private function actAsRole(array $permissions): User
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'card_'.Str::lower(Str::random(6)),
            'label' => 'Card test',
            'is_sistem' => 0,
            'order_by' => 0,
            'is_visible' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($permissions as $name) {
            DB::table('permission_role')->insert([
                'partner_id' => $this->partner->id,
                'role_id' => $roleId,
                'permission_id' => $this->permissionId($name),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $actor = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $roleId,
        ]);

        $this->actingAs($actor);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        return $actor;
    }

    private function grant(string $permission): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId($permission),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->user->unsetRelation('role');
    }

    private function writeLog(User $student, AuditEvent $event, string $description, ?int $userId, bool $asTarget = false): void
    {
        MyLog::query()->create([
            'event' => $event->value,
            'level' => $event->level()->value,
            'description' => $description,
            'partner_id' => $this->partner->id,
            'author_id' => $this->user->id,
            'user_id' => $userId,
            'target_type' => $asTarget ? $student->getMorphClass() : null,
            'target_id' => $asTarget ? $student->id : null,
            'created_at' => now(),
        ]);
    }
}
