<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages\Concerns;

use App\Models\LessonPackage;
use App\Models\Partner;
use App\Models\PartnerLegalEntity;
use App\Models\User;
use App\Models\UserLessonPackage;
use Illuminate\Support\Facades\DB;

trait LessonPackageAssignmentPaySmsTestHelpers
{
    protected function grantSmsAssignmentsAccess(?User $actor = null): void
    {
        $actor ??= $this->user;

        foreach (['lessonPackages.view', 'setPrices.packageAssignments.view'] as $permissionName) {
            DB::table('permission_role')->insertOrIgnore([
                'partner_id' => (int) $actor->partner_id,
                'role_id' => (int) $actor->role_id,
                'permission_id' => $this->permissionId($permissionName),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    protected function smsAjaxHeaders(): array
    {
        return ['X-Requested-With' => 'XMLHttpRequest'];
    }

    protected function seedTbankForSmsPartner(): void
    {
        $this->seedGlobalTbank([
            'terminal_key' => 'TERM_ULP_SMS',
            'token_password' => 'PWD_ULP_SMS',
            'e2c_terminal_key' => 'E2C_TERM',
            'e2c_token_password' => 'E2C_PWD',
        ]);

        PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-ULP-SMS')
            ->create(['is_default' => true]);

        Partner::query()->whereKey($this->partner->id)->update([
            'tinkoff_partner_id' => null,
            'tax_id' => null,
        ]);
        $this->partner->refresh();
    }

    /**
     * @param  array{
     *     fee?: float,
     *     student_phone?: string|null,
     *     is_paid?: bool,
     *     package_name?: string
     * }  $overrides
     * @return array{package: LessonPackage, student: User, assignment: UserLessonPackage}
     */
    protected function seedSmsAssignment(array $overrides = []): array
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Смс',
            'name' => 'Ученик',
            'is_enabled' => 1,
            'phone' => array_key_exists('student_phone', $overrides)
                ? $overrides['student_phone']
                : '+79001112233',
            'parent_id' => null,
        ]);

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => $overrides['package_name'] ?? 'Абонемент SMS',
            'schedule_type' => 'no_schedule',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $fee = (float) ($overrides['fee'] ?? 500.0);
        $assignment = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => 8,
            'lessons_remaining' => 8,
            'fee_amount_cents' => (int) round($fee * 100),
            'is_paid' => (bool) ($overrides['is_paid'] ?? false),
            'created_by' => $this->user->id,
        ]);

        return compact('package', 'student', 'assignment');
    }

    protected function smsPreviewUrl(int $assignmentId): string
    {
        return route('admin.lesson-packages.assignments.sms-preview', ['assignment' => $assignmentId]);
    }

    protected function smsSendUrl(int $assignmentId): string
    {
        return route('admin.lesson-packages.assignments.send-sms', ['assignment' => $assignmentId]);
    }

    /**
     * Кириллица в тексте → Unicode SMS: 70 символов = 1 сегмент, дальше по 67.
     */
    protected function unicodeSmsSegmentCount(string $message): int
    {
        $len = mb_strlen($message, 'UTF-8');
        if ($len === 0) {
            return 0;
        }
        if ($len <= 70) {
            return 1;
        }

        return (int) ceil($len / 67);
    }

    protected function assertFitsInOneUnicodeSms(string $message, string $context = ''): void
    {
        $len = mb_strlen($message, 'UTF-8');
        $segments = $this->unicodeSmsSegmentCount($message);
        $prefix = $context !== '' ? $context.': ' : '';

        $this->assertSame(
            1,
            $segments,
            $prefix.'ожидалась 1 Unicode SMS (≤70 символов), сейчас '.$len.' символов / '.$segments.' сегмент(ов): '.$message
        );
    }

    protected function assertPaySmsIsOneSegment(string $message, string $context = ''): void
    {
        $this->assertMatchesRegularExpression(
            '/^Оплатите абонемент \d+ руб: https?:\/\/.+/u',
            $message,
            ($context !== '' ? $context.': ' : '').'неожиданный шаблон SMS: '.$message
        );
        $this->assertStringContainsString('/p/', $message);
        $this->assertStringNotContainsString('/pay/ulp/', $message);
        $this->assertStringNotContainsString('на сумму', $message);
        $this->assertStringNotContainsString('«', $message);
        $this->assertFitsInOneUnicodeSms($message, $context);
    }

    /**
     * @return array<string, mixed>
     */
    protected function smsAssignmentDataRow(int $assignmentId): array
    {
        $json = $this->getJson(route('admin.lesson-packages.assignments.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
        ]), $this->smsAjaxHeaders())
            ->assertOk()
            ->json();

        $row = collect($json['data'] ?? [])->firstWhere('id', $assignmentId);
        $this->assertIsArray($row, 'В DataTables нет строки назначения '.$assignmentId);

        return $row;
    }
}
