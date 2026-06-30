<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Non-AJAX safety-net: PATCH без X-Requested-With → redirect на страницу раздела, запись обновлена.
 */
final class PartnerOrganizationNonAjaxSafetyNetFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->grantPartnerView($this->user);
        $this->grantPartnerUpdate($this->user);
    }

    public function test_update_non_ajax_redirects_and_updates_partner(): void
    {
        $newTitle = 'Non Ajax Org ' . Str::random(8);

        $this->from(route('admin.cur.company.edit'))
            ->patch(route('admin.cur.partner.update', $this->partner), [
                'title' => $newTitle,
                'email' => $this->partner->email,
                'phone' => '+79990001122',
            ])
            ->assertRedirect(route('admin.cur.company.edit'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('partners', [
            'id' => $this->partner->id,
            'title' => $newTitle,
        ]);
    }

    public function test_update_non_ajax_strips_legacy_fields(): void
    {
        $this->partner->update(['tax_id' => '5555555555', 'organization_name' => 'Было']);
        $newTitle = 'Non Ajax Strip ' . Str::random(6);

        $this->from(route('admin.cur.company.edit'))
            ->patch(route('admin.cur.partner.update', $this->partner), [
                'title' => $newTitle,
                'email' => $this->partner->email,
                'tax_id' => '8888888888',
                'organization_name' => 'Взлом',
            ])
            ->assertRedirect(route('admin.cur.company.edit'));

        $fresh = $this->partner->fresh();
        $this->assertSame($newTitle, $fresh->title);
        $this->assertSame('5555555555', $fresh->tax_id);
        $this->assertSame('Было', $fresh->organization_name);
    }

    public function test_update_non_ajax_validation_failure_redirects_back_with_errors_not_empty_200(): void
    {
        $originalTitle = $this->partner->title;

        $this->from(route('admin.cur.company.edit'))
            ->patch(route('admin.cur.partner.update', $this->partner), [
                'title' => '',
                'email' => 'bad-email',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['title', 'email']);

        $this->assertSame($originalTitle, $this->partner->fresh()->title);
    }

    public function test_update_non_ajax_foreign_partner_returns_403(): void
    {
        $this->from(route('admin.cur.company.edit'))
            ->patch(route('admin.cur.partner.update', $this->foreignPartner), [
                'title' => 'Hack',
                'email' => 'hack@example.test',
            ])
            ->assertForbidden();
    }

    private function grantPartnerView(User $user, ?Partner $partner = null): void
    {
        $partner ??= $this->partner;

        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $partner->id,
            'role_id' => $user->role_id,
            'permission_id' => $this->permissionId('account.partner.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function grantPartnerUpdate(User $user, ?Partner $partner = null): void
    {
        $partner ??= $this->partner;

        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $partner->id,
            'role_id' => $user->role_id,
            'permission_id' => $this->permissionId('account.partner.update'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
