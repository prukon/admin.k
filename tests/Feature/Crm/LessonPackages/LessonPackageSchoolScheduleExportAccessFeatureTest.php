<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\LessonPackages\Concerns\LessonPackageSchoolScheduleExportTestHelpers;

/**
 * Контроль доступа к выгрузке Excel календаря школы.
 *
 * @see UsersImportAccessFeatureTest
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class LessonPackageSchoolScheduleExportAccessFeatureTest extends CrmTestCase
{
    use LessonPackageSchoolScheduleExportTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->requirePhpSpreadsheet();
    }

    public function test_guest_is_denied_on_school_schedule_page_and_export_endpoints(): void
    {
        Auth::logout();

        $page = $this->get(route('admin.lesson-packages.school-schedule'));
        $this->assertContains(
            $page->getStatusCode(),
            [302, 401, 403],
            'Гость: страница расписания школы → '.$page->getStatusCode()
        );

        foreach ($this->exportEndpointsPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json'],
            );

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
            $this->assertNotSame(500, $response->getStatusCode());
        }
    }

    public function test_user_without_lesson_packages_view_gets_403_on_page_and_export(): void
    {
        $denied = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($denied)->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->get(route('admin.lesson-packages.school-schedule'))->assertForbidden();

        foreach ($this->exportEndpointsPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json'],
            );

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без lessonPackages.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_with_view_but_without_export_gets_403_on_export_and_no_ui(): void
    {
        $actor = $this->createUserWithoutPermission('lessonPackages.export', $this->partner);
        $this->grantLessonPackagePermission($actor, 'lessonPackages.view');
        $this->actingAs($actor)->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $html = $this->get(route('admin.lesson-packages.school-schedule'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('id="schoolCalExportBtn"', $html);
        $this->assertStringNotContainsString('id="schoolCalExportModal"', $html);

        foreach ($this->exportEndpointsPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json'],
            );

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без lessonPackages.export: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
            $this->assertNotSame(500, $response->getStatusCode());
        }
    }

    public function test_user_with_view_and_export_can_open_page_and_download_xlsx(): void
    {
        $actor = $this->createUserWithoutPermission('lessonPackages.export', $this->partner);
        $this->grantExportAccess($actor);
        $this->actingAs($actor)->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $html = $this->get(route('admin.lesson-packages.school-schedule'))
            ->assertOk()
            ->assertSee('id="schoolCalExportBtn"', false)
            ->assertSee('id="schoolCalExportModal"', false)
            ->assertSee('id="schoolCalExportSubmit"', false)
            ->assertSee('Этот месяц', false)
            ->assertSee('Прошлый месяц', false)
            ->getContent();

        $this->assertStringContainsString('schoolCalExportDateFrom', $html);
        $this->assertStringContainsString('schoolCalExportDateTo', $html);
        $this->assertStringContainsString('exportXlsx', $html);
        $this->assertNotSame('', trim($html));

        $download = $this->get($this->exportUrl($this->validExportPeriod()));
        $download->assertOk();
        $download->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $download->assertHeader('content-disposition');
        $this->assertNotSame('', trim((string) $download->streamedContent()));
        $this->assertNotSame(500, $download->getStatusCode());
    }

    public function test_export_endpoints_never_return_empty_200_or_500_for_authorized_actor(): void
    {
        $this->grantExportAccess($this->user);
        $this->actingAs($this->user);

        foreach ($this->exportEndpointsPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                [],
                [],
                [],
                $item['headers'] ?? [],
            );

            $status = $response->getStatusCode();
            $this->assertNotSame(500, $status, "{$item['method']} {$item['url']} не должен быть 500");
            $this->assertSame(200, $status, "{$item['method']} {$item['url']} → ожидался 200, получено {$status}");
            $this->assertNotSame('', trim((string) $response->streamedContent()), "Пустой 200: {$item['url']}");
        }
    }

    public function test_export_only_permission_without_view_still_forbidden_by_route_group(): void
    {
        $actor = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->grantLessonPackagePermission($actor, 'lessonPackages.export');
        $this->actingAs($actor)->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->get(route('admin.lesson-packages.school-schedule'))->assertForbidden();
        $this->get($this->exportUrl($this->validExportPeriod()))->assertForbidden();
    }

    public function test_superadmin_can_export_without_explicit_permission_row(): void
    {
        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->get(route('admin.lesson-packages.school-schedule'))
            ->assertOk()
            ->assertSee('id="schoolCalExportBtn"', false);

        $this->get($this->exportUrl($this->validExportPeriod()))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
