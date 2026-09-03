<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\PartnerLegalEntity;
use App\Models\Team;
use App\Models\User;
use App\Services\Signatures\PodpislonCredentialsException;
use App\Services\Signatures\PodpislonCredentialsResolver;
use Tests\Feature\Crm\CrmTestCase;

final class PodpislonCredentialsResolverTest extends CrmTestCase
{
    public function test_single_legal_entity_with_key_binds_and_snapshots_contract(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'podpislon_api_key' => 'le-key-1',
            'is_default' => true,
            'is_enabled' => true,
        ]);

        $contract = $this->makeDraftContract();

        $bound = app(PodpislonCredentialsResolver::class)->bindToContract($contract);

        $this->assertSame($entity->id, $bound->id);
        $this->assertSame($entity->id, $contract->fresh()->legal_entity_id);
        $this->assertSame('le-key-1', app(PodpislonCredentialsResolver::class)->apiKeyForContract($contract->fresh()));
    }

    public function test_missing_api_key_throws_with_field_error(): void
    {
        PartnerLegalEntity::factory()->for($this->partner)->create([
            'organization_name' => 'ИП Без ключа',
            'podpislon_api_key' => null,
            'is_enabled' => true,
        ]);

        $contract = $this->makeDraftContract();

        try {
            app(PodpislonCredentialsResolver::class)->bindToContract($contract);
            $this->fail('Expected PodpislonCredentialsException');
        } catch (PodpislonCredentialsException $e) {
            $this->assertSame('podpislon_api_key', $e->errorKey);
            $this->assertSame('podpislon_api_key_missing', $e->errorCode);
            $this->assertStringContainsString('ИП Без ключа', $e->getMessage());
            $this->assertNull($contract->fresh()->legal_entity_id);
        }
    }

    public function test_multi_entity_without_group_is_ambiguous(): void
    {
        PartnerLegalEntity::factory()->for($this->partner)->create([
            'podpislon_api_key' => 'key-a',
            'is_default' => true,
            'is_enabled' => true,
        ]);
        PartnerLegalEntity::factory()->for($this->partner)->create([
            'podpislon_api_key' => 'key-b',
            'is_default' => false,
            'is_enabled' => true,
        ]);

        $contract = $this->makeDraftContract(['group_id' => null]);

        $this->expectException(PodpislonCredentialsException::class);
        $this->expectExceptionMessage('Не удалось определить юр. лицо');

        app(PodpislonCredentialsResolver::class)->bindToContract($contract);
    }

    public function test_multi_entity_uses_team_bound_legal_entity(): void
    {
        PartnerLegalEntity::factory()->for($this->partner)->create([
            'podpislon_api_key' => 'key-default',
            'is_default' => true,
            'is_enabled' => true,
        ]);
        $second = PartnerLegalEntity::factory()->for($this->partner)->create([
            'podpislon_api_key' => 'key-group',
            'is_default' => false,
            'is_enabled' => true,
        ]);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'legal_entity_id' => $second->id,
        ]);

        $contract = $this->makeDraftContract(['group_id' => $team->id]);

        $bound = app(PodpislonCredentialsResolver::class)->bindToContract($contract);

        $this->assertSame($second->id, $bound->id);
        $this->assertSame('key-group', $bound->podpislon_api_key);
    }

    public function test_snapshotted_legal_entity_is_reused_even_if_team_rebounds(): void
    {
        $first = PartnerLegalEntity::factory()->for($this->partner)->create([
            'podpislon_api_key' => 'key-first',
            'is_default' => true,
            'is_enabled' => true,
        ]);
        $second = PartnerLegalEntity::factory()->for($this->partner)->create([
            'podpislon_api_key' => 'key-second',
            'is_default' => false,
            'is_enabled' => true,
        ]);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'legal_entity_id' => $first->id,
        ]);

        $contract = $this->makeDraftContract(['group_id' => $team->id]);
        app(PodpislonCredentialsResolver::class)->bindToContract($contract);

        $team->legal_entity_id = $second->id;
        $team->save();

        $again = app(PodpislonCredentialsResolver::class)->bindToContract($contract->fresh());

        $this->assertSame($first->id, $again->id);
        $this->assertSame('key-first', $again->podpislon_api_key);
    }

    public function test_whitespace_only_key_is_treated_as_missing(): void
    {
        PartnerLegalEntity::factory()->for($this->partner)->create([
            'organization_name' => 'ИП Пробелы',
            'podpislon_api_key' => '   ',
            'is_enabled' => true,
        ]);

        $contract = $this->makeDraftContract();

        try {
            app(PodpislonCredentialsResolver::class)->bindToContract($contract);
            $this->fail('Expected PodpislonCredentialsException');
        } catch (PodpislonCredentialsException $e) {
            $this->assertSame('podpislon_api_key', $e->errorKey);
            $this->assertSame('podpislon_api_key_missing', $e->errorCode);
            $this->assertNull($contract->fresh()->legal_entity_id);
        }
    }

    public function test_soft_deleted_snapshotted_legal_entity_still_provides_key(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'podpislon_api_key' => 'key-trashed',
            'is_enabled' => true,
        ]);

        $contract = $this->makeDraftContract(['legal_entity_id' => $entity->id]);
        $entity->delete();

        $bound = app(PodpislonCredentialsResolver::class)->bindToContract($contract->fresh());

        $this->assertSame($entity->id, $bound->id);
        $this->assertSame('key-trashed', $bound->podpislon_api_key);
    }

    public function test_snapshotted_legal_entity_of_another_partner_is_missing(): void
    {
        $foreign = PartnerLegalEntity::factory()->for($this->foreignPartner)->create([
            'podpislon_api_key' => 'foreign-key',
            'is_enabled' => true,
        ]);

        $contract = $this->makeDraftContract(['legal_entity_id' => $foreign->id]);

        try {
            app(PodpislonCredentialsResolver::class)->bindToContract($contract);
            $this->fail('Expected PodpislonCredentialsException');
        } catch (PodpislonCredentialsException $e) {
            $this->assertSame('legal_entity_id', $e->errorKey);
            $this->assertSame('legal_entity_missing', $e->errorCode);
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeDraftContract(array $overrides = []): Contract
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        return Contract::create(array_merge([
            'school_id' => $this->partner->id,
            'user_id' => $student->id,
            'group_id' => null,
            'source_pdf_path' => 'documents/2026/01/source.pdf',
            'source_sha256' => str_repeat('a', 64),
            'provider' => 'podpislon',
            'status' => Contract::STATUS_DRAFT,
        ], $overrides));
    }
}
