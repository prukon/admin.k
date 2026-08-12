<?php

namespace Tests\Feature\Crm\Cabinet;

use App\Models\Location;
use App\Models\ParentProfile;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamUserSyncService;
use App\Services\Users\FamilyStudentContextService;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Blade/разметка модалки «Добавить группу» и UX-правила видимости/содержимого.
 *
 * @see /docs/documentation/dashboard-cabinet.html#cabinet-attach-team
 */
final class CabinetTeamAttachMarkupFeatureTest extends CrmTestCase
{
    private TeamUserSyncService $sync;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sync = app(TeamUserSyncService::class);

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    public function test_modal_renders_fields_in_agreed_order_with_cancel_and_add_buttons(): void
    {
        [$student, $current, $candidate, $location] = $this->seedEligible();
        $this->grantPermission($student, 'account.user.team.update');
        $this->actingAs($student);

        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $modalStart = mb_strpos($html, 'id="cabinetAttachTeamModal"');
        $this->assertNotFalse($modalStart);
        $modalHtml = mb_substr($html, $modalStart, 3500);

        $posFio = mb_strpos($modalHtml, 'ФИО ученика');
        $posCurrent = mb_strpos($modalHtml, 'Текущая группа');
        $posObject = mb_strpos($modalHtml, 'Объект');
        $posNew = mb_strpos($modalHtml, 'Новая группа');
        $posCancel = mb_strpos($modalHtml, '>Отмена<');
        $posAdd = mb_strpos($modalHtml, 'id="cabinetAttachTeamSubmit"');

        $this->assertNotFalse($posFio);
        $this->assertNotFalse($posCurrent);
        $this->assertNotFalse($posObject);
        $this->assertNotFalse($posNew);
        $this->assertNotFalse($posCancel);
        $this->assertNotFalse($posAdd);

        $this->assertTrue($posFio < $posCurrent);
        $this->assertTrue($posCurrent < $posObject);
        $this->assertTrue($posObject < $posNew);
        $this->assertTrue($posNew < $posCancel);
        $this->assertTrue($posCancel < $posAdd);

        $this->assertStringContainsString((string) ($student->full_name ?: $student->name), $modalHtml);
        $this->assertStringContainsString((string) $current->title, $modalHtml);
        $this->assertStringContainsString((string) $location->name, $modalHtml);
        $this->assertStringContainsString('option value="'.$candidate->id.'"', $modalHtml);
        $this->assertStringContainsString('data-error-for="team_id"', $modalHtml);
        $this->assertStringContainsString('modal-dialog', $modalHtml);
        $this->assertStringNotContainsString('modal-fullscreen', $modalHtml);
    }

