<?php

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\User;

/**
 * UI выбора абонемента: модалка создания, карточка, superadmin через Gate::before.
 */
class ContractLessonPackageBindUiFeatureTest extends ContractsFeatureTestCase
{
    /** @test */
    public function create_modal_hides_package_field_without_bind_permission(): void
    {
        $this->get(route('contracts.index'))
            ->assertOk()
            ->assertDontSee('id="lesson_package_id"', false)
            ->assertDontSee('id="block-lesson-package"', false)
            ->assertSee('const canBindLessonPackage = false', false);
    }

    /** @test */
    public function create_modal_shows_package_field_with_bind_permission(): void
    {
        $this->grantLessonPackageBindPermission();
        $template = $this->createContractTemplateWithVersion(['title' => 'С абонементом'], [
            'fields_schema' => [
                ['key' => 'parent_full_name', 'label' => 'ФИО', 'required' => true],
                ['key' => 'package_name', 'label' => 'Абонемент', 'required' => false],
            ],
        ]);

        $html = $this->get(route('contracts.index'))
            ->assertOk()
            ->assertSee('id="lesson_package_id"', false)
            ->assertSee('id="block-lesson-package"', false)
            ->assertSee('const canBindLessonPackage = true', false)
            ->assertSee('selectedTemplateRequiresLessonPackage', false)
            ->assertSee('fetchAndApplyStudentPackages', false)
            ->assertSee('item.label || item.name', false)
            ->assertSee('Изменить шаблон договора, ученика и абонемент после создания договора будет нельзя', false)
            ->assertSee('Изменить файл, ученика и абонемент после создания договора будет нельзя', false)
            ->getContent();

        $this->assertStringContainsString(
            'data-requires-lesson-package="1"',
            $html
        );
        $this->assertStringContainsString((string) $template->id, $html);
        $this->assertStringContainsString('user-packages', $html);
        $this->assertStringContainsString('userPackagesUrl', $html);
    }

    /** @test */
    public function superadmin_sees_package_field_without_role_grant(): void
    {
        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->get(route('contracts.index'))
            ->assertOk()
            ->assertSee('id="lesson_package_id"', false)
            ->assertSee('const canBindLessonPackage = true', false);
    }

    /** @test */
    public function show_card_hides_package_row_without_bind_permission(): void
    {
        $contract = $this->makeShowContract('Скрытый абонемент');

        $html = $this->get(route('contracts.show', $contract))->assertOk()->getContent();
        $this->assertStringNotContainsString('<dt class="col-sm-4">Абонемент</dt>', $html);
        $this->assertStringNotContainsString('Скрытый абонемент', $html);
    }

    /** @test */
    public function show_card_renders_snapshot_name_with_bind_permission(): void
    {
        $this->grantLessonPackageBindPermission();
        $contract = $this->makeShowContract('Зафиксированный');

        $this->get(route('contracts.show', $contract))
            ->assertOk()
            ->assertSee('<dt class="col-sm-4">Абонемент</dt>', false)
            ->assertSee('Зафиксированный', false);
    }

    private function makeShowContract(string $packageName): Contract
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        return Contract::create([
            'school_id'         => $this->partner->id,
            'user_id'           => $student->id,
            'group_id'          => null,
            'source_pdf_path'   => 'documents/bind-ui.pdf',
            'source_sha256'     => str_repeat('b', 64),
            'provider'          => 'podpislon',
            'status'            => Contract::STATUS_DRAFT,
            'creation_mode'     => Contract::CREATION_MODE_PDF,
            'package_snapshot'  => ['name' => $packageName, 'price_cents' => 1000],
        ]);
    }
}
