<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;

final class PartnerOfertaPublicFeatureTest extends CrmTestCase
{
    public function test_guest_can_open_partner_offer_page(): void
    {
        Auth::logout();

        $this->get(route('partner.oferta'))
            ->assertOk()
            ->assertSee('Партнёрская оферта на использование сервиса', false)
            ->assertSee('редакция от 07.09.2026', false)
            ->assertDontSee('Скачать PDF', false)
            ->assertSee('включают все удержания', false)
            ->assertSee('70 (семьдесят) рублей за каждое формирование договора', false)
            ->assertDontSee('за каждое подписание', false)
            ->assertSee('от 1 (одного) до 7 (семи) рабочих дней', false)
            ->assertDontSee('Робокасса', false);
    }

    public function test_guest_cannot_download_partner_offer_pdf(): void
    {
        Auth::logout();

        $response = $this->get(route('partner.oferta.pdf'));

        $response->assertStatus(302);
        $this->assertGuest();
        $this->assertNotSame(200, $response->getStatusCode());
    }

    public function test_public_offer_endpoints_reject_non_get(): void
    {
        Auth::logout();

        foreach ([route('partner.oferta'), route('partner.oferta.pdf')] as $url) {
            foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
                $response = $this->call($method, $url, [], [], [], [
                    'HTTP_ACCEPT' => 'text/html',
                ]);
                $this->assertSame(405, $response->getStatusCode(), "{$method} {$url}");
            }
        }
    }
}
