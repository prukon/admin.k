<?php

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\Partner;
use App\Services\PartnerTelegramLinkService;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Crm\CrmTestCase;

class SchoolWidgetTelegramAutoLinkTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asAdmin();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
    }

    public function test_admin_can_create_telegram_connect_link(): void
    {
        config([
            'services.telegram.bot_token'    => 'test-token',
            'services.telegram.bot_username' => 'kidscrmLeadFormBot',
        ]);

        $response = $this->postJson(route('admin.school-widget.telegram-link'));

        $response
            ->assertOk()
            ->assertJsonStructure(['url', 'message', 'expires_at']);

        $this->assertStringContainsString('t.me/kidscrmLeadFormBot?start=pl_', $response->json('url'));
    }

    public function test_webhook_activates_partner_chat_id_from_start_payload(): void
    {
        config([
            'services.telegram.bot_token'    => 'test-token',
            'services.telegram.webhook_secret' => 'secret-test',
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $link = app(PartnerTelegramLinkService::class)->createLinkForPartner((int) $this->partner->id);

        $response = $this->postJson(route('webhooks.telegram.school-leads'), [
            'message' => [
                'text' => '/start ' . $link['start_payload'],
                'chat'  => ['id' => 987654321],
            ],
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'secret-test',
        ]);

        $response->assertOk();

        $this->partner->refresh();
        $this->assertSame('987654321', $this->partner->school_leads_telegram_chat_id);
    }

    public function test_disconnect_clears_telegram_chat_id(): void
    {
        $this->partner->school_leads_telegram_chat_id = '12345';
        $this->partner->save();

        $this->deleteJson(route('admin.school-widget.telegram-disconnect'))
            ->assertOk();

        $this->partner->refresh();
        $this->assertNull($this->partner->school_leads_telegram_chat_id);
    }
}
