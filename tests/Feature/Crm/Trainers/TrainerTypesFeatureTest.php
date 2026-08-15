<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Trainers;

use App\Models\Partner;
use App\Models\TrainerProfile;
use App\Models\TrainerSalaryKansasDraftTrainer;
use App\Models\TrainerSalarySnapshot;
use App\Models\TrainerType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\Schedule\ScheduleTrainerSalaryTestCase;

final class TrainerTypesFeatureTest extends ScheduleTrainerSalaryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
    }

    public function test_new_partner_gets_system_type_and_trainers_are_assigned(): void
    {
        $this->assertDatabaseHas('trainer_types', [
            'partner_id' => $this->partner->id,
            'name' => TrainerType::SYSTEM_DEFAULT_NAME,
            'is_system' => 1,
            'is_enabled' => 1,
            'rate_per_training_cents' => 0,
            'base_premium_cents' => 0,
        ]);

        $trainer = $this->makeTrainerProfile('Системный тип');
        $systemId = (int) TrainerType::query()
            ->where('partner_id', $this->partner->id)
            ->where('is_system', true)
            ->value('id');

        $this->assertSame($systemId, (int) $trainer->trainer_type_id);
        $this->assertSame(1, TrainerType::query()->where('partner_id', $this->partner->id)->where('is_system', true)->count());
    }

    public function test_catalog_forbidden_without_kansas(): void
    {
        $this->grantPermission('trainers.view');

        $this->getJson(route('admin.trainer-types.index'))->assertForbidden();
        $this->postJson(route('admin.trainer-types.store'), [
            'name' => 'Второй тренер',
            'rate_per_training' => 500,
            'base_premium' => 200,
        ])->assertForbidden();
    }

    public function test_trainers_view_with_kansas_can_list_but_cannot_mutate(): void
    {
        $this->grantTrainerSalaryViewKansas();

        $this->getJson(route('admin.trainer-types.index'))
            ->assertOk()
            ->assertJsonPath('can_manage', false)
            ->assertJsonCount(1, 'types')
            ->assertJsonPath('types.0.name', TrainerType::SYSTEM_DEFAULT_NAME);

        $this->postJson(route('admin.trainer-types.store'), [
            'name' => 'Второй тренер',
            'rate_per_training' => 500,
            'base_premium' => 200,
        ])->assertForbidden();
    }

    public function test_salary_manage_with_kansas_can_crud_extra_type(): void
    {
        $this->grantTrainerSalaryViewKansas();
        $this->grantTrainerSalaryManage();

        $create = $this->postJson(route('admin.trainer-types.store'), [
            'name' => 'Второй тренер',
            'rate_per_training' => 500.5,
            'base_premium' => 200,
            'sort_order' => 20,
        ]);
        $create->assertOk()
            ->assertJsonPath('message', 'Тип тренера создан')
            ->assertJsonPath('trainer_type.name', 'Второй тренер')
            ->assertJsonPath('trainer_type.rate_per_training', '500.50')
            ->assertJsonPath('trainer_type.is_system', 0);

        $id = (int) $create->json('trainer_type.id');
        $this->assertDatabaseHas('trainer_types', [
            'id' => $id,
            'partner_id' => $this->partner->id,
            'rate_per_training_cents' => 50050,
            'base_premium_cents' => 20000,
        ]);

        $this->putJson(route('admin.trainer-types.update', $id), [
            'name' => 'Помощник',
            'rate_per_training' => 400,
            'base_premium' => 150,
            'sort_order' => 15,
            'is_enabled' => 1,
        ])
            ->assertOk()
            ->assertJsonPath('trainer_type.name', 'Помощник');

        $this->deleteJson(route('admin.trainer-types.destroy', $id))
            ->assertOk()
            ->assertJsonPath('message', 'Тип тренера удалён');

        $this->assertDatabaseMissing('trainer_types', ['id' => $id]);
    }

    public function test_system_type_cannot_be_deleted_or_disabled_and_name_can_change(): void
    {
        $this->grantTrainerSalaryViewKansas();
        $this->grantTrainerSalaryManage();

        $system = TrainerType::query()
            ->where('partner_id', $this->partner->id)
            ->where('is_system', true)
            ->firstOrFail();

        $this->deleteJson(route('admin.trainer-types.destroy', $system))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        $this->putJson(route('admin.trainer-types.update', $system), [
            'name' => 'Старший тренер',
            'rate_per_training' => 0,
            'base_premium' => 0,
            'is_enabled' => 0,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['is_enabled']);

        $this->putJson(route('admin.trainer-types.update', $system), [
            'name' => 'Старший тренер',
            'rate_per_training' => 1200,
            'base_premium' => 300,
            'is_enabled' => 1,
        ])
            ->assertOk()
            ->assertJsonPath('trainer_type.name', 'Старший тренер')
            ->assertJsonPath('trainer_type.is_system', 1)
            ->assertJsonPath('trainer_type.rate_per_training', '1200.00');

        $this->assertDatabaseHas('trainer_types', [
            'id' => $system->id,
            'is_system' => 1,
            'is_enabled' => 1,
            'name' => 'Старший тренер',
        ]);
    }

    public function test_cannot_delete_type_assigned_to_trainer(): void
    {
        $this->grantTrainerSalaryViewKansas();
        $this->grantTrainerSalaryManage();

        $type = TrainerType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Стажёр',
        ]);
        $trainer = $this->makeTrainerProfile('Стажёр Иванов');
        $trainer->update(['trainer_type_id' => $type->id]);

        $this->deleteJson(route('admin.trainer-types.destroy', $type))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        $this->assertDatabaseHas('trainer_types', ['id' => $type->id]);
    }

    public function test_duplicate_name_returns_422_under_name(): void
    {
        $this->grantTrainerSalaryViewKansas();
        $this->grantTrainerSalaryManage();

        $this->postJson(route('admin.trainer-types.store'), [
            'name' => TrainerType::SYSTEM_DEFAULT_NAME,
            'rate_per_training' => 1,
            'base_premium' => 1,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_validation_errors_for_money_fields(): void
    {
        $this->grantTrainerSalaryViewKansas();
        $this->grantTrainerSalaryManage();

        $this->postJson(route('admin.trainer-types.store'), [
            'name' => 'Без сумм',
            'rate_per_training' => -1,
            'base_premium' => 'x',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['rate_per_training', 'base_premium']);
    }

    public function test_foreign_type_is_404(): void
    {
        $this->grantTrainerSalaryViewKansas();
        $this->grantTrainerSalaryManage();

        $foreign = TrainerType::query()
            ->where('partner_id', $this->foreignPartner->id)
            ->where('is_system', true)
            ->firstOrFail();

        $this->getJson(route('admin.trainer-types.show', $foreign))->assertNotFound();
        $this->putJson(route('admin.trainer-types.update', $foreign), [
            'name' => 'Чужой',
            'rate_per_training' => 1,
            'base_premium' => 1,
        ])->assertNotFound();
        $this->deleteJson(route('admin.trainer-types.destroy', $foreign))->assertNotFound();
    }

    public function test_guest_is_redirected_or_unauthorized(): void
    {
        $system = TrainerType::query()
            ->where('partner_id', $this->partner->id)
            ->where('is_system', true)
            ->firstOrFail();

        auth()->logout();
        $this->get(route('admin.trainer-types.index'))->assertRedirect();
        $this->getJson(route('admin.trainer-types.index'))->assertUnauthorized();
        $this->getJson(route('admin.trainer-types.show', $system))->assertUnauthorized();
        $this->postJson(route('admin.trainer-types.store'), [
            'name' => 'Гость',
            'rate_per_training' => 1,
            'base_premium' => 1,
        ])->assertUnauthorized();
        $this->putJson(route('admin.trainer-types.update', $system), [
            'name' => 'Гость',
            'rate_per_training' => 1,
            'base_premium' => 1,
        ])->assertUnauthorized();
        $this->deleteJson(route('admin.trainer-types.destroy', $system))->assertUnauthorized();
    }

    public function test_viewer_can_open_type_but_cannot_update_or_delete(): void
    {
        $this->grantTrainerSalaryViewKansas();
        $system = TrainerType::query()
            ->where('partner_id', $this->partner->id)
            ->where('is_system', true)
            ->firstOrFail();

        $this->getJson(route('admin.trainer-types.show', $system))
            ->assertOk()
            ->assertJsonPath('id', $system->id)
            ->assertJsonPath('is_system', 1)
            ->assertJsonPath('can_delete', false);

        $this->putJson(route('admin.trainer-types.update', $system), [
            'name' => $system->name,
            'rate_per_training' => 9,
            'base_premium' => 9,
        ])->assertForbidden();

        $extra = TrainerType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Не удалять без manage',
        ]);
        $this->deleteJson(route('admin.trainer-types.destroy', $extra))->assertForbidden();
        $this->assertDatabaseHas('trainer_types', ['id' => $extra->id]);
    }

    public function test_catalog_mutate_forbidden_without_kansas_on_every_write_endpoint(): void
    {
        $this->grantPermission('trainers.view');
        $this->grantTrainerSalaryManage();
        $system = TrainerType::query()
            ->where('partner_id', $this->partner->id)
            ->where('is_system', true)
            ->firstOrFail();

        $this->getJson(route('admin.trainer-types.show', $system))->assertForbidden();
        $this->putJson(route('admin.trainer-types.update', $system), [
            'name' => 'Без канзаса',
            'rate_per_training' => 1,
            'base_premium' => 1,
        ])->assertForbidden();
        $this->deleteJson(route('admin.trainer-types.destroy', $system))->assertForbidden();
    }

    public function test_index_json_lists_disabled_types_and_can_delete_flags(): void
    {
        $this->grantTrainerSalaryViewKansas();
        $this->grantTrainerSalaryManage();

        $unused = TrainerType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Свободный',
            'sort_order' => 5,
        ]);
        $disabled = TrainerType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Выключенный в списке',
            'is_enabled' => false,
            'sort_order' => 6,
        ]);
        $assigned = TrainerType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Занятый',
            'sort_order' => 7,
        ]);
        $trainer = $this->makeTrainerProfile('Назначен на тип');
        $trainer->update(['trainer_type_id' => $assigned->id]);

        $response = $this->getJson(route('admin.trainer-types.index'))
            ->assertOk()
            ->assertJsonPath('can_manage', true)
            ->assertJsonStructure([
                'can_manage',
                'types' => [
                    [
                        'id',
                        'name',
                        'sort_order',
                        'is_enabled',
                        'is_system',
                        'rate_per_training',
                        'base_premium',
                        'trainers_count',
                        'can_delete',
                    ],
                ],
            ]);
        $this->assertNotSame('', trim((string) $response->getContent()));

        $byId = collect($response->json('types'))->keyBy('id');
        $this->assertSame(0, (int) $byId[$unused->id]['is_system']);
        $this->assertTrue((bool) $byId[$unused->id]['can_delete']);
        $this->assertSame(0, (int) $byId[$disabled->id]['is_enabled']);
        $this->assertTrue((bool) $byId[$disabled->id]['can_delete']);
        $this->assertFalse((bool) $byId[$assigned->id]['can_delete']);
        $this->assertSame(1, (int) $byId[$assigned->id]['trainers_count']);
        $systemRow = collect($response->json('types'))->firstWhere('is_system', 1);
        $this->assertNotNull($systemRow);
        $this->assertFalse((bool) $systemRow['can_delete']);
    }

    public function test_same_type_name_is_allowed_for_another_partner(): void
    {
        $this->grantTrainerSalaryViewKansas();
        $this->grantTrainerSalaryManage();

        TrainerType::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'name' => 'Общее имя',
        ]);

        $this->postJson(route('admin.trainer-types.store'), [
            'name' => 'Общее имя',
            'rate_per_training' => 1,
            'base_premium' => 1,
        ])
            ->assertOk()
            ->assertJsonPath('trainer_type.name', 'Общее имя');
    }

    public function test_renaming_type_to_existing_name_returns_422_under_name(): void
    {
        $this->grantTrainerSalaryViewKansas();
        $this->grantTrainerSalaryManage();

        $first = TrainerType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Первый тип',
        ]);
        $second = TrainerType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Второй тип',
        ]);

        $this->putJson(route('admin.trainer-types.update', $second), [
            'name' => $first->name,
            'rate_per_training' => 0,
            'base_premium' => 0,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        $this->assertSame('Второй тип', $second->fresh()->name);
    }

    public function test_updating_type_rates_rewrites_kansas_drafts_but_not_snapshots(): void
    {
        $this->grantTrainerSalaryViewKansas();
        $this->grantTrainerSalaryManage();

        $trainer = $this->makeTrainerProfile('Ставки типа');
        $this->setTrainerTypeRates($trainer, 1000, 800);
        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->assertOk();

        $this->postJson(route('schedule.trainer-salary.snapshots.form-one', $trainer), [
            'year' => 2026,
            'month' => 5,
        ])->assertOk();

        $snapshot = TrainerSalarySnapshot::query()
            ->where('trainer_profile_id', $trainer->id)
            ->firstOrFail();
        $this->assertSame(100000, (int) $snapshot->rate_per_training_cents);
        $this->assertSame(0, (int) $snapshot->total_cents);

        $system = TrainerType::query()
            ->where('partner_id', $this->partner->id)
            ->where('is_system', true)
            ->firstOrFail();

        $this->putJson(route('admin.trainer-types.update', $system), [
            'name' => $system->name,
            'rate_per_training' => 250,
            'base_premium' => 50,
            'is_enabled' => 1,
        ])->assertOk();

        $row = collect(
            $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
                ->assertOk()
                ->json('rows')
        )->firstWhere('trainer_profile_id', $trainer->id);

        $this->assertSame('250.00', $row['rate_per_training']);
        $this->assertSame('50.00', $row['base_premium']);

        $settings = TrainerSalaryKansasDraftTrainer::query()
            ->whereHas('draftLine', fn ($q) => $q->where('trainer_profile_id', $trainer->id))
            ->firstOrFail();
        $this->assertSame(25000, (int) $settings->rate_per_training_cents);
        $this->assertSame(5000, (int) $settings->base_premium_cents);

        $snapshot->refresh();
        $this->assertSame(100000, (int) $snapshot->rate_per_training_cents);
        $this->assertSame(0, (int) $snapshot->total_cents);
    }

    public function test_kansas_trainer_card_requires_type_and_classic_does_not(): void
    {
        $this->grantTrainerSalaryViewKansas();
        $this->grantPermission('trainers.view');

        $this->get(route('admin.trainers.index'))
            ->assertOk()
            ->assertSee('id="trainerTypesModal"', false)
            ->assertSee('name="trainer_type_id"', false)
            ->assertSee('Тип тренера', false);

        $this->postJson(route('admin.trainers.store'), [
            'lastname' => 'Без',
            'name' => 'Типа',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['trainer_type_id']);

        $systemId = (int) TrainerType::query()
            ->where('partner_id', $this->partner->id)
            ->where('is_system', true)
            ->value('id');

        $this->postJson(route('admin.trainers.store'), [
            'lastname' => 'С',
            'name' => 'Типом',
            'trainer_type_id' => $systemId,
        ])->assertOk();

        $this->useClassicSchemeOnly();
        $this->get(route('admin.trainers.index'))
            ->assertOk()
            ->assertDontSee('id="trainerTypesModal"', false);

        $this->postJson(route('admin.trainers.store'), [
            'lastname' => 'Классика',
            'name' => 'Безтипа',
        ])->assertOk();

        $userId = (int) User::query()->where('lastname', 'Классика')->value('id');
        $profile = TrainerProfile::query()->where('user_id', $userId)->firstOrFail();
        $this->assertSame($systemId, (int) $profile->trainer_type_id);
    }

    public function test_changing_trainer_type_updates_kansas_draft_rates(): void
    {
        $this->grantTrainerSalaryViewKansas();
        $this->grantTrainerSalaryManage();
        $this->grantPermission('trainers.view');

        $second = TrainerType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Второй',
            'rate_per_training_cents' => 70000,
            'base_premium_cents' => 10000,
        ]);
        $trainer = $this->makeTrainerProfile('Смена типа');
        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->assertOk();

        $this->putJson(route('admin.trainers.update', $trainer), [
            'lastname' => 'Смена',
            'name' => 'Типа',
            'is_enabled' => 1,
            'trainer_type_id' => $second->id,
        ])->assertOk();

        $row = collect(
            $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->json('rows')
        )->firstWhere('trainer_profile_id', $trainer->id);

        $this->assertSame('700.00', $row['rate_per_training']);
        $this->assertSame('100.00', $row['base_premium']);
        $this->assertSame($second->id, (int) $trainer->fresh()->trainer_type_id);
    }

    public function test_salary_clerk_without_trainers_view_can_manage_types(): void
    {
        $actor = $this->createUserWithoutPermission('trainers.view');
        $this->actingAs($actor)->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->grantTrainerSalaryViewKansas($actor);
        $this->grantTrainerSalaryManage($actor);

        $this->get(route('admin.trainers.index'))->assertForbidden();

        $this->getJson(route('admin.trainer-types.index'))
            ->assertOk()
            ->assertJsonPath('can_manage', true);

        $this->postJson(route('admin.trainer-types.store'), [
            'name' => 'Стажёр ЗП',
            'rate_per_training' => 100,
            'base_premium' => 10,
        ])
            ->assertOk()
            ->assertJsonPath('trainer_type.name', 'Стажёр ЗП');
    }

    public function test_cannot_delete_type_assigned_to_soft_deleted_trainer(): void
    {
        $this->grantTrainerSalaryViewKansas();
        $this->grantTrainerSalaryManage();

        $type = TrainerType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Бывший стажёр',
        ]);
        $trainer = $this->makeTrainerProfile('Мягко удалён');
        $trainer->update(['trainer_type_id' => $type->id]);
        $trainer->delete();

        $this->deleteJson(route('admin.trainer-types.destroy', $type))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        $this->assertDatabaseHas('trainer_types', ['id' => $type->id]);
    }

    public function test_update_keeps_disabled_current_type_and_rejects_other_disabled(): void
    {
        $this->grantTrainerSalaryViewKansas();
        $this->grantPermission('trainers.view');

        $current = TrainerType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Текущий выключенный',
            'is_enabled' => false,
        ]);
        $other = TrainerType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Чужой выключенный',
            'is_enabled' => false,
        ]);
        $trainer = $this->makeTrainerProfile('Выключенный тип');
        $trainer->update(['trainer_type_id' => $current->id]);

        $this->putJson(route('admin.trainers.update', $trainer), [
            'lastname' => 'Выключенный',
            'name' => 'Тип',
            'is_enabled' => 1,
            'trainer_type_id' => $current->id,
        ])->assertOk();

        $this->putJson(route('admin.trainers.update', $trainer), [
            'lastname' => 'Выключенный',
            'name' => 'Тип',
            'is_enabled' => 1,
            'trainer_type_id' => $other->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['trainer_type_id']);
    }

    public function test_kansas_update_does_not_overwrite_classic_salary_defaults(): void
    {
        $this->grantTrainerSalaryViewKansas();
        $this->grantPermission('trainers.view');

        $trainer = $this->makeTrainerProfile('Оклад classic');
        $trainer->update([
            'default_base_salary_cents' => 70000,
            'default_rate_per_training_cents' => 50000,
        ]);

        $this->putJson(route('admin.trainers.update', $trainer), [
            'lastname' => 'Оклад',
            'name' => 'classic',
            'is_enabled' => 1,
            'trainer_type_id' => (int) $trainer->trainer_type_id,
            'default_base_salary' => 0,
            'default_rate_per_training' => 0,
        ])->assertOk();

        $trainer->refresh();
        $this->assertSame(70000, (int) $trainer->default_base_salary_cents);
        $this->assertSame(50000, (int) $trainer->default_rate_per_training_cents);
    }

    public function test_salary_page_shows_types_button_when_kansas_manage(): void
    {
        $this->grantTrainerSalaryViewKansas();
        $this->grantTrainerSalaryManage();
        $this->makeTrainerProfile('Кнопка типов');

        $this->get(route('schedule.trainer-salary', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertSee('Типы тренеров', false)
            ->assertSee('id="trainerTypesModal"', false)
            ->assertSee('/js/trainer-types.js', false);
    }

    public function test_another_partner_created_gets_own_system_type(): void
    {
        $fresh = Partner::factory()->create(['title' => 'Новая школа типов']);
        $this->assertSame(1, TrainerType::query()->where('partner_id', $fresh->id)->where('is_system', true)->count());
        $this->assertNotSame(
            TrainerType::query()->where('partner_id', $this->partner->id)->where('is_system', true)->value('id'),
            TrainerType::query()->where('partner_id', $fresh->id)->where('is_system', true)->value('id')
        );
    }
}
