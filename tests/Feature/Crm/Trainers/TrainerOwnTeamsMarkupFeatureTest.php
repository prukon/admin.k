<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Trainers;

/**
 * Blade: selected/disabled, порядок «Все / Без группы / свои», чужая группа не в опциях.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class TrainerOwnTeamsMarkupFeatureTest extends TrainerOwnTeamsScopeTestCase
{
    public function test_users_filter_defaults_to_all_groups_and_hides_foreign_option(): void
    {
        [$ownTeam, $otherTeam] = $this->seedTwoTeamsAndStudents();
        $this->makeRestrictedTrainer(['users.view', 'groups.own'], $ownTeam);

        $html = $this->get('/admin/users')->assertOk()->getContent();
        $this->assertTeamSelectOptions(
            $html,
            'id="filter-team"',
            '',
            'none',
            $ownTeam->title,
            $otherTeam->title,
            (string) $ownTeam->id
        );
        $this->assertStringContainsString('id="createStudentTeamIds"', $html);
        $this->assertStringContainsString('id="editStudentTeamIds"', $html);
        $this->assertStringContainsString('value="'.$ownTeam->id.'"', $html);
        $this->assertStringNotContainsString('value="'.$otherTeam->id.'"', $html);
    }

    public function test_journal_defaults_to_all_groups_selected_and_hides_foreign_option(): void
    {
        [$ownTeam, $otherTeam, $ownStudent, $otherStudent] = $this->seedTwoTeamsAndStudents();
        $this->makeRestrictedTrainer(['schedule.view', 'groups.own'], $ownTeam);

        $html = $this->get('/schedule')->assertOk()->getContent();
        $this->assertMatchesRegularExpression(
            '/<option value="all"\s+selected/s',
            $html
        );
        $this->assertStringContainsString($ownStudent->lastname, $html);
        $this->assertStringNotContainsString($otherStudent->lastname, $html);
        $this->assertTeamSelectOptions(
            $html,
            'id="filter-team"',
            'all',
            'none',
            $ownTeam->title,
            $otherTeam->title,
            (string) $ownTeam->id
        );
    }

    public function test_journal_own_team_query_selects_own_option_not_all(): void
    {
        [$ownTeam, $otherTeam] = $this->seedTwoTeamsAndStudents();
        $this->makeRestrictedTrainer(['schedule.view', 'groups.own'], $ownTeam);

        $html = $this->get(route('schedule.index', ['team' => $ownTeam->id]))->assertOk()->getContent();
        $this->assertMatchesRegularExpression(
            '/<option value="'.$ownTeam->id.'"\s+selected/s',
            $html
        );
        $this->assertStringNotContainsString($otherTeam->title, $html);
        $this->assertDoesNotMatchRegularExpression(
            '/<option value="all"[^>]*selected/',
            $html
        );
    }

    public function test_journal_none_filter_selects_without_group_and_does_not_show_grouped_foreign_student(): void
    {
        [$ownTeam, , $ownStudent, $otherStudent] = $this->seedTwoTeamsAndStudents();
        $ungrouped = $this->makeStudent('JNone_'.uniqid('', true), []);
        $this->makeRestrictedTrainer(['schedule.view', 'groups.own'], $ownTeam);

        $html = $this->get(route('schedule.index', ['team' => 'none']))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<option value="none"\s+selected/s', $html);
        $this->assertStringContainsString($ungrouped->lastname, $html);
        $this->assertStringNotContainsString($ownStudent->lastname, $html);
        $this->assertStringNotContainsString($otherStudent->lastname, $html);
    }

    public function test_chat_both_team_filters_hide_foreign_group_and_default_to_all(): void
    {
        [$ownTeam, $otherTeam] = $this->seedTwoTeamsAndStudents();
        $this->makeRestrictedTrainer(['messages.view', 'groups.own'], $ownTeam);

        $html = $this->get('/chat')->assertOk()->getContent();
        foreach (['id="contactsTeamFilter"', 'id="createGroupMembersTeamFilter"'] as $selectId) {
            $this->assertStringContainsString($selectId, $html);
            $this->assertTeamSelectOptions(
                $html,
                $selectId,
                '',
                'none',
                $ownTeam->title,
                $otherTeam->title,
                (string) $ownTeam->id
            );
        }
    }

    public function test_cabinet_first_paint_hides_foreign_student_and_team_and_keeps_all_groups_default(): void
    {
        [$ownTeam, $otherTeam, $ownStudent, $otherStudent] = $this->seedTwoTeamsAndStudents();
        $this->makeRestrictedTrainer([
            'dashboard.view',
            'users.view',
            'groups.own',
        ], $ownTeam);

        $html = $this->get('/cabinet')->assertOk()->getContent();
        $blade = (string) file_get_contents(resource_path('views/dashboard.blade.php'));
        $this->assertStringContainsString("@can('users.view')", $blade);
        $this->assertTrue(
            strpos($blade, "@can('users.view')") < strpos($blade, 'id="single-select-user"'),
            'Селект ФИО должен быть внутри @can(users.view)'
        );
        $this->assertStringContainsString('id="single-select-team"', $html);
        $this->assertStringContainsString('value="all"', $html);
        $this->assertStringContainsString('value="withoutTeam"', $html);
        $this->assertStringContainsString($ownTeam->title, $html);
        $this->assertStringNotContainsString($otherTeam->title, $html);
        $this->assertStringContainsString($ownStudent->lastname, $html);
        $this->assertStringNotContainsString($otherStudent->lastname, $html);
        $this->assertStringContainsString('data-team-id="'.$ownTeam->id.'"', $html);
        $this->assertStringNotContainsString('data-team-id="'.$otherTeam->id.'"', $html);
    }

    public function test_without_groups_own_foreign_team_option_is_present(): void
    {
        [$ownTeam, $otherTeam, $ownStudent, $otherStudent] = $this->seedTwoTeamsAndStudents();
        $this->makeRestrictedTrainer([
            'users.view',
            'groups.view',
            'schedule.view',
            'messages.view',
            'dashboard.view',
        ], $ownTeam);

        $users = $this->get('/admin/users')->assertOk()->getContent();
        $this->assertStringContainsString($otherTeam->title, $users);

        $journal = $this->get('/schedule')->assertOk()->getContent();
        $this->assertStringContainsString($otherTeam->title, $journal);
        $this->assertStringContainsString($otherStudent->lastname, $journal);
        $this->assertStringContainsString($ownStudent->lastname, $journal);

        $chat = $this->get('/chat')->assertOk()->getContent();
        $this->assertStringContainsString($otherTeam->title, $chat);

        $cabinet = $this->get('/cabinet')->assertOk()->getContent();
        $this->assertStringContainsString($otherTeam->title, $cabinet);
        $this->assertStringContainsString($otherStudent->lastname, $cabinet);
    }

    public function test_without_users_group_update_edit_team_select_is_disabled_create_stays_editable(): void
    {
        [$ownTeam] = $this->seedTwoTeamsAndStudents();
        $this->makeRestrictedTrainer(['users.view', 'groups.own'], $ownTeam);

        $html = $this->get('/admin/users')->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression(
            '/id="createStudentTeamIds"[^>]*\bdisabled\b/s',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/id="editStudentTeamIds"[^>]*\bdisabled\b/s',
            $html
        );
        $this->assertStringContainsString('Нет прав на изменение групп', $html);
    }

    public function test_school_leads_team_filter_hides_foreign_group(): void
    {
        [$ownTeam, $otherTeam] = $this->seedTwoTeamsAndStudents();
        $this->makeRestrictedTrainer(['schoolLeads.view', 'groups.own'], $ownTeam);

        $html = $this->get(route('admin.school-leads'))->assertOk()->getContent();
        $this->assertStringContainsString($ownTeam->title, $html);
        $this->assertStringNotContainsString($otherTeam->title, $html);
    }

    /**
     * @param  non-empty-string  $selectNeedle
     */
    private function assertTeamSelectOptions(
        string $html,
        string $selectNeedle,
        string $allValue,
        string $noneValue,
        string $ownTitle,
        string $otherTitle,
        string $ownId,
    ): void {
        $pos = strpos($html, $selectNeedle);
        $this->assertNotFalse($pos, $selectNeedle);
        $chunk = substr($html, $pos, 1800);
        $end = strpos($chunk, '</select>');
        $this->assertNotFalse($end, 'select закрыт: '.$selectNeedle);
        $select = substr($chunk, 0, $end);

        $allPos = strpos($select, 'value="'.$allValue.'"');
        $nonePos = strpos($select, 'value="'.$noneValue.'"');
        $ownPos = strpos($select, 'value="'.$ownId.'"');
        $this->assertNotFalse($allPos, 'Все группы в '.$selectNeedle);
        $this->assertNotFalse($nonePos, 'Без группы в '.$selectNeedle);
        $this->assertNotFalse($ownPos, 'своя группа в '.$selectNeedle);
        $this->assertLessThan($nonePos, $allPos, 'Все группы раньше «Без группы»');
        $this->assertLessThan($ownPos, $nonePos, '«Без группы» раньше своих');
        $this->assertStringContainsString($ownTitle, $select);
        $this->assertStringNotContainsString($otherTitle, $select);
    }
}
