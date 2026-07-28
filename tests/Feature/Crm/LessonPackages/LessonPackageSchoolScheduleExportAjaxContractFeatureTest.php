<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Http\Requests\Admin\ExportLessonPackagesSchoolScheduleRequest;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\LessonPackages\Concerns\LessonPackageSchoolScheduleExportTestHelpers;

/**
 * AJAX-контракт выгрузки Excel: getJson + X-Requested-With → JSON 422 / бинарный 200.
 *
 * UI модалки ходит через fetch(Accept: application/json): при ошибке периода — JSON errors
 * под полями дат; при успехе — blob .xlsx (не пустой 200).
 *
 * @see UsersImportAjaxContractFeatureTest
 */
final class LessonPackageSchoolScheduleExportAjaxContractFeatureTest extends CrmTestCase
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

    public function test_ajax_validation_returns_422_json_with_errors_under_fields_for_inverted_period(): void
    {
        $response = $this->getJson($this->exportUrl([
            'date_from' => '2026-07-31',
            'date_to' => '2026-07-01',
        ]), $this->exportAjaxHeaders());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date_to'])
            ->assertJsonStructure([
                'message',
                'errors' => ['date_to'],
            ]);

        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertNotSame(500, $response->getStatusCode());

        $errors = $response->json('errors.date_to');
        $this->assertIsArray($errors);
        $this->assertNotSame('', (string) ($errors[0] ?? ''));
    }

    public function test_ajax_validation_returns_422_json_when_period_exceeds_max_days(): void
    {
        $response = $this->getJson($this->exportUrl([
            'date_from' => '2025-01-01',
            'date_to' => '2026-12-31',
        ]), $this->exportAjaxHeaders());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date_to']);

        $message = (string) ($response->json('errors.date_to.0') ?? '');
        $this->assertStringContainsString((string) ExportLessonPackagesSchoolScheduleRequest::MAX_RANGE_DAYS, $message);
        $this->assertNotSame('', trim((string) $response->getContent()));
    }

    public function test_ajax_validation_returns_422_json_for_missing_dates(): void
    {
        $response = $this->getJson($this->exportUrl([]), $this->exportAjaxHeaders());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date_from', 'date_to'])
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'date_from',
                    'date_to',
                ],
            ]);

        $this->assertNotSame('', (string) ($response->json('errors.date_from.0') ?? ''));
        $this->assertNotSame('', (string) ($response->json('errors.date_to.0') ?? ''));
    }

    public function test_ajax_validation_returns_422_json_for_invalid_date_format(): void
    {
        $response = $this->getJson($this->exportUrl([
            'date_from' => '01.07.2026',
            'date_to' => '31.07.2026',
        ]), $this->exportAjaxHeaders());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date_from', 'date_to']);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame('', trim((string) $response->getContent()));
    }

    public function test_ajax_success_returns_xlsx_blob_not_empty_200(): void
    {
        $response = $this->call(
            'GET',
            $this->exportUrl($this->validExportPeriod()),
            [],
            [],
            [],
            $this->exportAjaxHeaders(),
        );

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $body = (string) $response->streamedContent();
        $this->assertNotSame('', trim($body));
        $this->assertStringStartsWith('PK', $body);
        $this->assertNotSame(500, $response->getStatusCode());
    }
}
