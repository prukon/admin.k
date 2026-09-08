<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

/**
 * AJAX-контракт POST /admin/users и PUT заявки при переключении на родителя из справочника.
 */
final class SchoolLeadCreateClientDirectorySwitchAjaxContractFeatureTest extends SchoolLeadCreateClientDirectorySwitchTestCase
{
    public function test_ajax_create_client_with_directory_parent_returns_user_json(): void
    {
        $this->actingAsLeadsAndUsersViewer();

        $directory = $this->makeDirectoryParent();
        $lead = $this->makeLead();

        $response = $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'parent_id'        => $directory->id,
                'parent_email'     => $directory->email,
                'parent_lastname'  => $directory->lastname,
                'parent_firstname' => $directory->firstname,
            ]),
            $this->ajaxHeaders()
        );

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'user' => ['id'],
                'welcome_email_sent',
            ]);

        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertIsBool($response->json('welcome_email_sent'));
        $this->assertSame((int) $response->json('user.id'), (int) $lead->fresh()->user_id);
    }

    public function test_ajax_occupied_email_returns_422_errors_parent_email(): void
    {
        $this->actingAsLeadsAndUsersViewer();

        $occupied = 'ajax-taken-'.uniqid('', true).'@example.test';
        $this->makeOccupiedStudentLogin($occupied);
        $lead = $this->makeLead(['parent_email' => $occupied]);

        $response = $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, ['parent_email' => $occupied]),
            $this->ajaxHeaders()
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['parent_email']);

        $errors = $response->json('errors');
        $this->assertIsArray($errors);
        $this->assertArrayHasKey('parent_email', $errors);
        $this->assertNotSame('', (string) ($errors['parent_email'][0] ?? ''));
    }

    public function test_ajax_put_lead_does_not_return_empty_200(): void
    {
        $this->actingAsLeadsAndUsersViewer();

        $directory = $this->makeDirectoryParent();
        $lead = $this->makeLead();
        $this->attachMatch($lead, $directory);

        $response = $this->putJson(
            route('admin.school-leads.update', $lead),
            $this->leadPutPayload($lead, [
                'parent_id'              => $directory->id,
                'parent_match_confirmed' => 'rejected',
            ]),
            $this->ajaxHeaders()
        );

        $response->assertOk();
        $this->assertNotSame('', trim((string) $response->getContent()));
        $response->assertJsonPath('parent_email', $lead->parent_email);
    }

    public function test_ajax_create_client_without_parent_email_returns_422(): void
    {
        $this->actingAsLeadsAndUsersViewer();

        $lead = $this->makeLead(['parent_email' => 'keep@example.test']);

        $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'parent_email' => '',
            ]),
            $this->ajaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_email']);
    }

    public function test_ajax_parents_search_returns_directory_parent_for_select2(): void
    {
        $this->actingAsLeadsAndUsersViewer();
        $directory = $this->makeDirectoryParent([
            'lastname' => 'ПоискСправочник',
        ]);

        $response = $this->getJson(
            route('admin.users.parents.search', ['q' => 'ПоискСправочник']),
            $this->ajaxHeaders()
        );

        $response->assertOk()
            ->assertJsonStructure(['results']);
        $this->assertNotSame('', trim((string) $response->getContent()));
        $ids = collect($response->json('results'))
            ->map(fn ($row) => (int) ($row['id'] ?? 0))
            ->all();
        $this->assertContains($directory->id, $ids);
        $hit = collect($response->json('results'))
            ->first(fn ($row) => (int) ($row['id'] ?? 0) === $directory->id);
        $this->assertIsArray($hit);
        $this->assertSame($directory->email, $hit['parent_email'] ?? null);
    }
}
