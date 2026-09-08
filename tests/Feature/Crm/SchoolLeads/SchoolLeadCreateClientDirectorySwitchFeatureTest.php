<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\User;

/**
 * UX-баг: занятый email «нового родителя» оставался в POST после переключения на справочник.
 * После фикса клиент создаётся с email/parent_id из формы, снимок заявки не затирается.
 */
final class SchoolLeadCreateClientDirectorySwitchFeatureTest extends SchoolLeadCreateClientDirectorySwitchTestCase
{
    public function test_new_parent_with_occupied_email_shows_error_under_parent_email_and_does_not_create_client(): void
    {
        $this->actingAsLeadsAndUsersViewer();

        $occupied = 'taken-new-parent-'.uniqid('', true).'@example.test';
        $this->makeOccupiedStudentLogin($occupied);

        $lead = $this->makeLead(['parent_email' => $occupied]);

        $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'parent_id'        => null,
                'parent_email'     => $occupied,
                'parent_lastname'  => 'Новый',
                'parent_firstname' => 'Родитель',
            ]),
            $this->ajaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_email'])
            ->assertJsonPath('errors.parent_email.0', 'Этот адрес электронной почты уже зарегистрирован.');

        $this->assertNull($lead->fresh()->user_id);
        $this->assertDatabaseMissing('users', [
            'partner_id' => $this->partner->id,
            'lastname'   => 'Учеников',
            'name'       => 'Пётр',
            'email'      => $occupied,
        ]);
    }

    public function test_after_occupied_email_error_switching_to_directory_parent_creates_client(): void
    {
        $this->actingAsLeadsAndUsersViewer();

        $occupied = 'stale-then-dir-'.uniqid('', true).'@example.test';
        $this->makeOccupiedStudentLogin($occupied);

        $directory = $this->makeDirectoryParent([
            'email'     => 'free-dir-'.uniqid('', true).'@example.test',
            'lastname'  => 'Справочников',
            'firstname' => 'Алексей',
        ]);

        $lead = $this->makeLead(['parent_email' => $occupied]);

        $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'parent_email' => $occupied,
            ]),
            $this->ajaxHeaders()
        )->assertStatus(422);

        $this->assertNull($lead->fresh()->user_id);

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

        $response->assertOk();
        $this->assertGreaterThan(0, (int) $response->json('user.id'));

        $user = User::findOrFail((int) $response->json('user.id'));
        $this->assertSame($directory->id, (int) $user->parent_id);
        $this->assertSame($directory->email, $user->email);
        $this->assertSame($user->id, (int) $lead->fresh()->user_id);
        $this->assertSame($occupied, $lead->fresh()->parent_email);
    }

    public function test_stale_occupied_email_with_directory_parent_id_still_returns_422(): void
    {
        $this->actingAsLeadsAndUsersViewer();

        $occupied = 'stale-payload-'.uniqid('', true).'@example.test';
        $this->makeOccupiedStudentLogin($occupied);

        $directory = $this->makeDirectoryParent([
            'email' => 'other-dir-'.uniqid('', true).'@example.test',
        ]);

        $lead = $this->makeLead(['parent_email' => $occupied]);
        $this->attachMatch($lead, $directory);

        $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'parent_id'    => $directory->id,
                'parent_email' => $occupied,
            ]),
            $this->ajaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_email'])
            ->assertJsonPath('errors.parent_email.0', 'Этот адрес электронной почты уже зарегистрирован.');

        $this->assertNull($lead->fresh()->user_id);
    }

    public function test_put_keeps_lead_snapshot_when_creating_client_from_directory_parent(): void
    {
        $this->actingAsLeadsAndUsersViewer();

        $occupied = 'snapshot-keep-'.uniqid('', true).'@example.test';
        $this->makeOccupiedStudentLogin($occupied);

        $directory = $this->makeDirectoryParent([
            'email'     => 'put-dir-'.uniqid('', true).'@example.test',
            'lastname'  => 'Карточка',
            'firstname' => 'Справочник',
        ]);

        $lead = $this->makeLead(['parent_email' => $occupied]);
        $this->attachMatch($lead, $directory);

        $put = $this->putJson(
            route('admin.school-leads.update', $lead),
            $this->leadPutPayload($lead, [
                'parent_id'              => $directory->id,
                'parent_match_confirmed' => 'rejected',
                'parent_email'           => $occupied,
                'parent_lastname'        => 'Заявочный',
                'parent_firstname'       => 'Родитель',
            ]),
            $this->ajaxHeaders()
        );

        $put->assertOk()
            ->assertJsonPath('parent_email', $occupied)
            ->assertJsonPath('parent_lastname', 'Заявочный');

        $lead->refresh();
        $this->assertSame($occupied, $lead->parent_email);
        $this->assertSame('Заявочный', $lead->parent_lastname);
        $this->assertSame($directory->id, (int) $lead->parent_id);

        $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'parent_id'        => $directory->id,
                'parent_email'     => $directory->email,
                'parent_lastname'  => $directory->lastname,
                'parent_firstname' => $directory->firstname,
            ]),
            $this->ajaxHeaders()
        )->assertOk();

        $lead->refresh();
        $this->assertSame($occupied, $lead->parent_email);
        $this->assertNotNull($lead->user_id);
    }

    public function test_directory_parent_of_same_family_creates_sibling_without_new_login(): void
    {
        $this->actingAsLeadsAndUsersViewer();

        $familyEmail = 'family-login-'.uniqid('', true).'@example.test';
        $familyParent = $this->makeDirectoryParent(['email' => $familyEmail]);
        $firstChild = $this->makeOccupiedStudentLogin($familyEmail, $familyParent);

        $lead = $this->makeLead(['parent_email' => $familyEmail]);

        $response = $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'parent_id'        => $familyParent->id,
                'parent_email'     => $familyEmail,
                'parent_lastname'  => $familyParent->lastname,
                'parent_firstname' => $familyParent->firstname,
            ]),
            $this->ajaxHeaders()
        );

        $response->assertOk();

        $sibling = User::findOrFail((int) $response->json('user.id'));
        $this->assertNull($sibling->email);
        $this->assertSame($familyParent->id, (int) $sibling->parent_id);
        $this->assertNotSame($firstChild->id, $sibling->id);
    }

    public function test_new_parent_with_free_email_creates_client_without_switching_to_directory(): void
    {
        $this->actingAsLeadsAndUsersViewer();

        $freeEmail = 'new-free-'.uniqid('', true).'@example.test';
        $lead = $this->makeLead(['parent_email' => $freeEmail]);

        $response = $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'parent_id'        => null,
                'parent_email'     => $freeEmail,
                'parent_lastname'  => 'Новый',
                'parent_firstname' => 'Родитель',
            ]),
            $this->ajaxHeaders()
        );

        $response->assertOk();
        $user = User::findOrFail((int) $response->json('user.id'));
        $this->assertSame($freeEmail, $user->email);
        $this->assertNotNull($user->parent_id);
        $this->assertSame($user->id, (int) $lead->fresh()->user_id);
    }
}
