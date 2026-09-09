<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Dashboard;

/**
 * Разметка селекта «ФИО» и UX: первый кадр, @can, payload для JS-пересборки.
 *
 * JS при смене группы делает userWithoutTeam.concat(usersTeam) — сотрудники
 * без группы раньше попадали в выпадающий список.
 */
final class DashboardCabinetFioSelectUxFeatureTest extends DashboardCabinetFioSelectTestCase
{
    public function test_first_cabinet_paint_shows_fio_select_with_students_in_agreed_order(): void
    {
        $this->actingAsCrmAdmin();

        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertNotSame('', trim($html));
        $headerPos = strpos($html, 'choose-user-header');
        $teamPos = strpos($html, 'id="single-select-team"');
        $userPos = strpos($html, 'id="single-select-user"');
        $this->assertNotFalse($headerPos);
        $this->assertNotFalse($teamPos);
        $this->assertNotFalse($userPos);
        $this->assertLessThan($teamPos, $headerPos);
        $this->assertLessThan($userPos, $teamPos);

        $this->assertStringContainsString('Выбор ученика:', $html);
        $this->assertStringContainsString('data-placeholder="ФИО"', $html);
        $this->assertStringContainsString('Выберите пользователя', $html);
        $this->assertStringContainsString('Все группы', $html);
        $this->assertStringContainsString('Без группы', $html);
        $this->assertStringContainsString('data-user-id="'.$this->studentInTeam->id.'"', $html);
        $this->assertStringContainsString('data-user-id="'.$this->studentWithoutTeam->id.'"', $html);
        $this->assertStringContainsString('FioInTeam Ivan', $html);
        $this->assertStringNotContainsString('data-user-id="'.$this->adminStaff->id.'"', $html);
        $this->assertStringNotContainsString('data-user-id="'.$this->trainerStaff->id.'"', $html);
        $this->assertStringNotContainsString('data-user-id="'.$this->customRoleStaff->id.'"', $html);
        $this->assertStringNotContainsString('data-user-id="'.$this->disabledStudent->id.'"', $html);
        $this->assertStringNotContainsString('data-user-id="'.$this->crmAdmin->id.'"', $html);
    }

    public function test_fio_select_is_wrapped_in_users_view_and_hidden_without_permission(): void
    {
        $this->actingAsCrmAdmin();
        $adminHtml = (string) $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('id="single-select-user"', $adminHtml);

        $this->actingAs($this->studentInTeam);
        $this->withSession(['current_partner' => $this->partner->id]);
        $studentHtml = (string) $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="single-select-user"', $studentHtml);
        $this->assertStringNotContainsString('choose-user-header', $studentHtml);
    }

    public function test_enabled_student_is_in_select_when_rule_matches(): void
    {
        $this->actingAsCrmAdmin();

        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('data-user-id="'.$this->studentInTeam->id.'"', $html);
    }

    public function test_disabled_student_is_not_forced_into_select(): void
    {
        $this->actingAsCrmAdmin();

        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('data-user-id="'.$this->disabledStudent->id.'"', $html);
        $this->assertStringNotContainsString('FioDisabled', $html);
    }

    public function test_student_name_with_html_is_escaped_in_fio_option(): void
    {
        $xss = $this->makeStudentWithTeams([$this->team], [
            'lastname' => '<img src=x onerror=alert(1)>',
            'name' => 'Xss',
            'is_enabled' => 1,
        ]);

        $this->actingAsCrmAdmin();
        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('data-user-id="'.$xss->id.'"', $html);
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
    }

    public function test_first_paint_and_ajax_all_exclude_the_same_staff(): void
    {
        $this->actingAsCrmAdmin();

        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();
        $json = $this->getJson(route('getTeamDetails', ['teamName' => 'all']))
            ->assertOk()
            ->json();

        $ajaxIds = $this->idsFromPayload($json['usersTeam']);
        $this->assertContains((int) $this->studentInTeam->id, $ajaxIds);
        $this->assertStringContainsString('data-user-id="'.$this->studentInTeam->id.'"', $html);

        foreach ([
            $this->adminStaff,
            $this->trainerStaff,
            $this->customRoleStaff,
            $this->disabledStudent,
            $this->crmAdmin,
        ] as $staff) {
            $this->assertStringNotContainsString('data-user-id="'.$staff->id.'"', $html);
            $this->assertNotContains((int) $staff->id, $ajaxIds);
        }
    }

    public function test_picking_a_group_does_not_append_staff_without_team_into_fio_options(): void
    {
        $this->actingAsCrmAdmin();

        $json = $this->getJson(route('getTeamDetails', [
            'teamId' => $this->team->id,
            'teamName' => $this->team->title,
        ]))
            ->assertOk()
            ->json();

        $this->assertTeamDetailsPayloadOnlyEnabledStudents($json);

        $concatIds = $this->jsConcatSelectIds($json);
        $this->assertContains((int) $this->studentInTeam->id, $concatIds);
        $this->assertContains((int) $this->studentWithoutTeam->id, $concatIds);
        $this->assertStaffAndDisabledNotInIds($concatIds);
        $this->assertNotContains((int) $this->adminStaff->id, $this->idsFromPayload($json['userWithoutTeam']));
    }

    public function test_picking_without_team_filter_does_not_list_admins_without_groups(): void
    {
        $this->actingAsCrmAdmin();

        $json = $this->getJson(route('getTeamDetails', ['teamName' => 'withoutTeam']))
            ->assertOk()
            ->json();

        $ids = $this->idsFromPayload($json['usersTeam']);
        $this->assertContains((int) $this->studentWithoutTeam->id, $ids);
        $this->assertStaffAndDisabledNotInIds($ids);
    }

    public function test_post_cabinet_with_team_title_filter_does_not_reset_fio_student_filter(): void
    {
        $this->actingAsCrmAdmin();

        $html = (string) $this->from(route('dashboard'))
            ->post(route('dashboard'), ['title' => 'Fio-Select'])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="single-select-user"', $html);
        $this->assertStringContainsString('data-user-id="'.$this->studentInTeam->id.'"', $html);
        $this->assertStringContainsString('Fio-Select-Group', $html);
        $this->assertStringNotContainsString('data-user-id="'.$this->trainerStaff->id.'"', $html);
    }

    public function test_get_user_details_of_student_fills_profile_fields_used_by_select_change(): void
    {
        $this->studentInTeam->forceFill([
            'email' => 'fio-in-team@example.test',
            'birthday' => '2012-03-04',
        ])->save();

        $this->actingAsCrmAdmin();

        $json = $this->getJson(route('getUserDetails', ['userId' => $this->studentInTeam->id]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json();

        $this->assertSame('fio-in-team@example.test', $json['user']['email'] ?? null);
        $this->assertSame('04.03.2012', $json['formattedBirthday']);
        $this->assertStringContainsString('Fio-Select-Group', (string) ($json['userTeamsLabel'] ?? ''));
    }
}
