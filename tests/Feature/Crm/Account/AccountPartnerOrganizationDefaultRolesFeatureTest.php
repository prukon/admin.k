<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

use App\Models\User;
use App\Services\PartnerContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\Feature\Crm\CrmTestCase;

/**
 * ЛК «Организация»: у ученика и тренера прав нет по умолчанию, форма есть у администратора.
 *
 * @see /docs/documentation/account-partner-organization
 */
final class AccountPartnerOrganizationDefaultRolesFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    public function test_student_gets_403_on_organization_page_and_update_without_extra_grants(): void
    {
        $this->assertSame('user', $this->user->role?->name);

        $originalTitle = $this->partner->title;

        $this->get(route('admin.cur.company.edit'))->assertForbidden();

        $this->patchJson(route('admin.cur.partner.update', $this->partner), $this->validPayload())
            ->assertForbidden();

        $this->from(route('account.user.edit'))
            ->patch(route('admin.cur.partner.update', $this->partner), $this->validPayload())
            ->assertForbidden();

        $this->assertSame($originalTitle, $this->partner->fresh()->title);
    }

    public function test_trainer_gets_403_on_organization_page_and_update_without_extra_grants(): void
    {
        $trainer = $this->actAsRole('trainer');
        $this->assertSame('trainer', $trainer->role?->name);

        $originalTitle = $this->partner->title;

        $this->get(route('admin.cur.company.edit'))->assertForbidden();
        $this->patchJson(route('admin.cur.partner.update', $this->partner), $this->validPayload())
            ->assertForbidden();

        $this->assertSame($originalTitle, $this->partner->fresh()->title);
    }

    public function test_guest_cannot_open_or_update_organization(): void
    {
        Auth::logout();

        $this->get(route('admin.cur.company.edit'))
            ->assertRedirect();

        $json = $this->patchJson(route('admin.cur.partner.update', $this->partner), $this->validPayload());
        $this->assertContains($json->getStatusCode(), [302, 401]);
        $this->assertNotSame(500, $json->getStatusCode());
        $this->assertNotSame(200, $json->getStatusCode());
    }

    public function test_student_account_page_hides_organization_tab_and_form(): void
    {
        $html = $this->get(route('account.user.edit'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('/account-settings/partner/edit', $html);
        $this->assertStringNotContainsString('id="partnerUpdateForm"', $html);
        $this->assertStringNotContainsString('У вас нет прав на изменение данных организации.', $html);
        $this->assertStringNotContainsString('name="sms_name"', $html);
    }

    public function test_trainer_account_page_hides_organization_tab(): void
    {
        $this->actAsRole('trainer');

        $this->get(route('account.user.edit'))
            ->assertOk()
            ->assertDontSee('/account-settings/partner/edit', false)
            ->assertDontSee('id="partnerUpdateForm"', false);
    }

    public function test_admin_first_open_shows_organization_tab_and_saved_title_in_field_order(): void
    {
        $this->asAdmin();

        $html = $this->get(route('admin.cur.company.edit'))
            ->assertOk()
            ->assertViewIs('account.index')
            ->assertViewHas('activeTab', 'partner')
            ->assertSee('Организация', false)
            ->assertSee('/account-settings/partner/edit', false)
            ->assertSee('id="partnerUpdateForm"', false)
            ->assertSee('Обновить данные', false)
            ->assertSee('Название школы/секции', false)
            ->assertDontSee('У вас нет прав на изменение данных организации.', false)
            ->assertDontSee('name="tax_id"', false)
            ->assertDontSee('name="city"', false)
            ->getContent();

        $titlePos = strpos($html, 'name="title"');
        $phonePos = strpos($html, 'name="phone"');
        $emailPos = strpos($html, 'name="email"');
        $websitePos = strpos($html, 'name="website"');
        $smsPos = strpos($html, 'name="sms_name"');

        $this->assertNotFalse($titlePos);
        $this->assertTrue($titlePos < $phonePos && $phonePos < $emailPos && $emailPos < $websitePos && $websitePos < $smsPos);
        $this->assertStringContainsString(e($this->partner->title), $html);
    }

    public function test_admin_reopen_keeps_saved_title_after_ajax_update(): void
    {
        $this->asAdmin();
        $newTitle = 'Школа '.Str::random(6);

        $this->patchJson(route('admin.cur.partner.update', $this->partner), $this->validPayload($newTitle))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Данные партнёра успешно обновлены.');

        $this->assertSame($newTitle, $this->partner->fresh()->title);

        // PartnerContext — singleton на процесс. В php-fpm каждый заход — новый процесс;
        // здесь сбрасываем кэш, чтобы повторное открытие читало сохранённое название из БД.
        $this->app->forgetInstance(PartnerContext::class);
        $this->app->forgetInstance('current_partner');

        $this->get(route('admin.cur.company.edit'))
            ->assertOk()
            ->assertSee('value="'.e($newTitle).'"', false)
            ->assertSee('name="title"', false);
    }

    public function test_admin_ajax_update_returns_422_errors_under_title_and_email(): void
    {
        $this->asAdmin();
        $originalTitle = $this->partner->title;

        $this->patchJson(route('admin.cur.partner.update', $this->partner), [
            'title' => '',
            'email' => 'not-an-email',
            'sms_name' => 'слишком длинное имя смс',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'email', 'sms_name']);

        $this->assertSame($originalTitle, $this->partner->fresh()->title);
    }

    public function test_admin_non_ajax_update_redirects_to_organization_and_saves(): void
    {
        $this->asAdmin();
        $newTitle = 'NonAjax '.Str::random(6);

        $this->from(route('admin.cur.company.edit'))
            ->patch(route('admin.cur.partner.update', $this->partner), $this->validPayload($newTitle))
            ->assertRedirect(route('admin.cur.company.edit'))
            ->assertSessionHas('success');

        $this->assertSame($newTitle, $this->partner->fresh()->title);
    }

    public function test_admin_non_ajax_validation_redirects_back_with_field_errors(): void
    {
        $this->asAdmin();
        $originalTitle = $this->partner->title;

        $this->from(route('admin.cur.company.edit'))
            ->patch(route('admin.cur.partner.update', $this->partner), [
                'title' => 'А',
                'email' => '',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['title', 'email']);

        $this->assertSame($originalTitle, $this->partner->fresh()->title);
    }

    public function test_wrong_methods_on_organization_endpoints_are_not_500_or_empty_200(): void
    {
        $this->asAdmin();

        foreach (['POST', 'PUT', 'DELETE'] as $method) {
            $response = $this->call($method, route('admin.cur.company.edit'));
            $this->assertNotContains(
                $response->getStatusCode(),
                [200, 500],
                "{$method} edit → {$response->getStatusCode()}"
            );
        }

        foreach (['GET', 'POST', 'PUT', 'DELETE'] as $method) {
            $response = $this->call($method, route('admin.cur.partner.update', $this->partner), $this->validPayload());
            $this->assertNotContains(
                $response->getStatusCode(),
                [200, 500],
                "{$method} update → {$response->getStatusCode()}"
            );
        }
    }

    private function actAsRole(string $roleName): User
    {
        $this->user->role_id = $this->roleId($roleName);
        $this->user->save();
        $this->user->unsetRelation('role');
        $this->actingAs($this->user->fresh());

        return $this->user->fresh();
    }

    /**
     * @return array{title: string, email: string, phone: string, website: string, sms_name: string}
     */
    private function validPayload(?string $title = null): array
    {
        return [
            'title' => $title ?? ('Org '.Str::random(6)),
            'email' => 'org_'.Str::lower(Str::random(8)).'@example.test',
            'phone' => '+79990001122',
            'website' => 'https://example.test',
            'sms_name' => 'SCHOOL',
        ];
    }
}
