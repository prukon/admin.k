<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/**
 * Доступ к POST /admin/school-leads/instruction-preview: гость, без права, без организации,
 * anti-leak, методы кроме POST не 500.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SchoolLeadLandingInstructionPreviewAccessFeatureTest extends SchoolLeadLandingInstructionPreviewTestCase
{
    public function test_guest_html_post_redirects_to_login(): void
    {
        Auth::logout();

        $response = $this->from(route('admin.school-leads.landing'))
            ->post($this->previewUrl(), [
                'omit_phone' => 1,
            ]);

        $this->assertContains($response->getStatusCode(), [302, 401, 403, 419]);
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());
        if ($response->getStatusCode() === 302) {
            $response->assertRedirect();
        }
    }

    public function test_guest_json_post_is_unauthorized(): void
    {
        Auth::logout();

        $this->postJson($this->previewUrl(), [
            'omit_phone' => 1,
        ])->assertUnauthorized();
    }

    public function test_user_without_landing_view_gets_403_on_preview(): void
    {
        $denied = $this->createUserWithoutPermission('schoolLeadLanding.view', $this->partner);
        $this->actingAs($denied);

        $this->postJson($this->previewUrl(), [
            'omit_phone' => 1,
        ])->assertForbidden();

        $this->from(route('admin.school-leads.landing'))
            ->post($this->previewUrl(), [
                'omit_phone' => 1,
            ])
            ->assertForbidden();
    }

    public function test_user_with_landing_view_can_preview_instruction(): void
    {
        $this->actingAsLandingViewer();
        $this->widgetWithSlug();

        $this->postJson($this->previewUrl(), [
            'omit_phone' => 1,
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath(
                'instruction_url',
                route('lead.instruction', ['landingSlug' => 'crm-instr-school'])
            );
    }

    public function test_admin_without_partner_is_logged_out_with_email_error(): void
    {
        $this->asAdmin();
        $this->user->partner_id = null;
        $this->user->save();
        $this->actingAs($this->user);
        $this->withSession([
            'current_partner' => null,
            '2fa:passed' => true,
        ]);

        $response = $this->post($this->previewUrl(), [
            'omit_phone' => 1,
        ]);

        $response->assertStatus(302);
        $this->assertGuest();
        $response->assertSessionHasErrors(['email' => 'Ваша организация недоступна.']);
    }

    public function test_non_superadmin_cannot_preview_another_partners_instruction_via_session(): void
    {
        $this->actingAsLandingViewer();
        $this->widgetWithSlug('own-school-slug');

        $foreignWidget = app(PartnerWidgetService::class)->ensureForPartner((int) $this->foreignPartner->id);
        $foreignWidget->update(['landing_slug' => 'foreign-school-slug']);

        $this->withSession([
            'current_partner' => $this->foreignPartner->id,
            '2fa:passed' => true,
        ]);

        $this->postJson($this->previewUrl(), [
            'omit_phone' => 1,
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath(
                'instruction_url',
                route('lead.instruction', ['landingSlug' => 'own-school-slug'])
            )
            ->assertJsonMissing(['instruction_url' => route('lead.instruction', [
                'landingSlug' => 'foreign-school-slug',
            ])]);
    }

    public function test_preview_route_rejects_non_post_without_500(): void
    {
        $this->actingAsLandingViewer();
        $this->widgetWithSlug();

        $url = $this->previewUrl();
        $route = Route::getRoutes()->getByName('admin.school-leads.instruction-preview');
        $this->assertNotNull($route);
        $this->assertContains('can:schoolLeadLanding.view', $route->gatherMiddleware());

        foreach (['GET', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $response = $this->call($method, $url, [], [], [], [
                'HTTP_ACCEPT' => 'text/html',
            ]);
            $this->assertContains(
                $response->getStatusCode(),
                [403, 404, 405, 419],
                "{$method} {$url} → {$response->getStatusCode()}"
            );
            $this->assertNotSame(500, $response->getStatusCode(), "{$method} {$url}");
            $this->assertNotSame(200, $response->getStatusCode(), "{$method} {$url} не должен быть пустым 200");
        }
    }
}
