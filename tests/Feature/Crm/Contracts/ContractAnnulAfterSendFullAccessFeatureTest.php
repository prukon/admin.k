<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;

/**
 * Полный доступ к аннулированию: admin/viewer 200; чужие HTTP-методы не 500.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ContractAnnulAfterSendFullAccessFeatureTest extends ContractAnnulAfterSendTestCase
{
    public function test_admin_gets_200_on_show_and_annul_json(): void
    {
        $this->asAdmin();
        $contract = $this->makeAnnulContract(['status' => Contract::STATUS_OPENED]);

        $show = $this->get(route('contracts.show', $contract));
        $show->assertOk();
        $this->assertNotSame('', trim((string) $show->getContent()));
        $show->assertSee('id="annulAfterSendBtn"', false);

        $this->postJson(route('contracts.revoke', $contract), [])
            ->assertOk()
            ->assertJsonPath('status', 'revoked')
            ->assertJsonPath('message', 'Договор аннулирован. 70 ₽ не возвращаются.');
    }

    public function test_superadmin_can_annul_sent_contract(): void
    {
        $this->asSuperadmin();
        $contract = $this->makeAnnulContract();

        $this->postJson(route('contracts.revoke', $contract), [])
            ->assertOk()
            ->assertJsonPath('status', 'revoked');

        $contract->refresh();
        $this->assertSame(Contract::STATUS_REVOKED, $contract->status);
    }

    public function test_wrong_http_methods_on_annul_are_not_500_and_do_not_annul(): void
    {
        $this->actingAsContractsViewer();
        $contract = $this->makeAnnulContract(['status' => Contract::STATUS_OPENED]);

        foreach (['GET', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $json = $this->json($method, route('contracts.revoke', $contract));
            $this->assertNotSame(500, $json->getStatusCode(), 'JSON '.$method);
            $this->assertNotSame(200, $json->getStatusCode(), 'JSON '.$method);
            $this->assertContains($json->getStatusCode(), [404, 405], 'JSON '.$method);

            $web = $this->call($method, route('contracts.revoke', $contract));
            $this->assertNotSame(500, $web->getStatusCode(), 'WEB '.$method);
            $this->assertNotSame(200, $web->getStatusCode(), 'WEB '.$method);
            $this->assertContains($web->getStatusCode(), [404, 405], 'WEB '.$method);
        }

        $contract->refresh();
        $this->assertSame(Contract::STATUS_OPENED, $contract->status);
    }
}
