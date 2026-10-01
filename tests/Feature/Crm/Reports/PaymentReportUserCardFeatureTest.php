<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Reports;

use App\Enums\AuditEvent;
use App\Models\MyLog;
use App\Models\ParentProfile;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

class PaymentReportUserCardFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        session(['current_partner' => $this->partner->id]);
        $this->asAdmin();
    }

    public function test_payments_page_renders_user_card_modal_and_name_link(): void
    {
        $html = $this->get(route('payments'))->assertOk()->getContent();

        $this->assertStringContainsString('id="paymentUserCardModal"', $html);
        $this->assertStringContainsString('peer-card-head', $html);
        $this->assertStringContainsString("paymentUserCardHeadLine('Группы'", $html);
        $this->assertStringContainsString("paymentUserCardHeadLine('Телефон'", $html);
        $this->assertStringContainsString("paymentUserCardHeadLine('Email'", $html);
        $this->assertStringContainsString("paymentUserCardHeadLine('Дата рождения'", $html);
        $this->assertStringContainsString('peer-card-head-aside', $html);
        $this->assertStringContainsString('peer-card-name-row', $html);
        $this->assertStringContainsString('id="paymentUserCardPresence"', $html);
        $this->assertStringContainsString('id="paymentUserCardComment"', $html);
        $this->assertStringContainsString('paymentUserCardCommentSave', $html);
        $this->assertStringContainsString('/admin/user-cards', $html);
        $this->assertStringContainsString('errors.comment', $html);
        $this->assertStringContainsString('fa-desktop', $html);
        $this->assertStringContainsString('fa-mobile-screen', $html);
        $this->assertStringContainsString('flag-icon-', $html);
        $this->assertStringNotContainsString('Последний онлайн', $html);
        $this->assertStringContainsString('js-payment-user-card', $html);
        $this->assertStringContainsString('Email родителя', $html);
        $this->assertStringContainsString('Дата рождения', $html);
        $this->assertStringContainsString('Скидка', $html);
        $this->assertStringContainsString('Семейный аккаунт', $html);
        $this->assertStringContainsString('fa-users', $html);
        $this->assertStringContainsString('fa-layer-group', $html);
        $this->assertStringContainsString('data-kids-tooltip-hint', $html);
        $this->assertStringContainsString('#paymentUserCardModal .peer-card-icons .kids-tooltip-hint', $html);
        $this->assertStringContainsString('cursor: default', $html);
        $this->assertStringContainsString("scopes: ['hint']", $html);
        $this->assertStringContainsString('/admin/reports/payments/users', $html);
        $this->assertStringContainsString('!row.user_id', $html);
        $this->assertStringContainsString('KidsCrmUserCard.renderName', $html);

        $chatJs = (string) file_get_contents(resource_path('js/chat.js'));
        $this->assertStringNotContainsString('parent_email', $chatJs);
        $this->assertStringNotContainsString('paymentUserCardModal', $chatJs);
    }

    public function test_user_card_returns_chat_fields_plus_email_birthday_and_discount(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Иванова',
            'firstname' => 'Мария',
            'phone' => '+79991112233',
            'email' => 'parent@example.com',
        ]);

        $this->user->forceFill([
            'lastname' => 'Иванов',
            'name' => 'Петя',
            'email' => 'student@example.com',
            'phone' => '+79990001122',
            'birthday' => '2014-03-15',
            'discount_percent' => 10,
            'discount_comment' => 'Многодетные',
            'parent_id' => $parent->id,
        ])->save();

        $this->getJson(route('reports.payments.users.show', $this->user))
            ->assertOk()
            ->assertJsonPath('id', (int) $this->user->id)
            ->assertJsonPath('full_name', 'Иванов Петя')
            ->assertJsonPath('email', 'student@example.com')
            ->assertJsonPath('parent_email', 'parent@example.com')
            ->assertJsonPath('birthday', '15.03.2014')
            ->assertJsonPath('discount_percent', 10)
            ->assertJsonPath('discount_comment', 'Многодетные')
            ->assertJsonPath('parent_full_name', 'Иванова Мария')
            ->assertJsonPath('partner_name', $this->partner->title)
            ->assertJsonStructure([
                'avatar',
                'phone',
                'parent_phone',
                'last_seen_label',
                'team_title',
            ]);
    }

    public function test_user_card_puts_presence_flag_and_device_in_the_payload(): void
    {
        $this->app->instance(\App\Services\Geo\IpCountryResolver::class, new class implements \App\Services\Geo\IpCountryResolver
        {
            public function resolve(?string $ip): ?\App\Services\Geo\IpCountry
            {
                return match ($ip) {
                    '203.0.113.10' => new \App\Services\Geo\IpCountry('DE', 'Германия'),
                    default => null,
                };
            }
        });

        $seen = now()->subHours(3);
        $this->user->forceFill([
            'last_seen_at' => $seen,
            'last_activity_ip' => null,
            'last_activity_user_agent' => null,
        ])->save();

        MyLog::query()->create([
            'event' => AuditEvent::AuthLogin->value,
            'level' => AuditEvent::AuthLogin->level()->value,
            'description' => "Логин: Ученик, IP: 203.0.113.10\nПлатформа: Windows 10, Браузер: Chrome 120, \nУстройство: WebKit,ПК: Да, Моб. устройство: Нет, Планшет: Нет",
            'partner_id' => $this->partner->id,
            'author_id' => $this->user->id,
            'created_at' => now(),
        ]);

        $this->getJson(route('reports.payments.users.show', $this->user))
            ->assertOk()
            ->assertJsonPath('is_online', false)
            ->assertJsonPath('presence_label', 'Офлайн')
            ->assertJsonPath('presence_seen', $seen->timezone((string) config('app.timezone'))->format('d.m.Y H:i'))
            ->assertJsonPath('login_device', 'desktop')
            ->assertJsonPath('login_device_label', 'Компьютер')
            ->assertJsonPath('login_flags.0.code', 'de')
            ->assertJsonPath('login_flags.0.label', 'Германия, Chrome 120');
    }

    public function test_admin_can_read_and_save_comment(): void
    {
        $this->user->forceFill(['comment' => 'Старый комментарий'])->save();

        $this->getJson(route('reports.payments.users.show', $this->user))
            ->assertOk()
            ->assertJsonPath('can_edit_comment', true)
            ->assertJsonPath('comment', 'Старый комментарий');

        $this->patchJson(route('user-cards.comment.update', $this->user), [
            'comment' => '  Новый комментарий  ',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Комментарий сохранён.')
            ->assertJsonPath('comment', 'Новый комментарий');

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'comment' => 'Новый комментарий',
        ]);
        $this->assertDatabaseHas('my_logs', [
            'event' => AuditEvent::UserUpdated->value,
            'user_id' => $this->user->id,
        ]);

        $this->patchJson(route('user-cards.comment.update', $this->user), [
            'comment' => '   ',
        ])->assertOk()->assertJsonPath('comment', '');

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'comment' => null,
        ]);
    }

    public function test_comment_validation_error_is_under_the_field(): void
    {
        $response = $this->patchJson(route('user-cards.comment.update', $this->user), [
            'comment' => str_repeat('я', 5001),
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.comment.0', 'Поле «Комментарий» не должно превышать 5000 символов.');
        $this->assertStringNotContainsString('The comment field', (string) $response->getContent());
    }

    public function test_comment_is_hidden_without_users_comment_permission(): void
    {
        $this->user->forceFill(['comment' => 'Секрет'])->save();
        DB::table('permission_role')
            ->where('partner_id', $this->partner->id)
            ->where('role_id', $this->user->role_id)
            ->where('permission_id', $this->permissionId('users.comment'))
            ->delete();

        $this->getJson(route('reports.payments.users.show', $this->user))
            ->assertOk()
            ->assertJsonPath('can_edit_comment', false)
            ->assertJsonPath('comment', '');

        $this->patchJson(route('user-cards.comment.update', $this->user), [
            'comment' => 'Чужое',
        ])->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'comment' => 'Секрет',
        ]);
    }

    public function test_comment_of_another_partner_returns_russian_error(): void
    {
        $response = $this->patchJson(route('user-cards.comment.update', $this->foreignUser), [
            'comment' => 'Нет',
        ]);

        $response->assertForbidden();
        $response->assertJsonPath('errors.user.0', 'Нет доступа к комментарию этого пользователя.');
        $this->assertStringNotContainsString('This action is unauthorized.', (string) $response->getContent());
    }

    public function test_user_card_omits_discount_when_percent_is_empty(): void
    {
        $this->user->forceFill([
            'discount_percent' => null,
            'discount_comment' => 'не должна попасть',
            'birthday' => null,
            'email' => null,
        ])->save();

        $this->getJson(route('reports.payments.users.show', $this->user))
            ->assertOk()
            ->assertJsonPath('discount_percent', null)
            ->assertJsonPath('discount_comment', '')
            ->assertJsonPath('birthday', '')
            ->assertJsonPath('email', '')
            ->assertJsonPath('family_siblings', [])
            ->assertJsonPath('has_family_account', false)
            ->assertJsonPath('has_multiple_teams', false);
    }

    public function test_user_card_lists_enabled_siblings_and_flags_multiple_teams(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $student = $this->createUserWithRole('user', $this->partner, [
            'lastname' => 'Орлов',
            'name' => 'Илья',
            'parent_id' => $parent->id,
            'is_enabled' => true,
        ]);
        $this->createUserWithRole('user', $this->partner, [
            'lastname' => 'Орлова',
            'name' => 'Анна',
            'parent_id' => $parent->id,
            'is_enabled' => true,
        ]);
        $this->createUserWithRole('user', $this->partner, [
            'lastname' => 'Орлов',
            'name' => 'Скрытый',
            'parent_id' => $parent->id,
            'is_enabled' => false,
        ]);

        $sync = app(TeamUserSyncService::class);
        $first = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Младшая']);
        $second = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Старшая']);
        $sync->attachTeamForStudent($student, (int) $first->id);
        $sync->attachTeamForStudent($student, (int) $second->id);

        $this->getJson(route('reports.payments.users.show', $student))
            ->assertOk()
            ->assertJsonPath('has_family_account', true)
            ->assertJsonPath('family_siblings', ['Орлова Анна'])
            ->assertJsonPath('has_multiple_teams', true);
    }

    public function test_journal_name_uses_the_same_user_card_modal(): void
    {
        $student = $this->createUserWithRole('user', $this->partner, [
            'lastname' => 'Журналов',
            'name' => 'Семён',
            'is_enabled' => true,
        ]);

        $html = $this->get(route('schedule.index'))->assertOk()->getContent();
        $this->assertStringContainsString('class="schedule-user-card-name js-user-card"', $html);
        $this->assertStringContainsString('data-user-id="'.$student->id.'"', $html);
        $this->assertStringContainsString('id="paymentUserCardModal"', $html);
        $this->assertStringContainsString('/schedule/users', $html);

        $this->getJson(route('schedule.users.show', $student))
            ->assertOk()
            ->assertJsonPath('id', (int) $student->id)
            ->assertJsonPath('full_name', 'Журналов Семён');
    }

    public function test_schedule_user_card_allows_schedule_view_without_reports_view(): void
    {
        $student = $this->createUserWithRole('user', $this->partner, [
            'lastname' => 'Только',
            'name' => 'Журнал',
            'is_enabled' => true,
        ]);
        $actor = $this->createUserWithoutPermission('reports.view', $this->partner);
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('schedule.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($actor)->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->getJson(route('schedule.users.show', $student))
            ->assertOk()
            ->assertJsonPath('full_name', 'Только Журнал');
        $this->getJson(route('reports.payments.users.show', $student))->assertForbidden();
    }

    public function test_user_card_of_soft_deleted_student_is_still_available(): void
    {
        $this->user->delete();

        $this->getJson(route('reports.payments.users.show', $this->user->id))
            ->assertOk()
            ->assertJsonPath('id', (int) $this->user->id);
    }

    public function test_user_card_of_another_partner_returns_russian_error(): void
    {
        $response = $this->getJson(route('reports.payments.users.show', $this->foreignUser));

        $response->assertForbidden();
        $response->assertJsonPath('errors.user.0', 'Нет доступа к карточке этого пользователя.');
        $response->assertJsonPath('message', 'Нет доступа к карточке этого пользователя.');
        $this->assertStringNotContainsString('This action is unauthorized.', (string) $response->getContent());
    }

    public function test_missing_user_returns_not_found_error(): void
    {
        $this->getJson(route('reports.payments.users.show', 999999))
            ->assertNotFound()
            ->assertJsonPath('errors.user.0', 'Пользователь не найден.');
    }

    public function test_other_report_pages_include_the_same_user_card_modal(): void
    {
        foreach ([
            'reports.ltv.teams.view',
            'reports.ltv.locations.view',
            'reports.payment.intents.view',
        ] as $permission) {
            DB::table('permission_role')->insertOrIgnore([
                'partner_id' => $this->partner->id,
                'role_id' => $this->user->role_id,
                'permission_id' => $this->permissionId($permission),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $routes = [
            'reports.payments.monthly',
            'reports.ltv',
            'reports.ltv.teams',
            'reports.ltv.locations',
            'debts',
            'reports.payment-intents.index',
        ];

        foreach ($routes as $routeName) {
            $html = $this->get(route($routeName))->assertOk()->getContent();
            $this->assertStringContainsString('id="paymentUserCardModal"', $html, $routeName);
            $this->assertStringContainsString('KidsCrmUserCard.renderName', $html, $routeName);
            $this->assertSame(1, substr_count($html, 'id="paymentUserCardModal"'), $routeName);
        }
    }

    public function test_user_card_requires_reports_view(): void
    {
        $actor = $this->createUserWithoutPermission('reports.view', $this->partner);
        $this->actingAs($actor);

        $this->getJson(route('reports.payments.users.show', $this->user))->assertForbidden();
    }
}
