<?php

namespace Tests\Feature\Crm\Contracts;

use App\Models\User;

class ContractsLookupsTest extends ContractsFeatureTestCase
{
    /** @test */
    public function users_search_returns_only_current_partner_enabled_users(): void
    {
        $u1 = User::factory()->create([
            'partner_id'  => $this->partner->id,
            'is_enabled'  => 1,
            'name'        => 'Ivan',
            'lastname'    => 'Petrov',
            'phone'       => '79001112233',
            'email'       => 'ivan@example.test',
        ]);

        $uDisabled = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 0,
            'name'       => 'Ivan',
            'lastname'   => 'Disabled',
        ]);

        $uForeign = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'is_enabled' => 1,
            'name'       => 'Ivan',
            'lastname'   => 'Foreign',
        ]);

        $resp = $this->getJson('/client-contracts/users-search?q=Ivan');

        $resp->assertStatus(200)
            ->assertJsonStructure(['results' => [['id', 'text']]]);

        $ids = collect($resp->json('results'))->pluck('id')->all();
        $this->assertContains($u1->id, $ids);
        $this->assertNotContains($uDisabled->id, $ids);
        $this->assertNotContains($uForeign->id, $ids);
    }

    /** @test */
    public function user_group_requires_user_id(): void
    {
        $this->getJson('/client-contracts/user-group')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['message', 'errors' => ['user_id']]);
    }

    /** @test */
    public function user_group_returns_empty_for_foreign_user(): void
    {
        $foreignStudent = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'is_enabled' => 1,
        ]);

        $this->getJson('/client-contracts/user-group?user_id=' . $foreignStudent->id)
            ->assertStatus(200)
            ->assertExactJson(['groups' => []]);
    }
}

