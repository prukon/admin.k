<?php

namespace Tests\Feature\Crm\Contracts;

use App\Models\ContractTemplate;
use App\Services\Contracts\ContractTemplatePrefillSources;
use Illuminate\Support\Facades\Storage;

/**
 * Создание и изменение шаблонов договоров: валидация, DOCX, поля, версии, UI модалок.
 */
class ContractTemplatesStoreUpdateFeatureTest extends ContractsFeatureTestCase
{
    /** @test */
    public function store_requires_title_and_docx(): void
    {
        $this->from(route('contract-templates.index'))
            ->post(route('contract-templates.store'), [])
            ->assertSessionHasErrors(['title', 'docx'])
            ->assertRedirect(route('contract-templates.index'));

        $this->from(route('contract-templates.index'))
            ->post(route('contract-templates.store'), [
                'title' => 'Без файла',
            ])
            ->assertSessionHasErrors('docx');
    }

    /** @test */
    public function store_rejects_docx_without_placeholders(): void
    {
        $this->from(route('contract-templates.index'))
            ->post(route('contract-templates.store'), [
                'title' => 'Пустой DOCX',
                'docx'  => $this->fakeDocxUploadedFile([]),
            ])
            ->assertSessionHasErrors('docx')
            ->assertRedirect(route('contract-templates.index'));
    }

