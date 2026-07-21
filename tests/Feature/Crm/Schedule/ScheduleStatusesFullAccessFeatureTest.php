<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\LessonOccurrenceStatus;
use App\Models\User;
use Database\Seeders\LessonOccurrenceStatusesSeeder;
use Illuminate\Support\Facades\Auth;

/**
 * Контроль доступа и smoke-200 для вкладки «Статусы занятий» на /schedule
 * и общего CRUD API lesson_occurrence_statuses.
 *
 * Gate: lessonOccurrenceStatuses.manage = schedule.view OR lessonPackages.view.
 */
final class ScheduleStatusesFullAccessFeatureTest extends ScheduleJournalTestCase
{
    private LessonOccurrenceStatus $customStatus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->customStatus = $this->createCustomOccurrenceStatus('Доступ CRUD');
    }

    private function actingWithOnlyScheduleView(): User
    {
        $actor = $this->makeCustomRoleUser();
        $this->grantScheduleView($actor);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        return $actor;
    }

    private function actingWithOnlyLessonPackagesView(): User
    {
        $actor = $this->makeCustomRoleUser();
        $this->grantLessonPackagesView($actor);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        return $actor;
    }

    public function test_guest_is_denied_on_page_and_all_endpoints(): void
    {
        Auth::logout();

        $this->get(route('schedule.occurrence-statuses'))->assertRedirect();
        $this->get(route('admin.lesson-packages.occurrence-statuses.index'))->assertRedirect();

        foreach ($this->allMutationEndpointsPayload() as $item) {
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
                [401, 419, 302],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_without_manage_permission_gets_403_on_page_and_endpoints(): void
    {
        $denied = $this->makeCustomRoleUser();
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('schedule.occurrence-statuses'))->assertForbidden();
        $this->get(route('admin.lesson-packages.occurrence-statuses.index'))->assertForbidden();

        foreach ($this->allMutationEndpointsPayload() as $item) {
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
                "Без прав: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_schedule_occurrence_statuses_page_ok_with_ui_markers_for_schedule_view(): void
    {
        $this->grantScheduleView();

        $this->get(route('schedule.occurrence-statuses'))
            ->assertOk()
            ->assertSee('schedule-section', false)
            ->assertSee('Статусы занятий', false)
            ->assertSee('losCreateModal', false)
            ->assertSee('losEditModal', false)
            ->assertSee('Списывает', false)
            ->assertSee('Посетил', false)
            ->assertSee('id="scheduleSectionTabs"', false)
            ->assertSee(route('schedule.occurrence-statuses', [], false), false);
    }

    public function test_admin_occurrence_statuses_page_ok_with_ui_markers_for_lesson_packages_view(): void
    {
        $this->actingWithOnlyLessonPackagesView();

        $this->get(route('admin.lesson-packages.occurrence-statuses.index'))
            ->assertOk()
            ->assertSee('Статусы занятий', false)
            ->assertSee('losCreateModal', false)
            ->assertSee('Посетил', false)
            ->assertSee('Списывает', false);
    }

    public function test_viewer_with_schedule_view_all_endpoints_return_expected_status(): void
    {
        $this->actingWithOnlyScheduleView();

        $this->get(route('schedule.occurrence-statuses'))->assertOk();
        $this->get(route('admin.lesson-packages.occurrence-statuses.index'))->assertOk();

        foreach ($this->allMutationEndpointsPayload() as $item) {
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
                "schedule.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}, body="
                .mb_substr((string) $response->getContent(), 0, 300)
            );

            if (($item['assert_json'] ?? null) !== null && $response->getStatusCode() === 200) {
                $response->assertJsonStructure($item['assert_json']);
            }
        }
    }

    public function test_viewer_with_lesson_packages_view_all_endpoints_return_expected_status(): void
    {
        $this->actingWithOnlyLessonPackagesView();

        $this->get(route('schedule.occurrence-statuses'))->assertOk();
        $this->get(route('admin.lesson-packages.occurrence-statuses.index'))->assertOk();

        foreach ($this->allMutationEndpointsPayload() as $item) {
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
                "lessonPackages.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_admin_with_schedule_view_all_endpoints_return_ok_and_json_contracts(): void
    {
        $this->asAdmin();
        $this->grantScheduleView();

        $this->get(route('schedule.occurrence-statuses'))->assertOk();

        $create = $this->postJson(route('admin.lesson-packages.occurrence-statuses.store'), [
            'title' => 'Smoke create',
            'color' => '#abcdef',
            'icon' => 'fa-solid fa-star',
            'consumes_lesson' => 0,
            'is_active' => 1,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Статус создан')
            ->assertJsonStructure([
                'message',
                'status' => ['id', 'partner_id', 'title', 'code', 'color', 'consumes_lesson', 'is_active', 'is_system'],
            ]);

        $id = (int) $create->json('status.id');

        $this->putJson(route('admin.lesson-packages.occurrence-statuses.update', $id), [
            'title' => 'Smoke update',
            'color' => '#112233',
            'icon' => 'fa-solid fa-star',
            'sort_order' => 77,
            'consumes_lesson' => 1,
            'is_active' => 1,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Статус обновлён');

        $rows = LessonOccurrenceStatus::query()
            ->forPartner((int) $this->partner->id)
            ->ordered()
            ->get(['id', 'sort_order']);

        $this->postJson(route('admin.lesson-packages.occurrence-statuses.reorder'), [
            'items' => $rows->map(fn (LessonOccurrenceStatus $row, int $i) => [
                'id' => $row->id,
                'sort_order' => ($i + 1) * 10,
            ])->all(),
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Порядок сохранён');

        $this->deleteJson(route('admin.lesson-packages.occurrence-statuses.destroy', $id))
            ->assertOk()
            ->assertJsonPath('message', 'Статус удалён');

        $this->assertDatabaseMissing('lesson_occurrence_statuses', ['id' => $id]);
    }

    public function test_reorder_endpoint_returns_ok_under_schedule_view(): void
    {
        $this->actingWithOnlyScheduleView();

        $rows = LessonOccurrenceStatus::query()
            ->forPartner((int) $this->partner->id)
            ->ordered()
            ->get(['id', 'sort_order']);

        $this->postJson(route('admin.lesson-packages.occurrence-statuses.reorder'), [
            'items' => $rows->values()->map(fn (LessonOccurrenceStatus $row, int $i) => [
                'id' => $row->id,
                'sort_order' => ($i + 1) * 10,
            ])->all(),
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Порядок сохранён');
    }

    public function test_journal_cell_update_with_occurrence_status_returns_ok_under_schedule_view(): void
    {
        $this->grantScheduleView();
        [$student, , $trainer] = $this->makeStudentTeamAndTrainer();

        $this->postJson(route('schedule.update'), [
            'user_id' => $student->id,
            'date' => '2026-05-20',
            'lesson_occurrence_status_id' => $this->visitedStatusId,
            'description' => 'Smoke journal cell',
            'trainer_profile_id' => $trainer->id,
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('schedule_users', [
            'user_id' => $student->id,
            'lesson_occurrence_status_id' => $this->visitedStatusId,
            'trainer_profile_id' => $trainer->id,
        ]);
    }

    /**
     * @return list<array{
     *     method: string,
     *     url: string,
     *     data?: array<string, mixed>,
     *     headers?: array<string, string>,
     *     expected: int,
     *     assert_json?: list<string|array>
     * }>
     */
    private function allMutationEndpointsPayload(): array
    {
        $deleteTarget = $this->createCustomOccurrenceStatus('Удалить access '.uniqid());
        $updateTarget = $this->customStatus;

        return [
            [
                'method' => 'POST',
                'url' => route('admin.lesson-packages.occurrence-statuses.store'),
                'data' => [
                    'title' => 'Статус access store '.uniqid(),
                    'icon' => 'fa-solid fa-check',
                    'color' => '#75eb81',
                    'consumes_lesson' => 0,
                    'is_active' => 1,
                ],
                'headers' => [
                    'HTTP_ACCEPT' => 'application/json',
                    'HTTP_X-Requested-With' => 'XMLHttpRequest',
                ],
                'expected' => 200,
                'assert_json' => ['message', 'status'],
            ],
            [
                'method' => 'PUT',
                'url' => route('admin.lesson-packages.occurrence-statuses.update', $updateTarget->id),
                'data' => [
                    'title' => 'Доступ CRUD (изм.)',
                    'icon' => 'fa-solid fa-check',
                    'color' => '#fadffb',
                    'sort_order' => 96,
                    'consumes_lesson' => 0,
                    'is_active' => 1,
                ],
                'headers' => [
                    'HTTP_ACCEPT' => 'application/json',
                    'HTTP_X-Requested-With' => 'XMLHttpRequest',
                ],
                'expected' => 200,
                'assert_json' => ['message'],
            ],
            [
                'method' => 'DELETE',
                'url' => route('admin.lesson-packages.occurrence-statuses.destroy', $deleteTarget->id),
                'headers' => [
                    'HTTP_ACCEPT' => 'application/json',
                    'HTTP_X-Requested-With' => 'XMLHttpRequest',
                ],
                'expected' => 200,
                'assert_json' => ['message'],
            ],
        ];
    }
}
