<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#users-contract-create-index совпадает с живым UX колонки «Договор»
 * на /admin/users: создать / черновик / PDF signed, lockUser, type actions.
 */
final class UsersContractCreateDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_create_from_clients_without_contradicting_live_ux(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="users-contract-create-index"', $html);
        $start = strpos($html, 'id="users-contract-create-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="kids-tooltip-contrast-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/admin/users', $chunk);
        $this->assertStringContainsString('contracts.view', $chunk);
        $this->assertStringContainsString('Создать договор', $chunk);
        $this->assertStringContainsString('js-open-create-contract-from-user', $chunk);
        $this->assertStringContainsString('Посмотреть черновик', $chunk);
        $this->assertStringContainsString('#0d6efd', $chunk);
        $this->assertStringContainsString('Создать ещё один договор', $chunk);
        $this->assertStringContainsString('fa-plus', $chunk);
        $this->assertStringContainsString('У черновика плюса нет', $chunk);
        $this->assertStringContainsString('не внутри PDF-ссылки', $chunk);
        $this->assertStringContainsString('lockUser: true', $chunk);
        $this->assertStringContainsString('user_id_locked', $chunk);
        $this->assertStringContainsString('create_contract_url', $chunk);
        $this->assertStringContainsString('<code>actions</code>, не <code>icon</code>', $chunk);
        $this->assertStringContainsString('на заявках его нет', $chunk);
        $this->assertStringContainsString('admin-users#user-contract-create', $chunk);
        $this->assertStringContainsString('AdminUsersContractCreateUxFeatureTest', $chunk);
        $this->assertStringContainsString('UsersContractCreateDocumentationContractTest', $chunk);

        $this->assertStringContainsString('не JSON 422', $chunk);
        $this->assertStringNotContainsString('серая PDF', $chunk);
        $this->assertStringNotContainsString('#6c757d', $chunk);
        $this->assertStringNotContainsString("type: 'icon'", $chunk);
    }

    public function test_related_doc_pages_link_announcement_and_keep_users_vs_leads_difference(): void
    {
        $users = $this->docFile('admin-users.html');
        $contracts = $this->docFile('contracts.html');
        $leads = $this->docFile('school-leads-widget.html');
        $partials = $this->docFile('reusable-ui-partials.html');

        $this->assertStringContainsString('/doc#users-contract-create-index', $users);
        $this->assertStringContainsString('id="user-contract-create"', $users);
        $this->assertStringContainsString('create_contract_url</code> в JSON списка пользователей <b>нет</b>', $users);
        $this->assertStringContainsString("тип <code>actions</code>", $users);
        $this->assertStringContainsString('«Создать договор» (<code>#btn-save</code>), не «Сохранить»', $users);
        $this->assertStringContainsString('Создать ещё один договор', $users);
        $this->assertStringContainsString('users-contract-add-btn', $users);
        $this->assertStringContainsString('У черновика плюса нет', $users);

        $this->assertStringContainsString('/doc#users-contract-create-index', $contracts);
        $this->assertStringContainsString('lockUser: true', $contracts);
        $this->assertStringContainsString('Footer модалки: «Отмена» и «Создать договор»', $contracts);
        $this->assertStringContainsString('Создать ещё один договор', $contracts);

        $this->assertStringContainsString('/doc#users-contract-create-index', $leads);
        $this->assertStringContainsString('create_contract_url', $leads);
        $this->assertStringContainsString('Создать ещё один договор', $leads);

        $this->assertStringContainsString('<code>actions</code> (договор: создать / черновик / PDF + плюс у signed)', $partials);
        $this->assertStringNotContainsString('<code>icon</code> (договор)', $partials);
    }

    public function test_catalog_and_controller_title_mention_create_from_clients(): void
    {
        $index = $this->docFile('index.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('id="users-contract-create-index"', $index);
        $this->assertStringContainsString('/doc#users-contract-create-index', $index);
        $this->assertStringContainsString('встроенная модалка, <code>lockUser</code>', $index);
        $this->assertStringContainsString('создание со списка клиентов и заявок', $index);

        $this->assertStringContainsString('договор (создать / черновик / иконка signed + плюс ещё один, встроенная модалка, lockUser)', $controller);
        $this->assertStringContainsString('создание со списка клиентов/заявок (lockUser)', $controller);
    }

    public function test_live_code_matches_documented_cell_and_lock_user(): void
    {
        $root = dirname(__DIR__, 3);
        $usersBlade = (string) file_get_contents($root.'/resources/views/admin/user.blade.php');
        $modal = (string) file_get_contents($root.'/resources/views/contracts/partials/create-modal.blade.php');
        $leads = (string) file_get_contents($root.'/resources/views/admin/school-leads/tabs/leads.blade.php');
        $contractsIndex = (string) file_get_contents($root.'/resources/views/contracts/index.blade.php');

        $this->assertStringContainsString('js-open-create-contract-from-user', $usersBlade);
        $this->assertStringContainsString('Создать договор', $usersBlade);
        $this->assertStringContainsString('Посмотреть черновик', $usersBlade);
        $this->assertStringContainsString('#0d6efd', $usersBlade);
        $this->assertStringContainsString("@include('partials.ui.tooltip-hint'", $usersBlade);
        $this->assertStringContainsString('users-signed-contract-hint-tpl', $usersBlade);
        $this->assertStringContainsString('users-contract-add-btn', $usersBlade);
        $this->assertStringContainsString('fa-plus', $usersBlade);
        $this->assertStringContainsString('Создать ещё один договор', $usersBlade);
        $this->assertStringContainsString('users-contract-cell', $usersBlade);
        $this->assertStringContainsString('data-kids-tooltip-hint', $usersBlade);
        $this->assertStringContainsString("scopes: ['hint']", $usersBlade);
        $this->assertStringContainsString("type: 'actions'", $usersBlade);
        $this->assertStringContainsString('{ lockUser: true }', $usersBlade);
        $this->assertStringNotContainsString("type: 'icon'", $usersBlade);

        $this->assertStringContainsString('id="btn-save" type="button" class="btn btn-primary">Создать договор</button>', $modal);
        $this->assertStringContainsString('setContractUserSelectLocked', $modal);
        $this->assertStringContainsString('user_id_locked', $modal);
        $this->assertStringContainsString('options.lockUser', $modal);

        $this->assertSame(
            2,
            substr_count($leads, 'KidsCrmContractCreate.openModal(preselected, { lockUser: true })')
        );
        $this->assertStringNotContainsString('users-contract-add-btn', $leads);
        $this->assertStringNotContainsString('Создать ещё один договор', $leads);
        $this->assertStringNotContainsString('lockUser: true', $contractsIndex);
    }

    public function test_docs_do_not_claim_create_contract_url_on_users_json(): void
    {
        $users = $this->docFile('admin-users.html');
        $index = $this->docFile('index.html');

        $this->assertStringContainsString('Поля <code>create_contract_url</code> в JSON списка пользователей <b>нет</b>', $users);
        $this->assertStringContainsString('ключа <code>create_contract_url</code> в JSON списка', $index);
        $this->assertStringNotContainsString('в JSON списка пользователей есть create_contract_url', $users);
        $this->assertStringNotContainsString('серая иконка PDF', $users);
        $this->assertStringNotContainsString('серая иконка PDF', $index);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
