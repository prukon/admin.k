<?php

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\PartnerWidget;
use App\Models\User;
use App\Services\PartnerWidgetService;
use App\Support\RuPhone;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Crm\CrmTestCase;

/**
 * CRM-вкладка «Страница заявки»: permission schoolLeadLanding.view, UI и контроль доступа.
 */
final class SchoolLeadLandingPageFullAccessFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    /**
     * @return list<array{method: string, url: string, headers?: array<string, string>}>
     */
    private function landingRoutesPayload(): array
    {
        return [
            [
                'method'  => 'GET',
                'url'     => route('admin.school-leads.landing'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
        ];
    }

    public function test_guest_cannot_access_landing_route(): void
    {
        Auth::logout();

        foreach ($this->landingRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                [],
                [],
                [],
                $item['headers'] ?? []
            );

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_without_school_lead_landing_view_gets_403(): void
    {
        $denied = $this->createUserWithoutPermission('schoolLeadLanding.view', $this->partner);
        $this->actingAs($denied);

        foreach ($this->landingRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                [],
                [],
                [],
                $item['headers'] ?? []
            );

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без schoolLeadLanding.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_with_school_lead_landing_view_all_endpoints_return_200(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeadLanding.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeadLanding.view');
        $this->actingAs($actor);

        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        foreach ($this->landingRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                [],
                [],
                [],
                $item['headers'] ?? []
            );

            $this->assertSame(
                200,
                $response->getStatusCode(),
                "С schoolLeadLanding.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_landing_page_renders_ui_and_view_data(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeadLanding.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeadLanding.view');
        $this->actingAs($actor);

        $widget = app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
        $widget->update(['landing_slug' => 'crm-test-school']);
        $landingUrl = route('lead.show', ['landingSlug' => 'crm-test-school']);
        $instructionUrl = route('lead.instruction', ['landingSlug' => 'crm-test-school']);

        $this->get(route('admin.school-leads.landing'))
            ->assertOk()
            ->assertViewIs('admin.school-leads.index')
            ->assertViewHas('activeTab', 'landing')
            ->assertViewHas('landingUrl', $landingUrl)
            ->assertViewHas('instructionUrl', $instructionUrl)
            ->assertViewHas('widget')
            ->assertViewHas('partner')
            ->assertSee('>Страница заявки</a>', false)
            ->assertSee('nav-link active', false)
            ->assertSee('>Заявки</a>', false)
            ->assertSee('id="landingSlugForm"', false)
            ->assertSee('id="landingSlugInput"', false)
            ->assertSee('id="landingUrl"', false)
            ->assertSee('id="copyLandingUrlBtn"', false)
            ->assertSee('id="copyLandingSuccess"', false)
            ->assertSee('Копировать', false)
            ->assertSee('Открыть страницу', false)
            ->assertSee('Инструкция для родителей', false)
            ->assertSee($landingUrl, false)
            ->assertSee('id="instructionPhoneModal"', false)
            ->assertSee('id="instructionOmitPhone"', false)
            ->assertSee('id="instructionPhoneInput"', false)
            ->assertSee('Не указывать номер телефона', false)
            ->assertSee('Нужно ли указывать номер телефона в инструкции?', false)
            ->assertSee('Брендированная страница с полной формой заявки', false);
    }

    public function test_landing_url_in_view_matches_partner_widget_landing_slug(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeadLanding.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeadLanding.view');
        $this->actingAs($actor);

        $widget = app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
        $widget->update(['landing_slug' => 'shkola-rossi']);

        $response = $this->get(route('admin.school-leads.landing'))->assertOk();

        $landingUrl = $response->viewData('landingUrl');
        $instructionUrl = $response->viewData('instructionUrl');
        $this->assertSame(
            route('lead.show', ['landingSlug' => 'shkola-rossi']),
            $landingUrl
        );
        $this->assertSame(
            route('lead.instruction', ['landingSlug' => 'shkola-rossi']),
            $instructionUrl
        );
        $this->assertStringContainsString('/lead/shkola-rossi', (string) $landingUrl);
        $this->assertStringContainsString('/lead/shkola-rossi/instruction', (string) $instructionUrl);
    }

    public function test_landing_url_null_when_slug_not_set(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeadLanding.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeadLanding.view');
        $this->actingAs($actor);

        $widget = app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
        $widget->update(['landing_slug' => null]);

        $html = $this->get(route('admin.school-leads.landing'))
            ->assertOk()
            ->assertViewHas('landingUrl', null)
            ->assertViewHas('instructionUrl', null)
            ->assertSee('Сохраните адрес страницы', false)
            ->getContent();

        $this->assertStringNotContainsString('Инструкция для родителей', $html);
    }

    public function test_instruction_button_opens_settings_modal_instead_of_direct_link(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeadLanding.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeadLanding.view');
        $this->actingAs($actor);

        $widget = app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
        $widget->update(['landing_slug' => 'crm-test-school']);

        $instructionUrl = route('lead.instruction', ['landingSlug' => 'crm-test-school']);
        $pdfUrl = route('lead.instruction.pdf', ['landingSlug' => 'crm-test-school']);
        $landingUrl = route('lead.show', ['landingSlug' => 'crm-test-school']);

        $html = $this->get(route('admin.school-leads.landing'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/id="openInstructionSettingsBtn"[^>]*data-bs-target="#instructionPhoneModal"/',
            $html
        );
        $this->assertStringContainsString('id="instructionPhoneModal"', $html);
        $this->assertStringContainsString('id="instructionPhoneForm"', $html);
        $this->assertStringContainsString('Не указывать номер телефона', $html);
        $this->assertStringContainsString('id="instructionOmitPhone"', $html);
        $this->assertStringNotContainsString('checked', substr($html, (int) strpos($html, 'id="instructionOmitPhone"'), 200));
        $this->assertStringContainsString('id="instructionPhoneInput"', $html);
        $this->assertStringNotContainsString('id="instructionAdminPhoneSelect"', $html);
        $this->assertStringNotContainsString('Другой номер', $html);
        $this->assertStringContainsString("method: 'POST'", $html);
        $this->assertStringContainsString('window.open(url, \'_blank\', \'noopener,noreferrer\')', $html);
        $this->assertStringContainsString('data-error-for="phone"', $html);
        $this->assertStringContainsString('instruction-preview', $html);
        $this->assertStringNotContainsString('href="'.$instructionUrl.'"', $html);
        $this->assertStringNotContainsString('href="'.$pdfUrl.'"', $html);

        $openPos = strpos($html, 'Открыть страницу');
        $instrPos = strpos($html, 'Инструкция для родителей');
        $this->assertNotFalse($openPos);
        $this->assertNotFalse($instrPos);
        $this->assertLessThan($instrPos, $openPos);
        $this->assertStringContainsString('href="'.$landingUrl.'"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
    }

    public function test_saving_slug_then_reopening_tab_shows_instruction_button(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeadLanding.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeadLanding.view');
        $this->actingAs($actor);

        $widget = app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
        $widget->update(['landing_slug' => null]);

        $htmlBefore = $this->get(route('admin.school-leads.landing'))->assertOk()->getContent();
        $this->assertStringNotContainsString('Инструкция для родителей', $htmlBefore);

        $this->putJson(route('admin.school-leads.landing-slug.update'), [
            'landing_slug' => 'after-save-instr',
        ])
            ->assertOk()
            ->assertJsonPath('landing_slug', 'after-save-instr')
            ->assertJsonPath('landing_url', route('lead.show', ['landingSlug' => 'after-save-instr']));

        $htmlAfter = $this->get(route('admin.school-leads.landing'))->assertOk()->getContent();
        $this->assertStringContainsString('Инструкция для родителей', $htmlAfter);
        $this->assertStringContainsString('id="openInstructionSettingsBtn"', $htmlAfter);
        $this->assertStringContainsString('id="instructionPhoneModal"', $htmlAfter);
    }

    public function test_non_ajax_slug_save_persists_and_is_not_empty_200(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeadLanding.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeadLanding.view');
        $this->actingAs($actor);

        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        $this->from(route('admin.school-leads.landing'))
            ->put(route('admin.school-leads.landing-slug.update'), [
                'landing_slug' => 'non-ajax-instr',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Адрес страницы сохранён.')
            ->assertJsonPath('landing_slug', 'non-ajax-instr');

        $this->assertDatabaseHas('partner_widgets', [
            'partner_id'   => $this->partner->id,
            'landing_slug' => 'non-ajax-instr',
        ]);
    }

    public function test_non_ajax_invalid_slug_redirects_back_with_field_error(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeadLanding.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeadLanding.view');
        $this->actingAs($actor);

        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        $this->from(route('admin.school-leads.landing'))
            ->put(route('admin.school-leads.landing-slug.update'), [
                'landing_slug' => 'ab',
            ])
            ->assertStatus(302)
            ->assertRedirect(route('admin.school-leads.landing'))
            ->assertSessionHasErrors(['landing_slug']);
    }

    public function test_landing_slug_ajax_reloads_so_instruction_button_appears(): void
    {
        $path = resource_path('views/admin/school-leads/tabs/landing.blade.php');
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $this->assertStringContainsString("\$form.on('submit'", $content);
        $this->assertStringContainsString('e.preventDefault()', $content);
        $this->assertStringContainsString('$.ajax({', $content);
        $this->assertStringContainsString("method: 'PUT'", $content);
        $this->assertStringContainsString('errors.landing_slug', $content);
        $this->assertStringContainsString('showSlugErrors(body.errors', $content);
        $this->assertStringContainsString('window.location.reload()', $content);
        $this->assertSame(1, substr_count($content, "\$form.on('submit'"));
        $this->assertSame(1, substr_count($content, 'window.location.reload()'));

        $submitPos = strpos($content, "\$form.on('submit'");
        $this->assertNotFalse($submitPos);
        $chunk = substr($content, $submitPos, 1800);
        $this->assertStringContainsString('e.preventDefault()', $chunk);
        $this->assertStringContainsString('window.location.reload()', $chunk);
        $this->assertStringContainsString('showSlugErrors(body.errors', $chunk);
    }

    public function test_update_landing_slug_saves_and_returns_url(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeadLanding.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeadLanding.view');
        $this->actingAs($actor);

        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        $this->putJson(route('admin.school-leads.landing-slug.update'), [
            'landing_slug' => 'fk-dinamo',
        ])
            ->assertOk()
            ->assertJsonPath('landing_slug', 'fk-dinamo')
            ->assertJsonPath('landing_url', route('lead.show', ['landingSlug' => 'fk-dinamo']));

        $this->assertDatabaseHas('partner_widgets', [
            'partner_id'   => $this->partner->id,
            'landing_slug' => 'fk-dinamo',
        ]);
    }

    public function test_update_landing_slug_rejects_reserved_slug(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeadLanding.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeadLanding.view');
        $this->actingAs($actor);

        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        $this->putJson(route('admin.school-leads.landing-slug.update'), [
            'landing_slug' => 'admin',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['landing_slug']);
    }

    public function test_user_with_only_landing_permission_can_update_slug(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeads.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeadLanding.view');
        $this->actingAs($actor);

        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        $this->putJson(route('admin.school-leads.landing-slug.update'), [
            'landing_slug' => 'only-landing-perm',
        ])
            ->assertOk()
            ->assertJsonPath('landing_slug', 'only-landing-perm');
    }

    public function test_update_landing_slug_rejects_duplicate(): void
    {
        $otherPartner = \App\Models\Partner::factory()->create();
        $otherWidget = app(PartnerWidgetService::class)->ensureForPartner((int) $otherPartner->id);
        $otherWidget->update(['landing_slug' => 'taken-slug']);

        $actor = $this->createUserWithoutPermission('schoolLeadLanding.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeadLanding.view');
        $this->actingAs($actor);

        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        $this->putJson(route('admin.school-leads.landing-slug.update'), [
            'landing_slug' => 'taken-slug',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['landing_slug']);
    }

    public function test_inactive_landing_shows_warning_on_crm_page(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeadLanding.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeadLanding.view');
        $this->actingAs($actor);

        $widget = app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
        $widget->update(['is_landing_active' => false]);

        $this->get(route('admin.school-leads.landing'))
            ->assertOk()
            ->assertSee('Страница заявки отключена', false)
            ->assertSee('alert-warning', false);
    }

    public function test_active_landing_does_not_show_disabled_warning(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeadLanding.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeadLanding.view');
        $this->actingAs($actor);

        $widget = app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
        $widget->update(['is_landing_active' => true]);

        $html = $this->get(route('admin.school-leads.landing'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Страница заявки отключена', $html);
    }

    public function test_user_with_only_landing_permission_cannot_access_leads_or_widget_routes(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeads.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeadLanding.view');
        $this->actingAs($actor);

        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        $this->get(route('admin.school-leads.landing'))->assertOk();
        $this->get(route('admin.school-leads'))->assertForbidden();
        $this->get(route('admin.school-leads.widget'))->assertForbidden();
        $this->get(route('admin.school-widget'))->assertForbidden();
    }

    public function test_user_with_landing_permission_does_not_see_widget_tab(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeads.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeadLanding.view');
        $this->actingAs($actor);

        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        $html = $this->get(route('admin.school-leads.landing'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('>Страница заявки</a>', $html);
        $this->assertStringNotContainsString('>Виджет для сайта</a>', $html);
        $this->assertStringNotContainsString(route('admin.school-leads.widget'), $html);
    }

    public function test_user_with_landing_and_widget_permissions_sees_both_optional_tabs(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeads.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeadLanding.view');
        $this->grantPermission($actor, 'schoolWidget.view');
        $this->actingAs($actor);

        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        $html = $this->get(route('admin.school-leads.landing'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('>Страница заявки</a>', $html);
        $this->assertStringContainsString('>Виджет для сайта</a>', $html);
        $this->assertStringContainsString(route('admin.school-leads.landing'), $html);
        $this->assertStringContainsString(route('admin.school-leads.widget'), $html);
    }

    public function test_superadmin_can_access_landing_without_explicit_permission(): void
    {
        $this->asSuperadmin();

        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        $this->get(route('admin.school-leads.landing'))
            ->assertOk()
            ->assertViewHas('activeTab', 'landing');
    }

    public function test_landing_page_provisions_partner_widget_if_missing(): void
    {
        PartnerWidget::query()->where('partner_id', $this->partner->id)->delete();

        $actor = $this->createUserWithoutPermission('schoolLeadLanding.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeadLanding.view');
        $this->actingAs($actor);

        $this->assertNull(
            PartnerWidget::query()->where('partner_id', $this->partner->id)->first()
        );

        $this->get(route('admin.school-leads.landing'))->assertOk();

        $widget = PartnerWidget::query()->where('partner_id', $this->partner->id)->first();
        $this->assertNotNull($widget);
        $this->assertNull($widget->landing_slug);
        $this->assertSame(48, strlen($widget->widget_key));
        $this->assertTrue($widget->is_landing_active);
    }

    public function test_landing_route_uses_school_lead_landing_view_middleware_only(): void
    {
        foreach (['admin.school-leads.landing', 'admin.school-leads.instruction-preview'] as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route, $routeName);

            $middleware = $route->gatherMiddleware();
            $this->assertContains('can:schoolLeadLanding.view', $middleware, $routeName);
            $this->assertNotContains('can:schoolWidget.view', $middleware, $routeName);
            $this->assertNotContains('can:schoolLeads.view', $middleware, $routeName);
        }
    }

    public function test_school_lead_landing_view_permission_exists_in_database(): void
    {
        $permission = DB::table('permissions')
            ->where('name', 'schoolLeadLanding.view')
            ->first();

        $this->assertNotNull($permission);
        $this->assertSame('Страница заявки (CRM)', $permission->description);
    }

    public function test_admin_role_receives_school_lead_landing_view_by_default(): void
    {
        $adminPermissions = config('role_base_permissions.roles.admin', []);

        $this->assertContains('schoolLeadLanding.view', $adminPermissions);
        $this->assertNotContains('schoolWidget.view', $adminPermissions);

        $permissionId = $this->permissionId('schoolLeadLanding.view');
        $adminRoleId = $this->roleId('admin');

        $assigned = DB::table('permission_role')
            ->where('partner_id', $this->partner->id)
            ->where('role_id', $adminRoleId)
            ->where('permission_id', $permissionId)
            ->exists();

        $this->assertTrue($assigned, 'Роль admin партнёра должна получать schoolLeadLanding.view автоматически');
    }

    public function test_instruction_modal_lists_only_enabled_partner_admin_phones(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeadLanding.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeadLanding.view');
        $this->actingAs($actor);

        $widget = app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
        $widget->update(['landing_slug' => 'crm-test-school']);

        $listed = $this->createUserWithRole('admin', $this->partner, [
            'name' => 'Анна',
            'lastname' => 'Селектова',
            'phone' => '79111111111',
            'email' => 'admin-listed-'.uniqid('', true).'@example.test',
        ]);
        $this->createUserWithRole('admin', $this->partner, [
            'name' => 'Борис',
            'lastname' => 'Безтелефона',
            'phone' => null,
            'email' => 'admin-nophone-'.uniqid('', true).'@example.test',
        ]);
        $this->createUserWithRole('admin', $this->partner, [
            'name' => 'Виктор',
            'lastname' => 'Отключён',
            'phone' => '79112222222',
            'is_enabled' => 0,
            'email' => 'admin-disabled-'.uniqid('', true).'@example.test',
        ]);
        $this->createUserWithRole('trainer', $this->partner, [
            'name' => 'Глеб',
            'lastname' => 'Тренеров',
            'phone' => '79113333333',
            'email' => 'trainer-phone-'.uniqid('', true).'@example.test',
        ]);
        $this->createUserWithRole('admin', $this->foreignPartner, [
            'name' => 'Диана',
            'lastname' => 'Чужая',
            'phone' => '79114444444',
            'email' => 'foreign-admin-'.uniqid('', true).'@example.test',
        ]);

        $html = $this->get(route('admin.school-leads.landing'))->assertOk()->getContent();

        $this->assertStringContainsString('id="instructionAdminPhoneSelect"', $html);
        $this->assertStringContainsString('Другой номер', $html);
        $this->assertStringContainsString('Селектова Анна — '.RuPhone::formatForInput('79111111111'), $html);
        $this->assertStringContainsString('data-phone="79111111111"', $html);
        $this->assertStringContainsString('value="'.$listed->id.'"', $html);
        $this->assertStringNotContainsString('Безтелефона', $html);
        $this->assertStringNotContainsString('Отключён', $html);
        $this->assertStringNotContainsString('Тренеров', $html);
        $this->assertStringNotContainsString('Чужая', $html);
        $this->assertStringNotContainsString('79112222222', $html);
        $this->assertStringNotContainsString('79113333333', $html);
        $this->assertStringNotContainsString('79114444444', $html);
    }

    public function test_instruction_preview_returns_url_with_phone_query(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeadLanding.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeadLanding.view');
        $this->actingAs($actor);

        $widget = app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
        $widget->update(['landing_slug' => 'crm-test-school']);

        $expected = route('lead.instruction', [
            'landingSlug' => 'crm-test-school',
            'phone' => '79115556677',
        ]);

        $this->postJson(route('admin.school-leads.instruction-preview'), [
            'omit_phone' => 0,
            'phone' => '+7 (911) 555-66-77',
        ])
            ->assertOk()
            ->assertJsonPath('instruction_url', $expected);

        $this->assertStringContainsString('phone=79115556677', $expected);
    }

    public function test_instruction_preview_omit_phone_returns_url_without_phone(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeadLanding.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeadLanding.view');
        $this->actingAs($actor);

        $widget = app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
        $widget->update(['landing_slug' => 'crm-test-school']);

        $expected = route('lead.instruction', ['landingSlug' => 'crm-test-school']);

        $this->postJson(route('admin.school-leads.instruction-preview'), [
            'omit_phone' => 1,
            'phone' => '+7 (911) 555-66-77',
        ])
            ->assertOk()
            ->assertJsonPath('instruction_url', $expected);

        $this->assertStringNotContainsString('phone=', $expected);
    }

    public function test_instruction_preview_requires_phone_when_not_omitted(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeadLanding.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeadLanding.view');
        $this->actingAs($actor);

        $widget = app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
        $widget->update(['landing_slug' => 'crm-test-school']);

        $this->postJson(route('admin.school-leads.instruction-preview'), [
            'omit_phone' => 0,
            'phone' => '',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone'])
            ->assertJsonPath('errors.phone.0', 'Укажите номер телефона.');
    }

    public function test_instruction_preview_rejects_invalid_phone(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeadLanding.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeadLanding.view');
        $this->actingAs($actor);

        $widget = app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
        $widget->update(['landing_slug' => 'crm-test-school']);

        $this->postJson(route('admin.school-leads.instruction-preview'), [
            'omit_phone' => 0,
            'phone' => '12345',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone'])
            ->assertJsonPath('errors.phone.0', 'Укажите корректный номер телефона.');
    }

    public function test_instruction_preview_without_slug_returns_422(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeadLanding.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeadLanding.view');
        $this->actingAs($actor);

        $widget = app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
        $widget->update(['landing_slug' => null]);

        $this->postJson(route('admin.school-leads.instruction-preview'), [
            'omit_phone' => 1,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.landing_slug.0', 'Сначала сохраните адрес страницы.');
    }

    public function test_guest_cannot_preview_instruction(): void
    {
        Auth::logout();

        $this->postJson(route('admin.school-leads.instruction-preview'), [
            'omit_phone' => 1,
        ])->assertUnauthorized();
    }

    public function test_user_without_school_lead_landing_view_cannot_preview_instruction(): void
    {
        $denied = $this->createUserWithoutPermission('schoolLeadLanding.view', $this->partner);
        $this->actingAs($denied);

        $this->postJson(route('admin.school-leads.instruction-preview'), [
            'omit_phone' => 1,
        ])->assertForbidden();
    }

    public function test_non_ajax_invalid_instruction_preview_redirects_back_with_field_error(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeadLanding.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeadLanding.view');
        $this->actingAs($actor);

        $widget = app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
        $widget->update(['landing_slug' => 'crm-test-school']);

        $this->from(route('admin.school-leads.landing'))
            ->post(route('admin.school-leads.instruction-preview'), [
                'omit_phone' => 0,
                'phone' => '',
            ])
            ->assertStatus(302)
            ->assertRedirect(route('admin.school-leads.landing'))
            ->assertSessionHasErrors(['phone']);
    }

    private function grantPermission(User $actor, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $actor->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
}
