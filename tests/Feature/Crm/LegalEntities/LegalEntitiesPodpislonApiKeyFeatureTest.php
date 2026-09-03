<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LegalEntities;

use App\Enums\PartnerLegalEntityBusinessType;
use App\Models\PartnerLegalEntity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * API-ключ Подпислона на юр. лице: только superadmin, шифрование, не отдаём plaintext.
 *
 * @see /docs/documentation/admin-legal-entities.html §4.2.3
 */
final class LegalEntitiesPodpislonApiKeyFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->asAdmin();
        $this->grantPermissions(['legal_entities.view', 'legal_entities.manage']);
    }

    /** @param list<string> $permissions */
    private function grantPermissions(array $permissions): void
    {
        foreach ($permissions as $permission) {
            DB::table('permission_role')->insertOrIgnore([
                'partner_id' => $this->partner->id,
                'role_id' => $this->user->role_id,
                'permission_id' => $this->permissionId($permission),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_school_admin_index_does_not_show_podpislon_api_key_field(): void
    {
        $this->get(route('admin.legal-entities.index'))
            ->assertOk()
            ->assertDontSee('API-ключ Подпислона', false);
    }

    public function test_superadmin_index_shows_podpislon_api_key_field_with_error_slot(): void
    {
        $this->asSuperadmin();

        $html = $this->get(route('admin.legal-entities.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="podpislon_api_key"', $html);
        $this->assertStringContainsString('API-ключ Подпислона', $html);
        $this->assertStringContainsString('data-error-for="podpislon_api_key"', $html);
        $this->assertStringContainsString('js-podpislon-api-key-set-hint', $html);
    }

    public function test_school_admin_store_ignores_posted_api_key(): void
    {
        $this->postJson(route('admin.legal-entities.store'), [
            'business_type' => 'IP',
            'organization_name' => 'ИП Без ключа',
            'tax_id' => '123456789016',
            'podpislon_api_key' => 'stolen-key-should-be-ignored',
            'is_enabled' => 1,
        ])
            ->assertOk()
            ->assertJsonMissingPath('legal_entity.podpislon_api_key');

        $entity = PartnerLegalEntity::query()
            ->where('partner_id', $this->partner->id)
            ->where('tax_id', '123456789016')
            ->first();

        $this->assertNotNull($entity);
        $this->assertFalse($entity->hasPodpislonApiKey());
    }

    public function test_superadmin_store_persists_encrypted_key_and_json_hides_it(): void
    {
        $this->asSuperadmin();

        $response = $this->postJson(route('admin.legal-entities.store'), [
            'business_type' => 'IP',
            'organization_name' => 'ИП С ключом',
            'tax_id' => '123456789017',
            'podpislon_api_key' => 'partner-company-api-key-1',
            'is_enabled' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Юр. лицо создано')
            ->assertJsonMissingPath('legal_entity.podpislon_api_key');

        $entity = PartnerLegalEntity::query()
            ->where('partner_id', $this->partner->id)
            ->where('tax_id', '123456789017')
            ->first();

        $this->assertNotNull($entity);
        $this->assertTrue($entity->hasPodpislonApiKey());
        $this->assertSame('partner-company-api-key-1', $entity->podpislon_api_key);

        $raw = DB::table('partner_legal_entities')->where('id', $entity->id)->value('podpislon_api_key');
        $this->assertNotSame('partner-company-api-key-1', $raw);
        $this->assertNotEmpty($raw);
    }

    public function test_superadmin_show_json_has_set_flag_but_not_plaintext_key(): void
    {
        $this->asSuperadmin();

        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'organization_name' => 'ИП Show Key',
            'podpislon_api_key' => 'secret-show-key',
        ]);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('admin.legal-entities.show', $entity))
            ->assertOk()
            ->assertJsonPath('podpislon_api_key_set', true)
            ->assertJsonMissingPath('podpislon_api_key');
    }

    public function test_school_admin_show_json_omits_key_flag(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'organization_name' => 'ИП School Show',
            'podpislon_api_key' => 'secret-school-must-not-see',
        ]);

        $json = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('admin.legal-entities.show', $entity))
            ->assertOk()
            ->json();

        $this->assertArrayNotHasKey('podpislon_api_key', $json);
        $this->assertArrayNotHasKey('podpislon_api_key_set', $json);
    }

    public function test_school_admin_update_cannot_change_existing_key(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'business_type' => PartnerLegalEntityBusinessType::IP,
            'organization_name' => 'ИП Нельзя менять ключ',
            'podpislon_api_key' => 'original-key',
        ]);

        $this->putJson(route('admin.legal-entities.update', $entity), [
            'business_type' => 'IP',
            'organization_name' => 'ИП Нельзя менять ключ',
            'podpislon_api_key' => 'hijacked-key',
            'is_enabled' => 1,
        ])->assertOk();

        $this->assertSame('original-key', $entity->fresh()->podpislon_api_key);
    }

    public function test_superadmin_empty_update_keeps_existing_key(): void
    {
        $this->asSuperadmin();

        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'business_type' => PartnerLegalEntityBusinessType::IP,
            'organization_name' => 'ИП Keep Key',
            'podpislon_api_key' => 'keep-me',
        ]);

        $this->putJson(route('admin.legal-entities.update', $entity), [
            'business_type' => 'IP',
            'organization_name' => 'ИП Keep Key',
            'podpislon_api_key' => '',
            'is_enabled' => 1,
        ])->assertOk();

        $this->assertSame('keep-me', $entity->fresh()->podpislon_api_key);
    }

    public function test_superadmin_update_replaces_key(): void
    {
        $this->asSuperadmin();

        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'business_type' => PartnerLegalEntityBusinessType::IP,
            'organization_name' => 'ИП Replace Key',
            'podpislon_api_key' => 'old-key',
        ]);

        $this->putJson(route('admin.legal-entities.update', $entity), [
            'business_type' => 'IP',
            'organization_name' => 'ИП Replace Key',
            'podpislon_api_key' => 'new-key',
            'is_enabled' => 1,
        ])->assertOk();

        $this->assertSame('new-key', $entity->fresh()->podpislon_api_key);
    }

    public function test_superadmin_store_validation_returns_422_for_too_long_key(): void
    {
        $this->asSuperadmin();

        $this->postJson(route('admin.legal-entities.store'), [
            'business_type' => 'IP',
            'organization_name' => 'ИП Длинный ключ',
            'podpislon_api_key' => str_repeat('k', 256),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['podpislon_api_key']);
    }

    public function test_guest_cannot_save_podpislon_api_key(): void
    {
        Auth::logout();

        $this->postJson(route('admin.legal-entities.store'), [
            'business_type' => 'IP',
            'organization_name' => 'ИП Гость ключ',
            'podpislon_api_key' => 'any',
        ])->assertUnauthorized();
    }

    public function test_fill_form_js_never_writes_plaintext_key_into_input(): void
    {
        $this->asSuperadmin();

        $content = $this->get(route('admin.legal-entities.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString("podpislonKeyInput.value = ''", $content);
        $this->assertStringNotContainsString('podpislon_api_key: data.podpislon_api_key', $content);
    }

    public function test_guest_cannot_open_index_or_update_podpislon_api_key(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'podpislon_api_key' => 'keep-from-guest',
        ]);

        Auth::logout();

        $index = $this->get(route('admin.legal-entities.index'));
        $this->assertContains($index->getStatusCode(), [302, 401, 403]);
        $this->assertNotSame(200, $index->getStatusCode());
        $this->assertNotSame(500, $index->getStatusCode());

        $update = $this->putJson(route('admin.legal-entities.update', $entity), [
            'business_type' => 'IP',
            'organization_name' => 'ИП Гость PUT',
            'podpislon_api_key' => 'stolen-by-guest',
            'is_enabled' => 1,
        ]);
        $this->assertContains($update->getStatusCode(), [302, 401, 403]);
        $this->assertNotSame(200, $update->getStatusCode());
        $this->assertNotSame(500, $update->getStatusCode());
        $this->assertSame('keep-from-guest', $entity->fresh()->podpislon_api_key);
    }

    public function test_manager_without_manage_permission_gets_403_when_saving_podpislon_api_key(): void
    {
        $actor = $this->createUserWithoutPermission('legal_entities.manage', $this->partner);
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('legal_entities.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($actor)->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ])->postJson(route('admin.legal-entities.store'), [
            'business_type' => 'IP',
            'organization_name' => 'ИП Без Manage ключ',
            'podpislon_api_key' => 'should-not-save',
            'is_enabled' => 1,
        ])->assertForbidden();

        $this->assertDatabaseMissing('partner_legal_entities', [
            'partner_id' => $this->partner->id,
            'organization_name' => 'ИП Без Manage ключ',
        ]);
    }

    public function test_ajax_store_validation_returns_422_with_podpislon_api_key_field_message(): void
    {
        $this->asSuperadmin();

        $this->postJson(route('admin.legal-entities.store'), [
            'business_type' => 'IP',
            'organization_name' => 'ИП Длинный ключ сообщение',
            'podpislon_api_key' => str_repeat('k', 256),
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['podpislon_api_key'])
            ->assertJsonPath(
                'errors.podpislon_api_key.0',
                'Поле «API-ключ Подпислона» не должно превышать 255 символов.'
            );
    }

    public function test_superadmin_show_json_flag_is_false_when_key_is_not_set(): void
    {
        $this->asSuperadmin();

        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'organization_name' => 'ИП Без ключа JSON',
            'podpislon_api_key' => null,
        ]);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('admin.legal-entities.show', $entity))
            ->assertOk()
            ->assertJsonPath('podpislon_api_key_set', false)
            ->assertJsonMissingPath('podpislon_api_key');
    }

    public function test_superadmin_store_json_does_not_leak_plaintext_key_in_body(): void
    {
        $this->asSuperadmin();

        $secret = 'plaintext-must-not-appear-in-json-'.uniqid('', true);

        $response = $this->postJson(route('admin.legal-entities.store'), [
            'business_type' => 'IP',
            'organization_name' => 'ИП JSON без утечки',
            'tax_id' => '123456789018',
            'podpislon_api_key' => $secret,
            'is_enabled' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Юр. лицо создано')
            ->assertJsonMissingPath('legal_entity.podpislon_api_key');

        $this->assertStringNotContainsString($secret, (string) $response->getContent());
    }

    public function test_datatable_and_show_html_do_not_leak_plaintext_key(): void
    {
        $this->asSuperadmin();
        $this->grantPermissions(['legal_entities.sm_register']);

        $secret = 'datatable-leak-'.uniqid('', true);
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'organization_name' => 'ИП DataTable Key',
            'podpislon_api_key' => $secret,
        ]);

        $data = $this->getJson(route('admin.legal-entities.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))->assertOk();

        $this->assertStringNotContainsString($secret, (string) $data->getContent());
        $this->assertArrayNotHasKey('podpislon_api_key', $data->json('data.0') ?? []);

        $show = $this->get(route('admin.legal-entities.show', $entity))->assertOk();
        $this->assertStringNotContainsString($secret, (string) $show->getContent());
        $this->assertStringNotContainsString('API-ключ Подпислона', (string) $show->getContent());
    }

    public function test_audit_log_records_key_as_set_without_plaintext(): void
    {
        $this->asSuperadmin();

        $secret = 'audit-secret-'.uniqid('', true);

        $this->postJson(route('admin.legal-entities.store'), [
            'business_type' => 'IP',
            'organization_name' => 'ИП Аудит ключа',
            'tax_id' => '123456789019',
            'podpislon_api_key' => $secret,
            'is_enabled' => 1,
        ])->assertOk();

        $log = \App\Models\MyLog::query()
            ->where('event', 'legal_entity.created')
            ->where('partner_id', $this->partner->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('API-ключ Подпислона: задан', (string) $log->description);
        $this->assertStringNotContainsString($secret, (string) $log->description);

        $logsJson = $this->getJson(route('logs.data.legal-entity', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))->assertOk();
        $this->assertStringNotContainsString($secret, (string) $logsJson->getContent());
    }

    public function test_foreign_partner_entity_podpislon_key_is_not_accessible(): void
    {
        $this->asSuperadmin();

        $foreign = PartnerLegalEntity::factory()->for($this->foreignPartner)->create([
            'organization_name' => 'Чужое юрлицо',
            'podpislon_api_key' => 'foreign-secret-key',
        ]);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('admin.legal-entities.show', $foreign))
            ->assertNotFound();

        $this->putJson(route('admin.legal-entities.update', $foreign), [
            'business_type' => 'IP',
            'organization_name' => 'Чужое юрлицо',
            'podpislon_api_key' => 'hijack-foreign',
            'is_enabled' => 1,
        ])->assertNotFound();

        $this->assertSame('foreign-secret-key', $foreign->fresh()->podpislon_api_key);
    }

    public function test_whitespace_only_update_keeps_existing_key(): void
    {
        $this->asSuperadmin();

        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'business_type' => PartnerLegalEntityBusinessType::IP,
            'organization_name' => 'ИП Пробелы ключа',
            'podpislon_api_key' => 'keep-spaces',
        ]);

        $this->putJson(route('admin.legal-entities.update', $entity), [
            'business_type' => 'IP',
            'organization_name' => 'ИП Пробелы ключа',
            'podpislon_api_key' => "  \t  ",
            'is_enabled' => 1,
        ])->assertOk();

        $this->assertSame('keep-spaces', $entity->fresh()->podpislon_api_key);
    }

    public function test_superadmin_can_change_key_after_tbank_registration(): void
    {
        $this->asSuperadmin();

        $smDetails = 'Выплата по договору, НДС не облагается';
        $entity = PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-PODPISLON-1')
            ->create([
                'business_type' => PartnerLegalEntityBusinessType::IP,
                'organization_name' => 'ИП После регистрации',
                'podpislon_api_key' => 'old-after-shop',
                'sm_details_template' => $smDetails,
            ]);

        $this->putJson(route('admin.legal-entities.update', $entity), [
            'business_type' => 'IP',
            'organization_name' => 'ИП После регистрации',
            'tax_id' => $entity->tax_id,
            'sm_details_template' => $smDetails,
            'podpislon_api_key' => 'new-after-shop',
            'is_default' => true,
            'is_enabled' => true,
        ])->assertOk();

        $this->assertSame('new-after-shop', $entity->fresh()->podpislon_api_key);
    }

    public function test_model_hides_podpislon_api_key_from_array_and_json(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'podpislon_api_key' => 'hidden-from-array',
        ]);

        $this->assertArrayNotHasKey('podpislon_api_key', $entity->toArray());
        $this->assertStringNotContainsString('hidden-from-array', $entity->toJson());
        $this->assertSame('hidden-from-array', $entity->podpislon_api_key);
    }

    public function test_school_admin_index_has_no_podpislon_password_input_in_markup(): void
    {
        $html = $this->get(route('admin.legal-entities.index'))
            ->assertOk()
            ->getContent();

        $this->assertSame(
            [],
            $this->podpislonPasswordInputs($html),
            'Сотрудник школы не должен видеть input API-ключа'
        );
        $this->assertStringNotContainsString('<h6 class="mb-0 mt-1">Подпислон</h6>', $html);
    }

    public function test_superadmin_create_and_edit_modals_render_password_key_after_enabled(): void
    {
        $this->asSuperadmin();

        $html = $this->get(route('admin.legal-entities.index'))
            ->assertOk()
            ->getContent();

        $inputs = $this->podpislonPasswordInputs($html);
        $this->assertCount(2, $inputs, 'Поле должно быть и в create, и в edit');

        foreach ($inputs as $tag) {
            $this->assertMatchesRegularExpression('/type=["\']password["\']/', $tag);
            $this->assertStringContainsString('autocomplete="new-password"', $tag);
            $this->assertStringContainsString('maxlength="255"', $tag);
        }

        $enabledPos = strpos($html, 'name="is_enabled"');
        $keyPos = strpos($html, 'name="podpislon_api_key"');
        $this->assertNotFalse($enabledPos);
        $this->assertNotFalse($keyPos);
        $this->assertLessThan($keyPos, $enabledPos);

        $createForm = $this->formHtml($html, 'legalEntityCreateForm');
        $editForm = $this->formHtml($html, 'legalEntityEditForm');

        $this->assertStringContainsString('name="podpislon_api_key"', $createForm);
        $this->assertStringContainsString('name="podpislon_api_key"', $editForm);
        $this->assertStringContainsString('Оставьте пустым, чтобы не менять', $editForm);
        $this->assertStringNotContainsString('Оставьте пустым, чтобы не менять', $createForm);

        $this->assertSame(1, substr_count($createForm, 'js-podpislon-api-key-set-hint d-none'));
        $this->assertSame(1, substr_count($editForm, 'js-podpislon-api-key-set-hint d-none'));
        $this->assertStringContainsString('data-error-for="podpislon_api_key"', $createForm);
        $this->assertStringContainsString('data-error-for="podpislon_api_key"', $editForm);
    }

    public function test_podpislon_key_input_is_not_sm_locked_unlike_bank_account(): void
    {
        $content = (string) file_get_contents(resource_path('views/admin/legal-entities/partials/crud-fields.blade.php'));

        $keyStart = strpos($content, 'name="podpislon_api_key"');
        $this->assertNotFalse($keyStart);
        $chunk = substr($content, max(0, $keyStart - 180), 420);
        $this->assertStringNotContainsString(
            'js-legal-entity-sm-locked',
            $chunk,
            'API-ключ Подпислона не должен блокироваться после sm-register'
        );
    }

    public function test_fill_form_and_create_modal_open_always_clear_key_and_toggle_hint(): void
    {
        $content = (string) file_get_contents(resource_path('views/admin/legal-entities/index.blade.php'));

        $fillPos = strpos($content, 'function fillForm(form, data)');
        $this->assertNotFalse($fillPos);
        $fillChunk = substr($content, (int) $fillPos, 3200);

        $this->assertStringNotContainsString('podpislon_api_key: data.podpislon_api_key', $fillChunk);
        $this->assertStringContainsString("podpislonKeyInput.value = ''", $fillChunk);
        $this->assertStringContainsString(
            "podpislonHint.classList.toggle('d-none', !data.podpislon_api_key_set)",
            $fillChunk
        );

        $createOpenPos = strpos($content, "getElementById('legalEntityCreateModal')");
        $this->assertNotFalse($createOpenPos);
        $createChunk = substr($content, (int) $createOpenPos, 900);
        $this->assertStringContainsString("addEventListener('show.bs.modal'", $createChunk);
        $this->assertStringContainsString("podpislonHint.classList.add('d-none')", $createChunk);
        $this->assertStringContainsString("podpislonKeyInput.value = ''", $createChunk);

        $this->assertStringContainsString('await openLegalEntityEditModal(id)', $content);
        $this->assertStringContainsString('fillForm(editForm, data)', $content);
        $this->assertStringContainsString("applyErrors(createForm, data.errors || {})", $content);
        $this->assertStringContainsString("applyErrors(editForm, data.errors || {})", $content);
    }

    /**
     * @return list<string>
     */
    private function podpislonPasswordInputs(string $html): array
    {
        preg_match_all('/<input\b[^>]*name="podpislon_api_key"[^>]*>/i', $html, $matches);

        return $matches[0] ?? [];
    }

    private function formHtml(string $html, string $formId): string
    {
        $pos = strpos($html, 'id="'.$formId.'"');
        $this->assertNotFalse($pos, "Форма {$formId} не найдена");
        $end = strpos($html, '</form>', $pos);
        $this->assertNotFalse($end);

        return substr($html, $pos, $end - $pos);
    }
}
