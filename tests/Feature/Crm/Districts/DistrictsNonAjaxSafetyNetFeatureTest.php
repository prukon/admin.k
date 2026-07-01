<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Districts;

use App\Models\District;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Non-AJAX safety-net: store/update/destroy без X-Requested-With → redirect, запись в БД создана/обновлена.
 */
final class DistrictsNonAjaxSafetyNetFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->asAdmin();
    }

    public function test_store_non_ajax_redirects_and_creates_district(): void
    {
        $payload = [
            'name' => 'Район non-ajax',
            'sort_order' => 5,
            'is_enabled' => 1,
        ];

        $this->post(route('admin.districts.store'), $payload)
            ->assertRedirect(route('admin.districts.index'));

        $this->assertDatabaseHas('districts', [
            'partner_id' => $this->partner->id,
            'name' => 'Район non-ajax',
            'sort_order' => 5,
            'is_enabled' => 1,
        ]);
    }

    public function test_store_non_ajax_validation_failure_redirects_back_with_errors_not_empty_200(): void
    {
        $this->from(route('admin.districts.index'))
            ->post(route('admin.districts.store'), [])
            ->assertStatus(302)
            ->assertSessionHasErrors(['name']);

        $this->assertDatabaseMissing('districts', [
            'partner_id' => $this->partner->id,
            'name' => '',
        ]);
    }

    public function test_update_non_ajax_redirects_and_updates_district(): void
    {
        $district = District::factory()->forPartner($this->partner->id)->create([
            'name' => 'До non-ajax update',
            'sort_order' => 1,
        ]);

        $this->put(route('admin.districts.update', $district), [
            'name' => 'После non-ajax update',
            'sort_order' => 10,
            'is_enabled' => 0,
        ])
            ->assertRedirect(route('admin.districts.index'));

        $this->assertDatabaseHas('districts', [
            'id' => $district->id,
            'name' => 'После non-ajax update',
            'sort_order' => 10,
            'is_enabled' => 0,
        ]);
    }

    public function test_update_non_ajax_validation_failure_redirects_with_errors_not_empty_200(): void
    {
        $district = District::factory()->forPartner($this->partner->id)->create([
            'name' => 'Валидация non-ajax',
        ]);

        $this->from(route('admin.districts.index'))
            ->put(route('admin.districts.update', $district), [
                'name' => '',
                'is_enabled' => 1,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['name']);

        $this->assertSame('Валидация non-ajax', $district->fresh()->name);
    }

    public function test_destroy_non_ajax_redirects_and_deletes_district(): void
    {
        $district = District::factory()->forPartner($this->partner->id)->create([
            'name' => 'На удаление non-ajax',
        ]);

        $this->delete(route('admin.districts.destroy', $district))
            ->assertRedirect(route('admin.districts.index'));

        $this->assertDatabaseMissing('districts', ['id' => $district->id]);
    }
}
