<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Enums\AuditEvent;
use App\Models\LessonPackage;
use App\Models\MyLog;
use App\Models\ParentProfile;
use App\Models\Partner;
use App\Models\PartnerLegalEntity;
use App\Models\PartnerWalletTransaction;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Services\SmsRuService;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Отправка SMS со ссылкой на оплату с вкладки назначений абонементов.
 */
final class LessonPackageAssignmentPaySmsFeatureTest extends CrmTestCase
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
        config(['billing.sms_send_fee' => 70.00]);
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

    private function seedTbankForPartner(): void
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
     * @return array{package: LessonPackage, student: User, assignment: UserLessonPackage}
     */
    private function seedAssignment(float $fee = 500.0, ?string $studentPhone = null): array
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Смс',
            'name' => 'Ученик',
            'is_enabled' => 1,
            'phone' => $studentPhone,
            'parent_id' => null,
        ]);

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Абонемент SMS',
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

    public function test_assignments_page_has_sms_column_and_modal(): void
    {
        $this->get(route('admin.lesson-packages.assignments'))
            ->assertOk()
            ->assertSee('Отправка СМС', false)
            ->assertSee('ulpColSmsSend', false)
            ->assertSee('ulpSmsSendModal', false)
            ->assertSee('js-ulp-send-sms', false)
            ->assertSee('Недостаточно средств. Пополните баланс кабинета', false)
            ->assertSee('Отправка сообщений платная, 70 руб. за сообщение', false);
    }

    public function test_assignments_data_includes_sms_flags(): void
    {
        $this->seedTbankForPartner();
        Partner::query()->whereKey($this->partner->id)->update(['wallet_balance_cents' => 0]);
        $ctx = $this->seedAssignment();

        $json = $this->getJson(route('admin.lesson-packages.assignments.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]), $this->ajaxHeaders())
            ->assertOk()
            ->json();

        $row = collect($json['data'] ?? [])->firstWhere('id', (int) $ctx['assignment']->id);
        $this->assertIsArray($row);
        $this->assertTrue((bool) $row['sms_send_available']);
        $this->assertTrue((bool) $row['pay_link_available']);
        $this->assertFalse((bool) $row['sms_wallet_ok']);
    }

    public function test_preview_prefers_parent_phone_and_locks_field(): void
    {
        $this->seedTbankForPartner();
        $ctx = $this->seedAssignment(500.0, '+79002223344');
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'phone' => '+79001112233',
        ]);
        $ctx['student']->forceFill(['parent_id' => $parent->id])->save();

        $response = $this->getJson(
            route('admin.lesson-packages.assignments.sms-preview', ['assignment' => $ctx['assignment']->id]),
            $this->ajaxHeaders()
        )
            ->assertOk()
            ->assertJsonPath('phone', '79001112233')
            ->assertJsonPath('phone_locked', true)
            ->assertJsonPath('phone_source', 'parent')
            ->assertJsonPath('fee', 70);

        $this->assertStringContainsString(
            'Оплатите абонемент 500 руб:',
            (string) $response->json('message')
        );
    }

    public function test_preview_falls_back_to_student_phone(): void
    {
        $this->seedTbankForPartner();
        $ctx = $this->seedAssignment(500.0, '+79002223344');

        $this->getJson(
            route('admin.lesson-packages.assignments.sms-preview', ['assignment' => $ctx['assignment']->id]),
            $this->ajaxHeaders()
        )
            ->assertOk()
            ->assertJsonPath('phone', '79002223344')
            ->assertJsonPath('phone_locked', true)
            ->assertJsonPath('phone_source', 'student');
    }

    public function test_preview_empty_phone_is_editable(): void
    {
        $this->seedTbankForPartner();
        $ctx = $this->seedAssignment(500.0, null);

        $this->getJson(
            route('admin.lesson-packages.assignments.sms-preview', ['assignment' => $ctx['assignment']->id]),
            $this->ajaxHeaders()
        )
            ->assertOk()
            ->assertJsonPath('phone', '')
            ->assertJsonPath('phone_locked', false)
            ->assertJsonPath('phone_source', null);
    }

    public function test_preview_422_when_pay_link_unavailable(): void
    {
        $ctx = $this->seedAssignment();

        $this->getJson(
            route('admin.lesson-packages.assignments.sms-preview', ['assignment' => $ctx['assignment']->id]),
            $this->ajaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sms']);
    }

    public function test_send_charges_wallet_sends_sms_and_writes_audit(): void
    {
        $this->seedTbankForPartner();
        Partner::query()->whereKey($this->partner->id)->update(['wallet_balance_cents' => 20000]);
        $ctx = $this->seedAssignment(500.0, '+79001112233');

        $this->mock(SmsRuService::class, function ($mock): void {
            $mock->shouldReceive('send')
                ->once()
                ->withArgs(function (string $phone, string $message): bool {
                    return $phone === '79001112233'
                        && str_contains($message, 'Оплатите абонемент 500 руб:')
                        && str_contains($message, '/p/')
                        && ! str_contains($message, '/pay/ulp/')
                        && ! str_contains($message, '«')
                        && ! str_contains($message, 'на сумму');
                })
                ->andReturn(true);
        });

        $this->postJson(
            route('admin.lesson-packages.assignments.send-sms', ['assignment' => $ctx['assignment']->id]),
            [],
            $this->ajaxHeaders()
        )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('phone_saved', false);

        $this->partner->refresh();
        $this->assertSame(13000, (int) $this->partner->wallet_balance_cents);

        $this->assertDatabaseHas('partner_wallet_transactions', [
            'partner_id' => $this->partner->id,
            'type' => 'debit',
            'amount_cents' => 7000,
            'status' => 'succeeded',
        ]);

        $this->assertNotNull(
            MyLog::query()
                ->where('partner_id', $this->partner->id)
                ->where('event', AuditEvent::UserLessonPackagePaySmsSent->value)
                ->latest('id')
                ->first()
        );
    }

    public function test_send_saves_entered_phone_to_student_account(): void
    {
        $this->seedTbankForPartner();
        Partner::query()->whereKey($this->partner->id)->update(['wallet_balance_cents' => 20000]);
        $ctx = $this->seedAssignment(500.0, null);

        $this->mock(SmsRuService::class, function ($mock): void {
            $mock->shouldReceive('send')->once()->with('79005556677', \Mockery::type('string'))->andReturn(true);
        });

        $this->postJson(
            route('admin.lesson-packages.assignments.send-sms', ['assignment' => $ctx['assignment']->id]),
            ['phone' => '+7 (900) 555-66-77'],
            $this->ajaxHeaders()
        )
            ->assertOk()
            ->assertJsonPath('phone_saved', true);

        $ctx['student']->refresh();
        $this->assertSame('+79005556677', (string) $ctx['student']->phone);
    }

    public function test_send_does_not_overwrite_existing_student_phone_when_parent_phone_used(): void
    {
        $this->seedTbankForPartner();
        Partner::query()->whereKey($this->partner->id)->update(['wallet_balance_cents' => 20000]);
        $ctx = $this->seedAssignment(500.0, '+79002223344');
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'phone' => '+79001112233',
        ]);
        $ctx['student']->forceFill(['parent_id' => $parent->id])->save();

        $this->mock(SmsRuService::class, function ($mock): void {
            $mock->shouldReceive('send')->once()->with('79001112233', \Mockery::type('string'))->andReturn(true);
        });

        $this->postJson(
            route('admin.lesson-packages.assignments.send-sms', ['assignment' => $ctx['assignment']->id]),
            ['phone' => '+7 (900) 555-66-77'],
            $this->ajaxHeaders()
        )
            ->assertOk()
            ->assertJsonPath('phone_saved', false);

        $ctx['student']->refresh();
        $this->assertSame('+79002223344', (string) $ctx['student']->phone);
    }

    public function test_repeat_send_charges_again(): void
    {
        $this->seedTbankForPartner();
        Partner::query()->whereKey($this->partner->id)->update(['wallet_balance_cents' => 20000]);
        $ctx = $this->seedAssignment(500.0, '+79001112233');

        $this->mock(SmsRuService::class, function ($mock): void {
            $mock->shouldReceive('send')->twice()->andReturn(true);
        });

        $url = route('admin.lesson-packages.assignments.send-sms', ['assignment' => $ctx['assignment']->id]);
        $this->postJson($url, [], $this->ajaxHeaders())->assertOk();
        $this->postJson($url, [], $this->ajaxHeaders())->assertOk();

        $this->partner->refresh();
        $this->assertSame(6000, (int) $this->partner->wallet_balance_cents);
        $this->assertSame(2, (int) PartnerWalletTransaction::query()
            ->where('partner_id', $this->partner->id)
            ->where('type', 'debit')
            ->where('status', 'succeeded')
            ->count());
    }

    public function test_send_422_when_wallet_insufficient(): void
    {
        $this->seedTbankForPartner();
        Partner::query()->whereKey($this->partner->id)->update(['wallet_balance_cents' => 1000]);
        $ctx = $this->seedAssignment(500.0, '+79001112233');

        $this->mock(SmsRuService::class, function ($mock): void {
            $mock->shouldReceive('send')->never();
        });

        $this->postJson(
            route('admin.lesson-packages.assignments.send-sms', ['assignment' => $ctx['assignment']->id]),
            [],
            $this->ajaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['wallet']);

        $this->partner->refresh();
        $this->assertSame(1000, (int) $this->partner->wallet_balance_cents);
    }

    public function test_send_422_when_phone_missing(): void
    {
        $this->seedTbankForPartner();
        Partner::query()->whereKey($this->partner->id)->update(['wallet_balance_cents' => 20000]);
        $ctx = $this->seedAssignment(500.0, null);

        $this->mock(SmsRuService::class, function ($mock): void {
            $mock->shouldReceive('send')->never();
        });

        $this->postJson(
            route('admin.lesson-packages.assignments.send-sms', ['assignment' => $ctx['assignment']->id]),
            [],
            $this->ajaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_send_422_when_phone_taken_by_another_user(): void
    {
        $this->seedTbankForPartner();
        Partner::query()->whereKey($this->partner->id)->update(['wallet_balance_cents' => 20000]);
        $ctx = $this->seedAssignment(500.0, null);
        User::factory()->create([
            'partner_id' => $this->partner->id,
            'phone' => '+79005556677',
        ]);

        $this->mock(SmsRuService::class, function ($mock): void {
            $mock->shouldReceive('send')->never();
        });

        $this->postJson(
            route('admin.lesson-packages.assignments.send-sms', ['assignment' => $ctx['assignment']->id]),
            ['phone' => '+7 (900) 555-66-77'],
            $this->ajaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_send_refunds_when_sms_gateway_fails(): void
    {
        $this->seedTbankForPartner();
        Partner::query()->whereKey($this->partner->id)->update(['wallet_balance_cents' => 20000]);
        $ctx = $this->seedAssignment(500.0, '+79001112233');

        $this->mock(SmsRuService::class, function ($mock): void {
            $mock->shouldReceive('send')->once()->andReturn('API error: fail');
        });

        $this->postJson(
            route('admin.lesson-packages.assignments.send-sms', ['assignment' => $ctx['assignment']->id]),
            [],
            $this->ajaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sms'])
            ->assertJsonPath('errors.sms.0', 'Не удалось отправить SMS: fail');

        $this->partner->refresh();
        $this->assertSame(20000, (int) $this->partner->wallet_balance_cents);
    }

    public function test_send_returns_404_for_foreign_assignment(): void
    {
        $this->seedTbankForPartner();
        $foreignStudent = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'phone' => '+79001112233',
        ]);
        $package = LessonPackage::query()->create([
            'partner_id' => $this->foreignPartner->id,
            'name' => 'Чужой',
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

        $this->postJson(
            route('admin.lesson-packages.assignments.send-sms', ['assignment' => $assignment->id]),
            [],
            $this->ajaxHeaders()
        )->assertNotFound();
    }

    public function test_guest_is_denied(): void
    {
        $ctx = $this->seedAssignment();
        \Illuminate\Support\Facades\Auth::logout();

        $preview = $this->getJson(route('admin.lesson-packages.assignments.sms-preview', [
            'assignment' => $ctx['assignment']->id,
        ]));
        $this->assertContains($preview->getStatusCode(), [302, 401, 403, 419]);

        $send = $this->postJson(route('admin.lesson-packages.assignments.send-sms', [
            'assignment' => $ctx['assignment']->id,
        ]));
        $this->assertContains($send->getStatusCode(), [302, 401, 403, 419]);
    }
}
