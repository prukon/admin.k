<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Cabinet;

/**
 * P1: JSON-контракт POST /cabinet/system-monitors —
 * 200 структура, 422 errors.system_monitors, 403, не те методы.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SystemMonitorsAjaxContractFeatureTest extends SystemMonitorsTestCase
{
    public function test_ajax_enable_returns_success_and_personal_flag(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => false])->save();

        $this->actingAs($this->user)
            ->postJson($this->toggleUrl(), ['system_monitors' => 1], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('system_monitors', true)
            ->assertJsonMissingPath('errors')
            ->assertJsonStructure(['success', 'system_monitors']);

        $this->assertTrue((bool) $this->user->fresh()->system_monitors);
    }

    public function test_ajax_disable_returns_success_false_flag(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => true])->save();

        $this->actingAs($this->user)
            ->postJson($this->toggleUrl(), ['system_monitors' => 0], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('system_monitors', false);

        $this->assertFalse((bool) $this->user->fresh()->system_monitors);
    }

    public function test_ajax_without_x_requested_with_still_returns_json_when_accept_is_json(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => false])->save();

        $this->actingAs($this->user)
            ->postJson($this->toggleUrl(), ['system_monitors' => 1])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('system_monitors', true);
    }

    public function test_missing_value_returns_422_under_system_monitors_field(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => false])->save();

        $this->actingAs($this->user)
            ->postJson($this->toggleUrl(), [], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['system_monitors'])
            ->assertJsonPath('errors.system_monitors.0', 'Укажите состояние системных мониторов.');

        $this->assertFalse((bool) $this->user->fresh()->system_monitors);
    }

    public function test_invalid_value_returns_422_under_system_monitors_field(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => true])->save();

        $this->actingAs($this->user)
            ->postJson($this->toggleUrl(), ['system_monitors' => 'maybe'], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['system_monitors'])
            ->assertJsonPath('errors.system_monitors.0', 'Некорректное значение системных мониторов.');

        $this->assertTrue(
            (bool) $this->user->fresh()->system_monitors,
            'невалидное значение не должно сбрасывать уже включённый флаг'
        );
    }

    public function test_boolean_strings_are_accepted_as_on_and_off(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => false])->save();

        $this->actingAs($this->user)
            ->postJson($this->toggleUrl(), ['system_monitors' => 'true'], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('system_monitors', true);
        $this->assertTrue((bool) $this->user->fresh()->system_monitors);

        $this->postJson($this->toggleUrl(), ['system_monitors' => 'false'], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('system_monitors', false);
        $this->assertFalse((bool) $this->user->fresh()->system_monitors);

        $this->postJson($this->toggleUrl(), ['system_monitors' => 'on'], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('system_monitors', true);

        $this->postJson($this->toggleUrl(), ['system_monitors' => 'off'], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('system_monitors', false);
    }

    public function test_ajax_forbidden_body_is_json_the_switch_can_parse(): void
    {
        $this->asAdmin();
        $this->user->forceFill(['system_monitors' => false])->save();

        $response = $this->actingAs($this->user)
            ->postJson($this->toggleUrl(), ['system_monitors' => 1], $this->ajaxHeaders());

        $response->assertForbidden();
        $this->assertStringContainsString(
            'json',
            strtolower((string) $response->headers->get('content-type'))
        );
        $payload = $response->json();
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('message', $payload);
        $this->assertNotSame('', trim((string) $payload['message']));
        $this->assertFalse((bool) $this->user->fresh()->system_monitors);
    }

    public function test_wrong_methods_are_not_silent_json_200(): void
    {
        $this->asSuperadmin();

        foreach (['GET', 'PATCH', 'PUT', 'DELETE'] as $method) {
            $json = $this->json($method, $this->toggleUrl(), ['system_monitors' => 1], $this->ajaxHeaders());
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON не 500');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' JSON не успешный 200');
            $this->assertContains($json->getStatusCode(), [404, 405], $method.' JSON');
        }
    }
}
