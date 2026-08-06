<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Enums\AuditEvent;
use App\Models\LessonPackage;
use App\Models\MyLog;
use App\Models\Partner;
use App\Models\PartnerLegalEntity;
use App\Models\User;
use App\Models\UserLessonPackage;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * AJAX-контракт истории назначений: logs-data + мутации, пишущие audit.
 *
 * @see LessonPackagesAjaxContractFeatureTest
 * @see LessonPackageAssignmentsAuditLogsFeatureTest
 */
final class LessonPackageAssignmentsHistoryAjaxContractFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('setPrices.packageAssignments.view');
    }

    private function grantPermission(string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function ajaxHeaders(): array
    {
        return ['X-Requested-With' => 'XMLHttpRequest'];
    }

    /**
     * @return array{package: LessonPackage, student: User, assignment: UserLessonPackage}
     */
    private function seedAssignment(float $fee = 100.0): array
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Ajax',
            'name' => 'Ученик',
            'is_enabled' => 1,
        ]);

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'History ajax package',
            'schedule_type' => 'no_schedule',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $assignment = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => 8,
            'lessons_remaining' => 8,
            'fee_amount_cents' => (int) round($fee * 100),
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);

        return compact('package', 'student', 'assignment');
    }

    private function seedTbankForPartner(): void
    {
        $this->seedGlobalTbank([
            'terminal_key' => 'TERM_HIST_AJAX',
            'token_password' => 'PWD_HIST_AJAX',
            'e2c_terminal_key' => 'E2C_TERM',
            'e2c_token_password' => 'E2C_PWD',
        ]);

        PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-HIST-AJAX')
            ->create(['is_default' => true]);

        Partner::query()->whereKey($this->partner->id)->update([
            'tinkoff_partner_id' => null,
            'tax_id' => null,
        ]);
        $this->partner->refresh();
    }

    public function test_logs_data_ajax_returns_datatable_json_not_empty(): void
    {
        $response = $this->getJson(route('logs.data.lesson-package-assignment', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]), $this->ajaxHeaders());

        $response->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
        $this->assertNotSame('', trim((string) $response->getContent()));
    }

    public function test_update_ajax_json_contract_writes_updated_audit(): void
    {
        $ctx = $this->seedAssignment(100.0);

        $this->putJson(
            route('admin.lesson-packages.assignments.update', ['assignment' => $ctx['assignment']->id]),
            ['fee_amount' => '175.00'],
            $this->ajaxHeaders()
        )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('assignment.fee_amount', 175)
            ->assertJsonStructure(['assignment' => ['id', 'fee_amount', 'user_display']]);

        $this->assertNotNull(
            MyLog::query()
                ->where('partner_id', $this->partner->id)
                ->where('event', AuditEvent::UserLessonPackageUpdated->value)
                ->where('description', 'like', '%100 ₽ → 175 ₽%')
                ->first()
        );
    }

    public function test_update_ajax_validation_returns_422_with_field_errors(): void
    {
        $ctx = $this->seedAssignment();

        $this->putJson(
            route('admin.lesson-packages.assignments.update', ['assignment' => $ctx['assignment']->id]),
            ['fee_amount' => ''],
            $this->ajaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fee_amount']);
    }

    public function test_destroy_ajax_json_contract_writes_deleted_audit(): void
    {
        $ctx = $this->seedAssignment();

        $this->deleteJson(
            route('admin.lesson-packages.assignments.destroy', ['assignment' => $ctx['assignment']->id]),
            [],
            $this->ajaxHeaders()
        )
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('user_lesson_packages', ['id' => $ctx['assignment']->id]);
        $this->assertNotNull(
            MyLog::query()
                ->where('partner_id', $this->partner->id)
                ->where('event', AuditEvent::UserLessonPackageDeleted->value)
                ->latest('id')
                ->first()
        );
    }

    public function test_manual_paid_ajax_json_contract_writes_manual_paid_audit(): void
    {
        $this->grantPermission('lessonPackages.manualPaid.manage');
        $ctx = $this->seedAssignment();

        $this->postJson(
            route('admin.lesson-packages.assignments.manual-paid', ['assignment' => $ctx['assignment']->id]),
            ['mode' => 'paid', 'comment' => 'Оплата картой в клубе'],
            $this->ajaxHeaders()
        )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('assignment.effective_is_paid', true)
            ->assertJsonStructure(['assignment' => ['id', 'effective_is_paid', 'manual_paid_note']]);

        $log = MyLog::query()
            ->where('partner_id', $this->partner->id)
            ->where('event', AuditEvent::UserLessonPackageManualPaid->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('Оплачен', (string) $log->description);
    }

    public function test_public_pay_link_ajax_json_contract_writes_issued_audit(): void
    {
        $this->seedTbankForPartner();
        $ctx = $this->seedAssignment(500.0);

        $this->postJson(
            route('admin.lesson-packages.assignments.public-pay-link', ['assignment' => $ctx['assignment']->id]),
            [],
            $this->ajaxHeaders()
        )
            ->assertOk()
            ->assertJsonStructure(['url']);

        $this->assertNotNull(
            MyLog::query()
                ->where('partner_id', $this->partner->id)
                ->where('event', AuditEvent::UserLessonPackagePublicPayLinkIssued->value)
                ->latest('id')
                ->first()
        );
    }

    public function test_public_pay_link_ajax_validation_returns_422_when_tbank_missing(): void
    {
        $ctx = $this->seedAssignment(500.0);

        $this->postJson(
            route('admin.lesson-packages.assignments.public-pay-link', ['assignment' => $ctx['assignment']->id]),
            [],
            $this->ajaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonStructure(['message']);
    }

    public function test_authorized_user_history_endpoints_return_expected_status_not_500(): void
    {
        $this->grantPermission('lessonPackages.manualPaid.manage');
        $this->seedTbankForPartner();
        $ctx = $this->seedAssignment(500.0);

        $disposable = UserLessonPackage::query()->create([
            'user_id' => $ctx['student']->id,
            'lesson_package_id' => $ctx['package']->id,
            'lessons_total' => 8,
            'lessons_remaining' => 8,
            'fee_amount_cents' => 4000,
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);

        $cases = [
            [
                'method' => 'GET',
                'url' => route('logs.data.lesson-package-assignment', ['draw' => 1, 'start' => 0, 'length' => 10]),
                'expect' => [200],
            ],
            [
                'method' => 'GET',
                'url' => route('admin.lesson-packages.assignments.show', ['assignment' => $ctx['assignment']->id]),
                'expect' => [200],
            ],
            [
                'method' => 'PUT',
                'url' => route('admin.lesson-packages.assignments.update', ['assignment' => $ctx['assignment']->id]),
                'data' => ['fee_amount' => '510.00'],
                'expect' => [200],
            ],
            [
                'method' => 'POST',
                'url' => route('admin.lesson-packages.assignments.manual-paid', ['assignment' => $ctx['assignment']->id]),
                'data' => ['mode' => 'unpaid', 'comment' => 'Сброс для матрицы'],
                'expect' => [200],
            ],
            [
                'method' => 'POST',
                'url' => route('admin.lesson-packages.assignments.public-pay-link', ['assignment' => $ctx['assignment']->id]),
                'data' => [],
                'expect' => [200],
            ],
            [
                'method' => 'DELETE',
                'url' => route('admin.lesson-packages.assignments.destroy', ['assignment' => $disposable->id]),
                'expect' => [200],
            ],
            [
                'method' => 'PUT',
                'url' => route('admin.lesson-packages.assignments.update', ['assignment' => $ctx['assignment']->id]),
                'data' => ['fee_amount' => ''],
                'expect' => [422],
            ],
        ];

        foreach ($cases as $item) {
            $response = $this->json($item['method'], $item['url'], $item['data'] ?? [], $this->ajaxHeaders());

            $this->assertContains(
                $response->getStatusCode(),
                $item['expect'],
                "{$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
            $this->assertNotSame(500, $response->getStatusCode());
            if ($response->getStatusCode() === 200) {
                $this->assertNotSame('', trim((string) $response->getContent()));
            }
        }
    }
}
