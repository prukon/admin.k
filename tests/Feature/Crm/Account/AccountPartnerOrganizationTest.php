<?php

namespace Tests\Feature\Crm\Account;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Crm\CrmTestCase;

class AccountPartnerOrganizationTest extends CrmTestCase
{
    public function test_partner_edit_page_ok_when_has_view_permission(): void
    {
        $resp = $this->withSession(['current_partner' => $this->partner->id])
            ->get(route('admin.cur.company.edit'));

        $resp->assertStatus(200);
        $resp->assertViewIs('account.index');
        $resp->assertViewHas('activeTab', 'partner');
    }

    public function test_partner_edit_forbidden_when_missing_view_permission(): void
    {
        $actor = $this->createUserWithoutPermission('account.partner.view', $this->partner);

        $resp = $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id])
            ->get(route('admin.cur.company.edit'));

        $resp->assertStatus(403);
    }

    public function test_partner_edit_shows_update_form_when_has_update_permission(): void
    {
        $resp = $this->withSession(['current_partner' => $this->partner->id])
            ->get(route('admin.cur.company.edit'));

        $resp->assertStatus(200);
        $resp->assertSee('Обновить данные');
    }

    public function test_partner_edit_hides_update_form_when_missing_update_permission(): void
    {
        $actor = $this->createUserWithoutPermission('account.partner.update', $this->partner);

        // Гарантируем, что view-права есть, иначе страница может быть 403 по view.
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $actor->role_id,
            'permission_id' => $this->permissionId('account.partner.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $resp = $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id])
            ->get(route('admin.cur.company.edit'));

        $resp->assertStatus(200);
        $resp->assertDontSee('Обновить данные');
        $resp->assertSee('У вас нет прав на изменение данных организации.');
    }

    public function test_partner_update_ok_when_has_update_permission_and_valid_payload(): void
    {
        $payload = [
            'business_type' => 'company',
            'title'         => 'Test Org ' . Str::random(8),
            'email'         => 'org_' . Str::lower(Str::random(10)) . '@example.com',
        ];

        $resp = $this->withSession([
                'current_partner' => $this->partner->id,
                '2fa:passed'      => true,
            ])
            ->patchJson(route('admin.cur.partner.update', $this->partner), $payload);

        $resp->assertStatus(200);
        $resp->assertJsonPath('success', true);

        $this->assertDatabaseHas('partners', [
            'id'            => $this->partner->id,
            'business_type' => 'company',
            'title'         => $payload['title'],
            'email'         => $payload['email'],
        ]);
    }

    public function test_partner_update_forbidden_when_missing_update_permission(): void
    {
        $actor = $this->createUserWithoutPermission('account.partner.update', $this->partner);

        $payload = [
            'business_type' => 'company',
            'title'         => 'NoPerm Org ' . Str::random(8),
            'email'         => 'noperm_' . Str::lower(Str::random(10)) . '@example.com',
        ];

        $resp = $this->actingAs($actor)
            ->withSession([
                'current_partner' => $this->partner->id,
                '2fa:passed'      => true,
            ])
            ->patchJson(route('admin.cur.partner.update', $this->partner), $payload);

        $resp->assertStatus(403);
    }

    public function test_partner_update_forbidden_when_trying_to_update_foreign_partner_id(): void
    {
        $payload = [
            'business_type' => 'company',
            'title'         => 'Foreign Org ' . Str::random(8),
            'email'         => 'foreign_' . Str::lower(Str::random(10)) . '@example.com',
        ];

        $resp = $this->withSession([
                'current_partner' => $this->partner->id,
                '2fa:passed'      => true,
            ])
            ->patchJson(route('admin.cur.partner.update', $this->foreignPartner), $payload);

        $resp->assertStatus(403);
    }

    public function test_partner_update_returns_422_on_validation_error(): void
    {
        $payload = [
            'business_type' => 'company',
            // title is required
            'email'         => 'bad_' . Str::lower(Str::random(10)) . '@example.com',
        ];

        $resp = $this->withSession([
                'current_partner' => $this->partner->id,
                '2fa:passed'      => true,
            ])
            ->patchJson(route('admin.cur.partner.update', $this->partner), $payload);

        $resp->assertStatus(422);
        $resp->assertJsonValidationErrors(['title']);
    }
}

