<?php

namespace Tests\Feature\Crm\Cabinet;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Переключатель ширины кабинета: users.layout_wide, только layout admin2.
 */
final class CabinetLayoutWideFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['broadcasting.default' => 'null']);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    public function test_guest_is_redirected_from_layout_wide_update(): void
    {
        Auth::logout();

        $response = $this->postJson(route('cabinet.layout-wide.update'), [
            'layout_wide' => 1,
        ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertTrue(
            $response->isRedirect() || $response->status() === 401,
            'гость не должен сохранять ширину кабинета'
        );
    }

    public function test_authenticated_user_can_enable_layout_wide(): void
    {
        $this->asAdmin();
        $this->user->forceFill(['layout_wide' => false])->save();

        $this->actingAs($this->user)
            ->postJson(route('cabinet.layout-wide.update'), [
                'layout_wide' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('layout_wide', true);

        $this->assertTrue((bool) $this->user->fresh()->layout_wide);
    }

    public function test_authenticated_user_can_disable_layout_wide(): void
    {
        $this->asAdmin();
        $this->user->forceFill(['layout_wide' => true])->save();

        $this->actingAs($this->user)
            ->postJson(route('cabinet.layout-wide.update'), [
                'layout_wide' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('layout_wide', false);

        $this->assertFalse((bool) $this->user->fresh()->layout_wide);
    }

    public function test_validation_error_is_returned_under_layout_wide_field(): void
    {
        $this->asAdmin();

        $this->actingAs($this->user)
            ->postJson(route('cabinet.layout-wide.update'), [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['layout_wide'])
            ->assertJsonPath('errors.layout_wide.0', 'Укажите ширину кабинета.');

        $this->actingAs($this->user)
            ->postJson(route('cabinet.layout-wide.update'), [
                'layout_wide' => 'wide',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['layout_wide'])
            ->assertJsonPath('errors.layout_wide.0', 'Некорректное значение ширины кабинета.');
    }

    public function test_update_does_not_change_another_users_layout_wide(): void
    {
        $this->asAdmin();
        $this->user->forceFill(['layout_wide' => false])->save();
        $this->foreignUser->forceFill(['layout_wide' => false])->save();

        $this->actingAs($this->user)
            ->postJson(route('cabinet.layout-wide.update'), [
                'layout_wide' => 1,
            ])
            ->assertOk();

        $this->assertTrue((bool) $this->user->fresh()->layout_wide);
        $this->assertFalse((bool) $this->foreignUser->fresh()->layout_wide);
    }

    public function test_cabinet_renders_toggle_and_layout_wide_class_from_user_setting(): void
    {
        $this->asAdmin();
        $this->user->forceFill(['layout_wide' => true])->save();

        $html = $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('layout-wide', $html);
        $this->assertStringContainsString('id="layout-wide-toggle"', $html);
        $this->assertStringContainsString('d-none d-md-flex', $html);
        $this->assertStringContainsString(route('cabinet.layout-wide.update', [], false), $html);
        $this->assertStringContainsString('.layout-wide .wrapper', $html);
        $this->assertStringContainsString('errors.layout_wide', $html);
    }

    public function test_cabinet_without_layout_wide_keeps_compact_body_class(): void
    {
        $this->asAdmin();
        $this->user->forceFill(['layout_wide' => false])->save();

        $html = $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="layout-wide-toggle"', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/<body[^>]*\blayout-wide\b/',
            $html,
            'без настройки у body не должно быть layout-wide'
        );
    }

    public function test_landing_does_not_render_layout_wide_toggle(): void
    {
        Auth::logout();

        $html = $this->get(route('landing.home'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('id="layout-wide-toggle"', $html);
        $this->assertStringNotContainsString('cabinet.layout-wide.update', $html);
    }

    public function test_non_ajax_update_redirects_and_persists(): void
    {
        $this->asAdmin();
        $this->user->forceFill(['layout_wide' => false])->save();

        $response = $this->from(route('dashboard'))
            ->actingAs($this->user)
            ->post(route('cabinet.layout-wide.update'), [
                'layout_wide' => 1,
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'нативный POST не должен отдавать сырой JSON 200');
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('status');
        $this->assertTrue((bool) $this->user->fresh()->layout_wide);
    }

    public function test_student_role_can_save_own_layout_wide(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'layout_wide' => false,
        ]);

        $this->actingAs($student)
            ->withSession([
                'current_partner' => $this->partner->id,
                '2fa:passed' => true,
            ])
            ->postJson(route('cabinet.layout-wide.update'), [
                'layout_wide' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('layout_wide', true);

        $this->assertTrue((bool) $student->fresh()->layout_wide);
    }
}
