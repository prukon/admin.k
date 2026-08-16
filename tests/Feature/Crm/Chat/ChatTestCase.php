<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use App\Models\ChatMessage;
use App\Models\ChatParticipant;
use App\Models\ChatThread;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

abstract class ChatTestCase extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);
        $this->app->forgetInstance(BroadcastManager::class);
        $this->app->forgetInstance('Illuminate\Contracts\Broadcasting\Factory');

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    protected function actingInPartner(User $user, ?Partner $partner = null): self
    {
        $partner ??= $this->partner;

        $this->actingAs($user);
        $this->withSession([
            'current_partner' => (int) ($user->partner_id ?: $partner->id),
            '2fa:passed' => true,
        ]);

        return $this;
    }

    /**
     * Reverb недоступен (как на стенде без процесса на :6008): HTTP чата не должен падать.
     */
    protected function useUnreachableReverb(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.driver' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => '1',
            'broadcasting.connections.reverb.options' => [
                'host' => '127.0.0.1',
                'port' => 1,
                'scheme' => 'http',
                'useTLS' => false,
                'timeout' => 1,
            ],
        ]);

        $this->app->forgetInstance(BroadcastManager::class);
        $this->app->forgetInstance('Illuminate\Contracts\Broadcasting\Factory');
    }

    protected function grantPermission(User $user, string $permissionName, ?int $partnerId = null): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $partnerId ?? (int) $user->partner_id,
            'role_id' => (int) $user->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function makePeer(string $prefix = 'ChatPeer_', array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'name' => $prefix.uniqid('', true),
            'is_enabled' => 1,
        ], $attributes));
    }

    /**
     * @param  list<int>  $userIds
     */
    protected function createThreadForUsers(array $userIds, string $subject = 'Test thread'): ChatThread
    {
        $thread = ChatThread::query()->create(['subject' => $subject]);

        foreach (array_unique($userIds) as $userId) {
            ChatParticipant::query()->create([
                'thread_id' => $thread->id,
                'user_id' => (int) $userId,
            ]);
        }

        return $thread;
    }

    protected function seedMessage(ChatThread $thread, int $userId, string $body): ChatMessage
    {
        return ChatMessage::query()->create([
            'thread_id' => $thread->id,
            'user_id' => $userId,
            'body' => $body,
        ]);
    }
}
