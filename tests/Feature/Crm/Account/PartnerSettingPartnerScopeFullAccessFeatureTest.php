<?php

namespace Tests\Feature\Crm\Account;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Учётная запись → Организация: доступ к странице и endpoint’ам,
 * partner-scope (STRICT_CURRENT) на чтение и обновление.
 */
final class PartnerSettingPartnerScopeFullAccessFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    public function test_guest_cannot_access_partner_organization_endpoints(): void
    {
        Auth::logout();

        foreach ($this->allSectionRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_without_account_partner_view_gets_403_on_all_endpoints(): void
    {
        $denied = $this->createUserWithoutPermission('account.partner.view', $this->partner);
        $this->actingAs($denied);

        foreach ($this->allSectionRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без account.partner.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_with_partner_permissions_all_section_endpoints_return_200(): void
    {
        $actor = $this->createUserWithoutPermission('account.partner.view', $this->partner);
        $this->grantPartnerView($actor);
        $this->grantPartnerUpdate($actor);
        $this->actingAs($actor);

        $this->get(route('admin.cur.company.edit'))
            ->assertOk()
            ->assertViewIs('account.index')
            ->assertViewHas('activeTab', 'partner');

        $payload = [
            'business_type' => 'company',
            'title'         => 'Scope Org ' . Str::random(8),
            'email'         => 'scope_' . Str::lower(Str::random(8)) . '@example.test',
        ];

        $this->patchJson(route('admin.cur.partner.update', $this->partner), $payload)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('partners', [
            'id'    => $this->partner->id,
            'title' => $payload['title'],
        ]);
    }

    public function test_user_with_view_only_cannot_update_partner(): void
    {
        $actor = $this->createUserWithoutPermission('account.partner.update', $this->partner);
        $this->grantPartnerView($actor);
        $this->actingAs($actor);

        $this->get(route('admin.cur.company.edit'))->assertOk();

        $this->patchJson(route('admin.cur.partner.update', $this->partner), [
            'business_type' => 'company',
            'title'         => 'No update ' . Str::random(6),
            'email'         => 'no_' . Str::lower(Str::random(6)) . '@example.test',
        ])->assertForbidden();
    }

    public function test_partner_edit_page_shows_current_partner_not_foreign(): void
    {
        $this->grantPartnerView($this->user);

        $foreignTitle = 'ForeignOrgTitle_' . Str::random(8);
        $this->foreignPartner->update(['title' => $foreignTitle]);

        $this->get(route('admin.cur.company.edit'))
            ->assertOk()
            ->assertViewHas('partner', fn ($p) => (int) $p->id === (int) $this->partner->id)
            ->assertSee($this->partner->title, false)
            ->assertDontSee($foreignTitle, false);
    }

    public function test_partner_update_forbidden_for_foreign_partner_id(): void
    {
        $this->grantPartnerView($this->user);
        $this->grantPartnerUpdate($this->user);

        $hackTitle = 'HackOrg_' . Str::random(8);
        $originalTitle = $this->foreignPartner->title;

        $this->patchJson(route('admin.cur.partner.update', $this->foreignPartner), [
            'business_type' => 'company',
            'title'         => $hackTitle,
            'email'         => 'hack_' . Str::lower(Str::random(6)) . '@example.test',
        ])
            ->assertForbidden()
            ->assertJson([
                'success' => false,
                'message' => 'Доступ запрещён.',
            ]);

        $this->foreignPartner->refresh();
        $this->assertSame($originalTitle, $this->foreignPartner->title);
    }

    public function test_superadmin_with_null_partner_id_sees_session_partner_on_page(): void
    {
        $this->asSuperadmin();
        $this->user->partner_id = null;
        $this->user->save();
        $this->grantPartnerView($this->user);

        $this->actingAs($this->user)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->get(route('admin.cur.company.edit'))
            ->assertOk()
            ->assertViewHas('partner', fn ($p) => (int) $p->id === (int) $this->partner->id);
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function allSectionRoutesPayload(): array
    {
        return [
            ['method' => 'GET', 'url' => route('admin.cur.company.edit')],
            [
                'method'  => 'PATCH',
                'url'     => route('admin.cur.partner.update', $this->partner),
                'data'    => [
                    'business_type' => 'company',
                    'title'         => 'Route ' . Str::random(6),
                    'email'         => 'route_' . Str::lower(Str::random(6)) . '@example.test',
                ],
                'headers' => ['HTTP_ACCEPT' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'],
            ],
        ];
    }

    private function grantPartnerView(User $user, ?Partner $partner = null): void
    {
        $partner ??= $this->partner;

        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $partner->id,
            'role_id'       => $user->role_id,
            'permission_id' => $this->permissionId('account.partner.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private function grantPartnerUpdate(User $user, ?Partner $partner = null): void
    {
        $partner ??= $this->partner;

        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $partner->id,
            'role_id'       => $user->role_id,
            'permission_id' => $this->permissionId('account.partner.update'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
}
