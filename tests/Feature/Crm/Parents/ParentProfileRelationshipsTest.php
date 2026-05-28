<?php

namespace Tests\Feature\Crm\Parents;

use App\Models\ParentProfile;
use App\Models\Role;
use App\Models\User;
use Tests\Feature\Crm\CrmTestCase;

final class ParentProfileRelationshipsTest extends CrmTestCase
{
    public function test_parent_profile_links_multiple_students(): void
    {
        $studentRoleId = (int) Role::query()->where('name', 'user')->value('id');

        $parentProfile = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Иванова',
            'firstname'  => 'Мария',
        ]);

        $child1 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $studentRoleId,
            'parent_id'  => $parentProfile->id,
        ]);

        $child2 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $studentRoleId,
            'parent_id'  => $parentProfile->id,
        ]);

        $parentProfile->refresh();

        $this->assertSame($parentProfile->id, $child1->parent_id);
        $this->assertSame($parentProfile->id, $child2->parent_id);
        $this->assertTrue($child1->parentProfile->is($parentProfile));
        $this->assertTrue($child2->parentProfile->is($parentProfile));
        $this->assertCount(2, $parentProfile->students);
        $this->assertSame('Иванова Мария', $parentProfile->full_name);
    }

    public function test_parent_profile_uses_soft_deletes(): void
    {
        $profile = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $profile->delete();

        $this->assertSoftDeleted('parents', ['id' => $profile->id]);
        $this->assertNull(ParentProfile::query()->find($profile->id));
        $this->assertNotNull(ParentProfile::withTrashed()->find($profile->id));
    }
}
