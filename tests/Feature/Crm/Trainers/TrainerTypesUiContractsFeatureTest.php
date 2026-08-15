<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Trainers;

use App\Models\Team;
use App\Models\TrainerType;
use Tests\Feature\Crm\Schedule\ScheduleTrainerSalaryTestCase;

/**
 * P1: UX-контракты типов тренера — первое открытие, видимость, selected/disabled,
 * дефолт системного типа, негатив без Канзаса.
 */
final class TrainerTypesUiContractsFeatureTest extends ScheduleTrainerSalaryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantPermission('trainers.view');
    }

    public function test_kansas_trainers_page_first_open_selects_system_type_and_hides_classic_salary_inputs(): void
    {
        $this->grantTrainerSalaryViewKansas();
        $this->grantTrainerSalaryManage();

        $system = TrainerType::query()
            ->where('partner_id', $this->partner->id)
            ->where('is_system', true)
            ->firstOrFail();
        $disabled = TrainerType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Выключенный стажёр',
            'is_enabled' => false,
        ]);

        $html = (string) $this->get(route('admin.trainers.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-bs-target="#trainerTypesModal"', $html);
        $this->assertStringContainsString('>Типы</', $html);
        $this->assertStringContainsString('id="trainerTypesModal"', $html);
        $this->assertStringContainsString('id="trainer-types-add-btn"', $html);
        $this->assertStringContainsString('class="modal-dialog"', $html);
        $this->assertStringNotContainsString('modal-dialog modal-xl', $html);
        $this->assertStringNotContainsString('modal-fullscreen', $html);
        $this->assertStringContainsString('data-error-for="trainer_type_id"', $html);
        $this->assertStringContainsString('data-error-for="name"', $html);
        $this->assertStringContainsString('data-error-for="rate_per_training"', $html);
        $this->assertStringContainsString('data-error-for="base_premium"', $html);
        $this->assertStringContainsString('/js/trainer-types.js', $html);

        $createSelect = $this->markupById($html, 'trainer-create-type');
        $this->assertStringNotContainsString('disabled', $createSelect);
        $this->assertMatchesRegularExpression(
            '/value="'.$system->id.'"[^>]*\bselected\b|\bselected\b[^>]*value="'.$system->id.'"/',
            $createSelect
        );
        $this->assertStringNotContainsString('value="'.$disabled->id.'"', $createSelect);

        $editSelect = $this->markupById($html, 'trainer-edit-type');
        $this->assertStringNotContainsString('disabled', $editSelect);
        $this->assertStringNotContainsString('value="'.$disabled->id.'"', $editSelect);

        $this->assertStringContainsString('disabled', $this->markupById($html, 'trainer-create-default-base-salary'));
        $this->assertStringContainsString('disabled', $this->markupById($html, 'trainer-create-default-rate'));
        $this->assertStringContainsString('js-trainer-type-fields "', $html);
        $this->assertStringContainsString('js-trainer-classic-salary-fields d-none', $html);

        $this->assertLessThan(
            strpos($html, 'name="rate_per_training"') ?: PHP_INT_MAX,
            strpos($html, 'id="trainer-type-name"') ?: PHP_INT_MAX,
            'В модалке типов название должно идти до оклада'
        );
        $this->assertLessThan(
            strpos($html, 'name="base_premium"') ?: PHP_INT_MAX,
            strpos($html, 'name="rate_per_training"') ?: PHP_INT_MAX,
            'В модалке типов оклад должен идти до базовой премии'
        );
    }

    public function test_kansas_trainers_page_without_salary_manage_shows_types_modal_but_hides_add_button(): void
    {
        $this->grantTrainerSalaryViewKansas();

        $html = (string) $this->get(route('admin.trainers.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="trainerTypesModal"', $html);
        $this->assertStringContainsString('data-bs-target="#trainerTypesModal"', $html);
        $this->assertStringNotContainsString('id="trainer-types-add-btn"', $html);
        $this->assertStringContainsString('canManage: false', $html);
    }

    public function test_classic_trainers_page_does_not_impose_types_ui_and_keeps_salary_fields_editable(): void
    {
        $html = (string) $this->get(route('admin.trainers.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('id="trainerTypesModal"', $html);
        $this->assertStringNotContainsString('id="trainer-types-add-btn"', $html);
        $this->assertStringNotContainsString('/js/trainer-types.js', $html);
        $this->assertStringContainsString('js-trainer-type-fields d-none', $html);

        $createSelect = $this->markupById($html, 'trainer-create-type');
        $this->assertStringContainsString('disabled', $createSelect);

        $this->assertStringNotContainsString('disabled', $this->markupById($html, 'trainer-create-default-base-salary'));
        $this->assertStringNotContainsString('disabled', $this->markupById($html, 'trainer-create-default-rate'));
        $this->assertStringNotContainsString('disabled', $this->markupById($html, 'trainer-edit-default-base-salary'));
    }

    public function test_kansas_salary_manage_page_shows_types_button_and_standard_modal(): void
    {
        $this->grantTrainerSalaryViewKansas();
        $this->grantTrainerSalaryManage();
        $this->makeTrainerProfile('Кнопка типов manage');

        $html = (string) $this->get(route('schedule.trainer-salary', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Типы тренеров', $html);
        $this->assertStringContainsString('data-bs-target="#trainerTypesModal"', $html);
        $this->assertStringContainsString('id="trainerTypesModal"', $html);
        $this->assertStringContainsString('id="trainer-types-add-btn"', $html);
        $this->assertStringContainsString('/js/trainer-types.js', $html);
        $this->assertStringContainsString('reason === \'open\'', $html);
        $this->assertStringContainsString('class="modal-dialog"', $html);
        $this->assertStringNotContainsString('modal-dialog modal-xl', $html);
        $this->assertStringNotContainsString('modal-fullscreen', $html);
    }

    public function test_kansas_salary_viewer_does_not_see_types_button_or_modal(): void
    {
        $this->grantTrainerSalaryViewKansas();
        $this->makeTrainerProfile('Кнопка типов view');

        $html = (string) $this->get(route('schedule.trainer-salary', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Типы тренеров', $html);
        $this->assertStringNotContainsString('id="trainerTypesModal"', $html);
        $this->assertStringNotContainsString('/js/trainer-types.js', $html);
        $this->assertStringNotContainsString('id="trainer-types-add-btn"', $html);
    }

    public function test_classic_salary_page_does_not_show_types_ui(): void
    {
        $this->grantTrainerSalaryView();
        $this->grantTrainerSalaryManage();
        $this->makeTrainerProfile('Classic без типов');

        $html = (string) $this->get(route('schedule.trainer-salary', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-scheme-code="classic"', $html);
        $this->assertStringNotContainsString('Типы тренеров', $html);
        $this->assertStringNotContainsString('id="trainerTypesModal"', $html);
    }

    public function test_kansas_salary_table_renders_type_rates_readonly_without_inputs(): void
    {
        $this->grantTrainerSalaryViewKansas();
        $this->grantTrainerSalaryManage();
        $trainer = $this->makeTrainerProfile('Readonly ставки');
        $this->setTrainerTypeRates($trainer, 400, 50);
        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа readonly']);
        $student = $this->makeStudent($team->id);
        $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-05-07');

        $page = (string) $this->get(route('schedule.trainer-salary', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString('data-field="rate_per_training"', $page);
        $this->assertStringNotContainsString('data-field="base_premium"', $page);
        $this->assertStringContainsString('trainer-salary-readonly', $page);

        $row = collect(
            $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
                ->assertOk()
                ->json('rows')
        )->firstWhere('trainer_profile_id', $trainer->id);

        $this->assertSame('400.00', $row['rate_per_training']);
        $this->assertSame('50.00', $row['base_premium']);
        $tableHtml = (string) $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->json('table_html');
        $this->assertStringNotContainsString('data-field="rate_per_training"', $tableHtml);
        $this->assertStringNotContainsString('name="rate_per_training"', $tableHtml);
        $this->assertStringContainsString('trainer-salary-readonly', $tableHtml);
    }

    public function test_reopening_trainers_page_keeps_system_type_selected_and_does_not_add_empty_option(): void
    {
        $this->grantTrainerSalaryViewKansas();

        $systemId = (int) TrainerType::query()
            ->where('partner_id', $this->partner->id)
            ->where('is_system', true)
            ->value('id');

        $first = $this->markupById(
            (string) $this->get(route('admin.trainers.index'))->assertOk()->getContent(),
            'trainer-create-type'
        );
        $second = $this->markupById(
            (string) $this->get(route('admin.trainers.index'))->assertOk()->getContent(),
            'trainer-create-type'
        );

        $this->assertMatchesRegularExpression(
            '/value="'.$systemId.'"[^>]*\bselected\b|\bselected\b[^>]*value="'.$systemId.'"/',
            $first
        );
        $this->assertMatchesRegularExpression(
            '/value="'.$systemId.'"[^>]*\bselected\b|\bselected\b[^>]*value="'.$systemId.'"/',
            $second
        );
        $this->assertStringNotContainsString('value=""', $first);
        $this->assertStringNotContainsString('value=""', $second);
    }

    private function markupById(string $html, string $id): string
    {
        $quoted = preg_quote($id, '/');
        if (preg_match('/<select\b[^>]*\bid="'.$quoted.'"[^>]*>.*?<\/select>/s', $html, $match) === 1) {
            return $match[0];
        }
        if (preg_match('/<input\b[^>]*\bid="'.$quoted.'"[^>]*>/s', $html, $match) === 1) {
            return $match[0];
        }
        if (preg_match('/<button\b[^>]*\bid="'.$quoted.'"[^>]*>.*?<\/button>/s', $html, $match) === 1) {
            return $match[0];
        }

        $this->fail('На странице нет элемента #'.$id);
    }
}
