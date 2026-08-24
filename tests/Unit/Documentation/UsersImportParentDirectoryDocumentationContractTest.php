<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#users-import-parent-fill-index должен совпадать с §8 и аккордеоном импорта.
 */
final class UsersImportParentDirectoryDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_parent_fill_rules(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="users-import-parent-fill-index"', $html);
        $start = strpos($html, 'id="users-import-parent-fill-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="chat-emoji-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('дописывается', $chunk);
        $this->assertStringContainsString('не очищают', $chunk);
        $this->assertStringContainsString('не перезаписывает', $chunk);
        $this->assertStringContainsString('текущего партнёра', $chunk);
        $this->assertStringContainsString('users.import', $chunk);
        $this->assertStringContainsString('UsersImportParentDirectory', $chunk);
        $this->assertStringContainsString('admin-users#user-import', $chunk);
        $this->assertStringContainsString('user-import-parent-fill', $chunk);
        $this->assertStringContainsString('UsersImportFeatureTest', $chunk);
        $this->assertStringContainsString('UsersImportParentDirectoryFillFeatureTest', $chunk);
        $this->assertStringContainsString('UsersImportParentDirectoryFillFullAccessFeatureTest', $chunk);
        $this->assertStringContainsString('UsersImportAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('UsersImportNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('UsersImportParentDirectoryTest', $chunk);
        $this->assertStringContainsString('BladeInlineJsSyntaxTest', $chunk);
        $this->assertStringContainsString('Родитель #id', $chunk);
        $this->assertStringNotContainsString('привязка только при полном совпадении', $chunk);
    }

    public function test_admin_users_section_and_import_modal_match_fill_rules(): void
    {
        $users = $this->docFile('admin-users.html');
        $modal = $this->viewFile('admin/users/_import_modal.blade.php');

        $this->assertStringContainsString('id="user-import"', $users);
        $this->assertStringContainsString('id="user-import-parent-fill"', $users);
        $this->assertStringContainsString('дописывается</b> в пустое поле', $users);
        $this->assertStringContainsString('не очищают</b> карточку', $users);
        $this->assertStringContainsString('не перезаписывает', $users);
        $this->assertStringContainsString('fillEmptyFromRow', $users);
        $this->assertStringContainsString('Исключение — карточка родителя', $users);
        $this->assertStringContainsString('UsersImportParentDirectory', $users);
        $this->assertStringContainsString('/doc#users-import-parent-fill-index', $users);
        $this->assertStringContainsString('после дозаписи', $users);
        $this->assertStringContainsString('UsersImportParentDirectoryFillFeatureTest.php', $users);
        $this->assertStringContainsString('UsersImportParentDirectoryFillFullAccessFeatureTest.php', $users);
        $this->assertStringContainsString('errors.file', $users);
        $this->assertStringContainsString('resetImportModal', $users);
        $this->assertStringNotContainsString('Для совпадения со справочником <code>parents.phone</code> должен совпадать', $users);

        $this->assertStringContainsString('не трогают</b> справочник', $modal);
        $this->assertStringContainsString('дописываются</b> в пустые поля карточки', $modal);
        $this->assertStringNotContainsString('В справочнике — только при полном совпадении', $modal);

        $family = $this->docFile('parents-and-family-cabinet.html');
        $this->assertStringContainsString('/doc#users-import-parent-fill-index', $family);
        $this->assertStringContainsString('дописать</b> пустые ФИО/телефон', $family);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3) . '/docs/documentation/' . $name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    private function viewFile(string $relative): string
    {
        $path = dirname(__DIR__, 3) . '/resources/views/' . $relative;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
