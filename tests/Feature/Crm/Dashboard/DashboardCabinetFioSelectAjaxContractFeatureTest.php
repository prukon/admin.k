<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Dashboard;

/**
 * JSON-контракт getUserDetails / getTeamDetails для селекта «ФИО».
 */
final class DashboardCabinetFioSelectAjaxContractFeatureTest extends DashboardCabinetFioSelectTestCase
{
    public function test_get_user_details_json_returns_student_payload(): void
    {
        $this->actingAsCrmAdmin();

        $json = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('getUserDetails', ['userId' => $this->studentInTeam->id]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'user' => ['id', 'partner_id', 'role_id', 'is_enabled'],
                'userTeam',
                'userTeamsLabel',
                'userPrice',
                'scheduleUser',
                'formattedBirthday',
                'userFields',
                'userFieldValues',
                'allFields',
            ])
            ->json();

        $this->assertSame((int) $this->studentInTeam->id, (int) $json['user']['id']);
        $this->assertSame($this->studentRoleId(), (int) $json['user']['role_id']);
        $this->assertSame(1, (int) $json['user']['is_enabled']);
        $this->assertSame((int) $this->partner->id, (int) $json['user']['partner_id']);
        $this->assertIsArray($json['userPrice']);
        $this->assertArrayNotHasKey('errors', $json);
    }

    public function test_get_user_details_json_rejects_staff_disabled_and_custom_role(): void
    {
        $this->actingAsCrmAdmin();

        foreach ([
            $this->adminStaff,
            $this->trainerStaff,
            $this->customRoleStaff,
            $this->disabledStudent,
            $this->crmAdmin,
        ] as $target) {
            $json = $this->withHeaders($this->ajaxHeaders())
                ->getJson(route('getUserDetails', ['userId' => $target->id]))
                ->assertOk()
                ->assertJson(['success' => false])
                ->json();

            $this->assertArrayNotHasKey('user', $json);
            $this->assertArrayNotHasKey('userPrice', $json);
        }
    }

    public function test_get_user_details_without_id_returns_success_false_not_422(): void
    {
        $this->actingAsCrmAdmin();

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('getUserDetails'))
            ->assertOk()
            ->assertJson(['success' => false]);
    }

    public function test_get_user_details_of_foreign_student_returns_success_false(): void
    {
        $foreign = $this->createUserWithRole('user', $this->foreignPartner, [
            'is_enabled' => 1,
        ]);

        $this->actingAsCrmAdmin();

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('getUserDetails', ['userId' => $foreign->id]))
            ->assertOk()
            ->assertJson(['success' => false]);
    }

    public function test_get_team_details_all_json_lists_only_enabled_students(): void
    {
        $this->actingAsCrmAdmin();

        $json = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('getTeamDetails', ['teamName' => 'all']))
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'team',
                'teamWeekDayId',
                'usersTeam',
                'userWithoutTeam',
            ])
            ->json();

        $this->assertTeamDetailsPayloadOnlyEnabledStudents($json);
        $ids = $this->idsFromPayload($json['usersTeam']);
        $this->assertContains((int) $this->studentInTeam->id, $ids);
        $this->assertContains((int) $this->studentWithoutTeam->id, $ids);
        $this->assertStaffAndDisabledNotInIds($ids);
        $this->assertStaffAndDisabledNotInIds($this->idsFromPayload($json['userWithoutTeam']));
    }

    public function test_get_team_details_without_team_json_excludes_staff_without_groups(): void
    {
        $this->actingAsCrmAdmin();

        $json = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('getTeamDetails', ['teamName' => 'withoutTeam']))
            ->assertOk()
            ->json();

        $this->assertTeamDetailsPayloadOnlyEnabledStudents($json);
        $ids = $this->idsFromPayload($json['usersTeam']);
        $this->assertContains((int) $this->studentWithoutTeam->id, $ids);
        $this->assertNotContains((int) $this->studentInTeam->id, $ids);
        $this->assertStaffAndDisabledNotInIds($ids);
    }

    public function test_get_team_details_specific_team_json_excludes_trainer_in_pivot(): void
    {
        $this->actingAsCrmAdmin();

        $json = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('getTeamDetails', [
                'teamId' => $this->team->id,
                'teamName' => $this->team->title,
            ]))
            ->assertOk()
            ->json();

        $this->assertTeamDetailsPayloadOnlyEnabledStudents($json);
        $ids = $this->idsFromPayload($json['usersTeam']);
        $this->assertContains((int) $this->studentInTeam->id, $ids);
        $this->assertNotContains((int) $this->trainerStaff->id, $ids);
        $this->assertNotContains((int) $this->disabledStudent->id, $ids);
    }

    public function test_get_team_details_named_team_without_id_returns_success_false_not_500(): void
    {
        $this->actingAsCrmAdmin();

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('getTeamDetails', [
                'teamName' => $this->team->title,
            ]))
            ->assertOk()
            ->assertJson(['success' => false]);
    }

    public function test_ajax_forbidden_actor_gets_json_403_not_empty_200(): void
    {
        $actor = $this->createUserWithoutPermission('dashboard.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id]);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('getUserDetails', ['userId' => $this->studentInTeam->id]));

        $response->assertStatus(403);
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());
    }

    public function test_json_post_cabinet_with_invalid_title_returns_422_under_title(): void
    {
        $this->actingAsCrmAdmin();

        $this->postJson(route('dashboard'), [
            'title' => ['not-a-string'],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }
}
