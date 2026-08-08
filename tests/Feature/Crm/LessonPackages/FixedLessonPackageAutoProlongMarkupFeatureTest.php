<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Blade/разметка и JS-контракт UI автопролонгации (assignments + school schedule).
 *
 * @see SlotCreateModalMarkupFeatureTest
 * @see BladeInlineJsSyntaxTest
 */
final class FixedLessonPackageAutoProlongMarkupFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        foreach (['lessonPackages.view', 'setPrices.packageAssignments.view'] as $permission) {
            DB::table('permission_role')->insertOrIgnore([
                'partner_id' => $this->partner->id,
                'role_id' => $this->user->role_id,
                'permission_id' => $this->permissionId($permission),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_assignments_page_renders_auto_prolong_create_and_edit_controls(): void
    {
        $html = $this->get(route('admin.lesson-packages.assignments'))
            ->assertOk()
            ->assertSee('ulp_auto_prolong_wrap', false)
            ->assertSee('ulp_auto_prolong_enabled', false)
            ->assertSee('ulp-modal-auto-prolong-wrap', false)
            ->assertSee('ulp-modal-auto-prolong', false)
            ->assertSee('ulp-modal-auto-prolong-err', false)
            ->assertSee('Автопролонгация', false)
            ->getContent();

        $this->assertNotSame('', trim($html));

        // Начальное состояние: блоки автопролонга скрыты (d-none), пока не выбран fixed / не загружена модалка.
        $this->assertTrue(
            (bool) preg_match('/id=["\']ulp_auto_prolong_wrap["\'][^>]*\bd-none\b|\bclass=["\'][^"\']*\bd-none\b[^"\']*["\'][^>]*id=["\']ulp_auto_prolong_wrap["\']/', $html),
            'ulp_auto_prolong_wrap должен быть скрыт (d-none) при первом открытии'
        );
        $this->assertTrue(
            (bool) preg_match('/id=["\']ulp-modal-auto-prolong-wrap["\'][^>]*\bd-none\b|\bclass=["\'][^"\']*\bd-none\b[^"\']*["\'][^>]*id=["\']ulp-modal-auto-prolong-wrap["\']/', $html),
            'ulp-modal-auto-prolong-wrap должен быть скрыт (d-none) при первом открытии'
        );

        // JS-правила: показ только для fixed + отправка флага + ошибки под полем.
        $this->assertStringContainsString('syncAutoProlongVisibility', $html);
        $this->assertStringContainsString("scheduleType === 'fixed'", $html);
        $this->assertStringContainsString('body.auto_prolong_enabled', $html);
        $this->assertStringContainsString('payload.errors.auto_prolong_enabled', $html);
        $this->assertStringContainsString('auto_prolong_editable', $html);
        $this->assertStringContainsString('ulpTypeRender', $html);
        $this->assertStringContainsString('auto_prolong_badge_label', $html);
        $this->assertStringContainsString('blocked_reason', $html);
        $this->assertStringContainsString("context: 'assign'", $html);
    }

    public function test_assignments_page_hides_create_modal_when_user_lacks_package_assignments_permission(): void
    {
        $denied = $this->createUserWithoutPermission('setPrices.packageAssignments.view', $this->partner);
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $denied->role_id,
            'permission_id' => $this->permissionId('lessonPackages.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($denied);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        // Без setPrices.packageAssignments.view вкладка/CRUD закрыты → 403 на страницу назначений.
        $this->get(route('admin.lesson-packages.assignments'))
            ->assertForbidden();
    }

    public function test_school_schedule_select2_preserves_blocked_reason_from_users_search(): void
    {
        $html = $this->get(route('admin.lesson-packages.school-schedule'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('schoolCalSlotUserSelect', $html);
        $this->assertStringContainsString('blocked_reason', $html);
        $this->assertStringContainsString('item.blocked', $html);
        $this->assertStringContainsString('templateResult', $html);
        // Текст причины приходит через @json (кириллица может быть \uXXXX) — проверяем присвоение.
        $this->assertMatchesRegularExpression('/\bblockReason\s*=/', $html);
    }
}
