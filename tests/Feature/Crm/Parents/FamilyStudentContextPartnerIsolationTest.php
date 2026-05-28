<?php

namespace Tests\Feature\Crm\Parents;

use App\Models\ParentProfile;
use App\Models\Role;
use App\Models\User;
use App\Services\Users\FamilyStudentContextService;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Семейный кабинет: изоляция партнёра при переключении ученика.
 */
final class FamilyStudentContextPartnerIsolationTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    public function test_student_does_not_see_sibling_from_foreign_partner(): void
    {
        $roleId = (int) Role::query()->where('name', 'user')->value('id');

        $parentProfile = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $brother1 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
            'parent_id'  => $parentProfile->id,
            'is_enabled' => true,
        ]);

        User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'role_id'    => $roleId,
            'parent_id'  => $parentProfile->id,
            'is_enabled' => true,
        ]);

        $accessible = app(FamilyStudentContextService::class)->accessibleStudents($brother1);

        $this->assertCount(1, $accessible);
        $this->assertTrue($accessible->contains('id', $brother1->id));
    }

    public function test_switch_denied_for_student_from_foreign_partner(): void
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

        $foreignChild = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'role_id'    => $roleId,
            'is_enabled' => true,
        ]);

        $this->actingAs($brother1)
            ->post(route('cabinet.active-student.switch'), [
                'student_user_id' => $foreignChild->id,
            ])
            ->assertForbidden();
    }

    public function test_switch_to_sibling_succeeds_with_redirect(): void
    {
        $roleId = (int) Role::query()->where('name', 'user')->value('id');

        $parentProfile = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $brother1 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
            'parent_id'  => $parentProfile->id,
            'is_enabled' => true,
        ]);

        $brother2 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
            'parent_id'  => $parentProfile->id,
            'is_enabled' => true,
        ]);

        $this->actingAs($brother1)
            ->post(route('cabinet.active-student.switch'), [
                'student_user_id' => $brother2->id,
            ])
            ->assertRedirect();

        $this->assertSame($brother2->id, session(FamilyStudentContextService::SESSION_KEY));
    }
}
