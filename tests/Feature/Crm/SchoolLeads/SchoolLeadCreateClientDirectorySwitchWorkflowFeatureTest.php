<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

/**
 * [P2] Страница заявок → занятый email → родитель из справочника → клиент в DataTables без F5.
 */
final class SchoolLeadCreateClientDirectorySwitchWorkflowFeatureTest extends SchoolLeadCreateClientDirectorySwitchTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_occupied_then_directory_parent_shows_client_in_datatable_without_reload(): void
    {
        $this->actingAsLeadsAndUsersViewer();

        $occupied = 'wf-taken-'.uniqid('', true).'@example.test';
        $this->makeOccupiedStudentLogin($occupied);
        $directory = $this->makeDirectoryParent();
        $lead = $this->makeLead([
            'parent_email'    => $occupied,
            'child_lastname'  => 'Воркфлоу',
            'child_firstname' => 'Клиент',
        ]);

        $page = $this->get(route('admin.school-leads'));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));
        $page->assertSee('id="editLeadModal"', false)
            ->assertSee('id="createClientBtn"', false)
            ->assertSee('collectCreateClientPayload', false)
            ->assertSee('Из справочника', false)
            ->assertSee('id="kidsMainToast"', false)
            ->assertDontSee('id="editLeadError"', false);

        $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'name'         => 'Клиент',
                'lastname'     => 'Воркфлоу',
                'parent_email' => $occupied,
            ]),
            $this->ajaxHeaders()
        )->assertStatus(422);

        $this->putJson(
            route('admin.school-leads.update', $lead),
            $this->leadPutPayload($lead, [
                'parent_id'    => $directory->id,
                'parent_email' => $occupied,
            ]),
            $this->ajaxHeaders()
        )->assertOk();

        $store = $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'name'             => 'Клиент',
                'lastname'         => 'Воркфлоу',
                'parent_id'        => $directory->id,
                'parent_email'     => $directory->email,
                'parent_lastname'  => $directory->lastname,
                'parent_firstname' => $directory->firstname,
            ]),
            $this->ajaxHeaders()
        );
        $store->assertOk();
        $userId = (int) $store->json('user.id');

        $data = $this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 25,
        ]));
        $data->assertOk();
        $this->assertNotSame('', trim((string) $data->getContent()));

        $row = collect($data->json('data') ?? [])
            ->first(fn ($row) => (int) ($row['id'] ?? 0) === (int) $lead->id);
        $this->assertIsArray($row);
        $this->assertSame($userId, (int) $row['user_id']);
        $this->assertSame($occupied, $lead->fresh()->parent_email);
    }
}
