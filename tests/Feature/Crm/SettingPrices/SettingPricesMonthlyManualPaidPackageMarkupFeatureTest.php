<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Разметка вкладки «По месяцам»: модалка комментария и Vite-модуль карточки.
 */
final class SettingPricesMonthlyManualPaidPackageMarkupFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->asAdmin();
        $this->grantLessonPackageTypePermissions($this->user, ['fixed', 'flexible', 'no_schedule']);
    }

    public function test_monthly_tab_renders_comment_modal_and_vite_module(): void
    {
        $html = $this->get(route('admin.settingPrices.indexMenu'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="manualUserPricePaidModal"', $html);
        $this->assertStringContainsString('id="manualUserPricePaidComment"', $html);
        $this->assertStringContainsString('id="manualUserPricePaidConfirmBtn"', $html);
        $this->assertStringContainsString('id="manualUserPricePaidCommentError"', $html);
        $this->assertStringContainsString('maxlength="5000"', $html);
        $this->assertStringContainsString('Комментарий', $html);

        $start = strpos($html, 'id="manualUserPricePaidModal"');
        $this->assertNotFalse($start);
        $chunk = substr($html, $start, 2500);
        $this->assertStringContainsString('class="modal-dialog modal-dialog-centered', $chunk);
        $this->assertStringNotContainsString('modal-xl', $chunk);
        $this->assertStringNotContainsString('modal-fullscreen', $chunk);

        $blade = (string) file_get_contents(resource_path('views/admin/SettingPrices/monthly.blade.php'));
        $this->assertStringContainsString("@include('includes.modal.manualUserPricePaidModal')", $blade);
        $this->assertStringContainsString("@vite(['resources/js/settings-prices.js'])", $blade);
        $this->assertStringNotContainsString('postManualPaid', $blade);
    }

    public function test_users_tab_has_comment_modal_but_not_monthly_card_ajax(): void
    {
        $html = $this->get(route('admin.settingPrices.users'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="manualUserPricePaidModal"', $html);

        $blade = (string) file_get_contents(resource_path('views/admin/SettingPrices/users.blade.php'));
        $this->assertStringContainsString("@include('includes.modal.manualUserPricePaidModal')", $blade);
        $this->assertStringContainsString("@vite(['resources/js/setting-prices-manual-paid-modal.js'])", $blade);
        $this->assertStringNotContainsString('resources/js/settings-prices.js', $blade);
        $this->assertStringNotContainsString('setting-prices-monthly-package-error', $blade);
        $this->assertStringNotContainsString('payload.lesson_package_id', $blade);
    }

    public function test_comment_modal_markup_without_manual_paid_manage_still_on_page(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.manualPaid.manage', $this->partner);
        $this->grantPermission($actor, 'setPrices.view');
        $this->actingAs($actor);

        $html = $this->get(route('admin.settingPrices.indexMenu'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="manualUserPricePaidModal"', $html);
        $this->assertStringNotContainsString('payload.lesson_package_id', $html);
    }

    private function grantPermission(User $actor, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
