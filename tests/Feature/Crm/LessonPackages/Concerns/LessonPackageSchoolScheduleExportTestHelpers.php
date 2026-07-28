<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

trait LessonPackageSchoolScheduleExportTestHelpers
{
    protected function grantLessonPackagePermission(User $actor, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => (int) $actor->partner_id,
            'role_id' => (int) $actor->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function grantExportAccess(User $actor): void
    {
        $this->grantLessonPackagePermission($actor, 'lessonPackages.view');
        $this->grantLessonPackagePermission($actor, 'lessonPackages.export');
    }

    protected function requirePhpSpreadsheet(): void
    {
        if (! class_exists(Spreadsheet::class)) {
            $this->markTestSkipped('PhpSpreadsheet не установлен. Выполните composer install от пользователя prukon.');
        }
    }

    /**
     * @param  array<string, string>  $query
     */
    protected function exportUrl(array $query = []): string
    {
        return route('admin.lesson-packages.school-schedule.export', $query);
    }

    /**
     * @return array{date_from: string, date_to: string}
     */
    protected function validExportPeriod(): array
    {
        return [
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-31',
        ];
    }

    /**
     * @return list<array{method: string, url: string, headers?: array<string, string>}>
     */
    protected function exportEndpointsPayload(): array
    {
        $period = $this->validExportPeriod();

        return [
            [
                'method' => 'GET',
                'url' => $this->exportUrl($period),
                'headers' => ['HTTP_ACCEPT' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            ],
            [
                'method' => 'GET',
                'url' => $this->exportUrl($period),
                'headers' => [
                    'HTTP_ACCEPT' => 'application/json',
                    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                ],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function exportAjaxHeaders(): array
    {
        return [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ];
    }
}
