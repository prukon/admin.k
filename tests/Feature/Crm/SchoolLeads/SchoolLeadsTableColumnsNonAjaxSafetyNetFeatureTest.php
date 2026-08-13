<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\UserTableSetting;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;

/**
 * [P1] Non-AJAX safety-net для сохранения видимости колонок заявок.
 * Успешный save без X-Requested-With возвращает JSON (как остальные columns-settings),
 * запись в БД создаётся; валидация — 302 с errors[columns], не пустой 200 и не 500.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see SchoolLeadsTableColumnsAjaxContractFeatureTest
 */
final class SchoolLeadsTableColumnsNonAjaxSafetyNetFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asAdmin();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    public function test_non_ajax_save_persists_visibility_and_does_not_return_empty_200(): void
    {
        UserTableSetting::where('user_id', $this->user->id)
            ->where('table_key', 'school_leads_index')
            ->delete();

        $response = $this->from(route('admin.school-leads'))
            ->post(route('admin.school-leads.columns-settings.save'), [
                'columns' => [
                    'child_full_name' => '1',
                    'name'            => '1',
                    'phone'           => '0',
                ],
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotSame('', trim((string) $response->getContent()));
        $response->assertJson(['success' => true]);

        $setting = UserTableSetting::where('user_id', $this->user->id)
            ->where('table_key', 'school_leads_index')
            ->firstOrFail();

        $this->assertSame([
            'child_full_name' => true,
            'name'            => true,
            'phone'           => false,
        ], $setting->columns);
    }

    public function test_non_ajax_save_without_columns_redirects_back_with_field_error(): void
    {
        UserTableSetting::where('user_id', $this->user->id)
            ->where('table_key', 'school_leads_index')
            ->delete();

        $this->from(route('admin.school-leads'))
            ->post(route('admin.school-leads.columns-settings.save'), [])
            ->assertStatus(302)
            ->assertSessionHasErrors(['columns']);

        $this->assertSame(
            0,
            UserTableSetting::where('user_id', $this->user->id)
                ->where('table_key', 'school_leads_index')
                ->count()
        );
    }

    public function test_non_ajax_save_with_invalid_columns_redirects_back_with_field_error(): void
    {
        $this->from(route('admin.school-leads'))
            ->post(route('admin.school-leads.columns-settings.save'), [
                'columns' => 'not-array',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['columns']);
    }

    public function test_guest_non_ajax_save_is_denied_and_does_not_persist(): void
    {
        Auth::logout();

        $response = $this->from(route('admin.school-leads'))
            ->post(route('admin.school-leads.columns-settings.save'), [
                'columns' => ['phone' => false],
            ]);

        $this->assertContains($response->getStatusCode(), [302, 401, 403]);
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertSame(
            0,
            UserTableSetting::where('table_key', 'school_leads_index')->count()
        );
    }

    public function test_user_without_permission_non_ajax_save_gets_403(): void
    {
        $denied = $this->createUserWithoutPermission('schoolLeads.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->from(route('admin.school-leads'))
            ->post(route('admin.school-leads.columns-settings.save'), [
                'columns' => ['phone' => false],
            ])
            ->assertForbidden();
    }
}
