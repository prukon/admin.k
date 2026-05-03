<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\User;
use App\Models\UserTableSetting;
use App\Services\SchoolScheduleViewSettingsService;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

final class SchoolScheduleViewSettingsFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    private function grantLessonPackagesView(): void
    {
        $permId = $this->permissionId('lessonPackages.view');

        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $permId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_view_settings_get_returns_defaults_when_missing(): void
    {
        $this->grantLessonPackagesView();

        $this->getJson(route('admin.lesson-packages.school-schedule.view-settings'))
            ->assertOk()
            ->assertExactJson([
                'view_start_min' => 540,
                'view_end_min' => 1260,
            ]);
    }

    public function test_view_settings_post_saves_and_get_returns(): void
    {
        $this->grantLessonPackagesView();

        $this->postJson(route('admin.lesson-packages.school-schedule.view-settings.save'), [
            'view_start_min' => 480,
            'view_end_min' => 1200,
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('view_start_min', 480)
            ->assertJsonPath('view_end_min', 1200);

        $this->getJson(route('admin.lesson-packages.school-schedule.view-settings'))
            ->assertOk()
            ->assertExactJson([
                'view_start_min' => 480,
                'view_end_min' => 1200,
            ]);

        $this->assertDatabaseHas('user_table_settings', [
            'user_id' => $this->user->id,
            'table_key' => 'school_schedule_view',
        ]);
    }

    public function test_view_settings_post_validation_not_multiple_of_30(): void
    {
        $this->grantLessonPackagesView();

        $this->postJson(route('admin.lesson-packages.school-schedule.view-settings.save'), [
            'view_start_min' => 541,
            'view_end_min' => 1260,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['view_start_min']);
    }

    public function test_view_settings_forbidden_without_permission(): void
    {
        $user = $this->createUserWithoutPermission('lessonPackages.view');
        $this->actingAs($user);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->getJson(route('admin.lesson-packages.school-schedule.view-settings'))->assertStatus(403);
        $this->postJson(route('admin.lesson-packages.school-schedule.view-settings.save'), [
            'view_start_min' => 540,
            'view_end_min' => 1260,
        ])->assertStatus(403);
    }

    public function test_view_settings_get_returns_defaults_when_columns_invalid_in_db(): void
    {
        $this->grantLessonPackagesView();

        UserTableSetting::query()->create([
            'user_id' => $this->user->id,
            'table_key' => SchoolScheduleViewSettingsService::TABLE_KEY,
            'columns' => [
                'view_start_min' => 999,
                'view_end_min' => 888,
            ],
        ]);

        $this->getJson(route('admin.lesson-packages.school-schedule.view-settings'))
            ->assertOk()
            ->assertExactJson([
                'view_start_min' => 540,
                'view_end_min' => 1260,
            ]);
    }

    public function test_view_settings_post_updates_existing_row_for_same_user(): void
    {
        $this->grantLessonPackagesView();

        $this->postJson(route('admin.lesson-packages.school-schedule.view-settings.save'), [
            'view_start_min' => 480,
            'view_end_min' => 1200,
        ])->assertOk();

        $this->postJson(route('admin.lesson-packages.school-schedule.view-settings.save'), [
            'view_start_min' => 600,
            'view_end_min' => 1320,
        ])->assertOk()
            ->assertJsonPath('view_start_min', 600)
            ->assertJsonPath('view_end_min', 1320);

        $this->assertDatabaseCount('user_table_settings', 1);
        $this->assertDatabaseHas('user_table_settings', [
            'user_id' => $this->user->id,
            'table_key' => SchoolScheduleViewSettingsService::TABLE_KEY,
        ]);
    }

    public function test_view_settings_isolated_per_user(): void
    {
        $this->grantLessonPackagesView();

        $this->postJson(route('admin.lesson-packages.school-schedule.view-settings.save'), [
            'view_start_min' => 420,
            'view_end_min' => 1140,
        ])->assertOk();

        /** @var User $other */
        $other = $this->createUserWithRole('admin', $this->partner);
        $this->actingAs($other);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->getJson(route('admin.lesson-packages.school-schedule.view-settings'))
            ->assertOk()
            ->assertExactJson([
                'view_start_min' => 540,
                'view_end_min' => 1260,
            ]);
    }

    public function test_view_settings_post_validation_end_not_multiple_of_30(): void
    {
        $this->grantLessonPackagesView();

        $this->postJson(route('admin.lesson-packages.school-schedule.view-settings.save'), [
            'view_start_min' => 540,
            'view_end_min' => 1261,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['view_end_min']);
    }

    public function test_view_settings_post_validation_start_out_of_range(): void
    {
        $this->grantLessonPackagesView();

        $this->postJson(route('admin.lesson-packages.school-schedule.view-settings.save'), [
            'view_start_min' => 1410,
            'view_end_min' => 1440,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['view_start_min']);
    }

    public function test_view_settings_post_validation_end_out_of_range_high(): void
    {
        $this->grantLessonPackagesView();

        $this->postJson(route('admin.lesson-packages.school-schedule.view-settings.save'), [
            'view_start_min' => 540,
            'view_end_min' => 1470,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['view_end_min']);
    }

    public function test_view_settings_post_validation_end_out_of_range_low(): void
    {
        $this->grantLessonPackagesView();

        $this->postJson(route('admin.lesson-packages.school-schedule.view-settings.save'), [
            'view_start_min' => 0,
            'view_end_min' => 30,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['view_end_min']);
    }

    public function test_view_settings_post_validation_span_less_than_one_hour(): void
    {
        $this->grantLessonPackagesView();

        $this->postJson(route('admin.lesson-packages.school-schedule.view-settings.save'), [
            'view_start_min' => 600,
            'view_end_min' => 630,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['view_end_min']);
    }

    public function test_view_settings_post_accepts_minimum_one_hour_window(): void
    {
        $this->grantLessonPackagesView();

        $this->postJson(route('admin.lesson-packages.school-schedule.view-settings.save'), [
            'view_start_min' => 600,
            'view_end_min' => 660,
        ])->assertOk()
            ->assertJsonPath('view_start_min', 600)
            ->assertJsonPath('view_end_min', 660);
    }

    public function test_view_settings_post_accepts_full_day_window(): void
    {
        $this->grantLessonPackagesView();

        $this->postJson(route('admin.lesson-packages.school-schedule.view-settings.save'), [
            'view_start_min' => 0,
            'view_end_min' => 1440,
        ])->assertOk()
            ->assertJsonPath('view_start_min', 0)
            ->assertJsonPath('view_end_min', 1440);
    }

    public function test_view_settings_post_accepts_late_start_and_midnight_end(): void
    {
        $this->grantLessonPackagesView();

        $this->postJson(route('admin.lesson-packages.school-schedule.view-settings.save'), [
            'view_start_min' => 1380,
            'view_end_min' => 1440,
        ])->assertOk()
            ->assertJsonPath('view_start_min', 1380)
            ->assertJsonPath('view_end_min', 1440);
    }

    public function test_view_settings_post_requires_integer_fields(): void
    {
        $this->grantLessonPackagesView();

        $this->postJson(route('admin.lesson-packages.school-schedule.view-settings.save'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['view_start_min', 'view_end_min']);
    }

    public function test_school_schedule_page_contains_view_settings_modal_markup(): void
    {
        $this->grantLessonPackagesView();

        $this->get(route('admin.lesson-packages.school-schedule'))
            ->assertOk()
            ->assertSee('schoolCalViewSettingsModal', false)
            ->assertSee('schoolCalViewSettingsSave', false)
            ->assertSee('schoolCalViewStart', false);
    }
}