    public function test_add_button_is_rendered_under_role_before_family_switcher(): void
    {
        $parent = ParentProfile::factory()->create(['partner_id' => $this->partner->id]);
        $brother1 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'parent_id' => $parent->id,
            'lastname' => 'Брат',
            'name' => 'Один',
            'is_enabled' => 1,
        ]);
        $brother2 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'parent_id' => $parent->id,
            'lastname' => 'Брат',
            'name' => 'Два',
            'is_enabled' => 1,
        ]);

        $location = Location::factory()->forPartner((int) $this->partner->id)->create(['name' => 'Семейный markup']);
        $teamA = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
            'title' => 'MU-A',
            'is_enabled' => 1,
        ]);
        $teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
            'title' => 'MU-B',
            'is_enabled' => 1,
        ]);
        $this->sync->attachTeamForStudent($brother1, (int) $teamA->id);

        $this->grantPermission($brother1, 'dashboard.view');
        $this->grantPermission($brother1, 'account.user.team.update');
        $this->actingAs($brother1);

        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $posRole = mb_strpos($html, 'Роль:');
        $posBtn = mb_strpos($html, 'cabinet-attach-team-trigger');
        $posFamily = mb_strpos($html, 'family-student-switcher');

        $this->assertNotFalse($posRole);
        $this->assertNotFalse($posBtn);
        $this->assertNotFalse($posFamily);
        $this->assertTrue($posRole < $posBtn, 'Блок группы должен быть под строкой «Роль»');
        $this->assertTrue($posBtn < $posFamily, 'Блок группы должен быть выше семейного переключателя');
        unset($brother2, $teamB);
    }

    public function test_sidebar_shows_current_group_names_and_change_link(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => 1,
        ]);
        $location = Location::factory()->forPartner((int) $this->partner->id)->create(['name' => 'Объект UI']);
        $teamA = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
            'title' => 'АльфаГруппа',
            'is_enabled' => 1,
        ]);
        $teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
            'title' => 'БетаГруппа',
            'is_enabled' => 1,
        ]);
        $teamC = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
            'title' => 'ГаммаКандидат',
            'is_enabled' => 1,
        ]);
        $this->sync->attachTeamForStudent($student, (int) $teamA->id);
        $this->sync->attachTeamForStudent($student, (int) $teamB->id);

        $this->grantPermission($student, 'dashboard.view');
        $this->grantPermission($student, 'account.user.team.update');
        $this->actingAs($student);

        $html = $this->get(route('dashboard'))->assertOk()->getContent();
        $triggerStart = mb_strpos($html, 'cabinet-attach-team-trigger');
        $this->assertNotFalse($triggerStart);
        $chunk = mb_substr($html, $triggerStart, 800);

        $this->assertStringContainsString('Группа:', $chunk);
        $this->assertStringContainsString('dt-cell-ellipsis', $chunk);
        $this->assertStringContainsString('js-dt-cell-ellipsis-tooltip', $chunk);
        $this->assertStringContainsString('data-dt-ellipsis-title="', $chunk);
        $this->assertStringContainsString('АльфаГруппа', $chunk);
        $this->assertStringContainsString('БетаГруппа', $chunk);
        $this->assertStringContainsString('>изменить</a>', $chunk);
        $this->assertStringContainsString('data-bs-target="#cabinetAttachTeamModal"', $chunk);
        $this->assertStringNotContainsString('ГаммаКандидат', $chunk);
    }

    public function test_cabinet_profile_group_row_shows_pencil_opening_same_modal(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => 1,
        ]);
        $location = Location::factory()->forPartner((int) $this->partner->id)->create(['name' => 'Объект карандаш']);
        $current = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
            'title' => 'ГруппаКарандаш',
            'is_enabled' => 1,
        ]);
        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
            'title' => 'КандидатКарандаш',
            'is_enabled' => 1,
        ]);
        $this->sync->attachTeamForStudent($student, (int) $current->id);

        $this->grantPermission($student, 'dashboard.view');
        $this->grantPermission($student, 'account.user.team.update');
        $this->actingAs($student);

        $html = $this->get(route('dashboard'))->assertOk()->getContent();
        $groupStart = mb_strpos($html, 'class="group"');
        $this->assertNotFalse($groupStart);
        $chunk = mb_substr($html, $groupStart, 900);

        $this->assertStringContainsString('cabinet-attach-team-pencil', $chunk);
        $this->assertStringContainsString('fa-pen', $chunk);
        $this->assertStringContainsString('data-bs-target="#cabinetAttachTeamModal"', $chunk);
        $this->assertTrue(
            mb_strpos($chunk, 'group-value') < mb_strpos($chunk, 'cabinet-attach-team-pencil'),
            'Карандаш должен быть справа от .group-value'
        );

        $this->revokePermission($student, 'account.user.team.update');
        $student->unsetRelation('role');
        $htmlNoPerm = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('cabinet-attach-team-pencil', $htmlNoPerm);
    }

    public function test_select_excludes_current_disabled_and_soft_deleted_teams(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => 1,
        ]);
        $location = Location::factory()->forPartner((int) $this->partner->id)->create(['name' => 'Фильтр групп']);
        $current = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
            'title' => 'УжеЕсть',
            'is_enabled' => 1,
        ]);
        $ok = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
            'title' => 'ДоступнаОк',
            'is_enabled' => 1,
        ]);
        $disabled = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
            'title' => 'ВыключенаГруппа',
            'is_enabled' => 0,
        ]);
        $deleted = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
            'title' => 'УдалённаяГруппа',
            'is_enabled' => 1,
        ]);
        $deleted->delete();

        $this->sync->attachTeamForStudent($student, (int) $current->id);
        $this->grantPermission($student, 'dashboard.view');
        $this->grantPermission($student, 'account.user.team.update');
        $this->actingAs($student);

        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('option value="'.$ok->id.'"', $html);
        $this->assertStringContainsString('ДоступнаОк', $html);
        $this->assertStringNotContainsString('option value="'.$current->id.'"', $html);
        $this->assertStringNotContainsString('ВыключенаГруппа', $html);
        $this->assertStringNotContainsString('УдалённаяГруппа', $html);
        $this->assertStringNotContainsString('option value="'.$disabled->id.'"', $html);
        $this->assertStringNotContainsString('option value="'.$deleted->id.'"', $html);
    }

    public function test_family_modal_shows_active_child_fio_not_logged_in_sibling(): void
    {
        $parent = ParentProfile::factory()->create(['partner_id' => $this->partner->id]);
        $brother1 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'parent_id' => $parent->id,
            'lastname' => 'Сидоров',
            'name' => 'Логин',
            'is_enabled' => 1,
        ]);
        $brother2 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'parent_id' => $parent->id,
            'lastname' => 'Сидоров',
            'name' => 'Активный',
            'is_enabled' => 1,
        ]);

        $location = Location::factory()->forPartner((int) $this->partner->id)->create(['name' => 'Семейный UX']);
        $teamA = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
            'title' => 'UX-A',
            'is_enabled' => 1,
        ]);
        $teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
            'title' => 'UX-B',
            'is_enabled' => 1,
        ]);
        // Группы только у активного ребёнка — иначе кнопка скрыта.
        $this->sync->attachTeamForStudent($brother2, (int) $teamA->id);

        $this->grantPermission($brother1, 'dashboard.view');
        $this->grantPermission($brother1, 'account.user.team.update');

        $this->actingAs($brother1)
            ->withSession([
                'current_partner' => $this->partner->id,
                '2fa:passed' => true,
                FamilyStudentContextService::SESSION_KEY => $brother2->id,
            ]);

        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('cabinetAttachTeamModal', $html);
        $this->assertStringContainsString((string) ($brother2->full_name ?: 'Сидоров Активный'), $html);
        // В блоке ФИО модалки не должно быть ФИО залогиненного, если смотрим брата.
        $modalStart = mb_strpos($html, 'id="cabinetAttachTeamModal"');
        $this->assertNotFalse($modalStart);
        $modalChunk = mb_substr($html, $modalStart, 2500);
        $this->assertStringContainsString('Сидоров', $modalChunk);
        $this->assertStringContainsString('Активный', $modalChunk);
        $this->assertStringNotContainsString('>Логин<', $modalChunk);
        $this->assertStringContainsString('option value="'.$teamB->id.'"', $modalChunk);
        $this->assertStringContainsString('UX-A', $modalChunk);
    }

    public function test_select_does_not_include_teams_from_unrelated_location(): void
    {
        [$student, $current, $candidate, $location] = $this->seedEligible();
        $otherLocation = Location::factory()->forPartner((int) $this->partner->id)->create([
            'name' => 'Несвязанный объект',
        ]);
        $unrelated = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $otherLocation->id,
            'title' => 'ЧужаяЛокацияMarkup',
            'is_enabled' => 1,
        ]);

        $this->grantPermission($student, 'account.user.team.update');
        $this->actingAs($student);

        $html = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('option value="'.$candidate->id.'"', $html);
        $this->assertStringNotContainsString('ЧужаяЛокацияMarkup', $html);
        $this->assertStringNotContainsString('option value="'.$unrelated->id.'"', $html);
        unset($current, $location);
    }

    public function test_without_location_bound_groups_permission_alone_does_not_render_modal(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => 1,
        ]);
        $this->grantPermission($student, 'dashboard.view');
        $this->grantPermission($student, 'account.user.team.update');
        $this->actingAs($student);

        $html = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('cabinetAttachTeamModal', $html);
        $this->assertStringNotContainsString('cabinet-attach-team-trigger', $html);
    }

    /**
     * @return array{0: User, 1: Team, 2: Team, 3: Location}
     */
    private function seedEligible(): array
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'lastname' => 'Маркап',
            'name' => 'Ученик',
            'is_enabled' => 1,
        ]);
        $location = Location::factory()->forPartner((int) $this->partner->id)->create([
            'name' => 'Объект Markup',
        ]);
        $current = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
            'title' => 'MU-Current',
            'is_enabled' => 1,
        ]);
        $candidate = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
            'title' => 'MU-Candidate',
            'is_enabled' => 1,
        ]);
        $this->sync->attachTeamForStudent($student, (int) $current->id);
        $this->grantPermission($student, 'dashboard.view');

        return [$student, $current, $candidate, $location];
    }

    private function grantPermission(User $actor, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => (int) $actor->partner_id,
            'role_id' => (int) $actor->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function revokePermission(User $actor, string $permissionName): void
    {
        DB::table('permission_role')
            ->where('partner_id', (int) $actor->partner_id)
            ->where('role_id', (int) $actor->role_id)
            ->where('permission_id', $this->permissionId($permissionName))
            ->delete();
    }
}
