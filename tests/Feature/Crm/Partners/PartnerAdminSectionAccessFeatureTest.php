<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Partners;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Контроль доступа к разделу /admin/partners: guest, partner.view, ожидаемые HTTP-статусы.
 */
final class PartnerAdminSectionAccessFeatureTest extends CrmTestCase
{
    private Partner $targetPartner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->targetPartner = Partner::factory()->create([
            'title' => 'Access matrix partner',
            'email' => 'access_' . Str::lower(Str::random(8)) . '@example.test',
            'is_enabled' => true,
        ]);
    }

    /**
     * @return list<array{
     *     method: string,
     *     url: string,
     *     data?: array<string, mixed>,
     *     json?: bool,
     *     expected: int
     * }>
     */
    private function authorizedRoutesWithExpectedStatus(): array
    {
        $disposable = Partner::factory()->create([
            'title' => 'Disposable access delete',
            'email' => 'del_access_' . Str::lower(Str::random(6)) . '@example.test',
        ]);

        $storeEmail = 'access_store_' . Str::lower(Str::random(8)) . '@example.test';

        return [
            [
                'method' => 'GET',
                'url' => route('admin.partner.index'),
                'expected' => 200,
            ],
            [
                'method' => 'GET',
                'url' => route('admin.partner.data', ['draw' => 1, 'start' => 0, 'length' => 10, 'status' => 'active']),
                'json' => true,
                'expected' => 200,
            ],
            [
                'method' => 'GET',
                'url' => route('admin.partner.columns-settings.get'),
                'json' => true,
                'expected' => 200,
            ],
            [
                'method' => 'POST',
                'url' => route('admin.partner.columns-settings.save'),
                'data' => ['columns' => ['title' => true, 'actions' => true]],
                'json' => true,
                'expected' => 200,
            ],
            [
                'method' => 'GET',
                'url' => route('logs.data.partner', ['draw' => 1, 'start' => 0, 'length' => 5]),
                'json' => true,
                'expected' => 200,
            ],
            [
                'method' => 'GET',
                'url' => route('admin.partner.edit', $this->targetPartner),
                'json' => true,
                'expected' => 200,
            ],
            [
                'method' => 'POST',
                'url' => route('admin.partner.store'),
                'data' => $this->validPartnerPayload([
                    'title' => 'Access store partner',
                    'email' => $storeEmail,
                ]),
                'json' => true,
                'expected' => 201,
            ],
            [
                'method' => 'PATCH',
                'url' => route('admin.partner.update', $this->targetPartner),
                'data' => $this->validPartnerPayload([
                    'title' => 'Access updated partner',
                    'email' => $this->targetPartner->email,
                ]),
                'json' => true,
                'expected' => 200,
            ],
            [
                'method' => 'DELETE',
                'url' => route('admin.partner.delete', $disposable),
                'json' => true,
                'expected' => 200,
            ],
        ];
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>}>
     */
    private function allSectionRoutes(): array
    {
        return array_map(
            static fn (array $item) => [
                'method' => $item['method'],
                'url' => $item['url'],
                'data' => $item['data'] ?? [],
            ],
            $this->authorizedRoutesWithExpectedStatus(),
        );
    }

    private function grantPartnerView(User $actor): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('partner.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_guest_cannot_access_partner_section_routes(): void
    {
        Auth::logout();

        foreach ($this->allSectionRoutes() as $route) {
            $response = $this->call(
                $route['method'],
                $route['url'],
                $route['data'] ?? [],
            );

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$route['method']} {$route['url']} → {$response->getStatusCode()}",
            );
            $this->assertNotSame(500, $response->getStatusCode());
        }
    }

    public function test_user_without_partner_view_gets_403_on_all_section_routes(): void
    {
        $denied = $this->createUserWithoutPermission('partner.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        foreach ($this->allSectionRoutes() as $route) {
            $response = $this->call(
                $route['method'],
                $route['url'],
                $route['data'] ?? [],
                [],
                [],
                ['HTTP_ACCEPT' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'],
            );

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без partner.view: {$route['method']} {$route['url']} → {$response->getStatusCode()}",
            );
        }
    }

    public function test_user_with_partner_view_gets_expected_status_on_all_section_routes(): void
    {
        $actor = $this->createUserWithoutPermission('partner.view', $this->partner);
        $this->grantPartnerView($actor);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        foreach ($this->authorizedRoutesWithExpectedStatus() as $route) {
            $headers = ($route['json'] ?? false)
                ? ['HTTP_ACCEPT' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest']
                : ['HTTP_ACCEPT' => 'text/html'];

            $response = $this->call(
                $route['method'],
                $route['url'],
                $route['data'] ?? [],
                [],
                [],
                $headers,
            );

            $this->assertSame(
                $route['expected'],
                $response->getStatusCode(),
                "С partner.view: {$route['method']} {$route['url']} → {$response->getStatusCode()}, ожидался {$route['expected']}",
            );
            $this->assertNotSame(500, $response->getStatusCode());

            if (($route['json'] ?? false) && in_array($route['expected'], [200, 201], true)) {
                $this->assertNotSame('', trim((string) $response->getContent()), "Пустой ответ: {$route['method']} {$route['url']}");
            }
        }
    }

    public function test_store_ajax_validation_failure_returns_422_not_500(): void
    {
        $this->asSuperadmin();

        $this->postJson(route('admin.partner.store'), [
            'title' => '',
            'email' => 'not-email',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'email']);
    }

    public function test_update_ajax_validation_failure_returns_422_not_500(): void
    {
        $this->asSuperadmin();

        $this->patchJson(route('admin.partner.update', $this->targetPartner), [
            'title' => '',
            'email' => 'bad',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'email']);
    }

    public function test_store_non_ajax_redirects_not_empty_200(): void
    {
        $this->asSuperadmin();

        $email = 'access_non_ajax_' . Str::lower(Str::random(8)) . '@example.test';

        $this->post(route('admin.partner.store'), $this->validPartnerPayload([
            'title' => 'Non-AJAX access test',
            'email' => $email,
        ]))
            ->assertRedirect(route('admin.partner.index'))
            ->assertSessionHas('ok');

        $this->assertNotNull(Partner::query()->where('email', $email)->first());
    }

    public function test_update_non_ajax_redirects_not_empty_200(): void
    {
        $this->asSuperadmin();

        $newTitle = 'Non-AJAX update access ' . Str::random(4);

        $this->patch(route('admin.partner.update', $this->targetPartner), $this->validPartnerPayload([
            'title' => $newTitle,
            'email' => $this->targetPartner->email,
        ]))
            ->assertRedirect(route('admin.partner.index'))
            ->assertSessionHas('ok');

        $this->assertSame($newTitle, $this->targetPartner->fresh()->title);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPartnerPayload(array $overrides = []): array
    {
        $email = $overrides['email'] ?? ('partner_' . Str::lower(Str::random(8)) . '@example.test');

        return array_merge([
            'title' => 'Тестовый партнёр',
            'sms_name' => 'TESTPARTNER',
            'phone' => '+79990001122',
            'email' => $email,
            'website' => 'https://example.test',
            'order_by' => 10,
            'is_enabled' => true,
        ], $overrides);
    }
}
