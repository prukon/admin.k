<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\Location;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Разметка модалки «Добавить слот»: селект объекта (0/1/2+), автовыбор единственной группы, кнопки.
 *
 * @see SchoolScheduleLocationFilterFeatureTest
 * @see docs/documentation/school-schedule-calendar.html
 */
final class SlotCreateModalMarkupFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    private function grantPermission(string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function grantCreateModalPermissions(): void
    {
        foreach ([
            'lessonPackages.view',
            'locations.view',
            'scheduleSlots.view',
            'scheduleSlots.manage',
        ] as $permission) {
            $this->grantPermission($permission);
        }
    }

    private function createModalHtml(string $pageHtml): string
    {
        $createFormPos = strpos($pageHtml, 'id="slotCreateForm"');
        $this->assertNotFalse($createFormPos);
        $editFormPos = strpos($pageHtml, 'id="slotEditForm"');
        $this->assertNotFalse($editFormPos);

        return substr($pageHtml, (int) $createFormPos, (int) $editFormPos - (int) $createFormPos);
    }

    private function selectInnerHtml(string $html, string $name): string
    {
        $matched = preg_match(
            '/name="' . preg_quote($name, '/') . '"[^>]*>([\s\S]*?)<\/select>/',
            $html,
            $matches
        );
        $this->assertSame(1, $matched, "Не найден select name=\"{$name}\"");

        return $matches[1];
    }

    private function assertOptionSelected(string $selectInnerHtml, string $value): void
    {
        $this->assertMatchesRegularExpression(
            '/<option[^>]*value="' . preg_quote($value, '/') . '"[^>]*selected/',
            $selectInnerHtml,
            "Ожидался selected у option value=\"{$value}\""
        );
    }

    private function assertOptionNotSelected(string $selectInnerHtml, string $value): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/<option[^>]*value="' . preg_quote($value, '/') . '"[^>]*selected/',
            $selectInnerHtml,
            "Не ожидался selected у option value=\"{$value}\""
        );
    }

    public function test_hides_location_select_when_partner_has_zero_enabled_locations(): void
    {
        $this->grantCreateModalPermissions();

        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => false,
            'name' => 'Выключенный объект',
        ]);

        $html = $this->get(route('admin.lesson-packages.school-schedule'))
            ->assertOk()
            ->getContent();

        $create = $this->createModalHtml($html);
        $this->assertStringNotContainsString('js-slot-location-select', $create);
        $this->assertStringContainsString('js-slot-team-select', $create);
        $this->assertStringContainsString('>Отмена</button>', $create);
        $this->assertStringContainsString('id="slotCreateSubmit">Добавить</button>', $create);
    }

    public function test_preselects_sole_enabled_location_and_keeps_select_visible(): void
    {
        $this->grantCreateModalPermissions();

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
            'name' => 'Единственный объект',
        ]);
        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => false,
            'name' => 'Выключенный',
        ]);

        $html = $this->get(route('admin.lesson-packages.school-schedule'))
            ->assertOk()
            ->getContent();

        $create = $this->createModalHtml($html);
        $this->assertStringContainsString('js-slot-location-select', $create);
        $locOpts = $this->selectInnerHtml($create, 'location_id');
        $this->assertOptionSelected($locOpts, (string) $location->id);
        $this->assertOptionNotSelected($locOpts, '');
    }

    public function test_with_two_enabled_locations_defaults_to_all_not_a_specific_object(): void
    {
        $this->grantCreateModalPermissions();

        $a = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
            'name' => 'Объект A',
        ]);
        $b = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
            'name' => 'Объект B',
        ]);

        $html = $this->get(route('admin.lesson-packages.school-schedule'))
            ->assertOk()
            ->getContent();

        $create = $this->createModalHtml($html);
        $this->assertStringContainsString('js-slot-location-select', $create);
        $this->assertStringContainsString('Объект A', $create);
        $this->assertStringContainsString('Объект B', $create);
        $locOpts = $this->selectInnerHtml($create, 'location_id');
        $this->assertOptionSelected($locOpts, '');
        $this->assertOptionNotSelected($locOpts, (string) $a->id);
        $this->assertOptionNotSelected($locOpts, (string) $b->id);
    }

    public function test_preselects_sole_team_for_default_selected_location(): void
    {
        $this->grantCreateModalPermissions();

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Единственная группа объекта',
            'location_id' => $location->id,
        ]);
        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа без объекта',
            'location_id' => null,
        ]);

        $html = $this->get(route('admin.lesson-packages.school-schedule'))
            ->assertOk()
            ->getContent();

        $create = $this->createModalHtml($html);
        $this->assertOptionSelected($this->selectInnerHtml($create, 'team_id'), (string) $team->id);
    }

    public function test_preselects_sole_team_when_no_location_select(): void
    {
        $this->grantCreateModalPermissions();

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Единственная группа партнёра',
            'location_id' => null,
        ]);

        $html = $this->get(route('admin.lesson-packages.school-schedule'))
            ->assertOk()
            ->getContent();

        $create = $this->createModalHtml($html);
        $this->assertStringNotContainsString('js-slot-location-select', $create);
        $this->assertOptionSelected($this->selectInnerHtml($create, 'team_id'), (string) $team->id);
    }

    public function test_does_not_preselect_team_when_multiple_teams_match_default_filter(): void
    {
        $this->grantCreateModalPermissions();

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);
        $teamA = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа 1',
            'location_id' => $location->id,
        ]);
        $teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа 2',
            'location_id' => $location->id,
        ]);

        $html = $this->get(route('admin.lesson-packages.school-schedule'))
            ->assertOk()
            ->getContent();

        $create = $this->createModalHtml($html);
        $teamOpts = $this->selectInnerHtml($create, 'team_id');
        $this->assertOptionSelected($teamOpts, '');
        $this->assertOptionNotSelected($teamOpts, (string) $teamA->id);
        $this->assertOptionNotSelected($teamOpts, (string) $teamB->id);
    }

    public function test_without_locations_view_hides_location_select_but_keeps_create_buttons(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('scheduleSlots.view');
        $this->grantPermission('scheduleSlots.manage');

        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        $html = $this->get(route('admin.lesson-packages.school-schedule'))
            ->assertOk()
            ->getContent();

        $create = $this->createModalHtml($html);
        $this->assertStringNotContainsString('js-slot-location-select', $create);
        $this->assertStringContainsString('js-slot-team-select', $create);
        $this->assertStringContainsString('>Отмена</button>', $create);
        $this->assertStringContainsString('id="slotCreateSubmit">Добавить</button>', $create);
    }

    public function test_edit_modal_buttons_remain_close_and_save(): void
    {
        $this->grantCreateModalPermissions();

        $html = $this->get(route('admin.lesson-packages.school-schedule'))
            ->assertOk()
            ->getContent();

        $editPos = strpos($html, 'id="slotEditModal"');
        $this->assertNotFalse($editPos);
        $editChunk = substr($html, (int) $editPos, 8000);

        $this->assertStringContainsString('>Закрыть</button>', $editChunk);
        $this->assertStringContainsString('id="slotEditSubmit">Сохранить</button>', $editChunk);
        $this->assertStringNotContainsString('id="slotCreateSubmit"', $editChunk);
    }
}
