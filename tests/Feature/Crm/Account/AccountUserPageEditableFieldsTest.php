<?php

namespace Tests\Feature\Crm\Account;

use App\Models\UserField;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

class AccountUserPageEditableFieldsTest extends CrmTestCase
{
    protected \App\Models\User $actorAdmin;

    protected int $roleAdminId;
    protected int $roleUserId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->roleAdminId = $this->roleId('admin');
        $this->roleUserId  = $this->roleId('user');

        $this->actorAdmin = $this->createUserWithRole('admin', $this->partner, [
            'name'     => 'Admin',
            'lastname' => 'Boss',
        ]);
    }

    public function test_editable_fields_respects_roles_pivot(): void
    {
        $this->actingAs($this->actorAdmin);

        // Поле 1 — доступно админу
        $f1 = UserField::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Рост',
            'slug'       => 'height',
        ]);
        DB::table('user_field_role')->insert([
            'user_field_id' => $f1->id,
            'role_id'       => $this->roleAdminId,
        ]);

        // Поле 2 — недоступно админу (только user)
        $f2 = UserField::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Любимый цвет',
            'slug'       => 'fav_color',
        ]);
        DB::table('user_field_role')->insert([
            'user_field_id' => $f2->id,
            'role_id'       => $this->roleUserId,
        ]);

        // Поле 3 — без ограничений (пустой pivot) => "как должно быть": доступно всем
        $f3 = UserField::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Город',
            'slug'       => 'city',
        ]);
        // pivot не заполняем

        $resp = $this->withSession(['current_partner' => $this->partner->id])
            ->get(route('admin.cur.user.edit', $this->actorAdmin));

        $resp->assertStatus(200);

        $resp->assertViewHas('editableFields', function ($editableFields) use ($f1, $f2, $f3) {
            $arr = is_array($editableFields) ? $editableFields : $editableFields->toArray();

            return isset($arr[$f1->id], $arr[$f2->id], $arr[$f3->id])
                && $arr[$f1->id] === true
                && $arr[$f2->id] === false
                && $arr[$f3->id] === true;
        });
    }
}