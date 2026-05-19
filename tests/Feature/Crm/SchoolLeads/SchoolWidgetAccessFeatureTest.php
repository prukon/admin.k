<?php

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\PartnerWidget;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доступ к разделу «Виджет заявок»: middleware can:schoolWidget.view и ответы endpoint’ов.
 */
class SchoolWidgetAccessFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        config([
            'services.telegram.bot_token'    => 'test-token',
            'services.telegram.bot_username' => 'kidscrmLeadFormBot',
        ]);
    }

    public function test_guest_cannot_access_school_widget_routes(): void
    {
        Auth::logout();

        $routes = [
            ['GET', route('admin.school-widget')],
            ['POST', route('admin.school-widget.telegram-link')],
            ['DELETE', route('admin.school-widget.telegram-disconnect')],
        ];

        foreach ($routes as [$method, $url]) {
            $response = $this->call($method, $url);
            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: ожидался 302/401/403/419 на {$method} {$url}, получен {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_without_school_widget_view_gets_403(): void
    {
        $denied = $this->createUserWithoutPermission('schoolWidget.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get(route('admin.school-widget'))->assertForbidden();
        $this->postJson(route('admin.school-widget.telegram-link'))->assertForbidden();
        $this->deleteJson(route('admin.school-widget.telegram-disconnect'))->assertForbidden();
    }

    public function test_admin_with_school_widget_view_all_endpoints_return_ok(): void
    {
        $this->asAdmin();

        $this->get(route('admin.school-widget'))
            ->assertOk()
            ->assertSee('Виджет заявок', false)
            ->assertSee('Подключить Telegram', false)
            ->assertSee('iframe', false);

        $this->postJson(route('admin.school-widget.telegram-link'))
            ->assertOk()
            ->assertJsonStructure(['url', 'message', 'expires_at']);

        $this->partner->school_leads_telegram_chat_id = '111222333';
        $this->partner->save();

        $this->deleteJson(route('admin.school-widget.telegram-disconnect'))
            ->assertOk();
    }

    public function test_partner_has_widget_record_after_provisioning(): void
    {
        $this->asAdmin();

        $widget = PartnerWidget::query()->where('partner_id', $this->partner->id)->first();

        $this->assertNotNull($widget);
        $this->assertTrue($widget->is_active);
        $this->assertSame(48, strlen($widget->widget_key));
    }
}
