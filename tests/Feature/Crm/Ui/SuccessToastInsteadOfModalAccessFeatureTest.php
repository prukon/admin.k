<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Ui;

use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Ui\Concerns\SuccessToastInsteadOfModalTestHelpers;

/**
 * Доступ к страницам, где успех без reload идёт во всплывайку:
 * гость, без права раздела, с правом — GET не 500 и не пустой 200.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SuccessToastInsteadOfModalAccessFeatureTest extends CrmTestCase
{
    use SuccessToastInsteadOfModalTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
    }

    public function test_guest_is_denied_on_toast_pages_and_never_gets_500(): void
    {
        Auth::logout();

        foreach ($this->toastPageSpecs() as $spec) {
            $url = route($spec['route']);
            $web = $this->get($url);
            $this->assertContains(
                $web->getStatusCode(),
                [302, 401, 403],
                "Гость web GET {$spec['key']} → {$web->getStatusCode()}"
            );
            $this->assertNotSame(500, $web->getStatusCode(), "Гость web GET {$spec['key']} → 500");
            $this->assertNotSame(200, $web->getStatusCode(), "Гость не должен видеть {$spec['key']}");

            $json = $this->getJson($url);
            $this->assertContains(
                $json->getStatusCode(),
                [302, 401, 403],
                "Гость JSON GET {$spec['key']} → {$json->getStatusCode()}"
            );
            $this->assertNotSame(500, $json->getStatusCode(), "Гость JSON GET {$spec['key']} → 500");
        }
    }

    public function test_manager_without_section_permission_gets_403_on_toast_pages(): void
    {
        foreach ($this->toastPageSpecs() as $spec) {
            $actor = $this->createUserWithoutPermission($spec['deny'], $this->partner);
            $this->actingAs($actor);
            $this->withSession([
                'current_partner' => $this->partner->id,
                '2fa:passed'      => true,
            ]);

            $forbidden = $this->get(route($spec['route']));
            $this->assertSame(403, $forbidden->getStatusCode(), "Без {$spec['deny']}: GET {$spec['key']}");

            $json = $this->getJson(route($spec['route']));
            $this->assertSame(403, $json->getStatusCode(), "Без {$spec['deny']}: JSON GET {$spec['key']}");
        }
    }

    public function test_authorized_user_opens_toast_pages_with_hidden_toast_not_empty_200(): void
    {
        foreach ($this->toastPageSpecs() as $spec) {
            $this->asAdminWith($spec['grant']);

            $response = $this->get(route($spec['route']));
            $this->assertSame(200, $response->getStatusCode(), "С правами: GET {$spec['key']} → {$response->getStatusCode()}");
            $html = $response->getContent();
            $this->assertNotSame('', trim($html), "{$spec['key']}: пустой 200");
            $this->assertStringContainsString(
                'id="kidsMainToast"',
                $html,
                "{$spec['key']}: на первом открытии должна быть общая всплывайка layout"
            );
            $this->assertStringContainsString('window.showToast', $html, $spec['key']);
            $this->assertStringContainsString(
                'z-index: 4050',
                $html,
                "{$spec['key']}: toast выше #confirmDeleteModal (1900) и #errorModal (4010)"
            );
        }
    }
}
