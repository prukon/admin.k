<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\Team;
use App\Models\TrainerSalarySnapshot;
use App\Models\User;

/**
 * Канзас: HTTP-доступ ко всем endpoint'ам ЗП и листов (гость, 403, 401, 200).
 */
final class ScheduleTrainerSalaryKansasAccessFeatureTest extends ScheduleTrainerSalaryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
    }

    public function test_guest_is_redirected_from_pages_and_unauthorized_on_json(): void
    {
        $trainer = $this->makeTrainerProfile('Канзас гость');
        auth()->logout();

        $this->get(route('schedule.trainer-salary'))->assertRedirect();
        $this->get(route('schedule.trainer-salary-sheets'))->assertRedirect();
        $this->get(route('schedule.trainer-salary-sheets.snapshot.show', ['snapshot' => 1]))->assertRedirect();
        $this->get(route('schedule.trainer-salary-sheets.batch.show', ['batchId' => 'x']))->assertRedirect();

        $this->getJson(route('schedule.trainer-salary.data'))->assertUnauthorized();
        $this->getJson(route('schedule.trainer-salary-sheets.data'))->assertUnauthorized();
        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'rate_per_training' => 1,
        ])->assertUnauthorized();
        $this->postJson(route('schedule.trainer-salary.snapshots.form-one', $trainer), [
            'year' => 2026,
            'month' => 5,
        ])->assertUnauthorized();
        $this->postJson(route('schedule.trainer-salary.snapshots.form-all'), [
            'year' => 2026,
            'month' => 5,
        ])->assertUnauthorized();
    }

    public function test_user_without_partner_is_logged_out_from_salary_page(): void
    {
        $actor = User::factory()->create(['partner_id' => null]);
        $this->actingAs($actor)->withSession([]);

        $this->get(route('schedule.trainer-salary'))
            ->assertRedirect()
            ->assertSessionHasErrors([
                'email' => 'Ваша организация недоступна.',
            ]);
        $this->assertGuest();
    }

    public function test_staff_without_view_permission_gets_403_on_salary_and_sheets(): void
    {
        $actor = $this->createUserWithoutPermission('schedule.trainerSalary.view', $this->partner);
        $trainer = $this->makeTrainerProfile('Канзас без view');

        $this->actingAs($actor)->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->get(route('schedule.trainer-salary'))->assertForbidden();
        $this->getJson(route('schedule.trainer-salary.data'))->assertForbidden();
        $this->get(route('schedule.trainer-salary-sheets'))->assertForbidden();
        $this->getJson(route('schedule.trainer-salary-sheets.data'))->assertForbidden();
        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'rate_per_training' => 1,
        ])->assertForbidden();
    }

    public function test_staff_with_view_and_manage_but_without_kansas_scheme_gets_403(): void
    {
        $this->grantPermission('schedule.trainerSalary.view');
        $this->grantPermission('schedule.trainerSalary.manage');
        $trainer = $this->makeTrainerProfile('Канзас без схемы');

        $this->get(route('schedule.trainer-salary'))->assertForbidden();
        $this->getJson(route('schedule.trainer-salary.data'))->assertForbidden();
        $this->get(route('schedule.trainer-salary-sheets'))->assertForbidden();
        $this->getJson(route('schedule.trainer-salary-sheets.data'))->assertForbidden();
        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'premium_increment' => 10,
        ])->assertForbidden();
        $this->postJson(route('schedule.trainer-salary.snapshots.form-one', $trainer), [
            'year' => 2026,
            'month' => 5,
        ])->assertForbidden();
        $this->postJson(route('schedule.trainer-salary.snapshots.form-all'), [
            'year' => 2026,
            'month' => 5,
        ])->assertForbidden();
    }

    public function test_staff_with_only_kansas_scheme_and_no_view_gets_403(): void
    {
        $this->grantKansasScheme();
        $trainer = $this->makeTrainerProfile('Только схема');

        $this->get(route('schedule.trainer-salary'))->assertForbidden();
        $this->getJson(route('schedule.trainer-salary.data'))->assertForbidden();
        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'rate_per_training' => 1,
        ])->assertForbidden();
    }

    public function test_viewer_with_kansas_can_open_pages_but_cannot_save_or_form_snapshots(): void
    {
        $this->grantTrainerSalaryViewKansas();
        $trainer = $this->makeTrainerProfile('Канзас просмотр');

        $this->get(route('schedule.trainer-salary', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertSee('ЗП тренеров', false)
            ->assertSee('data-can-manage="0"', false)
            ->assertDontSee('id="trainer-salary-form-all-btn"', false);

        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertJsonPath('scheme_code', 'kansas')
            ->assertJsonPath('can_manage', false);

        $this->get(route('schedule.trainer-salary-sheets', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertSee('Листы ЗП', false);

        $this->getJson(route('schedule.trainer-salary-sheets.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertJsonStructure(['year', 'month', 'sheets']);

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'rate_per_training' => 100,
        ])->assertForbidden();

        $this->postJson(route('schedule.trainer-salary.snapshots.form-one', $trainer), [
            'year' => 2026,
            'month' => 5,
        ])->assertForbidden();

        $this->postJson(route('schedule.trainer-salary.snapshots.form-all'), [
            'year' => 2026,
            'month' => 5,
        ])->assertForbidden();
    }

    public function test_manager_with_kansas_and_without_view_cannot_open_pages(): void
    {
        $this->grantPermission('schedule.trainerSalary.manage');
        $this->grantKansasScheme();

        $this->get(route('schedule.trainer-salary'))->assertForbidden();
        $this->getJson(route('schedule.trainer-salary.data'))->assertForbidden();
        $this->get(route('schedule.trainer-salary-sheets'))->assertForbidden();
    }

    public function test_manager_with_kansas_can_use_every_salary_endpoint_without_500(): void
    {
        $this->grantTrainerSalaryViewKansas();
        $this->grantTrainerSalaryManage();

        $trainer = $this->makeTrainerProfile('Канзас smoke');
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа smoke',
        ]);
        $student = $this->makeStudent($team->id);
        $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-05-11');

        $this->get(route('schedule.trainer-salary', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertSee('trainer-salary-app', false)
            ->assertSee('data-scheme-code="kansas"', false);

        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertJsonPath('scheme_code', 'kansas')
            ->assertJsonPath('can_manage', true)
            ->assertJsonPath('year', 2026)
            ->assertJsonPath('month', 5)
            ->assertJsonStructure([
                'year',
                'month',
                'month_label',
                'date_from',
                'date_to',
                'scheme_code',
                'draft_subtitle',
                'table_html',
                'rows',
                'can_manage',
            ]);

        $patch = $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'rate_per_training' => 900,
        ]);
        $patch->assertOk()
            ->assertJsonPath('message', 'Черновик сохранён')
            ->assertJsonPath('row.rate_per_training', '900.00')
            ->assertJsonPath('reload_table', true);
        $this->assertNotSame('', trim((string) $patch->getContent()));
        $this->assertIsString($patch->json('table_html'));
        $this->assertNotSame('', trim((string) $patch->json('table_html')));

        $formOne = $this->postJson(route('schedule.trainer-salary.snapshots.form-one', $trainer), [
            'year' => 2026,
            'month' => 5,
        ]);
        $formOne->assertOk()
            ->assertJsonPath('snapshot.scheme_code', 'kansas')
            ->assertJsonPath('snapshot.version', 1)
            ->assertJsonPath('reload_table', true);
        $this->assertStringContainsString('Слепок ЗП сформирован', (string) $formOne->json('message'));

        $formAll = $this->postJson(route('schedule.trainer-salary.snapshots.form-all'), [
            'year' => 2026,
            'month' => 5,
        ]);
        $formAll->assertOk()
            ->assertJsonStructure(['message', 'batch_id', 'snapshots_count', 'rows'])
            ->assertJsonPath('reload_table', true);
        $this->assertGreaterThan(0, (int) $formAll->json('snapshots_count'));

        $this->get(route('schedule.trainer-salary-sheets', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertSee('Листы ЗП', false);

        $sheets = $this->getJson(route('schedule.trainer-salary-sheets.data', [
            'year' => 2026,
            'month' => 5,
        ]))->assertOk();
        $this->assertIsArray($sheets->json('sheets'));

        $snapshotId = (int) TrainerSalarySnapshot::query()
            ->where('trainer_profile_id', $trainer->id)
            ->max('id');
        $this->assertGreaterThan(0, $snapshotId);

        $this->get(route('schedule.trainer-salary-sheets.snapshot.show', ['snapshot' => $snapshotId]))
            ->assertOk()
            ->assertSee('Канзас smoke', false)
            ->assertSee('trainer-salary-table--kansas', false)
            ->assertSee('trainer-salary-table--readonly', false)
            ->assertDontSee('trainer-salary-input', false)
            ->assertDontSee('trainer-salary-form-one-btn', false);

        $batchId = (string) $formAll->json('batch_id');
        $this->get(route('schedule.trainer-salary-sheets.batch.show', ['batchId' => $batchId]))
            ->assertOk()
            ->assertSee('Полный лист', false)
            ->assertSee('trainer-salary-table--readonly', false)
            ->assertDontSee('trainer-salary-input', false);
    }

    public function test_superadmin_without_scheme_still_sees_salary_tabs(): void
    {
        $this->asSuperadmin();
        $this->grantScheduleView();

        $html = (string) $this->get(route('schedule.trainer-salary'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('href="'.route('schedule.trainer-salary').'"', $html);
        $this->assertStringContainsString('href="'.route('schedule.trainer-salary-sheets').'"', $html);
        $this->assertStringContainsString('>ЗП тренеров</a>', $html);
        $this->assertStringContainsString('>Листы ЗП</a>', $html);
    }

    public function test_staff_with_view_but_without_scheme_does_not_see_salary_tabs_on_journal(): void
    {
        $this->grantScheduleView();
        $this->grantPermission('schedule.trainerSalary.view');

        $html = (string) $this->get(route('schedule.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('href="'.route('schedule.trainer-salary').'"', $html);
        $this->assertStringNotContainsString('href="'.route('schedule.trainer-salary-sheets').'"', $html);
        $this->assertStringNotContainsString('>ЗП тренеров</a>', $html);
        $this->assertStringNotContainsString('>Листы ЗП</a>', $html);
    }

    public function test_staff_with_kansas_sees_salary_tabs_on_journal(): void
    {
        $this->grantScheduleView();
        $this->grantTrainerSalaryViewKansas();

        $html = (string) $this->get(route('schedule.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('>ЗП тренеров</a>', $html);
        $this->assertStringContainsString('>Листы ЗП</a>', $html);
    }
}
