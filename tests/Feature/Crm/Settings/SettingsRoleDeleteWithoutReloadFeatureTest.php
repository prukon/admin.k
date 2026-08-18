<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Settings;

use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Ui\Concerns\SuccessToastInsteadOfModalTestHelpers;

/**
 * Удаление кастомной роли на «Настройки → Права и роли» без перезагрузки:
 * toast вместо success-модалки, колонка прав и строка в #rolesTable снимаются на клиенте.
 *
 * UX-баг прода: после DELETE открывалась success-модалка и сразу location.reload() —
 * лишний клик «OK» и полная перезагрузка страницы.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SettingsRoleDeleteWithoutReloadFeatureTest extends CrmTestCase
{
    use SuccessToastInsteadOfModalTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    public function test_guest_is_denied_on_role_delete_and_never_gets_500(): void
    {
        $roleId = $this->createCustomRoleViaAjax();
        Auth::logout();

        $web = $this->delete(route('admin.setting.role.delete'), [
            '_token'  => csrf_token(),
            'role_id' => $roleId,
        ]);
        $this->assertContains(
            $web->getStatusCode(),
            [302, 401, 403, 419],
            'Гость web DELETE роли → '.$web->getStatusCode()
        );
        $this->assertNotSame(500, $web->getStatusCode());
        $this->assertNotSame(200, $web->getStatusCode(), 'Гость не должен удалить роль');

        $json = $this->deleteJson(route('admin.setting.role.delete'), [
            'role_id' => $roleId,
        ], $this->ajaxHeaders());
        $this->assertContains(
            $json->getStatusCode(),
            [302, 401, 403, 419],
            'Гость JSON DELETE роли → '.$json->getStatusCode()
        );
        $this->assertNotSame(500, $json->getStatusCode());
        $this->assertNotSame(200, $json->getStatusCode());

        $this->assertDatabaseHas('roles', ['id' => $roleId]);
    }

    public function test_manager_without_roles_view_gets_403_on_role_delete_and_role_stays(): void
    {
        $this->asAdmin();
        $roleId = $this->createCustomRoleViaAjax();

        $actor = $this->createUserWithoutPermission('settings.roles.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->deleteJson(route('admin.setting.role.delete'), [
            'role_id' => $roleId,
        ], $this->ajaxHeaders())->assertForbidden();

        $web = $this->delete(route('admin.setting.role.delete'), [
            '_token'  => csrf_token(),
            'role_id' => $roleId,
        ]);
        $this->assertSame(403, $web->getStatusCode(), 'Без settings.roles.view web DELETE → 403');

        $this->assertDatabaseHas('roles', ['id' => $roleId]);
    }

    public function test_authorized_admin_deletes_custom_role_via_ajax_and_gets_success_json(): void
    {
        $this->asAdminWith(['settings.roles.view']);
        $roleId = $this->createCustomRoleViaAjax();

        $this->deleteJson(route('admin.setting.role.delete'), [
            'role_id' => $roleId,
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('roles', ['id' => $roleId]);
    }

    public function test_ajax_role_delete_without_id_returns_422_on_role_id_field(): void
    {
        $this->asAdminWith(['settings.roles.view']);

        $this->deleteJson(route('admin.setting.role.delete'), [], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['role_id']);
    }

    public function test_ajax_role_delete_unknown_id_returns_422_on_role_id_field(): void
    {
        $this->asAdminWith(['settings.roles.view']);

        $this->deleteJson(route('admin.setting.role.delete'), [
            'role_id' => 999999999,
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['role_id']);
    }

    public function test_ajax_role_delete_rejects_system_role_with_message_for_error_modal(): void
    {
        $this->asAdminWith(['settings.roles.view']);
        $systemRoleId = (int) Role::query()->where('is_sistem', 1)->value('id');
        $this->assertGreaterThan(0, $systemRoleId);

        $this->deleteJson(route('admin.setting.role.delete'), [
            'role_id' => $systemRoleId,
        ], $this->ajaxHeaders())
            ->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonFragment(['message' => 'Нельзя удалять системную роль!']);

        $this->assertDatabaseHas('roles', ['id' => $systemRoleId]);
    }

    public function test_non_ajax_role_delete_persists_and_is_not_empty_success(): void
    {
        $this->asAdminWith(['settings.roles.view']);
        $roleId = $this->createCustomRoleViaAjax();

        $response = $this->from(route('admin.setting.rule'))
            ->delete(route('admin.setting.role.delete'), [
                '_token'  => csrf_token(),
                'role_id' => $roleId,
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [200, 302]);
        if ($response->getStatusCode() === 200) {
            $this->assertNotSame('', trim((string) $response->getContent()), 'non-AJAX DELETE роли → пустой 200');
            $response->assertJsonPath('success', true);
        }

        $this->assertDatabaseMissing('roles', ['id' => $roleId]);
    }

    public function test_non_ajax_role_delete_without_id_redirects_with_role_id_field_error(): void
    {
        $this->asAdminWith(['settings.roles.view']);

        $response = $this->from(route('admin.setting.rule'))
            ->delete(route('admin.setting.role.delete'), [
                '_token' => csrf_token(),
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Валидация role_id не должна давать успешный 200');
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['role_id']);
    }

    public function test_wrong_http_methods_on_role_delete_never_return_500_or_empty_200(): void
    {
        $this->asAdminWith(['settings.roles.view']);
        $url = route('admin.setting.role.delete');

        foreach (['GET', 'POST', 'PUT', 'PATCH'] as $method) {
            foreach ([true, false] as $asJson) {
                $response = $asJson
                    ? $this->json($method, $url, ['role_id' => 1])
                    : $this->call($method, $url, ['_token' => csrf_token(), 'role_id' => 1]);

                $label = ($asJson ? 'JSON' : 'web')." {$method} {$url}";
                $this->assertNotSame(500, $response->getStatusCode(), "{$label} → 500");
                $this->assertContains(
                    $response->getStatusCode(),
                    [200, 302, 401, 403, 404, 405, 419, 422],
                    "{$label} → {$response->getStatusCode()}"
                );
                if ($response->getStatusCode() === 200 && in_array($method, ['GET', 'PUT', 'PATCH'], true)) {
                    $this->assertNotSame('', trim((string) $response->getContent()), "{$label} пустой 200");
                }
            }
        }
    }

    public function test_rules_page_first_open_shows_custom_role_delete_button_and_matrix_column_id(): void
    {
        $this->asAdminWith(['settings.roles.view']);
        $roleId = $this->createCustomRoleViaAjax();
        $adminId = (int) Role::query()->where('name', 'admin')->value('id');
        $superadminId = (int) Role::query()->where('name', 'superadmin')->value('id');

        $html = $this->get(route('admin.setting.rule'))
            ->assertOk()
            ->getContent();

        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('id="createRoleModal"', $html);
        $this->assertStringContainsString('id="rolesTable"', $html);
        $this->assertStringContainsString('id="permission-accordion"', $html);
        $this->assertStringContainsString('id="kidsMainToast"', $html);
        $this->assertStringContainsString('z-index: 4050', $html);
        $this->assertMatchesRegularExpression(
            '/id="kidsMainToastBody"><\/div>/',
            $html,
            'При первом открытии тело всплывайки пустое'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/id="kidsMainToast"[^>]*\bshow\b/',
            $html,
            'Всплывайка не должна быть уже показана'
        );

        $this->assertMatchesRegularExpression(
            '/<th[^>]*data-role-id="'.$roleId.'"/',
            $html,
            'Колонка кастомной роли в матрице прав приходит с data-role-id — иначе AJAX-удаление не снимет колонку'
        );
        $this->assertMatchesRegularExpression(
            '/<tr[^>]*data-role-id="'.$roleId.'"/',
            $html,
            'Строка кастомной роли в #rolesTable с data-role-id'
        );
        $this->assertTrue(
            (bool) preg_match(
                '/delete-role[^>]*data-role-id="'.$roleId.'"|data-role-id="'.$roleId.'"[^>]*delete-role/',
                $html
            ),
            'У кастомной роли есть кнопка удаления'
        );

        $this->assertGreaterThan(0, $adminId);
        $matchedAdminRow = preg_match(
            '/<tr[^>]*data-role-id="'.$adminId.'"(.*?)<\/tr>/s',
            $html,
            $adminRow
        );
        $this->assertSame(1, $matchedAdminRow, 'Строка системной роли admin есть в #rolesTable');
        $this->assertStringContainsString('Системная роль', $adminRow[1]);
        $this->assertStringNotContainsString('delete-role', $adminRow[1], 'Системную роль нельзя удалить из UI');

        if ($superadminId > 0) {
            $this->assertStringNotContainsString(
                'data-role-id="'.$superadminId.'"',
                $html,
                'Колонка superadmin в матрице и списке не показывается'
            );
        }

        $modalPos = strpos($html, 'id="createRoleModal"');
        $layoutSuccessJsPos = strpos($html, 'function showSuccessModal');
        $toastPos = strpos($html, 'id="kidsMainToast"');
        $this->assertNotFalse($modalPos);
        $this->assertNotFalse($layoutSuccessJsPos);
        $this->assertNotFalse($toastPos);
        $this->assertLessThan($layoutSuccessJsPos, $modalPos);
        $this->assertLessThan(
            $toastPos,
            $layoutSuccessJsPos,
            'Toast в layout после модалки ролей: повторное открытие «Настройки» не пересобирает всплывайку'
        );
    }

    public function test_delete_success_uses_toast_and_dom_update_instead_of_reload_modal(): void
    {
        $js = (string) file_get_contents(resource_path('views/admin/setting/rule.blade.php'));

        $this->assertStringNotContainsString(
            'showSuccessModal("Удаление роли"',
            $js,
            'UX-баг: success-модалка требовала лишний клик OK'
        );
        $this->assertStringNotContainsString(
            'location.reload();',
            $js,
            'UX-баг: полная перезагрузка после удаления роли'
        );
        $this->assertStringContainsString("window.showToast('Роль успешно удалена.', 'success')", $js);

        $deleteHandlerPos = strpos($js, "$(document).on('click', '.delete-role'");
        $this->assertNotFalse($deleteHandlerPos);
        $handler = substr($js, $deleteHandlerPos, 1800);
        $this->assertStringContainsString('showConfirmDeleteModal', $handler, 'Подтверждение удаления остаётся');
        $this->assertStringContainsString('removeRoleColumnFromPermissionTables(roleId)', $handler);
        $this->assertStringContainsString('removeRoleFromRolesTable(roleId)', $handler);
        $removeColPos = strpos($handler, 'removeRoleColumnFromPermissionTables(roleId)');
        $removeRowPos = strpos($handler, 'removeRoleFromRolesTable(roleId)');
        $toastPos = strpos($handler, "window.showToast('Роль успешно удалена.', 'success')");
        $this->assertNotFalse($removeColPos);
        $this->assertNotFalse($removeRowPos);
        $this->assertNotFalse($toastPos);
        $this->assertLessThan($removeRowPos, $removeColPos);
        $this->assertLessThan($toastPos, $removeRowPos);
        $this->assertStringContainsString('eroorRespone', $handler, 'Ошибка DELETE — модалка ошибки, не toast');
    }

    public function test_create_js_path_stamps_role_id_so_delete_without_reload_can_remove_column(): void
    {
        $js = (string) file_get_contents(resource_path('views/admin/setting/rule.blade.php'));

        $appendPos = strpos($js, 'function appendRoleColumnToPermissionTables');
        $this->assertNotFalse($appendPos);
        $appendChunk = substr($js, $appendPos, 1400);
        $this->assertStringContainsString(
            ".attr('data-role-id', roleId)",
            $appendChunk,
            'Создание роли без reload должно ставить data-role-id на th — иначе удаление только что созданной роли оставит колонку'
        );
        $this->assertStringContainsString(".attr('data-role-id', role.id)", $js);

        $removePos = strpos($js, 'function removeRoleColumnFromPermissionTables');
        $this->assertNotFalse($removePos);
        $removeChunk = substr($js, $removePos, 1100);
        $this->assertStringContainsString('thead th[data-role-id="', $removeChunk);
        $this->assertStringContainsString('$th.index()', $removeChunk);
        $this->assertLessThan($removePos, $appendPos);

        $tableRemovePos = strpos($js, 'function removeRoleFromRolesTable');
        $this->assertNotFalse($tableRemovePos);
        $tableChunk = substr($js, $tableRemovePos, 500);
        $this->assertStringContainsString('tr[data-role-id="', $tableChunk);
        $this->assertStringContainsString('.text(String(i + 1))', $tableChunk, 'После удаления номера строк в #rolesTable пересчитываются');
    }

    private function createCustomRoleViaAjax(): int
    {
        $this->asAdminWith(['settings.roles.view']);

        $create = $this->postJson(route('admin.setting.role.create'), [
            'name' => 'Удалить без reload '.uniqid('', true),
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);

        $roleId = (int) $create->json('role.id');
        $this->assertGreaterThan(0, $roleId);

        return $roleId;
    }
}