    /** @test */
    public function store_parses_multiple_placeholders_with_preset_labels(): void
    {
        $this->post(route('contract-templates.store'), [
            'title' => 'Мультиполя',
            'docx'  => $this->fakeDocxUploadedFile([
                'parent_lastname',
                'parent_phone',
                'custom_note',
            ]),
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('contract-templates.index'));

        $template = ContractTemplate::query()
            ->where('partner_id', $this->partner->id)
            ->where('title', 'Мультиполя')
            ->firstOrFail();

        $schema = collect($template->currentVersion->fields_schema ?? [])->keyBy('key');

        $this->assertTrue($schema->has('parent_lastname'));
        $this->assertTrue($schema->has('parent_phone'));
        $this->assertTrue($schema->has('custom_note'));
        $this->assertSame('Родитель: фамилия', $schema['parent_lastname']['label'] ?? null);
        $this->assertSame('Родитель: телефон', $schema['parent_phone']['label'] ?? null);
        $this->assertSame(1, (int) $template->currentVersion->version);
    }

    /** @test */
    public function store_applies_fields_override_on_create(): void
    {
        $this->post(route('contract-templates.store'), [
            'title'  => 'С переопределением полей',
            'docx'   => $this->fakeDocxUploadedFile(['parent_full_name']),
            'fields' => [
                [
                    'key'            => 'parent_full_name',
                    'label'          => 'ФИО законного представителя',
                    'required'       => 0,
                    'prefill_source' => ContractTemplatePrefillSources::PARENT_FULL_NAME,
                ],
            ],
        ])->assertSessionHasNoErrors();

        $template = ContractTemplate::query()
            ->where('title', 'С переопределением полей')
            ->firstOrFail();

        $field = $template->currentVersion->fields_schema[0] ?? [];
        $this->assertSame('ФИО законного представителя', $field['label'] ?? null);
        $this->assertFalse((bool) ($field['required'] ?? true));
        $this->assertSame(ContractTemplatePrefillSources::PARENT_FULL_NAME, $field['prefill_source'] ?? null);
    }

    /** @test */
    public function store_validation_rejects_invalid_prefill_source(): void
    {
        $this->from(route('contract-templates.index'))
            ->post(route('contract-templates.store'), [
                'title'  => 'Невалидный prefill',
                'docx'   => $this->fakeDocxUploadedFile(['parent_full_name']),
                'fields' => [
                    [
                        'key'            => 'parent_full_name',
                        'label'          => 'ФИО',
                        'prefill_source' => 'unknown_source',
                    ],
                ],
            ])
            ->assertSessionHasErrors('fields.0.prefill_source');
    }

    /** @test */
    public function update_persists_prefill_source_and_required_flags(): void
    {
        $template = $this->createContractTemplateWithVersion();

        $this->put(route('contract-templates.update', $template), [
            'title'  => $template->title,
            'fields' => [
                [
                    'key'            => 'parent_full_name',
                    'label'          => 'ФИО родителя',
                    'required'       => 1,
                    'prefill_source' => ContractTemplatePrefillSources::PARENT_FULL_NAME,
                ],
            ],
        ])->assertRedirect(route('contract-templates.index'));

        $field = $template->fresh()->currentVersion->fields_schema[0] ?? [];
        $this->assertSame('ФИО родителя', $field['label'] ?? null);
        $this->assertTrue((bool) ($field['required'] ?? false));
        $this->assertSame(ContractTemplatePrefillSources::PARENT_FULL_NAME, $field['prefill_source'] ?? null);
    }

    /** @test */
    public function update_ignores_unknown_field_keys_in_request(): void
    {
        $template = $this->createContractTemplateWithVersion();

        $this->put(route('contract-templates.update', $template), [
            'title'  => $template->title,
            'fields' => [
                [
                    'key'      => 'parent_full_name',
                    'label'    => 'ФИО',
                    'required' => 1,
                ],
                [
                    'key'      => 'phantom_field',
                    'label'    => 'Не должно сохраниться',
                    'required' => 1,
                ],
            ],
        ])->assertRedirect();

        $keys = array_column($template->fresh()->currentVersion->fields_schema ?? [], 'key');
        $this->assertSame(['parent_full_name'], $keys);
    }

    /** @test */
    public function update_system_field_stays_not_required_after_save(): void
    {
        $template = $this->createContractTemplateWithVersion();

        $this->put(route('contract-templates.update', $template), [
            'title' => $template->title,
            'docx'  => $this->fakeDocxUploadedFile(['parent_full_name', 'contract_date']),
        ])->assertRedirect();

        $this->put(route('contract-templates.update', $template), [
            'title'  => $template->title,
            'fields' => [
                [
                    'key'            => 'parent_full_name',
                    'label'          => 'ФИО',
                    'required'       => 1,
                    'prefill_source' => '',
                ],
                [
                    'key'      => 'contract_date',
                    'label'    => 'Дата',
                    'required' => 1,
                ],
            ],
        ])->assertRedirect();

        $schema = collect($template->fresh()->currentVersion->fields_schema ?? [])->keyBy('key');
        $this->assertTrue((bool) ($schema['parent_full_name']['required'] ?? false));
        $this->assertFalse((bool) ($schema['contract_date']['required'] ?? true));
        $this->assertEmpty($schema['contract_date']['prefill_source'] ?? null);
    }

    /** @test */
    public function update_unarchives_template_when_is_archived_zero(): void
    {
        $template = $this->createContractTemplateWithVersion(['is_archived' => true]);

        $this->put(route('contract-templates.update', $template), [
            'title'       => $template->title,
            'is_archived' => 0,
            'fields'      => [
                [
                    'key'      => 'parent_full_name',
                    'label'    => 'ФИО',
                    'required' => 1,
                ],
            ],
        ])->assertRedirect();

        $this->assertFalse($template->fresh()->is_archived);
    }

    /** @test */
    public function update_with_new_docx_preserves_previous_field_labels_for_matching_keys(): void
    {
        $template = $this->createContractTemplateWithVersion();

        $this->put(route('contract-templates.update', $template), [
            'title'  => $template->title,
            'fields' => [
                [
                    'key'      => 'parent_full_name',
                    'label'    => 'Кастомное ФИО',
                    'required' => 1,
                ],
            ],
        ])->assertRedirect();

        $oldVersionId = $template->fresh()->current_version_id;

        $this->put(route('contract-templates.update', $template), [
            'title' => $template->title,
            'docx'  => $this->fakeDocxUploadedFile(['parent_full_name', 'passport']),
        ])->assertRedirect();

        $template->refresh()->load('currentVersion');
        $this->assertNotSame($oldVersionId, $template->current_version_id);

        $schema = collect($template->currentVersion->fields_schema ?? [])->keyBy('key');
        $this->assertSame('Кастомное ФИО', $schema['parent_full_name']['label'] ?? null);
        $this->assertTrue($schema->has('passport'));
    }

    /** @test */
    public function update_validation_rejects_invalid_prefill_source(): void
    {
        $template = $this->createContractTemplateWithVersion();

        $this->from(route('contract-templates.index', ['edit' => $template->id]))
            ->put(route('contract-templates.update', $template), [
                'title'  => $template->title,
                'fields' => [
                    [
                        'key'            => 'parent_full_name',
                        'label'          => 'ФИО',
                        'prefill_source' => 'invalid_key',
                    ],
                ],
            ])
            ->assertSessionHasErrors('fields.0.prefill_source')
            ->assertRedirect(route('contract-templates.index', ['edit' => $template->id]));
    }

    /** @test */
    public function edit_modal_renders_fields_table_and_docx_panel(): void
    {
        $template = $this->createContractTemplateWithVersion([], [
            'fields_schema' => [
                [
                    'key'      => 'parent_lastname',
                    'label'    => 'Фамилия',
                    'required' => true,
                ],
                [
                    'key'      => 'parent_phone',
                    'label'    => 'Телефон',
                    'required' => true,
                ],
            ],
        ]);

        $this->get(route('contract-templates.index', ['edit' => $template->id]))
            ->assertOk()
            ->assertSee('id="contract-template-fields-table"', false)
            ->assertSee('name="fields[0][key]"', false)
            ->assertSee('name="fields[0][label]"', false)
            ->assertSee('name="fields[0][required]"', false)
            ->assertSee('contract-template-docx-update-panel', false)
            ->assertSee(route('contract-templates.download-docx', $template), false)
            ->assertSee('parent_lastname', false)
            ->assertSee('parent_phone', false);
    }

    /** @test */
    public function edit_foreign_template_on_index_returns_404(): void
    {
        $foreign = $this->createContractTemplateWithVersion([
            'partner_id' => $this->foreignPartner->id,
        ]);

        $this->get(route('contract-templates.index', ['edit' => $foreign->id]))
            ->assertStatus(404);
    }

    /** @test */
    public function download_docx_returns_error_when_file_missing(): void
    {
        Storage::fake();

        $template = $this->createContractTemplateWithVersion([], [
            'docx_path' => 'contract-templates/missing.docx',
        ]);

        $this->from(route('contract-templates.index', ['edit' => $template->id]))
            ->get(route('contract-templates.download-docx', $template))
            ->assertRedirect()
            ->assertSessionHasErrors('docx');
    }

    /** @test */
    public function show_email_returns_422_when_template_has_no_version(): void
    {
        $template = ContractTemplate::create([
            'partner_id'         => $this->partner->id,
            'title'              => 'Без версии',
            'is_archived'        => false,
            'current_version_id' => null,
        ]);

        $this->getJson(route('contract-templates.email.show', $template))
            ->assertStatus(422)
            ->assertJsonPath('message', 'У шаблона нет активной версии.');
    }

    /** @test */
    public function update_email_returns_422_when_template_has_no_version(): void
    {
        $template = ContractTemplate::create([
            'partner_id'         => $this->partner->id,
            'title'              => 'Без версии для письма',
            'is_archived'        => false,
            'current_version_id' => null,
        ]);

        $this->putJson(route('contract-templates.update-email', $template), [
            'email_subject'   => 'Тема',
            'email_body_html' => '<p>Текст</p>',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function store_persists_docx_in_storage_and_sha256_on_version(): void
    {
        $this->post(route('contract-templates.store'), [
            'title' => 'Файл в storage',
            'docx'  => $this->fakeDocxUploadedFile(['parent_full_name']),
        ])->assertSessionHasNoErrors();

        $version = ContractTemplate::query()
            ->where('title', 'Файл в storage')
            ->firstOrFail()
            ->currentVersion;

        $this->assertNotEmpty($version->docx_path);
        $this->assertTrue(Storage::exists($version->docx_path));
        $this->assertSame(64, strlen((string) $version->docx_sha256));
    }

    /** @test */
    public function update_without_docx_keeps_same_version_id(): void
    {
        $template = $this->createContractTemplateWithVersion();
        $versionId = $template->current_version_id;

        $this->put(route('contract-templates.update', $template), [
            'title'  => 'Только название',
            'fields' => [
                [
                    'key'      => 'parent_full_name',
                    'label'    => 'ФИО',
                    'required' => 1,
                ],
            ],
        ])->assertRedirect();

        $template->refresh();
        $this->assertSame($versionId, $template->current_version_id);
        $this->assertSame('Только название', $template->title);
    }

    /** @test */
    public function create_modal_on_index_shows_docx_upload_and_variables_reference(): void
    {
        $this->get(route('contract-templates.index', ['create' => 1]))
            ->assertOk()
            ->assertSee('name="docx"', false)
            ->assertSee('name="title"', false)
            ->assertSee('contract-template-variables-reference', false)
            ->assertSee('accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document"', false);
    }

    /** @test */
    public function index_with_email_query_opens_email_modal_context(): void
    {
        $template = $this->createContractTemplateWithVersion();

        $this->get(route('contract-templates.index', ['email' => $template->id]))
            ->assertOk()
            ->assertViewHas('openEmailTemplateId', $template->id)
            ->assertSee('id="editTemplateEmailModal"', false);
    }

    /** @test */
    public function index_with_unknown_email_template_returns_404(): void
    {
        $foreign = $this->createContractTemplateWithVersion([
            'partner_id' => $this->foreignPartner->id,
        ]);

        $this->get(route('contract-templates.index', ['email' => $foreign->id]))
            ->assertStatus(404);
    }
}
