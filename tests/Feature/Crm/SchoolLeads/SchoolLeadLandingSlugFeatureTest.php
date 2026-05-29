<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\Partner;
use App\Models\User;
use App\Services\PartnerWidgetService;
use App\Support\PartnerLandingSlug;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Slug страницы заявки: сохранение в CRM, валидация, уникальность, доступ к API.
 */
final class SchoolLeadLandingSlugFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    public function test_guest_cannot_update_landing_slug(): void
    {
        Auth::logout();

        $response = $this->putJson(route('admin.school-leads.landing-slug.update'), [
            'landing_slug' => 'guest-try',
        ]);

        $this->assertContains(
            $response->getStatusCode(),
            [302, 401, 403, 419],
            'Гость не должен сохранять slug'
        );
    }

    public function test_user_without_school_lead_landing_view_cannot_update_slug(): void
    {
        $denied = $this->createUserWithoutPermission('schoolLeadLanding.view', $this->partner);
        $this->actingAs($denied);

        $this->putJson(route('admin.school-leads.landing-slug.update'), [
            'landing_slug' => 'denied-try',
        ])
            ->assertForbidden();
    }

    public function test_update_slug_normalizes_input_before_save(): void
    {
        $actor = $this->grantLandingViewActor();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        $this->putJson(route('admin.school-leads.landing-slug.update'), [
            'landing_slug' => '  FK-Dinamo  ',
        ])
            ->assertOk()
            ->assertJsonPath('landing_slug', 'fk-dinamo')
            ->assertJsonPath('landing_url', route('lead.show', ['landingSlug' => 'fk-dinamo']));

        $this->assertDatabaseHas('partner_widgets', [
            'partner_id'   => $this->partner->id,
            'landing_slug' => 'fk-dinamo',
        ]);
    }

    public function test_update_slug_replaces_spaces_and_special_chars_with_hyphens(): void
    {
        $this->grantLandingViewActor();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        $this->putJson(route('admin.school-leads.landing-slug.update'), [
            'landing_slug' => 'shkola__rossi!!!',
        ])
            ->assertOk()
            ->assertJsonPath('landing_slug', 'shkola-rossi');
    }

    public function test_partner_can_change_slug_to_new_value(): void
    {
        $this->grantLandingViewActor();
        $widget = app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
        $widget->update(['landing_slug' => 'old-slug']);

        $this->putJson(route('admin.school-leads.landing-slug.update'), [
            'landing_slug' => 'new-slug',
        ])
            ->assertOk()
            ->assertJsonPath('landing_slug', 'new-slug');

        $this->assertDatabaseHas('partner_widgets', [
            'partner_id'   => $this->partner->id,
            'landing_slug' => 'new-slug',
        ]);
        $this->assertDatabaseMissing('partner_widgets', [
            'partner_id'   => $this->partner->id,
            'landing_slug' => 'old-slug',
        ]);
    }

    public function test_partner_can_save_same_slug_again_without_unique_error(): void
    {
        $this->grantLandingViewActor();
        $widget = app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
        $widget->update(['landing_slug' => 'my-school']);

        $this->putJson(route('admin.school-leads.landing-slug.update'), [
            'landing_slug' => 'my-school',
        ])
            ->assertOk()
            ->assertJsonPath('landing_slug', 'my-school');
    }

    public function test_update_slug_rejects_too_short_value(): void
    {
        $this->grantLandingViewActor();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        $this->putJson(route('admin.school-leads.landing-slug.update'), [
            'landing_slug' => 'ab',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['landing_slug']);
    }

    public function test_update_slug_rejects_too_long_value(): void
    {
        $this->grantLandingViewActor();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        $this->putJson(route('admin.school-leads.landing-slug.update'), [
            'landing_slug' => str_repeat('a', PartnerLandingSlug::MAX_LENGTH + 1),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['landing_slug']);
    }

    public function test_update_slug_rejects_cyrillic_only_input_after_normalization(): void
    {
        $this->grantLandingViewActor();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        $this->putJson(route('admin.school-leads.landing-slug.update'), [
            'landing_slug' => 'школа',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['landing_slug']);
    }

    public function test_update_slug_converts_underscores_to_hyphens(): void
    {
        $this->grantLandingViewActor();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        $this->putJson(route('admin.school-leads.landing-slug.update'), [
            'landing_slug' => 'shkola_rossi',
        ])
            ->assertOk()
            ->assertJsonPath('landing_slug', 'shkola-rossi');
    }

    public function test_update_slug_rejects_empty_after_normalization(): void
    {
        $this->grantLandingViewActor();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        $this->putJson(route('admin.school-leads.landing-slug.update'), [
            'landing_slug' => '---',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['landing_slug']);
    }

    /**
     * @dataProvider reservedSlugProvider
     */
    public function test_update_slug_rejects_reserved_slugs(string $reserved): void
    {
        $this->grantLandingViewActor();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        $this->putJson(route('admin.school-leads.landing-slug.update'), [
            'landing_slug' => $reserved,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['landing_slug']);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function reservedSlugProvider(): array
    {
        $cases = [];
        foreach (['admin', 'widget', 'blog', 'lead'] as $slug) {
            $cases[$slug] = [$slug];
        }

        return $cases;
    }

    public function test_update_slug_rejects_duplicate_of_another_partner(): void
    {
        $otherPartner = Partner::factory()->create();
        $otherWidget = app(PartnerWidgetService::class)->ensureForPartner((int) $otherPartner->id);
        $otherWidget->update(['landing_slug' => 'unique-school']);

        $this->grantLandingViewActor();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        $this->putJson(route('admin.school-leads.landing-slug.update'), [
            'landing_slug' => 'unique-school',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['landing_slug']);
    }

    public function test_landing_slug_update_route_is_registered_before_school_lead_parameter_route(): void
    {
        $slugRoute = Route::getRoutes()->getByName('admin.school-leads.landing-slug.update');
        $updateRoute = Route::getRoutes()->getByName('admin.school-leads.update');

        $this->assertNotNull($slugRoute);
        $this->assertNotNull($updateRoute);
        $this->assertStringContainsString('landing-slug', $slugRoute->uri());
        $this->assertLessThan(
            $this->routeRegistrationIndex('admin.school-leads.update'),
            $this->routeRegistrationIndex('admin.school-leads.landing-slug.update'),
            'Маршрут landing-slug должен регистрироваться раньше {schoolLead}, иначе PUT даст 404.'
        );
    }

    public function test_public_lead_routes_use_landing_slug_parameter(): void
    {
        foreach (['lead.show', 'lead.teams', 'lead.team-info', 'lead.submit'] as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route, "Маршрут {$routeName} не найден");
            $this->assertStringContainsString('{landingSlug}', $route->uri(), $routeName);
        }
    }

    private function grantLandingViewActor(): User
    {
        $actor = $this->createUserWithoutPermission('schoolLeadLanding.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeadLanding.view');
        $this->actingAs($actor);

        return $actor;
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

    private function routeRegistrationIndex(string $routeName): int
    {
        $routes = Route::getRoutes()->getRoutes();
        foreach ($routes as $index => $route) {
            if ($route->getName() === $routeName) {
                return $index;
            }
        }

        $this->fail("Маршрут {$routeName} не найден в списке регистрации.");

        return -1;
    }
}
