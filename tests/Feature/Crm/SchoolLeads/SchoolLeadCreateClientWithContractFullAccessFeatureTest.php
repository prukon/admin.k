<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\Contract;
use App\Models\Partner;
use App\Models\SchoolLead;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

/**
 * Smoke endpoint'ов фичи «с договором / без»: ни один вызов не 500 и не бессмысленный пустой 200.
 */
final class SchoolLeadCreateClientWithContractFullAccessFeatureTest extends SchoolLeadCreateClientWithContractTestCase
{
    public function test_guest_is_denied_on_all_feature_endpoints(): void
    {
        Auth::logout();
        $lead = $this->makeLead();
        $template = $this->makeContractTemplate();

        foreach ($this->featureRoutes($lead, $template->id) as $item) {
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
                [302, 401, 403, 404, 405, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
            $this->assertNotSame(500, $response->getStatusCode());
        }
    }

    public function test_user_without_required_rights_does_not_get_500(): void
    {
        $denied = $this->createUserWithoutPermission('schoolLeads.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $lead = $this->makeLead();
        $template = $this->makeContractTemplate();

        foreach ($this->featureRoutes($lead, $template->id) as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertNotSame(
                500,
                $response->getStatusCode(),
                "Без прав: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_viewer_with_all_permissions_gets_json_or_validation_not_500(): void
    {
        Mail::fake();
        $this->actingAsLeadsUsersAndContractsViewer();
        $template = $this->makeContractTemplate();
        $lead = $this->makeLead();

        $page = $this->get(route('admin.school-leads'));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));

        $ok = $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'send_contract'        => 1,
                'contract_template_id' => $template->id,
            ]),
            $this->ajaxHeaders()
        );
        $this->assertNotSame(500, $ok->getStatusCode());
        $this->assertSame(200, $ok->getStatusCode());
        $this->assertNotSame('', trim((string) $ok->getContent()));
        $this->assertSame(1, Contract::query()->count());

        $this->partner->wallet_balance_cents = 0;
        $this->partner->save();
        $secondLead = $this->makeLead(['parent_email' => 'second-'.uniqid('', true).'@example.test']);

        $fail = $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($secondLead, [
                'send_contract'        => 1,
                'contract_template_id' => $template->id,
            ]),
            $this->ajaxHeaders()
        );
        $this->assertSame(422, $fail->getStatusCode());
        $this->assertNotSame('', trim((string) $fail->getContent()));
        $this->assertNull($secondLead->fresh()->user_id);

        $without = $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($secondLead, [
                'send_contract' => 0,
            ]),
            $this->ajaxHeaders()
        );
        $without->assertOk();
        $this->assertNotSame('', trim((string) $without->getContent()));
        $this->assertNotNull($secondLead->fresh()->user_id);
    }

    public function test_unsupported_verbs_do_not_return_500(): void
    {
        $this->actingAsLeadsUsersAndContractsViewer();
        $lead = $this->makeLead();

        foreach ([
            ['PATCH', route('admin.school-leads.update', $lead)],
            ['DELETE', route('admin.school-leads.update', $lead)],
            ['DELETE', route('admin.user.store')],
        ] as [$method, $url]) {
            $response = $this->call($method, $url, [], [], [], ['HTTP_ACCEPT' => 'application/json']);
            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 404, 405, 419, 422],
                "{$method} {$url} → {$response->getStatusCode()}"
            );
            $this->assertNotSame(500, $response->getStatusCode());
            $this->assertNotSame(200, $response->getStatusCode());
        }
    }

    public function test_foreign_partner_lead_with_contract_is_not_converted(): void
    {
        $this->actingAsLeadsUsersAndContractsViewer();
        $template = $this->makeContractTemplate();

        $foreign = Partner::query()->where('id', '!=', $this->partner->id)->first()
            ?? $this->foreignPartner;

        $foreignLead = SchoolLead::factory()->forPartner((int) $foreign->id)->create([
            'child_firstname' => 'Чужой',
            'child_lastname'  => 'Лид',
            'parent_email'    => 'foreign-lead-'.uniqid('', true).'@example.test',
        ]);

        $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($foreignLead, [
                'send_contract'        => 1,
                'contract_template_id' => $template->id,
            ]),
            $this->ajaxHeaders()
        )->assertStatus(422);

        $this->assertNull($foreignLead->fresh()->user_id);
        $this->assertSame(0, Contract::query()->count());
    }

    public function test_session_foreign_partner_does_not_convert_foreign_lead(): void
    {
        $this->actingAsLeadsUsersAndContractsViewer();
        $this->withSession([
            'current_partner' => $this->foreignPartner->id,
            '2fa:passed'      => true,
        ]);

        $template = $this->makeContractTemplate();
        $foreignLead = SchoolLead::factory()->forPartner((int) $this->foreignPartner->id)->create([
            'child_firstname' => 'Сессия',
            'child_lastname'  => 'Чужая',
            'parent_email'    => 'session-foreign-'.uniqid('', true).'@example.test',
        ]);

        $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($foreignLead, [
                'send_contract'        => 1,
                'contract_template_id' => $template->id,
            ]),
            $this->ajaxHeaders()
        )->assertStatus(422);

        $this->assertNull($foreignLead->fresh()->user_id);
        $this->assertSame(0, Contract::query()->count());
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function featureRoutes($lead, int $templateId): array
    {
        return [
            [
                'method'  => 'GET',
                'url'     => route('admin.school-leads'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.school-leads.data', [
                    'draw'   => 1,
                    'start'  => 0,
                    'length' => 10,
                ]),
            ],
            [
                'method'  => 'PUT',
                'url'     => route('admin.school-leads.update', $lead),
                'data'    => $this->leadPutPayload($lead),
                'headers' => [
                    'HTTP_ACCEPT'           => 'application/json',
                    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                ],
            ],
            [
                'method'  => 'POST',
                'url'     => route('admin.user.store'),
                'data'    => $this->createClientPayload($lead, [
                    'send_contract'  => 0,
                    'validate_only' => 1,
                ]),
                'headers' => [
                    'HTTP_ACCEPT'           => 'application/json',
                    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                ],
            ],
            [
                'method'  => 'POST',
                'url'     => route('admin.user.store'),
                'data'    => $this->createClientPayload($lead, [
                    'send_contract'        => 1,
                    'contract_template_id' => $templateId,
                ]),
                'headers' => [
                    'HTTP_ACCEPT'           => 'application/json',
                    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                ],
            ],
            [
                'method'  => 'POST',
                'url'     => route('admin.user.store'),
                'data'    => $this->createClientPayload($lead, [
                    'send_contract' => 0,
                ]),
                'headers' => [
                    'HTTP_ACCEPT'           => 'application/json',
                    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                ],
            ],
            [
                'method'  => 'POST',
                'url'     => route('admin.user.store'),
                'data'    => $this->createClientPayload($lead, [
                    'send_contract'        => 1,
                    'contract_template_id' => $templateId,
                ]),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
        ];
    }
}
