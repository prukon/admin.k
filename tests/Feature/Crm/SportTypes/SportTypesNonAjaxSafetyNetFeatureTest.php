<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SportTypes;

use App\Models\SportType;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Non-AJAX safety-net: store/update/destroy без X-Requested-With → redirect, запись в БД создана/обновлена.
 */
final class SportTypesNonAjaxSafetyNetFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->asAdmin();
        $this->grantPermissions(['sport_types.view', 'sport_types.manage']);
    }

    /** @param list<string> $permissions */
    private function grantPermissions(array $permissions): void
    {
        foreach ($permissions as $permission) {
            DB::table('permission_role')->insertOrIgnore([
                'partner_id' => $this->partner->id,
                'role_id' => $this->user->role_id,
                'permission_id' => $this->permissionId($permission),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_store_non_ajax_redirects_and_creates_sport_type(): void
    {
        $payload = [
            'name' => 'Вид спорта non-ajax',
            'sort' => 5,
            'is_enabled' => 1,
        ];

        $this->post(route('admin.sport-types.store'), $payload)
            ->assertRedirect(route('admin.sport-types.index'));

        $this->assertDatabaseHas('sport_types', [
            'partner_id' => $this->partner->id,
            'name' => 'Вид спорта non-ajax',
            'sort' => 5,
            'is_enabled' => 1,
        ]);
    }

    public function test_store_non_ajax_validation_failure_redirects_back_with_errors_not_empty_200(): void
    {
        $this->from(route('admin.sport-types.index'))
            ->post(route('admin.sport-types.store'), [])
            ->assertStatus(302)
            ->assertSessionHasErrors(['name']);

        $this->assertDatabaseMissing('sport_types', [
            'partner_id' => $this->partner->id,
            'name' => '',
        ]);
    }

    public function test_update_non_ajax_redirects_and_updates_sport_type(): void
    {
        $sportType = SportType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'До non-ajax update',
            'sort' => 1,
        ]);

        $this->put(route('admin.sport-types.update', $sportType), [
            'name' => 'После non-ajax update',
            'description' => 'Описание',
            'sort' => 10,
            'is_enabled' => 0,
        ])
            ->assertRedirect(route('admin.sport-types.index'));

        $this->assertDatabaseHas('sport_types', [
            'id' => $sportType->id,
            'name' => 'После non-ajax update',
            'sort' => 10,
            'is_enabled' => 0,
        ]);
    }

    public function test_update_non_ajax_validation_failure_redirects_with_errors_not_empty_200(): void
    {
        $sportType = SportType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Валидация non-ajax',
        ]);

        $this->from(route('admin.sport-types.index'))
            ->put(route('admin.sport-types.update', $sportType), [
                'name' => '',
                'is_enabled' => 1,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['name']);

        $this->assertSame('Валидация non-ajax', $sportType->fresh()->name);
    }

    public function test_destroy_non_ajax_redirects_and_deletes_sport_type(): void
    {
        $sportType = SportType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'На удаление non-ajax',
        ]);

        $this->delete(route('admin.sport-types.destroy', $sportType))
            ->assertRedirect(route('admin.sport-types.index'));

        $this->assertDatabaseMissing('sport_types', ['id' => $sportType->id]);
    }
}
