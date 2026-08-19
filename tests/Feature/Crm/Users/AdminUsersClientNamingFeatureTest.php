<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users;

use App\Models\Role;
use App\Services\Users\UsersSectionTabsResolver;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Users\Concerns\GrantsUsersSectionPermissions;

/**
 * UX-нейминг: ученики (roles.name = user) — «Клиенты» / «Клиент».
 * Зонтик раздела, сайдбар и h4 остаются «Пользователи».
 *
 * UX-баг: переименовать только вкладку/тулбар недостаточно — при переключении
 * на тренеров/сотрудников заголовок не должен стать «Клиенты», а повторное
 * открытие /admin/users не должно сбрасывать копирайт обратно на «Пользователи»
 * в h1/вкладке.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see /docs/documentation/admin-users-section.html
 */
final class AdminUsersClientNamingFeatureTest extends CrmTestCase
{
    use GrantsUsersSectionPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    public function test_users_page_first_open_shows_clients_in_tab_and_toolbar_but_users_in_sidebar(): void
    {
        $this->asAdminWithUsersSection();

        $html = $this->get(route('admin.user1'))
            ->assertOk()
            ->getContent();

        $this->assertUsersPageClientNaming($html);
    }

    public function test_reopening_users_page_keeps_client_toolbar_and_does_not_rename_section_heading(): void
    {
        $this->asAdminWithUsersSection();

        $first = $this->get(route('admin.user1'))->assertOk()->getContent();
        $this->assertUsersPageClientNaming($first);

        $this->get(route('admin.trainers.index'))->assertOk();

        $again = $this->get(route('admin.user1'))->assertOk()->getContent();
        $this->assertUsersPageClientNaming($again);
    }

    public function test_trainers_tab_keeps_users_section_heading_and_does_not_force_clients_title(): void
    {
        $this->asAdminWithUsersSection();

        $html = $this->get(route('admin.trainers.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<p>Пользователи</p>', $html);
        $this->assertStringContainsString('>Пользователи</h4>', $html);
        $this->assertStringContainsString('>Тренеры</h1>', $html);
        $this->assertStringContainsString('>Клиенты</a>', $html);
        $this->assertStringNotContainsString('>Клиенты</h1>', $html);
        $this->assertStringContainsString('Создание тренера', $html);
        $this->assertStringContainsString('Редактирование тренера', $html);
        $this->assertStringNotContainsString('Создание клиента', $html);
        $this->assertStringNotContainsString('Редактирование клиента', $html);
    }

    public function test_administrators_tab_keeps_users_section_heading_and_staff_copy(): void
    {
        $this->asAdminWithUsersSection();

        $html = $this->get(route('admin.administrators.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<p>Пользователи</p>', $html);
        $this->assertStringContainsString('>Пользователи</h4>', $html);
        $this->assertStringContainsString('>Администратор</h1>', $html);
        $this->assertStringContainsString('>Администраторы</a>', $html);
        $this->assertStringContainsString('>Клиенты</a>', $html);
        $this->assertStringNotContainsString('>Клиенты</h1>', $html);
        $this->assertStringContainsString('Создание: Администратор', $html);
        $this->assertStringNotContainsString('Создание клиента', $html);
    }

    public function test_custom_fields_modal_stays_user_fields_not_client_fields(): void
    {
        $this->asAdminWithUsersSection();

        $html = $this->get(route('admin.user1'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Настройка пользовательских полей', $html);
        $this->assertStringContainsString('title="Настройка пользовательских полей"', $html);
        $this->assertStringNotContainsString('пользовательских клиентов', $html);
        $this->assertStringNotContainsString('клиентских полей', $html);
    }

    public function test_import_button_visible_only_with_users_import_permission(): void
    {
        $actor = $this->createUserWithoutPermission('users.import', $this->partner);
        $this->actingAs($actor);
        $this->grantUsersView($actor);

        $without = $this->get(route('admin.user1'))->assertOk()->getContent();
        $this->assertStringNotContainsString('title="Импорт учеников из Excel"', $without);

        $this->grantPermission($actor, 'users.import');
        $actor->unsetRelation('role');

        $with = $this->get(route('admin.user1'))->assertOk()->getContent();
        $this->assertStringContainsString('title="Импорт учеников из Excel"', $with);
        $this->assertStringContainsString('data-bs-target="#usersImportModal"', $with);
    }

    public function test_rules_page_shows_client_role_label_and_clients_permission_group(): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, 'settings.roles.view');

        $html = $this->get(route('admin.setting.rule'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Управление клиентами', $html);
        $this->assertTrue(
            (bool) preg_match('/<th[^>]*>\s*Клиент\s*</u', $html),
            'Колонка системной роли user должна называться «Клиент»'
        );
        $this->assertStringContainsString('Страница &quot;Пользователи&quot;', $html);
        $this->assertStringContainsString('users.view', $html);
        $this->assertStringNotContainsString('Управление пользователями', $html);
    }

    public function test_account_cabinet_shows_client_role_label_for_student(): void
    {
        $this->actingAs($this->user);

        $html = $this->get(route('account.user.edit'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Клиент', $html);
        $this->assertStringNotContainsString('card-header fw-semibold">Пользователь</div>', $html);
    }

    public function test_reports_filter_placeholder_stays_all_users_not_all_clients(): void
    {
        $path = resource_path('views/admin/report/payment_intents.blade.php');
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $this->assertStringContainsString('data-placeholder="Все пользователи"', $content);
        $this->assertStringNotContainsString('data-placeholder="Все клиенты"', $content);
    }

    public function test_student_role_label_in_database_is_client(): void
    {
        $role = Role::query()->where('name', 'user')->firstOrFail();
        $this->assertSame('Клиент', $role->label);
    }

    public function test_resolver_student_tab_label_is_clients_not_users(): void
    {
        $this->asAdminWithUsersSection();

        $tabs = app(UsersSectionTabsResolver::class)->resolve();
        $student = collect($tabs)->firstWhere('id', 'users');

        $this->assertNotNull($student);
        $this->assertSame('Клиенты', $student['label']);
        $this->assertSame('admin.user1', $student['route']);
    }

    private function asAdminWithUsersSection(): void
    {
        $this->asAdmin();
        $this->grantStaffSectionAccess($this->user);
        $this->grantTrainersView($this->user);
    }

    private function assertUsersPageClientNaming(string $html): void
    {
        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('<p>Пользователи</p>', $html);
        $this->assertStringContainsString('>Пользователи</h4>', $html);
        $this->assertStringContainsString('>Клиенты</h1>', $html);
        $this->assertStringContainsString('>Клиенты</a>', $html);
        $this->assertStringContainsString('>Все клиенты</option>', $html);
        $this->assertStringContainsString('id="new-user"', $html);
        $this->assertStringContainsString('data-bs-target="#createUserModal"', $html);
        $this->assertStringContainsString('id="createUserModalLabel">Создание клиента</h5>', $html);
        $this->assertStringContainsString('id="editUserModalLabel">Редактирование клиента</h5>', $html);
        $this->assertStringContainsString('Настройка пользовательских полей', $html);
        $this->assertStringNotContainsString('id="createUserModalLabel">Создание пользователя</h5>', $html);
        $this->assertStringNotContainsString('id="editUserModalLabel">Редактирование пользователя</h5>', $html);
        $this->assertStringNotContainsString('>Все пользователи</option>', $html);
    }
}
