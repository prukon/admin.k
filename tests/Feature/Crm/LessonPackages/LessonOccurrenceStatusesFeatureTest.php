<?php

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonOccurrenceStatus;
use App\Models\Partner;
use Database\Seeders\LessonOccurrenceStatusesSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

final class LessonOccurrenceStatusesFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    private function grantPermission(string $permissionName): void
    {
        $permId = $this->permissionId($permissionName);

        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $permId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_index_forbidden_without_lesson_packages_view(): void
    {
        $actor = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('admin.lesson-packages.occurrence-statuses.index'))
            ->assertStatus(403);
    }

    public function test_index_ok_and_seeds_system_statuses(): void
    {
        $this->grantPermission('lessonPackages.view');

        LessonOccurrenceStatus::query()->where('partner_id', $this->partner->id)->delete();

        $this->get(route('admin.lesson-packages.occurrence-statuses.index'))
            ->assertOk()
            ->assertSee('Статусы занятий')
            ->assertSee('Запись')
            ->assertSee('Списывает');

        $this->assertSame(
            5,
            LessonOccurrenceStatus::query()->where('partner_id', $this->partner->id)->count()
        );

        $codes = LessonOccurrenceStatus::query()
            ->where('partner_id', $this->partner->id)
            ->pluck('code')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            ['attended', 'cancelled', 'frozen', 'not_attended', 'scheduled'],
            $codes
        );

        $this->assertTrue(LessonOccurrenceStatus::query()
            ->where('partner_id', $this->partner->id)
            ->where('code', 'attended')
            ->value('consumes_lesson'));
        $this->assertTrue(LessonOccurrenceStatus::query()
            ->where('partner_id', $this->partner->id)
            ->where('code', 'not_attended')
            ->value('consumes_lesson'));
        $this->assertFalse(LessonOccurrenceStatus::query()
            ->where('partner_id', $this->partner->id)
            ->where('code', 'scheduled')
            ->value('consumes_lesson'));
    }

    public function test_store_forbidden_without_view_permission(): void
    {
        $actor = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->postJson(route('admin.lesson-packages.occurrence-statuses.store'), [
            'title' => 'Свой',
            'color' => '#111111',
        ])->assertStatus(403);
    }

    public function test_store_creates_custom_status(): void
    {
        $this->grantPermission('lessonPackages.view');

        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);

        $this->postJson(route('admin.lesson-packages.occurrence-statuses.store'), [
            'title' => 'Дополнительный',
            'color' => '#aabbcc',
            'icon' => 'fa-solid fa-star',
            'is_active' => true,
        ])->assertOk();

        $created = LessonOccurrenceStatus::query()
            ->where('partner_id', $this->partner->id)
            ->where('title', 'Дополнительный')
            ->firstOrFail();

        $this->assertSame('#aabbcc', $created->color);
        $this->assertFalse($created->is_system);
        $this->assertGreaterThan(0, $created->sort_order);
        $this->assertFalse($created->consumes_lesson);
    }

    public function test_update_system_status_rejects_title_change(): void
    {
        $this->grantPermission('lessonPackages.view');

        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);

        /** @var LessonOccurrenceStatus $scheduled */
        $scheduled = LessonOccurrenceStatus::query()
            ->where('partner_id', $this->partner->id)
            ->where('code', 'scheduled')
            ->firstOrFail();

        $this->putJson(route('admin.lesson-packages.occurrence-statuses.update', $scheduled->id), [
            'title' => 'Взлом',
            'color' => '#123456',
            'icon' => 'fa-solid fa-icons',
            'sort_order' => 15,
            'consumes_lesson' => false,
            'is_active' => true,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['title']);

        $scheduled->refresh();
        $this->assertSame('Запись', $scheduled->title);
    }

    public function test_update_system_status_allows_color_icon_sort(): void
    {
        $this->grantPermission('lessonPackages.view');

        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);

        /** @var LessonOccurrenceStatus $scheduled */
        $scheduled = LessonOccurrenceStatus::query()
            ->where('partner_id', $this->partner->id)
            ->where('code', 'scheduled')
            ->firstOrFail();

        $this->putJson(route('admin.lesson-packages.occurrence-statuses.update', $scheduled->id), [
            'color' => '#abcdef',
            'icon' => 'fa-solid fa-calendar-day',
            'sort_order' => 12,
            'consumes_lesson' => true,
            'is_active' => false,
        ])->assertOk();

        $scheduled->refresh();
        $this->assertSame('#abcdef', $scheduled->color);
        $this->assertSame('fa-solid fa-calendar-day', $scheduled->icon);
        $this->assertSame(12, $scheduled->sort_order);
        $this->assertFalse($scheduled->is_active);
        $this->assertTrue($scheduled->consumes_lesson);
    }

    public function test_destroy_system_status_is_forbidden(): void
    {
        $this->grantPermission('lessonPackages.view');

        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);

        /** @var LessonOccurrenceStatus $scheduled */
        $scheduled = LessonOccurrenceStatus::query()
            ->where('partner_id', $this->partner->id)
            ->where('code', 'scheduled')
            ->firstOrFail();

        $this->deleteJson(route('admin.lesson-packages.occurrence-statuses.destroy', $scheduled->id))
            ->assertStatus(422);

        $this->assertDatabaseHas('lesson_occurrence_statuses', ['id' => $scheduled->id]);
    }

    public function test_destroy_custom_status_ok(): void
    {
        $this->grantPermission('lessonPackages.view');

        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);

        $this->postJson(route('admin.lesson-packages.occurrence-statuses.store'), [
            'title' => 'Удаляемый',
            'color' => '#222222',
        ])->assertOk();

        $custom = LessonOccurrenceStatus::query()
            ->where('partner_id', $this->partner->id)
            ->where('title', 'Удаляемый')
            ->firstOrFail();

        $this->deleteJson(route('admin.lesson-packages.occurrence-statuses.destroy', $custom->id))
            ->assertOk();

        $this->assertDatabaseMissing('lesson_occurrence_statuses', ['id' => $custom->id]);
    }

    public function test_reorder_updates_all_rows_and_requires_full_set(): void
    {
        $this->grantPermission('lessonPackages.view');

        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);

        $rows = LessonOccurrenceStatus::query()
            ->where('partner_id', $this->partner->id)
            ->orderBy('id')
            ->get();

        $this->assertGreaterThanOrEqual(5, $rows->count());

        // Неполный список — 422
        $this->postJson(route('admin.lesson-packages.occurrence-statuses.reorder'), [
            'items' => [
                ['id' => $rows->first()->id, 'sort_order' => 1],
            ],
        ])->assertStatus(422);

        $items = $rows->values()->map(function (LessonOccurrenceStatus $s, int $i) {
            return ['id' => $s->id, 'sort_order' => ($i + 1) * 100];
        })->all();

        $this->postJson(route('admin.lesson-packages.occurrence-statuses.reorder'), [
            'items' => $items,
        ])->assertOk();

        foreach ($items as $row) {
            $this->assertDatabaseHas('lesson_occurrence_statuses', [
                'id' => $row['id'],
                'sort_order' => $row['sort_order'],
            ]);
        }
    }

    public function test_cannot_update_status_of_another_partner(): void
    {
        $this->grantPermission('lessonPackages.view');

        /** @var Partner $other */
        $other = Partner::factory()->create();
        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $other->id);

        $foreign = LessonOccurrenceStatus::query()
            ->where('partner_id', $other->id)
            ->where('code', 'scheduled')
            ->firstOrFail();

        $this->putJson(route('admin.lesson-packages.occurrence-statuses.update', $foreign->id), [
            'color' => '#ffffff',
            'icon' => null,
            'sort_order' => 1,
            'consumes_lesson' => false,
            'is_active' => true,
        ])->assertStatus(404);
    }

    public function test_occurrence_statuses_page_and_all_mutations_require_lesson_packages_view(): void
    {
        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);

        /** @var LessonOccurrenceStatus $scheduled */
        $scheduled = LessonOccurrenceStatus::query()
            ->where('partner_id', $this->partner->id)
            ->where('code', 'scheduled')
            ->firstOrFail();

        $actor = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('admin.lesson-packages.occurrence-statuses.index'))
            ->assertStatus(403);

        $this->postJson(route('admin.lesson-packages.occurrence-statuses.store'), [
            'title' => 'X',
            'color' => '#111111',
            'consumes_lesson' => false,
        ])->assertStatus(403);

        $this->putJson(route('admin.lesson-packages.occurrence-statuses.update', $scheduled->id), [
            'color' => '#222222',
            'icon' => null,
            'sort_order' => 5,
            'consumes_lesson' => false,
            'is_active' => true,
        ])->assertStatus(403);

        $this->deleteJson(route('admin.lesson-packages.occurrence-statuses.destroy', $scheduled->id))
            ->assertStatus(403);

        $this->postJson(route('admin.lesson-packages.occurrence-statuses.reorder'), [
            'items' => [
                ['id' => $scheduled->id, 'sort_order' => 10],
            ],
        ])->assertStatus(403);
    }

    public function test_occurrence_statuses_all_actions_return_200_when_authorized(): void
    {
        $this->grantPermission('lessonPackages.view');
        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);

        $this->get(route('admin.lesson-packages.occurrence-statuses.index'))
            ->assertOk();

        $this->postJson(route('admin.lesson-packages.occurrence-statuses.store'), [
            'title' => 'Смоук кастом',
            'color' => '#334455',
            'icon' => 'fa-solid fa-star',
            'consumes_lesson' => true,
            'is_active' => true,
        ])->assertOk();

        $custom = LessonOccurrenceStatus::query()
            ->where('partner_id', $this->partner->id)
            ->where('title', 'Смоук кастом')
            ->firstOrFail();

        /** @var LessonOccurrenceStatus $scheduled */
        $scheduled = LessonOccurrenceStatus::query()
            ->where('partner_id', $this->partner->id)
            ->where('code', 'scheduled')
            ->firstOrFail();

        $this->putJson(route('admin.lesson-packages.occurrence-statuses.update', $custom->id), [
            'title' => 'Смоук кастом переименован',
            'color' => '#445566',
            'icon' => 'fa-solid fa-book',
            'sort_order' => 999,
            'consumes_lesson' => false,
            'is_active' => true,
        ])->assertOk();

        $this->putJson(route('admin.lesson-packages.occurrence-statuses.update', $scheduled->id), [
            'color' => '#fedcba',
            'icon' => 'fa-solid fa-calendar-day',
            'sort_order' => 11,
            'consumes_lesson' => false,
            'is_active' => true,
        ])->assertOk();

        $rows = LessonOccurrenceStatus::query()
            ->where('partner_id', $this->partner->id)
            ->orderBy('id')
            ->get();

        $items = $rows->values()->map(static function (LessonOccurrenceStatus $s, int $i) {
            return ['id' => $s->id, 'sort_order' => ($i + 1) * 10];
        })->all();

        $this->postJson(route('admin.lesson-packages.occurrence-statuses.reorder'), [
            'items' => $items,
        ])->assertOk();

        $this->deleteJson(route('admin.lesson-packages.occurrence-statuses.destroy', $custom->id))
            ->assertOk();
    }

    public function test_admin_role_can_access_occurrence_statuses_without_explicit_permission_row(): void
    {
        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);

        $this->asAdmin();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->get(route('admin.lesson-packages.occurrence-statuses.index'))
            ->assertOk()
            ->assertSee('Статусы занятий');

        /** @var LessonOccurrenceStatus $scheduled */
        $scheduled = LessonOccurrenceStatus::query()
            ->where('partner_id', $this->partner->id)
            ->where('code', 'scheduled')
            ->firstOrFail();

        $this->postJson(route('admin.lesson-packages.occurrence-statuses.store'), [
            'title' => 'Админ кастом',
            'color' => '#abcdee',
            'consumes_lesson' => false,
        ])->assertOk();

        $custom = LessonOccurrenceStatus::query()
            ->where('partner_id', $this->partner->id)
            ->where('title', 'Админ кастом')
            ->firstOrFail();

        $this->putJson(route('admin.lesson-packages.occurrence-statuses.update', $scheduled->id), [
            'color' => '#111111',
            'icon' => null,
            'sort_order' => (int) $scheduled->sort_order,
            'consumes_lesson' => false,
            'is_active' => true,
        ])->assertOk();

        $rows = LessonOccurrenceStatus::query()
            ->where('partner_id', $this->partner->id)
            ->orderBy('id')
            ->get();
        $items = $rows->values()->map(static function (LessonOccurrenceStatus $s, int $i) {
            return ['id' => $s->id, 'sort_order' => ($i + 1) * 5];
        })->all();

        $this->postJson(route('admin.lesson-packages.occurrence-statuses.reorder'), ['items' => $items])
            ->assertOk();

        $this->deleteJson(route('admin.lesson-packages.occurrence-statuses.destroy', $custom->id))
            ->assertOk();
    }

    public function test_store_validation_errors(): void
    {
        $this->grantPermission('lessonPackages.view');
        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);

        $this->postJson(route('admin.lesson-packages.occurrence-statuses.store'), [
            'color' => '#111111',
            'consumes_lesson' => false,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['title']);

        $this->postJson(route('admin.lesson-packages.occurrence-statuses.store'), [
            'title' => 'Без цвета',
            'consumes_lesson' => false,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['color']);

        $this->postJson(route('admin.lesson-packages.occurrence-statuses.store'), [
            'title' => 'Неверный цвет',
            'color' => 'not-a-hex',
            'consumes_lesson' => false,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['color']);

        $this->postJson(route('admin.lesson-packages.occurrence-statuses.store'), [
            'title' => 'Иконка мимо списка',
            'color' => '#111111',
            'icon' => 'fa-solid fa-nonexistent-icon-xyz',
            'consumes_lesson' => false,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['icon']);
    }

    public function test_update_validation_errors(): void
    {
        $this->grantPermission('lessonPackages.view');
        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);

        /** @var LessonOccurrenceStatus $scheduled */
        $scheduled = LessonOccurrenceStatus::query()
            ->where('partner_id', $this->partner->id)
            ->where('code', 'scheduled')
            ->firstOrFail();

        $this->putJson(route('admin.lesson-packages.occurrence-statuses.update', $scheduled->id), [
            'color' => 'bad',
            'icon' => null,
            'sort_order' => 10,
            'consumes_lesson' => false,
            'is_active' => true,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['color']);

        $this->putJson(route('admin.lesson-packages.occurrence-statuses.update', $scheduled->id), [
            'color' => '#010101',
            'icon' => null,
            'sort_order' => -1,
            'consumes_lesson' => false,
            'is_active' => true,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['sort_order']);
    }

    public function test_store_sets_consumes_lesson_when_true(): void
    {
        $this->grantPermission('lessonPackages.view');
        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);

        $this->postJson(route('admin.lesson-packages.occurrence-statuses.store'), [
            'title' => 'Расходует',
            'color' => '#010203',
            'consumes_lesson' => true,
        ])->assertOk();

        $row = LessonOccurrenceStatus::query()
            ->where('partner_id', $this->partner->id)
            ->where('title', 'Расходует')
            ->firstOrFail();

        $this->assertTrue($row->consumes_lesson);
    }

    public function test_cannot_destroy_status_of_another_partner(): void
    {
        $this->grantPermission('lessonPackages.view');

        /** @var Partner $other */
        $other = Partner::factory()->create();
        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $other->id);

        $foreignCustom = LessonOccurrenceStatus::query()->create([
            'partner_id' => $other->id,
            'code' => 'custom_test_foreign_del',
            'title' => 'Чужой удаляемый',
            'color' => '#999999',
            'icon' => null,
            'sort_order' => 900,
            'consumes_lesson' => false,
            'is_system' => false,
            'is_active' => true,
        ]);

        $this->deleteJson(route('admin.lesson-packages.occurrence-statuses.destroy', $foreignCustom->id))
            ->assertStatus(404);

        $this->assertDatabaseHas('lesson_occurrence_statuses', ['id' => $foreignCustom->id]);
    }

    public function test_store_json_returns_created_payload_shape(): void
    {
        $this->grantPermission('lessonPackages.view');
        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);

        $response = $this->postJson(route('admin.lesson-packages.occurrence-statuses.store'), [
            'title' => 'JSON форма',
            'color' => '#abcdef',
            'consumes_lesson' => false,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'status' => [
                    'id',
                    'partner_id',
                    'code',
                    'title',
                    'color',
                    'consumes_lesson',
                ],
            ]);

        $this->assertSame('JSON форма', $response->json('status.title'));
    }

    public function test_update_json_returns_message_ok(): void
    {
        $this->grantPermission('lessonPackages.view');
        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);

        /** @var LessonOccurrenceStatus $scheduled */
        $scheduled = LessonOccurrenceStatus::query()
            ->where('partner_id', $this->partner->id)
            ->where('code', 'scheduled')
            ->firstOrFail();

        $this->putJson(route('admin.lesson-packages.occurrence-statuses.update', $scheduled->id), [
            'color' => '#010101',
            'icon' => 'fa-solid fa-clock',
            'sort_order' => 10,
            'consumes_lesson' => false,
            'is_active' => true,
        ])
            ->assertOk()
            ->assertJson(['message' => 'Статус обновлён']);
    }
}
