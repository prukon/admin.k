<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Dashboard;

use Illuminate\Support\Facades\Auth;

/**
 * Доступ к селекту «ФИО» и AJAX-ручкам: гость / 403 / 200, users.view.
 */
final class DashboardCabinetFioSelectAccessFeatureTest extends DashboardCabinetFioSelectTestCase
{
    public function test_guest_is_denied_on_fio_select_endpoints(): void
    {
        Auth::logout();

        foreach ($this->fioSelectEndpointsPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
            $this->assertNotSame(500, $response->getStatusCode());
            $this->assertNotSame(200, $response->getStatusCode());
        }
    }

    public function test_user_without_dashboard_view_gets_403_on_fio_select_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('dashboard.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id]);

        foreach ($this->fioSelectEndpointsPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без dashboard.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
            $this->assertNotSame(500, $response->getStatusCode());
            $this->assertNotSame(200, $response->getStatusCode());
        }
    }

    public function test_admin_with_users_view_gets_200_and_sees_fio_select(): void
    {
        $this->actingAsCrmAdmin();

        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertIsString($html);
        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('id="single-select-user"', $html);
        $this->assertStringContainsString('Выбор ученика:', $html);
        $this->assertStringContainsString('data-user-id="'.$this->studentInTeam->id.'"', $html);
    }

    public function test_student_without_users_view_opens_cabinet_without_fio_select(): void
    {
        $this->actingAs($this->studentInTeam);
        $this->withSession(['current_partner' => $this->partner->id]);

        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString('id="single-select-user"', $html);
        $this->assertStringNotContainsString('id="single-select-team"', $html);
        $this->assertStringNotContainsString('Выбор ученика:', $html);
        $this->assertStringNotContainsString('data-user-id="'.$this->studentInTeam->id.'"', $html);
    }

    public function test_trainer_without_users_view_opens_cabinet_without_fio_select(): void
    {
        $this->actingAs($this->trainerStaff);
        $this->withSession(['current_partner' => $this->partner->id]);

        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertNotSame('', trim($html));
        $this->assertStringNotContainsString('id="single-select-user"', $html);
        $this->assertStringNotContainsString('Выбор ученика:', $html);
    }

    public function test_trainer_with_users_view_sees_fio_select_of_students_only(): void
    {
        $this->grantPermissionForUser($this->trainerStaff, 'users.view');
        $this->actingAs($this->trainerStaff);
        $this->withSession(['current_partner' => $this->partner->id]);

        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('id="single-select-user"', $html);
        $this->assertStringContainsString('data-user-id="'.$this->studentInTeam->id.'"', $html);
        $this->assertStringNotContainsString('data-user-id="'.$this->adminStaff->id.'"', $html);
        $this->assertStringNotContainsString('data-user-id="'.$this->trainerStaff->id.'"', $html);
        $this->assertStringNotContainsString('data-user-id="'.$this->customRoleStaff->id.'"', $html);
        $this->assertStringNotContainsString('data-user-id="'.$this->disabledStudent->id.'"', $html);
    }

    public function test_student_with_dashboard_view_gets_200_on_ajax_details_for_another_student(): void
    {
        $this->actingAs($this->studentWithoutTeam);
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->getJson(route('getUserDetails', ['userId' => $this->studentInTeam->id]))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->getJson(route('getTeamDetails', ['teamName' => 'all']))
            ->assertOk()
            ->assertJson(['success' => true]);
    }
}
