<?php

namespace Tests\Feature\Crm\Schedule;

use App\Models\Status;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Контроль доступа к endpoint'ам статусов журнала /schedule.
 */
final class ScheduleStatusesFullAccessFeatureTest extends ScheduleJournalTestCase
{
    private Status $customStatus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->customStatus = $this->createCustomScheduleStatus('Доступ CRUD');
    }

    private function actingAsViewer(): User
    {
        $actor = $this->createUserWithoutPermission('schedule.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantScheduleView($actor);

        return $actor;
    }

    public function test_guest_is_denied_on_schedule_page_and_status_endpoints(): void
    {
        Auth::logout();

        $this->get(route('schedule.index'))->assertStatus(302);

        foreach ($this->statusManagementRoutesPayload() as $item) {
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
                [401, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_without_schedule_view_gets_403_on_all_status_endpoints(): void
    {
        $denied = $this->createUserWithoutPermission('schedule.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('schedule.index'))->assertStatus(403);

        foreach ($this->statusManagementRoutesPayload() as $item) {
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
                "Без schedule.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_viewer_with_schedule_view_all_status_endpoints_return_expected_status(): void
    {
        $this->actingAsViewer();

        $this->get(route('schedule.index'))
            ->assertOk()
            ->assertSee('id="settingsModal"', false)
            ->assertSee('id="statuses-table-body"', false);

        foreach ($this->statusManagementRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                $item['expected'],
                $response->getStatusCode(),
                "Viewer: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_admin_all_status_endpoints_return_expected_status(): void
    {
        $this->asAdmin();
        $this->grantScheduleView();

        foreach ($this->statusManagementRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                $item['expected'],
                $response->getStatusCode(),
                "Админ: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }

        $this->get(route('schedule.index'))->assertOk();
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>, expected: int}>
     */
    private function statusManagementRoutesPayload(): array
    {
        $deleteTarget = $this->createCustomScheduleStatus('Удалить access');

        return [
            [
                'method' => 'GET',
                'url' => route('statuses.index'),
                'expected' => 200,
            ],
            [
                'method' => 'POST',
                'url' => route('statuses.store'),
                'data' => [
                    'name' => 'Статус access store',
                    'icon' => 'fas fa-check',
                    'color' => '#75eb81',
                    'sort_order' => 95,
                ],
                'headers' => [
                    'HTTP_ACCEPT' => 'application/json',
                    'HTTP_X-Requested-With' => 'XMLHttpRequest',
                ],
                'expected' => 200,
            ],
            [
                'method' => 'PATCH',
                'url' => route('statuses.update', $this->customStatus->id),
                'data' => [
                    'name' => 'Доступ CRUD (изм.)',
                    'icon' => 'fas fa-check',
                    'color' => '#fadffb',
                    'sort_order' => 96,
                ],
                'headers' => [
                    'HTTP_ACCEPT' => 'application/json',
                    'HTTP_X-Requested-With' => 'XMLHttpRequest',
                ],
                'expected' => 200,
            ],
            [
                'method' => 'DELETE',
                'url' => route('statuses.destroy', $deleteTarget->id),
                'headers' => [
                    'HTTP_ACCEPT' => 'application/json',
                    'HTTP_X-Requested-With' => 'XMLHttpRequest',
                ],
                'expected' => 200,
            ],
        ];
    }
}
