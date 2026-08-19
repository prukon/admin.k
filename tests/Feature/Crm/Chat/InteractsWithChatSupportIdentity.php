<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use App\Models\User;

trait InteractsWithChatSupportIdentity
{
    /**
     * @return array<string, string>
     */
    protected function chatAjaxHeaders(): array
    {
        return ['X-Requested-With' => 'XMLHttpRequest'];
    }

    /**
     * @param  int|null  $partnerId  0 — текущая школа, null — без партнёра
     */
    protected function makeSupport(string $lastname, string $name, ?int $partnerId = 0): User
    {
        $partner = $partnerId === null ? null : ($partnerId === 0 ? $this->partner->id : $partnerId);

        return User::factory()->create([
            'partner_id' => $partner,
            'role_id' => $this->roleId('superadmin'),
            'lastname' => $lastname.uniqid('', true),
            'name' => $name,
            'email' => 'sa-secret-'.uniqid('', true).'@example.test',
            'phone' => '+79001112233',
            'is_enabled' => 1,
            'team_id' => null,
        ]);
    }
}
