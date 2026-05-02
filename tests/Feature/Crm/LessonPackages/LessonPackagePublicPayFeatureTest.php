<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\Partner;
use App\Models\PaymentSystem;
use App\Models\UserLessonPackage;
use App\Models\UserLessonPackagePublicPayLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Crm\CrmTestCase;

final class LessonPackagePublicPayFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    private function grantPermission(string $permissionName): void
    {
        $permId = $this->permissionId($permissionName);

        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $permId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedTbankForPartner(): void
    {
        PaymentSystem::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'tbank',
            'test_mode' => 1,
            'settings' => [
                'terminal_key' => 'TERM_PUBLIC_PAY',
                'token_password' => 'PWD_PUBLIC_PAY',
                'e2c_terminal_key' => 'E2C_TERM',
                'e2c_token_password' => 'E2C_PWD',
            ],
        ]);

        Partner::query()->whereKey($this->partner->id)->update([
            'tinkoff_partner_id' => 'SHOP-PUBLIC-PAY-TEST',
        ]);
        $this->partner->refresh();
    }

    private function createUnpaidAssignment(float $fee = 500.0): UserLessonPackage
    {
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Публичная оплата',
            'schedule_type' => 'no_schedule',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        return UserLessonPackage::query()->create([
            'user_id' => $this->user->id,
            'lesson_package_id' => $package->id,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => (int) $package->lessons_count,
            'lessons_remaining' => (int) $package->lessons_count,
            'fee_amount' => number_format($fee, 2, '.', ''),
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_unknown_public_token_returns_404(): void
    {
        $token = str_repeat('a', 64);

        $this->get(route('ulp.public.pay', ['token' => $token]))
            ->assertNotFound();
    }

    public function test_issue_public_pay_link_422_when_tbank_not_configured(): void
    {
        $this->grantPermission('lessonPackages.view');
        $ulp = $this->createUnpaidAssignment();

        $this->postJson(route('admin.lesson-packages.assignments.public-pay-link', ['assignment' => $ulp->id]))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Оплата T‑Bank не подключена для этого клуба');
    }

    public function test_issue_public_pay_link_422_when_already_paid(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->seedTbankForPartner();
        $ulp = $this->createUnpaidAssignment();
        UserLessonPackage::query()->whereKey($ulp->id)->update(['is_paid' => true]);

        $this->postJson(route('admin.lesson-packages.assignments.public-pay-link', ['assignment' => $ulp->id]))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Назначение уже оплачено');
    }

    public function test_issue_public_pay_link_returns_https_url_and_public_page_loads_with_fake_init(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->seedTbankForPartner();
        $ulp = $this->createUnpaidAssignment();

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $url = $request->url();
            if (str_contains($url, '/v2/Init')) {
                $body = $request->data();

                return Http::response([
                    'Success' => true,
                    'PaymentId' => '7700994411',
                    'PaymentURL' => 'https://pay.example.test/',
                    'OrderId' => (string) ($body['OrderId'] ?? 'order-x'),
                    'ErrorCode' => '0',
                ], 200);
            }
            if (str_contains($url, '/v2/GetState')) {
                return Http::response([
                    'Success' => true,
                    'ErrorCode' => '0',
                    'Status' => 'NEW',
                ], 200);
            }

            return Http::response(['Success' => false], 500);
        });

        $issue = $this->postJson(route('admin.lesson-packages.assignments.public-pay-link', ['assignment' => $ulp->id]))
            ->assertOk();

        $url = (string) $issue->json('url');
        $this->assertStringContainsString('/pay/ulp/', $url);

        $token = substr($url, strrpos($url, '/') + 1);
        $this->assertSame(64, strlen($token));

        $this->get(route('ulp.public.pay', ['token' => $token]))
            ->assertOk()
            ->assertSee('Оплата через СБП', false);

        $this->assertDatabaseHas('user_lesson_package_public_pay_links', [
            'user_lesson_package_id' => $ulp->id,
            'tinkoff_payment_id' => '7700994411',
        ]);
    }

    public function test_expired_public_link_shows_status_page(): void
    {
        $ulp = $this->createUnpaidAssignment();

        UserLessonPackagePublicPayLink::query()->create([
            'user_lesson_package_id' => $ulp->id,
            'partner_id' => $this->partner->id,
            'token' => bin2hex(random_bytes(32)),
            'expires_at' => now()->subDay(),
        ]);

        $token = (string) UserLessonPackagePublicPayLink::query()->where('user_lesson_package_id', $ulp->id)->value('token');

        $this->get(route('ulp.public.pay', ['token' => $token]))
            ->assertOk()
            ->assertSee('Ссылка недействительна', false);
    }
}
