<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\PermissionCapabilityHint;
use Tests\TestCase;

final class PermissionCapabilityHintTest extends TestCase
{
    public function test_empty_catalog_returns_empty_title(): void
    {
        config(['permission_capability_hints' => [
            'missing.permission' => [],
        ]]);

        $this->assertSame([], PermissionCapabilityHint::items('missing.permission'));
        $this->assertSame('', PermissionCapabilityHint::title('missing.permission'));
        $this->assertSame('', PermissionCapabilityHint::title('permission.that.does.not.exist'));
    }

    public function test_single_item_has_no_number(): void
    {
        config(['permission_capability_hints' => [
            'one.item' => ['Только одно место'],
        ]]);

        $this->assertSame('Только одно место', PermissionCapabilityHint::title('one.item'));
    }

    public function test_multiple_items_are_numbered_each_on_new_line(): void
    {
        config(['permission_capability_hints' => [
            'two.items' => ['Первое', 'Второе', 'Третье'],
        ]]);

        $this->assertSame(
            "1. Первое\n2. Второе\n3. Третье",
            PermissionCapabilityHint::title('two.items')
        );
    }

    public function test_blank_catalog_lines_are_skipped(): void
    {
        config(['permission_capability_hints' => [
            'blank.lines' => ['  ', 'Есть', ''],
        ]]);

        $this->assertSame(['Есть'], PermissionCapabilityHint::items('blank.lines'));
        $this->assertSame('Есть', PermissionCapabilityHint::title('blank.lines'));
    }

    public function test_every_permission_seeder_name_has_non_empty_catalog(): void
    {
        $seeder = (string) file_get_contents(database_path('seeders/PermissionSeeder.php'));
        preg_match_all("/^\\s*\\['name'\\s*=>\\s*'([^']+)'/m", $seeder, $matches);
        $names = $matches[1] ?? [];

        $this->assertNotEmpty($names);

        $catalog = require config_path('permission_capability_hints.php');
        $this->assertIsArray($catalog);

        $missing = [];
        $empty = [];
        foreach ($names as $name) {
            if (! array_key_exists($name, $catalog)) {
                $missing[] = $name;
                continue;
            }

            $items = PermissionCapabilityHint::items($name);
            if ($items === []) {
                $empty[] = $name;
            }
        }

        $this->assertSame([], $missing, 'В каталоге нет ключей: '.implode(', ', $missing));
        $this->assertSame([], $empty, 'Пустой каталог у прав: '.implode(', ', $empty));

        $extra = array_diff(array_keys($catalog), $names);
        $this->assertSame([], array_values($extra), 'Лишние ключи каталога: '.implode(', ', $extra));
    }

    public function test_dashboard_view_hint_lists_console_places(): void
    {
        $title = PermissionCapabilityHint::title('dashboard.view');

        $this->assertStringStartsWith('1. ', $title);
        $this->assertStringContainsString("\n2. ", $title);
        $this->assertStringContainsString('Консоль', $title);
        $this->assertStringContainsString('/cabinet', $title);
    }

    public function test_schedule_slots_view_hint_lists_auto_attendance_checkbox(): void
    {
        $title = PermissionCapabilityHint::title('scheduleSlots.view');

        $this->assertStringContainsString('Расписание школы', $title);
        $this->assertStringContainsString('Автосписание', $title);
        $this->assertStringContainsString('Разрешена заморозка', $title);
        $this->assertStringContainsString('Срок действия (дни)', $title);
        $this->assertStringContainsString('шаблона абонемента', $title);
    }
}
