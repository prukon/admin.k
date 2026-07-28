<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\LessonPackages\Concerns\LessonPackageSchoolScheduleExportTestHelpers;

/**
 * Non-AJAX safety-net выгрузки Excel.
 *
 * Endpoint — GET (скачивание файла), не store/update. Поэтому вместо «POST → 302 + запись в БД»
 * проверяем: web-GET без X-Requested-With при ошибке → redirect + session errors (не пустой 200);
 * при успехе → 200 + непустой .xlsx.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see UsersImportNonAjaxSafetyNetFeatureTest
 */
final class LessonPackageSchoolScheduleExportNonAjaxSafetyNetFeatureTest extends CrmTestCase
{
    use LessonPackageSchoolScheduleExportTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->grantExportAccess($this->user);
        $this->actingAs($this->user);
        $this->requirePhpSpreadsheet();
    }

    public function test_non_ajax_invalid_period_redirects_back_with_session_errors_not_empty_200(): void
    {
        $response = $this->from(route('admin.lesson-packages.school-schedule'))
            ->get($this->exportUrl([
                'date_from' => '2026-07-31',
                'date_to' => '2026-07-01',
            ]));

        $response->assertStatus(302);
        $response->assertRedirect(route('admin.lesson-packages.school-schedule'));
        $response->assertSessionHasErrors(['date_to']);
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());
    }

    public function test_non_ajax_missing_dates_redirects_back_with_field_errors(): void
    {
        $response = $this->from(route('admin.lesson-packages.school-schedule'))
            ->get($this->exportUrl([]));

        $response->assertStatus(302);
        $response->assertRedirect(route('admin.lesson-packages.school-schedule'));
        $response->assertSessionHasErrors(['date_from', 'date_to']);
        $this->assertNotSame(500, $response->getStatusCode());
    }

    public function test_non_ajax_valid_period_downloads_xlsx_not_empty_200(): void
    {
        $response = $this->from(route('admin.lesson-packages.school-schedule'))
            ->get($this->exportUrl($this->validExportPeriod()));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->assertHeader('content-disposition');

        $body = (string) $response->streamedContent();
        $this->assertNotSame('', trim($body));
        $this->assertStringStartsWith('PK', $body);
        $this->assertNotSame(500, $response->getStatusCode());
    }

    public function test_non_ajax_period_over_max_days_redirects_with_date_to_error(): void
    {
        $response = $this->from(route('admin.lesson-packages.school-schedule'))
            ->get($this->exportUrl([
                'date_from' => '2025-01-01',
                'date_to' => '2026-12-31',
            ]));

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['date_to']);
        $this->assertNotSame(200, $response->getStatusCode());
    }
}
