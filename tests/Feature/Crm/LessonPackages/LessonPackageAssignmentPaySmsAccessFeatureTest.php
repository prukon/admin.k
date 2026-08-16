<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\User;
use App\Models\UserLessonPackage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\LessonPackages\Concerns\LessonPackageAssignmentPaySmsTestHelpers;

/**
 * Доступ к превью и отправке SMS со ссылкой на оплату назначения.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class LessonPackageAssignmentPaySmsAccessFeatureTest extends CrmTestCase
{
    use LessonPackageAssignmentPaySmsTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        config(['billing.sms_send_fee' => 70.00]);
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>}>
     */
    private function smsEndpoints(int $assignmentId): array
    {
        return [
            [
                'method' => 'GET',
                'url' => $this->smsPreviewUrl($assignmentId),
            ],
            [
                'method' => 'POST',
                'url' => $this->smsSendUrl($assignmentId),
                'data' => [],
            ],
        ];
    }

    /**
     * @param  list<int>  $allowed
     */
    private function assertSmsEndpointsStatus(
        int $assignmentId,
        array $allowed,
        string $label,
        bool $asJson = true,
    ): void {
        foreach ($this->smsEndpoints($assignmentId) as $item) {
            if ($asJson) {
                $response = $this->json($item['method'], $item['url'], $item['data'] ?? []);
            } else {
                $server = ['HTTP_ACCEPT' => 'text/html'];
                $payload = $item['data'] ?? [];
                if ($item['method'] !== 'GET') {
                    $payload['_token'] = csrf_token();
                }
                $response = $this->call($item['method'], $item['url'], $payload, [], [], $server);
            }

            $this->assertContains(
                $response->getStatusCode(),
                $allowed,
                "{$label} [".($asJson ? 'JSON' : 'web')."]: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
            $this->assertNotSame(500, $response->getStatusCode());
            if ($response->getStatusCode() === 200) {
                $this->assertNotSame(
                    '',
                    trim((string) $response->getContent()),
                    "Пустой 200: {$item['method']} {$item['url']}"
                );
            }
        }
    }

    public function test_guest_is_denied_on_sms_preview_and_send_json_and_web(): void
    {
        $ctx = $this->seedSmsAssignment();
        Auth::logout();

        $this->assertSmsEndpointsStatus($ctx['assignment']->id, [302, 401, 403, 419], 'Гость JSON', true);
        $this->assertSmsEndpointsStatus($ctx['assignment']->id, [302, 401, 403, 419], 'Гость web', false);
    }

    public function test_manager_without_lesson_packages_view_gets_403(): void
    {
        $ctx = $this->seedSmsAssignment();
        $denied = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->assertSmsEndpointsStatus($ctx['assignment']->id, [403], 'Без lessonPackages.view JSON', true);
        $this->assertSmsEndpointsStatus($ctx['assignment']->id, [403], 'Без lessonPackages.view web', false);
    }

    public function test_manager_without_package_assignments_permission_gets_403(): void
    {
        $ctx = $this->seedSmsAssignment();
        $denied = $this->createUserWithoutPermission('setPrices.packageAssignments.view', $this->partner);
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $denied->role_id,
            'permission_id' => $this->permissionId('lessonPackages.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->assertSmsEndpointsStatus(
            $ctx['assignment']->id,
            [403],
            'Без setPrices.packageAssignments.view JSON',
            true
        );
        $this->assertSmsEndpointsStatus(
            $ctx['assignment']->id,
            [403],
            'Без setPrices.packageAssignments.view web',
            false
        );
    }

    public function test_manager_with_permissions_gets_expected_status_not_500_or_empty_200(): void
    {
        $this->grantSmsAssignmentsAccess();
        $ctx = $this->seedSmsAssignment();

        $this->assertSmsEndpointsStatus($ctx['assignment']->id, [200, 422], 'С правами JSON', true);
        $this->assertSmsEndpointsStatus($ctx['assignment']->id, [200, 302, 422], 'С правами web', false);
    }

    public function test_admin_without_package_assignments_permission_gets_403(): void
    {
        $this->asAdmin();
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $ctx = $this->seedSmsAssignment();

        $this->assertSmsEndpointsStatus($ctx['assignment']->id, [403], 'Admin без packageAssignments JSON', true);
    }

    public function test_admin_with_permissions_can_open_preview_when_pay_link_ready(): void
    {
        $this->asAdmin();
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantSmsAssignmentsAccess($this->user);
        $this->seedTbankForSmsPartner();
        $ctx = $this->seedSmsAssignment();

        $this->getJson($this->smsPreviewUrl($ctx['assignment']->id), $this->smsAjaxHeaders())
            ->assertOk()
            ->assertJsonStructure(['phone', 'phone_locked', 'message', 'fee', 'pay_url']);
    }

    public function test_superadmin_can_open_sms_preview_without_explicit_grants(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->seedTbankForSmsPartner();
        $ctx = $this->seedSmsAssignment();

        $this->getJson($this->smsPreviewUrl($ctx['assignment']->id), $this->smsAjaxHeaders())
            ->assertOk();
    }

    public function test_foreign_assignment_preview_and_send_return_404(): void
    {
        $this->grantSmsAssignmentsAccess();
        $this->seedTbankForSmsPartner();

        $foreignStudent = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'phone' => '+79001112233',
        ]);
        $package = LessonPackage::query()->create([
            'partner_id' => $this->foreignPartner->id,
            'name' => 'Чужой SMS',
            'schedule_type' => 'no_schedule',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);
        $assignment = UserLessonPackage::query()->create([
            'user_id' => $foreignStudent->id,
            'lesson_package_id' => $package->id,
            'lessons_total' => 8,
            'lessons_remaining' => 8,
            'fee_amount_cents' => 50000,
            'is_paid' => false,
            'created_by' => $foreignStudent->id,
        ]);

        $this->getJson($this->smsPreviewUrl($assignment->id), $this->smsAjaxHeaders())
            ->assertNotFound();
        $this->postJson($this->smsSendUrl($assignment->id), [], $this->smsAjaxHeaders())
            ->assertNotFound();
    }
}
