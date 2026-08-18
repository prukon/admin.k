<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc и admin-locations §4 должны совпадать с UI объектов:
 * create/edit/delete — общий #kidsMainToast, не showSuccessModal.
 */
final class LocationsToastDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_location_create_edit_delete_toast(): void
    {
        $html = $this->docFile('index.html');
        $start = strpos($html, 'id="success-toast-instead-of-modal-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="admin-sidebar-stability-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('Объект создан', $chunk);
        $this->assertStringContainsString('Объект обновлён', $chunk);
        $this->assertStringContainsString('Объект успешно удалён.', $chunk);
        $this->assertStringContainsString('Объект удалён', $chunk);
        $this->assertStringContainsString('LocationsToastDocumentationContractTest', $chunk);
        $this->assertStringContainsString('#kidsMainToast', $chunk);
    }

    public function test_admin_locations_doc_describes_create_edit_delete_toast(): void
    {
        $html = $this->docFile('admin-locations.html');

        $this->assertStringContainsString('window.showToast', $html);
        $this->assertStringContainsString('Объект создан', $html);
        $this->assertStringContainsString('Объект обновлён', $html);
        $this->assertStringContainsString('Объект успешно удалён.', $html);
        $this->assertStringContainsString('showSuccessModal', $html);
        $this->assertStringContainsString('LocationsToastDocumentationContractTest', $html);
        $this->assertStringContainsString('hidden.bs.modal', $html);
        $this->assertStringContainsString('#confirmDeleteModal', $html);
    }

    public function test_locations_index_js_matches_documented_toasts(): void
    {
        $path = dirname(__DIR__, 3).'/resources/views/admin/locations/index.blade.php';
        $this->assertFileExists($path);
        $js = (string) file_get_contents($path);

        $this->assertStringContainsString("window.showToast(data.message || 'Объект создан', 'success')", $js);
        $this->assertStringContainsString("window.showToast(data.message || 'Объект обновлён', 'success')", $js);
        $this->assertStringContainsString("window.showToast('Объект успешно удалён.', 'success')", $js);
        $this->assertStringContainsString(
            "confirmEl.addEventListener('hidden.bs.modal', showDeletedToast, { once: true })",
            $js
        );
        $this->assertStringNotContainsString("showSuccessModal('Создание объекта'", $js);
        $this->assertStringNotContainsString("showSuccessModal('Редактирование объекта'", $js);
        $this->assertStringNotContainsString("showSuccessModal('Удаление объекта'", $js);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
