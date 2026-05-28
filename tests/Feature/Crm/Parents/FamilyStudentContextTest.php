<?php

namespace Tests\Feature\Crm\Parents;

use App\Models\ParentProfile;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Services\Users\FamilyStudentContextService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Tests\Feature\Crm\CrmTestCase;

final class FamilyStudentContextTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    public function test_siblings_with_same_parent_id_see_each_other_in_accessible_students(): void
    {
        $roleId = (int) Role::query()->where('name', 'user')->value('id');

        $parentProfile = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Иванова',
            'firstname'  => 'Мария',
        ]);

        $brother1 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
            'parent_id'  => $parentProfile->id,
            'lastname'   => 'Иванов',
            'name'       => 'Петя',
        ]);

        $brother2 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
            'parent_id'  => $parentProfile->id,
            'lastname'   => 'Иванов',
            'name'       => 'Вася',
        ]);

        $service = app(FamilyStudentContextService::class);

        $fromBrother1 = $service->accessibleStudents($brother1);
        $this->assertTrue($service->shouldShowSwitcher($brother1));
        $this->assertCount(2, $fromBrother1);
        $this->assertTrue($fromBrother1->contains('id', $brother1->id));
        $this->assertTrue($fromBrother1->contains('id', $brother2->id));

        $fromBrother2 = $service->accessibleStudents($brother2);
        $this->assertTrue($service->shouldShowSwitcher($brother2));
        $this->assertCount(2, $fromBrother2);
        $this->assertTrue($fromBrother2->contains('id', $brother1->id));
        $this->assertTrue($fromBrother2->contains('id', $brother2->id));
    }

    public function test_student_without_parent_id_has_no_switcher(): void
    {
        $roleId = (int) Role::query()->where('name', 'user')->value('id');

        $lonely = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
            'parent_id'  => null,
        ]);

        $service = app(FamilyStudentContextService::class);

        $this->assertFalse($service->shouldShowSwitcher($lonely));
        $this->assertCount(1, $service->accessibleStudents($lonely));
        $this->assertTrue($service->accessibleStudents($lonely)->contains('id', $lonely->id));
    }

    public function test_switch_active_student_stores_session_and_payments_use_child(): void
    {
        $roleId = (int) Role::query()->where('name', 'user')->value('id');

        $parentProfile = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $brother1 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
            'parent_id'  => $parentProfile->id,
        ]);

        $brother2 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
            'parent_id'  => $parentProfile->id,
        ]);

        Payment::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id'    => $brother2->id,
            'summ'       => 500,
        ]);

        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $brother1->role_id,
            'permission_id' => $this->permissionId('myPayments.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->actingAs($brother1)
            ->post(route('cabinet.active-student.switch'), [
                'student_user_id' => $brother2->id,
            ])
            ->assertRedirect();

        $this->assertSame($brother2->id, session(FamilyStudentContextService::SESSION_KEY));

        $active = app(FamilyStudentContextService::class)->activeStudent($brother1);
        $this->assertSame($brother2->id, $active->id);

        $this->actingAs($brother1)
            ->getJson('/getUserPayments?draw=1&start=0&length=10')
            ->assertOk();

        $this->assertDatabaseHas('payments', [
            'user_id' => $brother2->id,
            'summ'    => 500,
        ]);
    }

    public function test_switch_denied_for_unrelated_student(): void
    {
        $roleId = (int) Role::query()->where('name', 'user')->value('id');

        $parentProfile = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $brother1 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
            'parent_id'  => $parentProfile->id,
        ]);

        $stranger = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
        ]);

        $this->actingAs($brother1)
            ->post(route('cabinet.active-student.switch'), [
                'student_user_id' => $stranger->id,
            ])
            ->assertForbidden();
    }

    public function test_sidebar_panel_identity_uses_parent_profile_when_switcher_visible(): void
    {
        $roleId = (int) Role::query()->where('name', 'user')->value('id');

        $parentProfile = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Иванова',
            'firstname'  => 'Мария',
            'email'      => 'mama@example.com',
        ]);

        $brother1 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
            'parent_id'  => $parentProfile->id,
            'name'       => 'Петя',
            'email'      => 'petya@example.com',
        ]);

        User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
            'parent_id'  => $parentProfile->id,
            'name'       => 'Вася',
        ]);

        $service = app(FamilyStudentContextService::class);
        $identity = $service->sidebarPanelIdentity($brother1);

        $this->assertSame('Иванова Мария', $identity['name']);
        $this->assertSame('mama@example.com', $identity['email']);
    }

    public function test_sidebar_panel_identity_uses_student_when_no_switcher(): void
    {
        $roleId = (int) Role::query()->where('name', 'user')->value('id');

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
            'name'       => 'Один',
            'email'      => 'one@example.com',
        ]);

        $identity = app(FamilyStudentContextService::class)->sidebarPanelIdentity($student);

        $this->assertSame('Один', $identity['name']);
        $this->assertSame('one@example.com', $identity['email']);
    }

    public function test_accessible_students_is_memoized_for_same_actor_within_request(): void
    {
        $roleId = (int) Role::query()->where('name', 'user')->value('id');

        $parentProfile = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $brother1 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
            'parent_id'  => $parentProfile->id,
        ]);

        User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
            'parent_id'  => $parentProfile->id,
        ]);

        $service = app(FamilyStudentContextService::class);

        DB::enableQueryLog();
        DB::flushQueryLog();

        $first = $service->accessibleStudents($brother1);
        $queriesAfterFirst = count(DB::getQueryLog());

        DB::flushQueryLog();

        $second = $service->accessibleStudents($brother1);
        $queriesAfterSecond = count(DB::getQueryLog());

        $this->assertInstanceOf(Collection::class, $first);
        $this->assertTrue($first->contains('id', $brother1->id));
        $this->assertSame($first->pluck('id')->all(), $second->pluck('id')->all());
        $this->assertGreaterThan(0, $queriesAfterFirst);
        $this->assertSame(0, $queriesAfterSecond);
    }

    public function test_disabled_sibling_not_in_list_but_actor_still_accessible(): void
    {
        $roleId = (int) Role::query()->where('name', 'user')->value('id');

        $parentProfile = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $activeBrother = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
            'parent_id'  => $parentProfile->id,
            'is_enabled' => true,
        ]);

        User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
            'parent_id'  => $parentProfile->id,
            'is_enabled' => false,
        ]);

        $accessible = app(FamilyStudentContextService::class)->accessibleStudents($activeBrother);

        $this->assertCount(1, $accessible);
        $this->assertTrue($accessible->contains('id', $activeBrother->id));
        $this->assertFalse(app(FamilyStudentContextService::class)->shouldShowSwitcher($activeBrother));
    }
}
