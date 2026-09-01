<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Models\User;
use App\Support\RuPhone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Public\Concerns\ProvidesSchoolLeadLandingFixtures;
use Tests\TestCase;

/**
 * Публичная инструкция для родителей: /lead/{slug}/instruction.
 */
final class SchoolLeadLandingInstructionFeatureTest extends TestCase
{
    use ProvidesSchoolLeadLandingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchoolLeadLandingFixtures([
            'title' => 'Центр содействия развития спорта',
            'phone' => '+7 (966) 939-14-13',
        ]);
    }

    public function test_guest_can_open_instruction_page(): void
    {
        Auth::logout();

        $slug = (string) $this->landingWidget->landing_slug;
        $landingUrl = route('lead.show', ['landingSlug' => $slug]);

        $this->assertFileExists(public_path('js/qrcode-generator.min.js'));
        $this->assertFileExists(public_path('img/logo.png'));

        $this->get(route('lead.instruction', ['landingSlug' => $slug]))
            ->assertOk()
            ->assertViewIs('landing.partner-lead-instruction')
            ->assertSee('Как записать ребёнка на секцию', false)
            ->assertSee('Центр содействия развития спорта', false)
            ->assertSee('Запись — Центр содействия развития спорта', false)
            ->assertSee($landingUrl, false)
            ->assertSee('id="landing-qr"', false)
            ->assertSee('data-url="'.$landingUrl.'"', false)
            ->assertSee('js/qrcode-generator.min.js', false)
            ->assertSee('qr.createSvgTag', false)
            ->assertDontSee('Место для QR-кода', false)
            ->assertSee('личный кабинет', false)
            ->assertSee('подпишите договор', false)
            ->assertSee(RuPhone::formatForInput('+7 (966) 939-14-13'), false)
            ->assertSee('Распечатать', false)
            ->assertSee('Скачать PDF', false)
            ->assertSee(route('lead.instruction.pdf', ['landingSlug' => $slug]), false)
            ->assertSee('img/logo.png', false)
            ->assertSee('alt="kidscrm.online"', false)
            ->assertSee('https://kidscrm.online/', false)
            ->assertSee('CRM для учёта детских секций, приёма оплат и онлайн-подписания договоров', false);
    }

    public function test_authenticated_user_can_open_instruction_page(): void
    {
        $user = User::factory()->create([
            'partner_id' => $this->landingPartner->id,
        ]);
        $this->actingAs($user);

        $this->get(route('lead.instruction', [
            'landingSlug' => $this->landingWidget->landing_slug,
        ]))
            ->assertOk()
            ->assertViewIs('landing.partner-lead-instruction');
    }

    public function test_guest_instruction_is_200_not_login_redirect(): void
    {
        Auth::logout();

        $response = $this->get(route('lead.instruction', [
            'landingSlug' => $this->landingWidget->landing_slug,
        ]));

        $response->assertOk();
        $this->assertNotContains($response->getStatusCode(), [301, 302, 401, 403]);
        $response->assertDontSee('password', false);
    }

    public function test_user_without_crm_permissions_can_open_instruction_and_pdf(): void
    {
        $user = User::factory()->create([
            'partner_id' => $this->landingPartner->id,
        ]);
        $this->actingAs($user);

        $slug = (string) $this->landingWidget->landing_slug;

        $this->get(route('lead.instruction', ['landingSlug' => $slug]))
            ->assertOk()
            ->assertSee('Скачать PDF', false);

        $this->get(route('lead.instruction.pdf', ['landingSlug' => $slug]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_instruction_hides_phone_when_partner_phone_empty(): void
    {
        Auth::logout();
        $this->landingPartner->update(['phone' => null]);

        $html = $this->get(route('lead.instruction', [
            'landingSlug' => $this->landingWidget->landing_slug,
        ]))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('просто позвоните нам', $html);
        $this->assertStringContainsString('Мы всегда рядом и с радостью поможем.', $html);
    }

    public function test_instruction_does_not_show_other_partner_name(): void
    {
        Auth::logout();

        $this->get(route('lead.instruction', [
            'landingSlug' => $this->landingWidget->landing_slug,
        ]))
            ->assertOk()
            ->assertSee('Центр содействия развития спорта', false)
            ->assertDontSee('Чужая школа', false);
    }

    public function test_inactive_landing_instruction_returns_404(): void
    {
        Auth::logout();
        $this->landingWidget->update(['is_landing_active' => false]);

        $this->get(route('lead.instruction', [
            'landingSlug' => $this->landingWidget->landing_slug,
        ]))->assertNotFound();
    }

    public function test_unknown_slug_instruction_returns_404(): void
    {
        Auth::logout();

        $this->get(route('lead.instruction', ['landingSlug' => 'unknown-landing-page']))
            ->assertNotFound();
    }

    public function test_guest_can_download_instruction_pdf(): void
    {
        Auth::logout();

        $slug = (string) $this->landingWidget->landing_slug;

        $response = $this->get(route('lead.instruction.pdf', ['landingSlug' => $slug]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringContainsString(
            'instrukciya-'.$slug.'.pdf',
            (string) $response->headers->get('content-disposition')
        );
        $pdf = $response->getContent();
        $this->assertIsString($pdf);
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(2000, strlen($pdf));
    }

    public function test_inactive_landing_instruction_pdf_returns_404(): void
    {
        Auth::logout();
        $this->landingWidget->update(['is_landing_active' => false]);

        $this->get(route('lead.instruction.pdf', [
            'landingSlug' => $this->landingWidget->landing_slug,
        ]))->assertNotFound();
    }

    public function test_unknown_slug_instruction_pdf_returns_404(): void
    {
        Auth::logout();

        $this->get(route('lead.instruction.pdf', ['landingSlug' => 'unknown-landing-page']))
            ->assertNotFound();
    }

    public function test_instruction_pdf_is_attachment_not_html(): void
    {
        Auth::logout();

        $slug = (string) $this->landingWidget->landing_slug;
        $response = $this->get(route('lead.instruction.pdf', ['landingSlug' => $slug]), [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString(
            'attachment;',
            (string) $response->headers->get('content-disposition')
        );
        $this->assertStringContainsString(
            'instrukciya-'.$slug.'.pdf',
            (string) $response->headers->get('content-disposition')
        );

        $pdf = $response->getContent();
        $this->assertIsString($pdf);
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringNotContainsString('<!DOCTYPE html>', $pdf);
        $this->assertStringContainsString('/Image', $pdf);
        $this->assertNotSame(500, $response->getStatusCode());
    }

    public function test_instruction_endpoints_reject_non_get_without_500(): void
    {
        Auth::logout();

        $slug = (string) $this->landingWidget->landing_slug;
        $urls = [
            route('lead.instruction', ['landingSlug' => $slug]),
            route('lead.instruction.pdf', ['landingSlug' => $slug]),
        ];

        foreach ($urls as $url) {
            foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
                $response = $this->call($method, $url, [], [], [], [
                    'HTTP_ACCEPT' => 'text/html',
                ]);
                $this->assertSame(
                    405,
                    $response->getStatusCode(),
                    "{$method} {$url} → {$response->getStatusCode()}"
                );
                $this->assertNotSame(500, $response->getStatusCode(), "{$method} {$url}");
                $this->assertNotSame(200, $response->getStatusCode(), "{$method} {$url} не должен быть пустым 200");
            }
        }
    }

    public function test_instruction_pdf_route_has_no_auth_middleware(): void
    {
        $route = Route::getRoutes()->getByName('lead.instruction.pdf');
        $this->assertNotNull($route);

        $middleware = $route->gatherMiddleware();
        $this->assertNotContains('auth', $middleware);
        $this->assertNotContains('2fa', $middleware);
        $this->assertNotContains('can:schoolLeadLanding.view', $middleware);
        $this->assertStringContainsString('{landingSlug}', $route->uri());
        $this->assertStringContainsString('instruction.pdf', $route->uri());
    }

    public function test_instruction_route_has_no_auth_middleware(): void
    {
        $route = Route::getRoutes()->getByName('lead.instruction');
        $this->assertNotNull($route);

        $middleware = $route->gatherMiddleware();
        $this->assertNotContains('auth', $middleware);
        $this->assertNotContains('2fa', $middleware);
        $this->assertNotContains('can:schoolLeadLanding.view', $middleware);
        $this->assertNotContains('can:schoolLeads.view', $middleware);
        $this->assertStringContainsString('{landingSlug}', $route->uri());
    }
}
