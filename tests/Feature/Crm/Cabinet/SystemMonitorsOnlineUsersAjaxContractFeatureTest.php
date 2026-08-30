<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Cabinet;

use App\Models\Partner;
use App\Services\Chat\UserPresence;
use App\Support\OnlineUsersMonitor;

/**
 * P1: JSON-контракт GET /cabinet/system-monitors/online-users —
 * структура, окно 120 с, все партнёры, без лишних полей.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SystemMonitorsOnlineUsersAjaxContractFeatureTest extends SystemMonitorsTestCase
{
    public function test_snapshot_is_available_when_personal_flag_is_off(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => false])->save();
        $this->createUserWithRole('user', $this->partner, [
            'lastname' => 'Флаг',
            'name' => 'Выкл',
            'last_seen_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->getJson($this->onlineUsersUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('partners.0.users.0.name', 'Флаг Выкл');
    }

    public function test_empty_snapshot_has_ok_total_zero_and_empty_partners(): void
    {
        $this->asSuperadmin();

        $this->actingAs($this->user)
            ->getJson($this->onlineUsersUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('total', 0)
            ->assertJsonPath('partners', [])
            ->assertJsonPath('online_within_seconds', UserPresence::ONLINE_WITHIN_SECONDS)
            ->assertJsonStructure(['ok', 'online_within_seconds', 'total', 'partners']);
    }

    public function test_json_does_not_leak_password_or_email(): void
    {
        $this->asSuperadmin();
        $this->createUserWithRole('user', $this->partner, [
            'lastname' => 'Иванов',
            'name' => 'Иван',
            'email' => 'secret-online@example.test',
            'last_seen_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson($this->onlineUsersUrl(), $this->ajaxHeaders())
            ->assertOk();

        $payload = $response->json('partners.0.users.0');
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('id', $payload);
        $this->assertArrayHasKey('name', $payload);
        $this->assertArrayNotHasKey('email', $payload);
        $this->assertArrayNotHasKey('password', $payload);
        $this->assertArrayNotHasKey('last_seen_at', $payload);
        $this->assertStringNotContainsString('secret-online@example.test', (string) $response->getContent());
        $this->assertStringNotContainsString('"password"', (string) $response->getContent());
    }

    public function test_user_at_online_window_edge_is_listed_and_stale_is_not(): void
    {
        $this->asSuperadmin();
        $now = now();
        $this->travelTo($now);

        $fresh = $this->createUserWithRole('user', $this->partner, [
            'lastname' => 'Свежий',
            'name' => 'Юзер',
            'last_seen_at' => $now->copy()->subSeconds(UserPresence::ONLINE_WITHIN_SECONDS),
        ]);
        $this->createUserWithRole('user', $this->partner, [
            'lastname' => 'Старый',
            'name' => 'Юзер',
            'last_seen_at' => $now->copy()->subSeconds(UserPresence::ONLINE_WITHIN_SECONDS + 1),
        ]);
        $this->createUserWithRole('user', $this->partner, [
            'lastname' => 'Пустой',
            'name' => 'Пинг',
            'last_seen_at' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson($this->onlineUsersUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('total', 1);

        $this->assertSame($fresh->id, (int) $response->json('partners.0.users.0.id'));
        $this->assertSame('Свежий Юзер', $response->json('partners.0.users.0.name'));
    }

    public function test_snapshot_excludes_viewer_and_includes_users_from_other_partners(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill([
            'lastname' => 'Супер',
            'name' => 'Админ',
            'last_seen_at' => now(),
        ])->save();
        $other = Partner::factory()->create(['title' => 'Чужая школа']);
        $peer = $this->createUserWithRole('user', $other, [
            'lastname' => 'Гость',
            'name' => 'Школы',
            'last_seen_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->withSession([
                'current_partner' => $this->partner->id,
                '2fa:passed' => true,
            ])
            ->getJson($this->onlineUsersUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('total', 1);

        $ids = collect($response->json('partners'))
            ->flatMap(fn ($group) => $group['users'])
            ->pluck('id')
            ->all();
        $names = collect($response->json('partners'))
            ->flatMap(fn ($group) => $group['users'])
            ->pluck('name')
            ->all();
        $this->assertNotContains($this->user->id, $ids);
        $this->assertNotContains('Супер Админ', $names);
        $this->assertContains('Гость Школы', $names);
        $this->assertContains($peer->id, $ids);
    }

    public function test_snapshot_is_empty_when_only_the_viewer_is_online(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill([
            'lastname' => 'Супер',
            'name' => 'Админ',
            'last_seen_at' => now(),
        ])->save();

        $this->actingAs($this->user)
            ->getJson($this->onlineUsersUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('total', 0)
            ->assertJsonPath('partners', []);
    }

    public function test_snapshot_excludes_viewer_from_same_partner_count(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill([
            'lastname' => 'Супер',
            'name' => 'Админ',
            'partner_id' => $this->partner->id,
            'last_seen_at' => now(),
        ])->save();
        $peer = $this->createUserWithRole('user', $this->partner, [
            'lastname' => 'Коллега',
            'name' => 'Онлайн',
            'last_seen_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson($this->onlineUsersUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('partners.0.count', 1)
            ->assertJsonPath('partners.0.users.0.name', 'Коллега Онлайн');

        $this->assertSame($peer->id, (int) $response->json('partners.0.users.0.id'));
        $this->assertNotSame($this->user->id, (int) $response->json('partners.0.users.0.id'));
    }

    public function test_empty_names_and_partner_title_use_fallbacks(): void
    {
        $this->asSuperadmin();
        $namelessPartner = Partner::factory()->create(['title' => '   ']);
        $user = $this->createUserWithRole('user', $namelessPartner, [
            'lastname' => '',
            'name' => '',
            'last_seen_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->getJson($this->onlineUsersUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('partners.0.title', 'Без названия')
            ->assertJsonPath('partners.0.users.0.name', '#'.$user->id);
    }

    public function test_soft_deleted_partner_still_groups_users_under_its_title(): void
    {
        $this->asSuperadmin();
        $partner = Partner::factory()->create(['title' => 'Архив-школа']);
        $this->createUserWithRole('admin', $partner, [
            'lastname' => 'Архивов',
            'name' => 'Антон',
            'last_seen_at' => now(),
        ]);
        $partner->delete();

        $this->actingAs($this->user)
            ->getJson($this->onlineUsersUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('partners.0.title', 'Архив-школа')
            ->assertJsonPath('partners.0.users.0.name', 'Архивов Антон');
    }

    public function test_missing_partner_block_is_last_and_uses_agreed_title(): void
    {
        $this->asSuperadmin();
        Partner::factory()->create(['title' => 'Якорь']);
        $this->createUserWithRole('user', $this->partner, [
            'lastname' => 'Школьный',
            'name' => 'Ученик',
            'last_seen_at' => now(),
        ]);
        $this->createUserWithRole('user', $this->partner, [
            'lastname' => 'Безшколы',
            'name' => 'Борис',
            'partner_id' => null,
            'last_seen_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson($this->onlineUsersUrl(), $this->ajaxHeaders())
            ->assertOk();

        $titles = collect($response->json('partners'))->pluck('title')->all();
        $this->assertSame(OnlineUsersMonitor::MISSING_PARTNER_TITLE, $titles[array_key_last($titles)]);
        $this->assertNotSame(OnlineUsersMonitor::MISSING_PARTNER_TITLE, $titles[0]);
    }

    public function test_ajax_forbidden_body_is_json_the_overlay_can_parse(): void
    {
        $this->asAdmin();

        $response = $this->actingAs($this->user)
            ->getJson($this->onlineUsersUrl(), $this->ajaxHeaders());

        $response->assertForbidden();
        $this->assertStringContainsString('json', strtolower((string) $response->headers->get('content-type')));
        $payload = $response->json();
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('message', $payload);
        $this->assertNotSame('', trim((string) $payload['message']));
    }
}
