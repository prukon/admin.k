<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\User;

/**
 * Non-AJAX safety-net: без X-Requested-With — 302, запись в БД, не пустой 200.
 */
final class SchoolLeadCreateClientDirectorySwitchNonAjaxSafetyNetFeatureTest extends SchoolLeadCreateClientDirectorySwitchTestCase
{
    public function test_occupied_email_non_ajax_redirects_back_with_parent_email_error(): void
    {
        $this->actingAsLeadsAndUsersViewer();

        $occupied = 'nona-taken-'.uniqid('', true).'@example.test';
        $this->makeOccupiedStudentLogin($occupied);
        $lead = $this->makeLead(['parent_email' => $occupied]);

        $response = $this->from(route('admin.school-leads'))
            ->post(route('admin.user.store'), $this->createClientPayload($lead, [
                'parent_email' => $occupied,
            ]));

        $response->assertRedirect(route('admin.school-leads'));
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertSessionHasErrors(['parent_email']);
        $this->assertNull($lead->fresh()->user_id);
    }

    public function test_directory_parent_non_ajax_redirects_and_creates_client(): void
    {
        $this->actingAsLeadsAndUsersViewer();

        $occupied = 'nona-stale-'.uniqid('', true).'@example.test';
        $this->makeOccupiedStudentLogin($occupied);
        $directory = $this->makeDirectoryParent();
        $lead = $this->makeLead(['parent_email' => $occupied]);

        $this->from(route('admin.school-leads'))
            ->post(route('admin.user.store'), $this->createClientPayload($lead, [
                'parent_email' => $occupied,
            ]))
            ->assertSessionHasErrors(['parent_email']);

        $response = $this->from(route('admin.school-leads'))
            ->post(route('admin.user.store'), $this->createClientPayload($lead, [
                'parent_id'        => $directory->id,
                'parent_email'     => $directory->email,
                'parent_lastname'  => $directory->lastname,
                'parent_firstname' => $directory->firstname,
            ]));

        $response->assertRedirect(route('admin.user1'));
        $this->assertNotSame(200, $response->getStatusCode());

        $user = User::query()
            ->where('partner_id', $this->partner->id)
            ->where('parent_id', $directory->id)
            ->where('lastname', $lead->child_lastname)
            ->first();
        $this->assertNotNull($user);
        $this->assertSame($user->id, (int) $lead->fresh()->user_id);
    }

    public function test_put_lead_non_ajax_redirects_and_keeps_snapshot(): void
    {
        $this->actingAsLeadsAndUsersViewer();

        $snapshotEmail = 'nona-snap-'.uniqid('', true).'@example.test';
        $directory = $this->makeDirectoryParent();
        $lead = $this->makeLead(['parent_email' => $snapshotEmail]);
        $this->attachMatch($lead, $directory);

        $response = $this->from(route('admin.school-leads'))
            ->put(route('admin.school-leads.update', $lead), $this->leadPutPayload($lead, [
                'parent_id'              => $directory->id,
                'parent_match_confirmed' => 'rejected',
                'parent_email'           => $snapshotEmail,
            ]));

        $response->assertRedirect(route('admin.school-leads'));
        $this->assertNotSame(200, $response->getStatusCode());

        $lead->refresh();
        $this->assertSame($snapshotEmail, $lead->parent_email);
        $this->assertSame($directory->id, (int) $lead->parent_id);
    }
}
