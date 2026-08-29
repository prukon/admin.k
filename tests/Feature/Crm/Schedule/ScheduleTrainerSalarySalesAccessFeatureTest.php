<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\TrainerSalarySnapshot;
use App\Models\User;

/**
 * Схема sales: HTTP-доступ ко всем endpoint'ам ЗП и листов (гость, 403, 401, 200).
 */
final class ScheduleTrainerSalarySalesAccessFeatureTest extends ScheduleTrainerSalaryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
    }

    public function test_guest_is_redirected_from_pages_and_unauthorized_on_json(): void
    {
        $trainer = $this->makeTrainerProfile('Sales гость');
        auth()->logout();

        $this->get(route('schedule.trainer-salary'))->assertRedirect();
        $this->get(route('schedule.trainer-salary-sheets'))->assertRedirect();
        $this->get(route('schedule.trainer-salary-sheets.snapshot.show', ['snapshot' => 1]))->assertRedirect();
        $this->get(route('schedule.trainer-salary-sheets.batch.show', ['batchId' => 'x']))->assertRedirect();

        $this->get(route('schedule.trainer-salary.data'))->assertRedirect();
        $this->getJson(route('schedule.trainer-salary.data'))->assertUnauthorized();
        $this->getJson(route('schedule.trainer-salary-sheets.data'))->assertUnauthorized();
        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'sales_percent' => 1,
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
        $trainer = $this->makeTrainerProfile('Sales без view');

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
            'sales_percent' => 1,
        ])->assertForbidden();
    }

    public function test_staff_with_view_and_manage_but_without_sales_scheme_gets_403(): void
    {
        $this->grantPermission('schedule.trainerSalary.view');
        $this->grantPermission('schedule.trainerSalary.manage');
        $trainer = $this->makeTrainerProfile('Sales без схемы');

        $this->get(route('schedule.trainer-salary'))->assertForbidden();
        $this->getJson(route('schedule.trainer-salary.data'))->assertForbidden();
        $this->get(route('schedule.trainer-salary-sheets'))->assertForbidden();
        $this->getJson(route('schedule.trainer-salary-sheets.data'))->assertForbidden();
        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'sales_percent' => 10,
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

    public function test_staff_with_only_sales_scheme_and_no_view_gets_403(): void
    {
        $this->grantSalesScheme();
        $trainer = $this->makeTrainerProfile('Только схема sales');

        $this->get(route('schedule.trainer-salary'))->assertForbidden();
        $this->getJson(route('schedule.trainer-salary.data'))->assertForbidden();
        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'sales_percent' => 1,
        ])->assertForbidden();
    }

    public function test_viewer_with_sales_can_open_pages_but_cannot_save_or_form_snapshots(): void
    {
        $this->grantTrainerSalaryViewSales();
        $trainer = $this->makeTrainerProfile('Sales просмотр');

        $this->get(route('schedule.trainer-salary', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertSee('ЗП тренеров', false)
            ->assertSee('data-scheme-code="sales"', false)
            ->assertSee('data-can-manage="0"', false)
            ->assertDontSee('id="trainer-salary-form-all-btn"', false);

        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertJsonPath('scheme_code', 'sales')
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
            'sales_percent' => 10,
        ])->assertForbidden();

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'base_salary' => 1000,
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

    public function test_manager_with_sales_and_without_view_cannot_open_pages(): void
    {
        $this->grantPermission('schedule.trainerSalary.manage');
        $this->grantSalesScheme();

        $this->get(route('schedule.trainer-salary'))->assertForbidden();
        $this->getJson(route('schedule.trainer-salary.data'))->assertForbidden();
        $this->get(route('schedule.trainer-salary-sheets'))->assertForbidden();
    }

    public function test_manager_with_sales_can_use_every_salary_endpoint_without_500(): void
    {
        $this->grantTrainerSalaryViewSales();
        $this->grantTrainerSalaryManage();

        $trainer = $this->makeTrainerProfile('Sales smoke');

        $page = $this->get(route('schedule.trainer-salary', ['year' => 2026, 'month' => 5]));
        $this->assertNotSame(500, $page->getStatusCode());
        $page->assertOk()
            ->assertSee('trainer-salary-app', false)
            ->assertSee('data-scheme-code="sales"', false);

        $data = $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]));
        $this->assertNotSame(500, $data->getStatusCode());
        $data->assertOk()
            ->assertJsonPath('scheme_code', 'sales')
            ->assertJsonPath('can_manage', true)
            ->assertJsonPath('year', 2026)
            ->assertJsonPath('month', 5)
            ->assertJsonPath('show_trainer_types', false)
            ->assertJsonStructure([
                'year',
                'month',
                'month_label',
                'date_from',
                'date_to',
                'scheme_code',
                'draft_subtitle',
                'draft_view_data',
                'table_html',
                'month_settings_html',
                'rows',
                'can_manage',
            ]);
        $this->assertNotSame('', trim((string) $data->json('draft_subtitle')));
        $this->assertSame('', (string) $data->json('month_settings_html'));
        $this->assertNotSame('', trim((string) $data->json('table_html')));

        $patch = $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'sales_percent' => 15,
        ]);
        $this->assertNotSame(500, $patch->getStatusCode());
        $patch->assertOk()
            ->assertJsonPath('message', 'Черновик сохранён')
            ->assertJsonPath('row.sales_percent', 15);
        $this->assertArrayNotHasKey('reload_table', $patch->json());
        $this->assertArrayNotHasKey('table_html', $patch->json());
        $this->assertNotSame('', trim((string) $patch->getContent()));

        $formOne = $this->postJson(route('schedule.trainer-salary.snapshots.form-one', $trainer), [
            'year' => 2026,
            'month' => 5,
        ]);
        $this->assertNotSame(500, $formOne->getStatusCode());
        $formOne->assertOk()
            ->assertJsonPath('snapshot.scheme_code', 'sales')
            ->assertJsonPath('snapshot.version', 1);
        $this->assertArrayNotHasKey('reload_table', $formOne->json());
        $this->assertStringContainsString('Слепок ЗП сформирован', (string) $formOne->json('message'));

        $formAll = $this->postJson(route('schedule.trainer-salary.snapshots.form-all'), [
            'year' => 2026,
            'month' => 5,
        ]);
        $this->assertNotSame(500, $formAll->getStatusCode());
        $formAll->assertOk()
            ->assertJsonStructure(['message', 'batch_id', 'snapshots_count', 'rows']);
        $this->assertArrayNotHasKey('reload_table', $formAll->json());
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

        $sheet = $this->get(route('schedule.trainer-salary-sheets.snapshot.show', ['snapshot' => $snapshotId]));
        $this->assertNotSame(500, $sheet->getStatusCode());
        $sheet->assertOk()
            ->assertSee('Sales smoke', false)
            ->assertSee('trainer-salary-table--readonly', false)
            ->assertDontSee('trainer-salary-input', false)
            ->assertDontSee('trainer-salary-form-one-btn', false);

        $batchId = (string) $formAll->json('batch_id');
        $batch = $this->get(route('schedule.trainer-salary-sheets.batch.show', ['batchId' => $batchId]));
        $this->assertNotSame(500, $batch->getStatusCode());
        $batch->assertOk()
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
        $this->assertStringContainsString('data-scheme-code="classic"', $html);
        $this->assertStringNotContainsString('data-scheme-code="sales"', $html);
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

    public function test_staff_with_sales_sees_salary_tabs_on_journal(): void
    {
        $this->grantScheduleView();
        $this->grantTrainerSalaryViewSales();

        $html = (string) $this->get(route('schedule.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('>ЗП тренеров</a>', $html);
        $this->assertStringContainsString('>Листы ЗП</a>', $html);
    }
}
