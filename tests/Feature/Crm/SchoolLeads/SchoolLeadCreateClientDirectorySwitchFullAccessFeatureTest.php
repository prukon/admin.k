<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\Partner;
use App\Models\SchoolLead;
use Illuminate\Support\Facades\Auth;

/**
 * Smoke endpoint'ов фичи: ни один вызов не 500 и не бессмысленный пустой 200.
 */
final class SchoolLeadCreateClientDirectorySwitchFullAccessFeatureTest extends SchoolLeadCreateClientDirectorySwitchTestCase
{
    public function test_guest_is_denied_on_all_feature_endpoints(): void
    {
        Auth::logout();
        $lead = $this->makeLead();
        $directory = $this->makeDirectoryParent();

        foreach ($this->featureRoutes($lead, $directory) as $item) {
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
        $directory = $this->makeDirectoryParent();

        foreach ($this->featureRoutes($lead, $directory) as $item) {
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

    public function test_admin_workflow_returns_200_and_does_not_empty_payload(): void
    {
        $this->actingAsLeadsAndUsersViewer();

        $occupied = 'full-taken-'.uniqid('', true).'@example.test';
        $this->makeOccupiedStudentLogin($occupied);
        $directory = $this->makeDirectoryParent();
        $lead = $this->makeLead(['parent_email' => $occupied]);
        $this->attachMatch($lead, $directory);

        $page = $this->get(route('admin.school-leads'));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));

        $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, ['parent_email' => $occupied]),
            $this->ajaxHeaders()
        )->assertStatus(422);

        $put = $this->putJson(
            route('admin.school-leads.update', $lead),
            $this->leadPutPayload($lead, [
                'parent_id'              => $directory->id,
                'parent_match_confirmed' => 'rejected',
            ]),
            $this->ajaxHeaders()
        );
        $put->assertOk();
        $this->assertNotSame('', trim((string) $put->getContent()));

        $store = $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'parent_id'        => $directory->id,
                'parent_email'     => $directory->email,
                'parent_lastname'  => $directory->lastname,
                'parent_firstname' => $directory->firstname,
            ]),
            $this->ajaxHeaders()
        );
        $store->assertOk();
        $this->assertNotSame('', trim((string) $store->getContent()));

        $data = $this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 25,
        ]));
        $data->assertOk();
        $row = collect($data->json('data') ?? [])
            ->first(fn ($row) => (int) ($row['id'] ?? 0) === (int) $lead->id);
        $this->assertIsArray($row);
        $this->assertSame((int) $store->json('user.id'), (int) $row['user_id']);
    }

    public function test_unsupported_verbs_do_not_return_500(): void
    {
        $this->actingAsLeadsAndUsersViewer();
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
        }
    }

    public function test_foreign_partner_lead_is_not_converted(): void
    {
        $this->actingAsLeadsAndUsersViewer();

        $foreign = Partner::query()->where('id', '!=', $this->partner->id)->first()
            ?? $this->foreignPartner;

        $foreignLead = SchoolLead::factory()->forPartner((int) $foreign->id)->create([
            'child_firstname' => 'Чужой',
            'child_lastname'  => 'Лид',
        ]);

        $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($foreignLead, [
                'parent_email' => $this->schoolLeadClientParentEmail('foreign'),
            ]),
            $this->ajaxHeaders()
        )->assertStatus(422);

        $this->assertNull($foreignLead->fresh()->user_id);
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function featureRoutes($lead, $directory): array
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
                    'parent_id'    => $directory->id,
                    'parent_email' => $directory->email,
                ]),
                'headers' => [
                    'HTTP_ACCEPT'           => 'application/json',
                    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                ],
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.users.parents.search', ['q' => 'Справочников']),
            ],
        ];
    }
}
