<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Cabinet;

/**
 * P1: нативный POST без X-Requested-With — 302 на страницу раздела, флаг в БД,
 * не сырой JSON 200. Валидация — redirect с errors.system_monitors.
 *
 * UX-баг до контракта: контроллер мог отдать JSON 200 → белый экран в браузере.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SystemMonitorsNonAjaxSafetyNetFeatureTest extends SystemMonitorsTestCase
{
    public function test_native_post_enable_redirects_to_dashboard_and_persists(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => false])->save();

        $response = $this->from(route('dashboard'))
            ->actingAs($this->user)
            ->post($this->toggleUrl(), [
                'system_monitors' => 1,
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'нативный POST не должен отдавать сырой JSON 200');
        $this->assertNotSame(201, $response->getStatusCode());
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('status', 'Системные мониторы включены.');
        $this->assertTrue((bool) $this->user->fresh()->system_monitors);
        $this->assertStringNotContainsString(
            '"success"',
            (string) $response->getContent()
        );
    }

    public function test_native_post_disable_redirects_and_clears_flag(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => true])->save();

        $response = $this->from(route('dashboard'))
            ->actingAs($this->user)
            ->post($this->toggleUrl(), [
                'system_monitors' => 0,
            ]);

        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('status', 'Системные мониторы выключены.');
        $this->assertFalse((bool) $this->user->fresh()->system_monitors);
    }

    public function test_native_post_from_chat_redirects_back_to_chat(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => false])->save();

        $response = $this->from(route('chat.index'))
            ->actingAs($this->user)
            ->post($this->toggleUrl(), [
                'system_monitors' => 1,
            ]);

        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertRedirect(route('chat.index'));
        $this->assertTrue((bool) $this->user->fresh()->system_monitors);
    }

    public function test_native_post_without_value_redirects_with_field_error(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => false])->save();

        $response = $this->from(route('dashboard'))
            ->actingAs($this->user)
            ->post($this->toggleUrl(), []);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHasErrors([
            'system_monitors' => 'Укажите состояние системных мониторов.',
        ]);
        $this->assertFalse((bool) $this->user->fresh()->system_monitors);
    }

    public function test_native_post_with_invalid_value_redirects_with_field_error(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => true])->save();

        $response = $this->from(route('dashboard'))
            ->actingAs($this->user)
            ->post($this->toggleUrl(), [
                'system_monitors' => 'maybe',
            ]);

        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHasErrors([
            'system_monitors' => 'Некорректное значение системных мониторов.',
        ]);
        $this->assertTrue(
            (bool) $this->user->fresh()->system_monitors,
            'ошибка валидации не должна выключать уже включённый флаг'
        );
    }

    public function test_native_wrong_methods_are_not_empty_200(): void
    {
        $this->asSuperadmin();

        foreach (['GET', 'PATCH', 'PUT', 'DELETE'] as $method) {
            $html = $this->from(route('dashboard'))
                ->actingAs($this->user)
                ->call($method, $this->toggleUrl(), ['system_monitors' => 1]);
            $this->assertNotSame(500, $html->getStatusCode(), $method.' HTML не 500');
            $this->assertNotSame(200, $html->getStatusCode(), $method.' HTML не пустой 200');
            $this->assertContains($html->getStatusCode(), [404, 405], $method.' HTML');
        }
    }
}
