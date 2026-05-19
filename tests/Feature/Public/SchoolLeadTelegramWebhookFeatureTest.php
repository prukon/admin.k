<?php

namespace Tests\Feature\Public;

use App\Models\Partner;
use App\Models\PartnerTelegramLinkToken;
use App\Services\PartnerTelegramLinkService;
use App\Services\PartnerWidgetService;
use Database\Seeders\PermissionGroupsSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SchoolLeadTelegramWebhookFeatureTest extends TestCase
{
    use RefreshDatabase;

    private Partner $partner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->seed(PermissionGroupsSeeder::class);
        $this->seed(PermissionSeeder::class);

        config([
            'services.telegram.bot_token'      => 'test-token',
            'services.telegram.webhook_secret' => 'webhook-secret',
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $this->partner = Partner::factory()->create();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
    }

    public function test_webhook_rejects_invalid_secret(): void
    {
        $link = app(PartnerTelegramLinkService::class)->createLinkForPartner((int) $this->partner->id);

        $this->postJson(route('webhooks.telegram.school-leads'), [
            'message' => [
                'text' => '/start ' . $link['start_payload'],
                'chat'  => ['id' => 111],
            ],
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'wrong-secret',
        ])->assertForbidden();
    }

    public function test_webhook_rejects_expired_link_token(): void
    {
        $plain = 'expiredtokenfortestpurposeonly1234567890ab';
        PartnerTelegramLinkToken::create([
            'partner_id' => $this->partner->id,
            'token'      => $plain,
            'expires_at' => now()->subMinute(),
        ]);

        $this->postJson(route('webhooks.telegram.school-leads'), [
            'message' => [
                'text' => '/start pl_' . $plain,
                'chat'  => ['id' => 222],
            ],
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'webhook-secret',
        ])->assertOk();

        $this->partner->refresh();
        $this->assertNull($this->partner->school_leads_telegram_chat_id);
    }

    public function test_webhook_rejects_reused_link_token(): void
    {
        $link = app(PartnerTelegramLinkService::class)->createLinkForPartner((int) $this->partner->id);
        $headers = ['X-Telegram-Bot-Api-Secret-Token' => 'webhook-secret'];

        $payload = [
            'message' => [
                'text' => '/start ' . $link['start_payload'],
                'chat'  => ['id' => 333],
            ],
        ];

        $this->postJson(route('webhooks.telegram.school-leads'), $payload, $headers)->assertOk();

        $this->partner->refresh();
        $this->assertSame('333', $this->partner->school_leads_telegram_chat_id);

        $this->postJson(route('webhooks.telegram.school-leads'), $payload, $headers)->assertOk();

        $this->partner->refresh();
        $this->assertSame('333', $this->partner->school_leads_telegram_chat_id);
    }

    public function test_webhook_accepts_start_with_bot_username_suffix(): void
    {
        $link = app(PartnerTelegramLinkService::class)->createLinkForPartner((int) $this->partner->id);

        $this->postJson(route('webhooks.telegram.school-leads'), [
            'message' => [
                'text' => '/start@kidscrmLeadFormBot ' . $link['start_payload'],
                'chat'  => ['id' => 444],
            ],
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'webhook-secret',
        ])->assertOk();

        $this->partner->refresh();
        $this->assertSame('444', $this->partner->school_leads_telegram_chat_id);
    }
}
