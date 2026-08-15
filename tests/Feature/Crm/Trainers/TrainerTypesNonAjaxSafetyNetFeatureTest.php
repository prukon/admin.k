<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Trainers;

use App\Models\TrainerType;
use App\Models\User;
use Tests\Feature\Crm\Schedule\ScheduleTrainerSalaryTestCase;

/**
 * P1: non-AJAX safety-net и AJAX-контракт справочника типов тренера (модалка).
 * CRUD типов — JSON даже без X-Requested-With (как autosave ЗП Канзаса), не пустой 200.
 * Карточка тренера — обычная форма: non-AJAX 302, AJAX JSON.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see \Tests\Feature\Crm\Schedule\ScheduleTrainerSalaryKansasUiContractsFeatureTest::test_non_ajax_patch_persists_draft_and_returns_json_not_empty_200
 */
final class TrainerTypesNonAjaxSafetyNetFeatureTest extends ScheduleTrainerSalaryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantTrainerSalaryViewKansas();
        $this->grantTrainerSalaryManage();
        $this->grantPermission('trainers.view');
    }

    public function test_store_type_without_ajax_header_creates_record_and_returns_json_not_empty_200(): void
    {
        $response = $this->from(route('admin.trainers.index'))
            ->post(route('admin.trainer-types.store'), [
                'name' => 'Стажёр non-ajax',
                'rate_per_training' => 150.5,
                'base_premium' => 20,
                'sort_order' => 12,
                'is_enabled' => 1,
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(302, $response->getStatusCode(), 'Модалка типов отвечает JSON, не redirect');
        $response->assertOk()
            ->assertJsonPath('message', 'Тип тренера создан')
            ->assertJsonPath('trainer_type.name', 'Стажёр non-ajax')
            ->assertJsonPath('trainer_type.rate_per_training', '150.50');
        $this->assertNotSame('', trim((string) $response->getContent()));

        $this->assertDatabaseHas('trainer_types', [
            'partner_id' => $this->partner->id,
            'name' => 'Стажёр non-ajax',
            'rate_per_training_cents' => 15050,
            'base_premium_cents' => 2000,
            'is_system' => 0,
        ]);
    }

    public function test_store_type_without_ajax_header_validation_redirects_back_with_field_error(): void
    {
        $this->from(route('admin.trainers.index'))
            ->post(route('admin.trainer-types.store'), [
                'name' => '',
                'rate_per_training' => 1,
                'base_premium' => 1,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['name']);

        $this->assertDatabaseMissing('trainer_types', [
            'partner_id' => $this->partner->id,
            'name' => '',
        ]);
    }

    public function test_update_type_without_ajax_header_persists_and_returns_json_not_empty_200(): void
    {
        $type = TrainerType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'До non-ajax',
            'rate_per_training_cents' => 10000,
            'base_premium_cents' => 0,
        ]);

        $response = $this->from(route('admin.trainers.index'))
            ->put(route('admin.trainer-types.update', $type), [
                'name' => 'После non-ajax',
                'rate_per_training' => 80,
                'base_premium' => 15,
                'sort_order' => 8,
                'is_enabled' => 1,
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(302, $response->getStatusCode());
        $response->assertOk()
            ->assertJsonPath('message', 'Тип тренера обновлён')
            ->assertJsonPath('trainer_type.name', 'После non-ajax');
        $this->assertNotSame('', trim((string) $response->getContent()));

        $this->assertDatabaseHas('trainer_types', [
            'id' => $type->id,
            'name' => 'После non-ajax',
            'rate_per_training_cents' => 8000,
            'base_premium_cents' => 1500,
        ]);
    }

    public function test_destroy_type_without_ajax_header_deletes_and_returns_json_not_empty_200(): void
    {
        $type = TrainerType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Удалить non-ajax',
        ]);

        $response = $this->from(route('admin.trainers.index'))
            ->delete(route('admin.trainer-types.destroy', $type));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(302, $response->getStatusCode());
        $response->assertOk()
            ->assertJsonPath('message', 'Тип тренера удалён');
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertDatabaseMissing('trainer_types', ['id' => $type->id]);
    }

    public function test_ajax_store_returns_json_contract_and_ajax_validation_returns_422_under_fields(): void
    {
        $ok = $this->postJson(route('admin.trainer-types.store'), [
            'name' => 'AJAX тип',
            'rate_per_training' => 10,
            'base_premium' => 2,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $ok->assertOk()
            ->assertJsonStructure([
                'message',
                'trainer_type' => [
                    'id',
                    'name',
                    'rate_per_training',
                    'base_premium',
                    'is_system',
                    'can_delete',
                    'trainers_count',
                ],
            ])
            ->assertJsonPath('trainer_type.name', 'AJAX тип');

        $this->postJson(route('admin.trainer-types.store'), [
            'name' => '',
            'rate_per_training' => -3,
            'base_premium' => 'нет',
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'rate_per_training', 'base_premium']);
    }

    public function test_ajax_update_validation_returns_422_under_money_fields(): void
    {
        $type = TrainerType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Валидация update',
        ]);

        $this->putJson(route('admin.trainer-types.update', $type), [
            'name' => 'Валидация update',
            'rate_per_training' => -1,
            'base_premium' => '',
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['rate_per_training', 'base_premium']);

        $this->assertSame('Валидация update', $type->fresh()->name);
    }

    public function test_kansas_trainer_store_without_ajax_redirects_and_assigns_type(): void
    {
        $systemId = (int) TrainerType::query()
            ->where('partner_id', $this->partner->id)
            ->where('is_system', true)
            ->value('id');

        $response = $this->post(route('admin.trainers.store'), [
            'lastname' => 'NonAjax',
            'name' => 'Канзас',
            'is_enabled' => 1,
            'trainer_type_id' => $systemId,
        ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertRedirect(route('admin.trainers.index'));

        $userId = (int) User::query()->where('lastname', 'NonAjax')->where('name', 'Канзас')->value('id');
        $this->assertGreaterThan(0, $userId);
        $this->assertDatabaseHas('trainer_profiles', [
            'user_id' => $userId,
            'partner_id' => $this->partner->id,
            'trainer_type_id' => $systemId,
        ]);
    }

    public function test_kansas_trainer_store_without_type_non_ajax_redirects_with_field_error(): void
    {
        $this->from(route('admin.trainers.index'))
            ->post(route('admin.trainers.store'), [
                'lastname' => 'Безтипа',
                'name' => 'NonAjax',
                'is_enabled' => 1,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['trainer_type_id']);

        $this->assertDatabaseMissing('users', [
            'partner_id' => $this->partner->id,
            'lastname' => 'Безтипа',
            'name' => 'NonAjax',
        ]);
    }

    public function test_kansas_trainer_store_ajax_without_type_returns_422_under_trainer_type_id(): void
    {
        $this->postJson(route('admin.trainers.store'), [
            'lastname' => 'Безтипа',
            'name' => 'Ajax',
            'is_enabled' => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['trainer_type_id']);
    }

    public function test_guest_non_ajax_mutate_is_denied_and_does_not_persist(): void
    {
        auth()->logout();

        $this->post(route('admin.trainer-types.store'), [
            'name' => 'Гость тип',
            'rate_per_training' => 1,
            'base_premium' => 1,
        ])->assertRedirect();

        $this->assertDatabaseMissing('trainer_types', [
            'partner_id' => $this->partner->id,
            'name' => 'Гость тип',
        ]);
    }

    public function test_user_without_manage_non_ajax_store_gets_403(): void
    {
        $this->revokePermission('schedule.trainerSalary.manage');

        $this->from(route('admin.trainers.index'))
            ->post(route('admin.trainer-types.store'), [
                'name' => 'Без manage',
                'rate_per_training' => 1,
                'base_premium' => 1,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('trainer_types', [
            'partner_id' => $this->partner->id,
            'name' => 'Без manage',
        ]);
    }
}
