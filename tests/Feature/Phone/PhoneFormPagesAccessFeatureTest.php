<?php

namespace Tests\Feature\Phone;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Phone\Concerns\InteractsWithPhoneInput;
use Tests\Feature\Public\Concerns\ProvidesSchoolLeadLandingFixtures;
use Tests\TestCase;

/**
 * Страницы и эндпоинты с полями телефона: доступ 200 для авторизованных ролей / публичных форм.
 */
final class PhoneFormPagesAccessFeatureTest extends TestCase
{
    use InteractsWithPhoneInput;
    use ProvidesSchoolLeadLandingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureWritableCompiledViews();
        $this->setUpSchoolLeadLandingFixtures();
    }

    public function test_public_phone_form_pages_return_200_and_include_centralized_mask(): void
    {
        Auth::logout();

        $slug = (string) $this->landingWidget->landing_slug;

        $landing = $this->get(route('lead.show', ['landingSlug' => $slug]));
        $landing->assertOk();
        $this->assertCentralizedPhoneMaskAssetsInHtml($landing->getContent());

        $widget = $this->get(route('widget.school-lead.show', [
            'widgetKey' => $this->landingWidget->widget_key,
        ]));
        $widget->assertOk();
        $this->assertCentralizedPhoneMaskAssetsInHtml($widget->getContent());
    }

    public function test_auth_phone_setup_pages_return_200_with_centralized_mask(): void
    {
        $user = User::factory()->create([
            'partner_id' => $this->landingPartner->id,
            'role_id'    => Role::query()->where('name', 'user')->value('id'),
            'phone'      => null,
        ]);

        $this->actingAs($user)
            ->withSession(['current_partner' => $this->landingPartner->id])
            ->get(route('two-factor.phone'))
            ->assertOk()
            ->assertSee('js-phone-mask', false);

        $this->actingAs($user)
            ->withSession(['current_partner' => $this->landingPartner->id])
            ->get(route('security.phone.form'))
            ->assertOk()
            ->assertSee('js-phone-mask', false);
    }
}
