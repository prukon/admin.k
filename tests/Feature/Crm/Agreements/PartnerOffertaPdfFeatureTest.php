<?php

namespace Tests\Feature\Crm\Agreements;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

final class PartnerOffertaPdfFeatureTest extends CrmTestCase
{
    private const PERMISSION = 'partner.oferta.pdf';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    public function test_guest_cannot_download_partner_offer_pdf(): void
    {
        Auth::logout();

        foreach ([route('admin.partnerOferta.pdf'), route('partner.oferta.pdf')] as $url) {
            $response = $this->get($url);
            $response->assertStatus(302);
            $this->assertGuest();
            $this->assertNotSame(200, $response->getStatusCode());
        }
    }

    public function test_admin_does_not_see_pdf_button_and_gets_403(): void
    {
        $this->asAdmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->get(route('admin.partnerOferta'))
            ->assertOk()
            ->assertDontSee('Скачать PDF', false)
            ->assertSee('Партнёрская оферта на использование сервиса', false);

        $this->get(route('admin.partnerOferta.pdf'))->assertForbidden();
        $this->get(route('partner.oferta.pdf'))->assertForbidden();
    }

    public function test_superadmin_sees_pdf_button_and_downloads_pdf(): void
    {
        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->get(route('admin.partnerOferta'))
            ->assertOk()
            ->assertSee('Скачать PDF', false)
            ->assertSee(route('partner.oferta.pdf'), false);

        $response = $this->get(route('partner.oferta.pdf'));
        $response
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename="partnerskaya-oferta-kidscrm.pdf"');
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $adminPdf = $this->get(route('admin.partnerOferta.pdf'));
        $adminPdf->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_admin_with_granted_permission_can_download_pdf(): void
    {
        $this->asAdmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId(self::PERMISSION),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->user->unsetRelation('role');

        $this->get(route('admin.partnerOferta'))
            ->assertOk()
            ->assertSee('Скачать PDF', false);

        $this->get(route('partner.oferta.pdf'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_pdf_endpoint_rejects_non_get(): void
    {
        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $url = route('partner.oferta.pdf');

        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $response = $this->call($method, $url, [], [], [], [
                'HTTP_ACCEPT' => 'text/html',
            ]);

            $this->assertSame(405, $response->getStatusCode(), "{$method} {$url}");
            $this->assertNotSame(200, $response->getStatusCode());
            $this->assertNotSame(500, $response->getStatusCode());
        }
    }
}
