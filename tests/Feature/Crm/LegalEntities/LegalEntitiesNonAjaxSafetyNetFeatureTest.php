<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LegalEntities;

use App\Enums\PartnerLegalEntityBusinessType;
use App\Models\PartnerLegalEntity;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Non-AJAX safety-net: store/update без X-Requested-With → redirect, запись в БД создана/обновлена.
 */
final class LegalEntitiesNonAjaxSafetyNetFeatureTest extends CrmTestCase
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

    public function test_store_non_ajax_redirects_and_creates_entity(): void
    {
        $payload = [
            'business_type' => 'IP',
            'title' => 'ИП без ajax',
            'tax_id' => '123456789012',
            'is_enabled' => 1,
        ];

        $this->post(route('admin.legal-entities.store'), $payload)
            ->assertRedirect(route('admin.legal-entities.index'));

        $entity = PartnerLegalEntity::query()
            ->where('partner_id', $this->partner->id)
            ->where('title', 'ИП без ajax')
            ->first();

        $this->assertNotNull($entity);
        $this->assertSame(PartnerLegalEntityBusinessType::IP, $entity->business_type);
        $this->assertSame('123456789012', $entity->tax_id);
    }

    public function test_store_non_ajax_validation_failure_redirects_back_with_errors_not_empty_200(): void
    {
        $this->from(route('admin.legal-entities.index'))
            ->post(route('admin.legal-entities.store'), [
                'business_type' => 'INVALID',
                'title' => '',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['business_type', 'title']);

        $this->assertDatabaseMissing('partner_legal_entities', [
            'partner_id' => $this->partner->id,
            'title' => '',
        ]);
    }

    public function test_update_non_ajax_redirects_and_updates_entity(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'title' => 'До non-ajax update',
            'business_type' => PartnerLegalEntityBusinessType::OOO,
        ]);

        $payload = [
            'business_type' => 'OOO',
            'title' => 'После non-ajax update',
            'is_default' => true,
            'is_enabled' => true,
        ];

        $this->put(route('admin.legal-entities.update', $entity), $payload)
            ->assertRedirect(route('admin.legal-entities.show', $entity))
            ->assertSessionHas('ok', 'Юр. лицо обновлено');

        $this->assertSame('После non-ajax update', $entity->fresh()->title);
    }

    public function test_update_non_ajax_validation_failure_redirects_with_errors_not_empty_200(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'title' => 'Валидация non-ajax',
        ]);

        $this->from(route('admin.legal-entities.show', $entity))
            ->put(route('admin.legal-entities.update', $entity), [
                'business_type' => 'BAD',
                'title' => '',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['business_type', 'title']);

        $this->assertSame('Валидация non-ajax', $entity->fresh()->title);
    }
}
