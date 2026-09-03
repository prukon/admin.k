<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LegalEntities;

use App\Enums\PartnerLegalEntityBusinessType;
use App\Models\PartnerLegalEntity;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Native POST/PUT без X-Requested-With: 302 на раздел + ключ создан/обновлён (не пустой 200).
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see /docs/documentation/admin-legal-entities.html §4.2.3
 */
final class LegalEntitiesPodpislonApiKeyNonAjaxSafetyNetFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->asSuperadmin();
        $this->grantPermissions(['legal_entities.view', 'legal_entities.manage', 'legal_entities.sm_register']);
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

    public function test_store_non_ajax_redirects_and_creates_entity_with_podpislon_api_key(): void
    {
        $response = $this->from(route('admin.legal-entities.index'))
            ->post(route('admin.legal-entities.store'), [
                'business_type' => 'IP',
                'organization_name' => 'ИП NonAjax ключ',
                'tax_id' => '123456789021',
                'podpislon_api_key' => 'native-post-key',
                'is_enabled' => 1,
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Create без AJAX не должен быть пустым 200');
        $response->assertRedirect(route('admin.legal-entities.index'));

        $entity = PartnerLegalEntity::query()
            ->where('partner_id', $this->partner->id)
            ->where('tax_id', '123456789021')
            ->first();

        $this->assertNotNull($entity);
        $this->assertSame('native-post-key', $entity->podpislon_api_key);
    }

    public function test_store_non_ajax_validation_redirects_back_with_podpislon_api_key_error(): void
    {
        $this->from(route('admin.legal-entities.index'))
            ->post(route('admin.legal-entities.store'), [
                'business_type' => 'IP',
                'organization_name' => 'ИП NonAjax длинный ключ',
                'podpislon_api_key' => str_repeat('z', 256),
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['podpislon_api_key']);

        $this->assertDatabaseMissing('partner_legal_entities', [
            'partner_id' => $this->partner->id,
            'organization_name' => 'ИП NonAjax длинный ключ',
        ]);
    }

    public function test_update_non_ajax_redirects_and_replaces_podpislon_api_key(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'business_type' => PartnerLegalEntityBusinessType::IP,
            'organization_name' => 'До native PUT ключа',
            'podpislon_api_key' => 'old-native-key',
        ]);

        $response = $this->from(route('admin.legal-entities.index'))
            ->put(route('admin.legal-entities.update', $entity), [
                'business_type' => 'IP',
                'organization_name' => 'После native PUT ключа',
                'podpislon_api_key' => 'new-native-key',
                'is_default' => true,
                'is_enabled' => true,
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'PUT без AJAX не должен быть пустым 200');
        $response->assertRedirect(route('admin.legal-entities.show', $entity));

        $this->assertSame('new-native-key', $entity->fresh()->podpislon_api_key);
    }

    public function test_update_non_ajax_empty_key_keeps_existing_and_redirects(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'business_type' => PartnerLegalEntityBusinessType::IP,
            'organization_name' => 'Native empty keep',
            'podpislon_api_key' => 'keep-native',
        ]);

        $this->from(route('admin.legal-entities.show', $entity))
            ->put(route('admin.legal-entities.update', $entity), [
                'business_type' => 'IP',
                'organization_name' => 'Native empty keep',
                'podpislon_api_key' => '',
                'is_default' => true,
                'is_enabled' => true,
            ])
            ->assertRedirect(route('admin.legal-entities.show', $entity));

        $this->assertSame('keep-native', $entity->fresh()->podpislon_api_key);
    }

    public function test_school_admin_non_ajax_store_ignores_posted_key(): void
    {
        $this->asAdmin();
        $this->grantPermissions(['legal_entities.view', 'legal_entities.manage']);

        $this->from(route('admin.legal-entities.index'))
            ->post(route('admin.legal-entities.store'), [
                'business_type' => 'IP',
                'organization_name' => 'ИП School native ключ',
                'tax_id' => '123456789022',
                'podpislon_api_key' => 'school-native-stolen',
                'is_enabled' => 1,
            ])
            ->assertRedirect(route('admin.legal-entities.index'));

        $entity = PartnerLegalEntity::query()
            ->where('partner_id', $this->partner->id)
            ->where('tax_id', '123456789022')
            ->first();

        $this->assertNotNull($entity);
        $this->assertFalse($entity->hasPodpislonApiKey());
    }
}
