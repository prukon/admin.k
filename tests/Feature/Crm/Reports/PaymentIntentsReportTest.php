<?php

namespace Tests\Feature\Crm\Reports;

use App\Models\Partner;
use App\Models\PaymentIntent;
use App\Models\User;
use App\Models\UserTableSetting;
use Tests\Feature\Crm\CrmTestCase;

class PaymentIntentsReportTest extends CrmTestCase
{
    /**
     * [P0] Контроль доступа по праву can:reports.payment.intents.view (страница, data, total, columns, search).
     */
    public function test_payment_intents_routes_require_permission(): void
    {
        $actor = $this->createUserWithoutPermission('reports.payment.intents.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get(route('reports.payment-intents.index'))->assertForbidden();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.payment-intents.data', ['draw' => 1]))
            ->assertForbidden();

        $this->get(route('reports.payment-intents.total'))->assertForbidden();

        $this->get('/admin/reports/payment-intents/columns-settings')->assertForbidden();
        $this->postJson('/admin/reports/payment-intents/columns-settings', ['columns' => ['id' => true]])->assertForbidden();

        $this->get(route('reports.payment-intents.partners.search', ['q' => 'test']))->assertForbidden();
        $this->get(route('reports.payment-intents.users.search', ['q' => 'test']))->assertForbidden();
    }

    /**
     * [P0] Для пользователя с правом все основные endpoints отчёта доступны (200).
     */
    public function test_payment_intents_endpoints_return_200_for_authorized_user(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get(route('reports.payment-intents.index'))->assertOk();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.payment-intents.data', ['draw' => 1]))
            ->assertOk();

        $this->get(route('reports.payment-intents.total'))->assertOk();

        $this->get('/admin/reports/payment-intents/columns-settings')->assertOk();
        $this->postJson('/admin/reports/payment-intents/columns-settings', ['columns' => ['id' => true]])->assertOk();

        $this->get(route('reports.payment-intents.partners.search', ['q' => '']))->assertOk();
        $this->get(route('reports.payment-intents.users.search', ['q' => '']))->assertOk();
    }

    /**
     * [P0] /admin/reports/payment-intents/total возвращает сумму out_sum по фильтрам.
     */
    public function test_payment_intents_total_endpoint_returns_correct_sum(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'out_sum' => 1000,
        ]);
        PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'out_sum' => 2000,
        ]);
        PaymentIntent::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'out_sum' => 9999,
        ]);

        $expectedRaw = 3000.0;
        $expectedFormatted = number_format($expectedRaw, 0, '', ' ');

        $this->get(route('reports.payment-intents.total'))
            ->assertOk()
            ->assertJson([
                'total_formatted' => $expectedFormatted,
                'total_raw' => $expectedRaw,
            ]);
    }

    /**
     * [P1] columns-settings сохраняются с table_key=reports_payment_intents.
     */
    public function test_payment_intents_columns_settings_saved_and_loaded(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get('/admin/reports/payment-intents/columns-settings')
            ->assertOk()
            ->assertExactJson([]);

        $payload = [
            'columns' => [
                'id' => true,
                'meta' => false,
            ],
        ];

        $this->postJson('/admin/reports/payment-intents/columns-settings', $payload)
            ->assertOk()
            ->assertJson(['success' => true]);

        $setting = UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', 'reports_payment_intents')
            ->first();

        $this->assertNotNull($setting);
        $this->assertSame($payload['columns'], $setting->columns);

        $this->get('/admin/reports/payment-intents/columns-settings')
            ->assertOk()
            ->assertExactJson($payload['columns']);
    }

    /**
     * [P1] Select2 partners/users search возвращают results[].
     */
    public function test_payment_intents_select2_search_endpoints_return_results(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $p = Partner::factory()->create(['title' => 'AAA Partner']);
        $u = User::factory()->create([
            'partner_id' => $p->id,
            'lastname' => 'Иванов',
            'name' => 'Иван',
        ]);

        $partnersJson = $this->get(route('reports.payment-intents.partners.search', ['q' => 'AAA']))
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('results', $partnersJson);
        $this->assertNotEmpty($partnersJson['results']);

        $usersJson = $this->get(route('reports.payment-intents.users.search', ['q' => 'Иван', 'partner_id' => $p->id]))
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('results', $usersJson);
        $ids = collect($usersJson['results'])->pluck('id')->all();
        $this->assertContains($u->id, $ids);
    }
}

