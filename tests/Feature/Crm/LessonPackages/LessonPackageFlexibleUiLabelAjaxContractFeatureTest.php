<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\PaymentNotificationRule;
use App\Models\User;
use App\Models\UserLessonPackage;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * AJAX-контракт подписи типа: код flexible / UI «Предоплата», errors.schedule_type по полям.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class LessonPackageFlexibleUiLabelAjaxContractFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->grantPermission('lessonPackages.view');
        $this->grantLessonPackageTypePermissions($this->user, ['flexible']);
    }

    private function grantPermission(string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ajax предоплата',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price' => '1200.00',
            'freeze_enabled' => 0,
            'auto_attendance_enabled' => 0,
        ], $overrides);
    }

    /**
     * @return array<string, string>
     */
    private function ajaxHeaders(): array
    {
        return ['X-Requested-With' => 'XMLHttpRequest'];
    }

    public function test_datatable_row_keeps_flexible_code_and_shows_prepay_label(): void
    {
        $namedFlexible = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Гибкий 8 занятий',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 150000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);
        $fixed = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Фикс 8',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 150000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);
        $single = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Разовое',
            'schedule_type' => 'no_schedule',
            'duration_days' => 1,
            'lessons_count' => 1,
            'price_cents' => 50000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $json = $this->getJson(route('admin.lesson-packages.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
        ]), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data'])
            ->json();

        $this->assertNotSame('', trim((string) json_encode($json)));

        $flexRow = collect($json['data'] ?? [])->firstWhere('id', $namedFlexible->id);
        $this->assertIsArray($flexRow);
        $this->assertSame('Гибкий 8 занятий', $flexRow['name']);
        $this->assertSame('flexible', $flexRow['schedule_type']);
        $this->assertSame('Предоплата', $flexRow['schedule_type_label']);
        $this->assertNotSame('Гибкий', $flexRow['schedule_type_label']);

        $fixedRow = collect($json['data'] ?? [])->firstWhere('id', $fixed->id);
        $this->assertIsArray($fixedRow);
        $this->assertSame('Фиксированный', $fixedRow['schedule_type_label']);
        $this->assertSame('fixed', $fixedRow['schedule_type']);

        $singleRow = collect($json['data'] ?? [])->firstWhere('id', $single->id);
        $this->assertIsArray($singleRow);
        $this->assertSame('Разовое занятие', $singleRow['schedule_type_label']);
    }

    public function test_filter_by_flexible_code_still_works_and_russian_label_is_not_a_filter_value(): void
    {
        LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Filter flex',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);
        LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Filter fixed',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $byCode = $this->getJson(route('admin.lesson-packages.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'schedule_type' => 'flexible',
        ]), $this->ajaxHeaders())->assertOk()->json();

        $names = collect($byCode['data'] ?? [])->pluck('name')->all();
        $this->assertContains('Filter flex', $names);
        $this->assertNotContains('Filter fixed', $names);
        foreach ($byCode['data'] ?? [] as $row) {
            $this->assertSame('flexible', $row['schedule_type']);
            $this->assertSame('Предоплата', $row['schedule_type_label']);
        }

        $byLabel = $this->getJson(route('admin.lesson-packages.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'schedule_type' => 'Предоплата',
        ]), $this->ajaxHeaders())->assertOk()->json();
        $labelNames = collect($byLabel['data'] ?? [])->pluck('name')->all();
        $this->assertContains(
            'Filter fixed',
            $labelNames,
            'Фильтр должен слать код flexible; русская подпись «Предоплата» не входит в SCHEDULE_TYPES и не сужает выборку.'
        );
    }

    public function test_ajax_create_and_update_store_flexible_code_not_russian_label(): void
    {
        $this->postJson(
            route('admin.lesson-packages.store'),
            $this->validPayload(['name' => 'Ajax создан flexible']),
            $this->ajaxHeaders()
        )
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonMissingPath('errors');

        $package = LessonPackage::query()
            ->where('partner_id', $this->partner->id)
            ->where('name', 'Ajax создан flexible')
            ->firstOrFail();
        $this->assertSame('flexible', (string) $package->schedule_type);

        $this->putJson(
            route('admin.lesson-packages.update', ['lessonPackage' => $package->id]),
            $this->validPayload([
                'name' => 'Ajax обновлён flexible',
                'lessons_count' => 4,
            ]),
            $this->ajaxHeaders()
        )
            ->assertOk()
            ->assertJson(['success' => true]);

        $package->refresh();
        $this->assertSame('flexible', (string) $package->schedule_type);
        $this->assertSame('Ajax обновлён flexible', (string) $package->name);
        $this->assertSame(4, (int) $package->lessons_count);
    }

    public function test_ajax_create_returns_422_under_schedule_type_when_client_sends_ui_label_or_omits_type(): void
    {
        foreach (['Предоплата', 'Гибкий', 'prepay', ''] as $invalid) {
            $payload = $this->validPayload(['name' => 'Bad type '.$invalid]);
            if ($invalid === '') {
                unset($payload['schedule_type']);
            } else {
                $payload['schedule_type'] = $invalid;
            }

            $response = $this->postJson(
                route('admin.lesson-packages.store'),
                $payload,
                $this->ajaxHeaders()
            );

            $response->assertStatus(422)
                ->assertJsonStructure(['message', 'errors' => ['schedule_type']]);
            $this->assertNotSame(200, $response->getStatusCode());
            $this->assertNotSame(500, $response->getStatusCode());
            $this->assertNotSame('', trim((string) $response->getContent()));

            if ($invalid === '') {
                $response->assertJsonPath('errors.schedule_type.0', 'Выберите тип абонемента.');
            } else {
                $response->assertJsonPath('errors.schedule_type.0', 'Некорректный тип абонемента.');
            }
        }

        $this->assertDatabaseMissing('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Bad type Предоплата',
        ]);
    }

    public function test_show_json_for_edit_modal_returns_flexible_code_not_prepay_word(): void
    {
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Show flexible',
            'schedule_type' => 'flexible',
            'duration_days' => 45,
            'lessons_count' => 10,
            'price_cents' => 250000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $this->getJson(route('admin.lesson-packages.show', ['lessonPackage' => $package->id]), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('lesson_package.schedule_type', 'flexible')
            ->assertJsonPath('lesson_package.name', 'Show flexible')
            ->assertJsonStructure([
                'success',
                'lesson_package' => ['id', 'name', 'schedule_type', 'duration_days'],
            ]);

        $payload = $this->getJson(
            route('admin.lesson-packages.show', ['lessonPackage' => $package->id]),
            $this->ajaxHeaders()
        )->json('lesson_package');
        $this->assertArrayNotHasKey('schedule_type_label', $payload);
        $this->assertNotSame('Предоплата', $payload['schedule_type']);
    }

    public function test_assignments_ajax_row_and_show_keep_code_and_prepay_label(): void
    {
        $this->grantPermission('setPrices.packageAssignments.view');

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Тестов',
            'name' => 'Ученик',
            'is_enabled' => 1,
        ]);
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Гибкий 8 занятий',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);
        $assignment = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'lessons_total' => 8,
            'lessons_remaining' => 8,
            'fee_amount_cents' => 10000,
            'is_paid' => 0,
            'created_by' => $this->user->id,
        ]);

        $json = $this->getJson(route('admin.lesson-packages.assignments.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'filter_schedule_type' => 'flexible',
        ]), $this->ajaxHeaders())
            ->assertOk()
            ->json();

        $row = collect($json['data'] ?? [])->firstWhere('id', $assignment->id);
        $this->assertIsArray($row);
        $this->assertSame('Предоплата', $row['type_label']);
        $this->assertSame('Гибкий 8 занятий', $row['package_name']);
        $this->assertNotSame('Гибкий', $row['type_label']);

        $this->getJson(
            route('admin.lesson-packages.assignments.show', ['assignment' => $assignment->id]),
            $this->ajaxHeaders()
        )
            ->assertOk()
            ->assertJsonPath('assignment.schedule_type', 'flexible')
            ->assertJsonPath('assignment.schedule_type_label', 'Предоплата')
            ->assertJsonPath('assignment.lesson_package_name', 'Гибкий 8 занятий');
    }

    public function test_payment_notification_rules_json_maps_flexible_to_prepay_and_rejects_russian_type(): void
    {
        $this->grantPermission('setPrices.view');
        $this->grantPermission('setPrices.paymentNotifications.manage');

        $index = $this->getJson(
            route('admin.settingPrices.paymentNotifications.rules.index'),
            $this->ajaxHeaders()
        )
            ->assertOk()
            ->assertJsonPath('schedule_type_labels.flexible', 'Предоплата')
            ->assertJsonPath('schedule_type_labels.fixed', 'Фиксированный')
            ->assertJsonPath('schedule_type_labels.postpay', 'Постоплата')
            ->json();
        $this->assertNotSame('Гибкий', $index['schedule_type_labels']['flexible'] ?? null);

        $this->postJson(route('admin.settingPrices.paymentNotifications.rules.store'), [
            'name' => 'Правило предоплаты',
            'is_enabled' => true,
            'trigger_type' => PaymentNotificationRule::TRIGGER_DAY_OF_MONTH,
            'trigger_value' => 5,
            'billing_month_offset' => 0,
            'schedule_types' => ['flexible'],
            'subject_template' => 'Тема',
            'body_html_template' => '<p>Тело</p>',
        ], $this->ajaxHeaders())
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('rule.schedule_types.0', 'flexible');

        $this->postJson(route('admin.settingPrices.paymentNotifications.rules.store'), [
            'name' => 'Правило с русской подписью',
            'is_enabled' => true,
            'trigger_type' => PaymentNotificationRule::TRIGGER_DAY_OF_MONTH,
            'trigger_value' => 5,
            'billing_month_offset' => 0,
            'schedule_types' => ['Предоплата'],
            'subject_template' => 'Тема',
            'body_html_template' => '<p>Тело</p>',
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['schedule_types.0']);

        $this->postJson(route('admin.settingPrices.paymentNotifications.rules.store'), [
            'name' => 'Правило Гибкий',
            'is_enabled' => true,
            'trigger_type' => PaymentNotificationRule::TRIGGER_DAY_OF_MONTH,
            'trigger_value' => 5,
            'billing_month_offset' => 0,
            'schedule_types' => ['Гибкий'],
            'subject_template' => 'Тема',
            'body_html_template' => '<p>Тело</p>',
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['schedule_types.0']);
    }
}
