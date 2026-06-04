<?php

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\User;
use App\Services\Contracts\ContractTemplateVariablePresets;
use Tests\Feature\Crm\Account\Concerns\InteractsWithAccountContractFill;

class ContractTemplateFillSortOrderFeatureTest extends ContractsFeatureTestCase
{
    use InteractsWithAccountContractFill;

    protected function setUp(): void
    {
        parent::setUp();

        config(['queue.default' => 'sync']);
    }

    /** @test */
    public function template_edit_form_shows_fill_sort_order_column_for_superadmin(): void
    {
        $this->asSuperadmin();

        $template = $this->createContractTemplateWithVersion([], [
            'fields_schema' => [
                [
                    'key'             => 'parent_lastname',
                    'label'           => 'Родитель: фамилия',
                    'required'        => true,
                    'prefill_source'  => null,
                    'fill_sort_order' => 11,
                ],
                [
                    'key'             => 'custom_note',
                    'label'           => 'Примечание',
                    'required'        => false,
                    'prefill_source'  => null,
                    'fill_sort_order' => 350,
                ],
            ],
        ]);

        $this->get(route('contract-templates.index', ['edit' => $template->id]))
            ->assertOk()
            ->assertSee('name="fields[0][fill_sort_order]"', false)
            ->assertSee('name="fields[1][fill_sort_order]"', false)
            ->assertSee('value="11"', false)
            ->assertSee('value="350"', false);
    }

    /** @test */
    public function template_edit_form_hides_fill_sort_order_column_without_permission(): void
    {
        $template = $this->createContractTemplateWithVersion([], [
            'fields_schema' => [
                [
                    'key'             => 'parent_lastname',
                    'label'           => 'Родитель: фамилия',
                    'required'        => true,
                    'prefill_source'  => null,
                    'fill_sort_order' => 11,
                ],
            ],
        ]);

        $this->get(route('contract-templates.index', ['edit' => $template->id]))
            ->assertOk()
            ->assertDontSee('name="fields[0][fill_sort_order]"', false)
            ->assertDontSee('>Порядок<', false);
    }

    /** @test */
    public function update_persists_fill_sort_order_in_fields_schema_for_superadmin(): void
    {
        $this->asSuperadmin();

        $template = $this->createContractTemplateWithVersion([], [
            'fields_schema' => [
                [
                    'key'             => 'parent_phone',
                    'label'           => 'Родитель: телефон',
                    'required'        => true,
                    'prefill_source'  => null,
                    'fill_sort_order' => 20,
                ],
                [
                    'key'             => 'custom_note',
                    'label'           => 'Примечание',
                    'required'        => false,
                    'prefill_source'  => null,
                    'fill_sort_order' => ContractTemplateVariablePresets::FILL_SORT_DEFAULT_CUSTOM,
                ],
            ],
        ]);

        $this->put(route('contract-templates.update', $template), [
            'title'       => $template->title,
            'is_archived' => 0,
            'fields'      => [
                [
                    'key'             => 'parent_phone',
                    'label'           => 'Родитель: телефон',
                    'required'        => 1,
                    'prefill_source'  => '',
                    'fill_sort_order' => 25,
                ],
                [
                    'key'             => 'custom_note',
                    'label'           => 'Примечание',
                    'required'        => 0,
                    'prefill_source'  => '',
                    'fill_sort_order' => 400,
                ],
            ],
        ])->assertRedirect(route('contract-templates.index'));

        $template->refresh()->load('currentVersion');
        $schema = collect($template->currentVersion->fields_schema ?? [])->keyBy('key');

        $this->assertSame(25, $schema['parent_phone']['fill_sort_order'] ?? null);
        $this->assertSame(400, $schema['custom_note']['fill_sort_order'] ?? null);
    }

    /** @test */
    public function update_ignores_fill_sort_order_without_permission(): void
    {
        $template = $this->createContractTemplateWithVersion([], [
            'fields_schema' => [
                [
                    'key'             => 'parent_phone',
                    'label'           => 'Родитель: телефон',
                    'required'        => true,
                    'prefill_source'  => null,
                    'fill_sort_order' => 20,
                ],
                [
                    'key'             => 'custom_note',
                    'label'           => 'Примечание',
                    'required'        => false,
                    'prefill_source'  => null,
                    'fill_sort_order' => 350,
                ],
            ],
        ]);

        $this->put(route('contract-templates.update', $template), [
            'title'       => $template->title,
            'is_archived' => 0,
            'fields'      => [
                [
                    'key'             => 'parent_phone',
                    'label'           => 'Родитель: телефон',
                    'required'        => 1,
                    'prefill_source'  => '',
                    'fill_sort_order' => 999,
                ],
                [
                    'key'             => 'custom_note',
                    'label'           => 'Примечание',
                    'required'        => 0,
                    'prefill_source'  => '',
                    'fill_sort_order' => 888,
                ],
            ],
        ])->assertRedirect(route('contract-templates.index'));

        $template->refresh()->load('currentVersion');
        $schema = collect($template->currentVersion->fields_schema ?? [])->keyBy('key');

        $this->assertSame(20, $schema['parent_phone']['fill_sort_order'] ?? null);
        $this->assertSame(350, $schema['custom_note']['fill_sort_order'] ?? null);
    }

    /** @test */
    public function account_fill_modal_respects_fill_sort_order_from_template_schema(): void
    {
        $template = $this->createContractTemplateWithVersion([], [
            'fields_schema' => [
                [
                    'key'             => 'parent_phone',
                    'label'           => 'Родитель: телефон',
                    'required'        => true,
                    'prefill_source'  => null,
                    'fill_sort_order' => 20,
                ],
                [
                    'key'             => 'parent_lastname',
                    'label'           => 'Родитель: фамилия',
                    'required'        => true,
                    'prefill_source'  => null,
                    'fill_sort_order' => 11,
                ],
                [
                    'key'             => 'custom_note',
                    'label'           => 'Примечание',
                    'required'        => false,
                    'prefill_source'  => null,
                    'fill_sort_order' => 350,
                ],
            ],
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        $contract = Contract::create([
            'school_id'                    => $this->partner->id,
            'user_id'                      => $student->id,
            'group_id'                     => null,
            'creation_mode'                => Contract::CREATION_MODE_TEMPLATE,
            'contract_template_version_id' => $template->currentVersion->id,
            'source_pdf_path'              => null,
            'source_sha256'                => null,
            'status'                       => Contract::STATUS_AWAITING_CLIENT_FILL,
            'fill_expires_at'              => now()->addDays(3),
            'provider'                     => 'podpislon',
        ]);

        $this->actingAs($student)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $html = $this->getContractFillModalHtml($contract);

        $this->assertFillFormFieldOrder($html, [
            'parent_lastname',
            'parent_phone',
            'custom_note',
        ]);
    }
}
