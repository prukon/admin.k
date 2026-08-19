<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use App\Models\ChatParticipant;
use App\Models\ChatThread;
use App\Models\Role;
use App\Models\Team;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Models\Weekday;

trait InteractsWithTeamGroupChats
{
    /**
     * @return array<string, string>
     */
    protected function teamChatAjaxHeaders(): array
    {
        return ['X-Requested-With' => 'XMLHttpRequest'];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function teamStorePayload(string $title, array $extra = []): array
    {
        return array_merge([
            'title' => $title,
            'default_duration_minutes' => 60,
            'order_by' => 10,
            'is_enabled' => 1,
            'weekdays' => Weekday::query()->take(1)->pluck('id')->all(),
        ], $extra);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function storeTeamViaAjax(string $title, array $extra = []): Team
    {
        $this->postJson(
            route('admin.team.store'),
            $this->teamStorePayload($title, $extra),
            $this->teamChatAjaxHeaders()
        )->assertOk();

        return Team::query()->where('title', $title)->firstOrFail();
    }

    protected function teamThread(Team $team): ChatThread
    {
        $thread = ChatThread::query()->where('team_id', $team->id)->first();
        $this->assertNotNull($thread, 'Нет группового чата для учебной группы '.$team->id);

        return $thread;
    }

    protected function assertUserInThread(User $user, ChatThread $thread): void
    {
        $this->assertTrue(
            ChatParticipant::query()
                ->where('thread_id', $thread->id)
                ->where('user_id', $user->id)
                ->exists(),
            'Ожидали пользователя '.$user->id.' в чате '.$thread->id
        );
    }

    protected function assertUserNotInThread(User $user, ChatThread $thread): void
    {
        $this->assertFalse(
            ChatParticipant::query()
                ->where('thread_id', $thread->id)
                ->where('user_id', $user->id)
                ->exists(),
            'Не ожидали пользователя '.$user->id.' в чате '.$thread->id
        );
    }

    protected function assertNoTeamChatCreated(): void
    {
        $this->assertSame(
            0,
            ChatThread::query()->whereNotNull('team_id')->count(),
            'Чат учебной группы не должен был появиться'
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function studentStorePayload(string $email, array $extra = []): array
    {
        return array_merge([
            'name' => 'Новый',
            'lastname' => 'УченикЧат',
            'email' => $email,
            'role_id' => $this->roleId('user'),
            'birthday' => '2015-01-01',
            'start_date' => '2024-09-01',
            'is_enabled' => 1,
            'password' => 'TestPass123!',
            'password_confirmation' => 'TestPass123!',
        ], $extra);
    }

    protected function makeEnabledSuperadmin(string $lastname = 'АдминЧат'): User
    {
        return User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('superadmin'),
            'name' => 'Супер',
            'lastname' => $lastname,
            'is_enabled' => 1,
            'team_id' => null,
        ]);
    }

    protected function makeTrainerProfile(string $name): TrainerProfile
    {
        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => (int) Role::query()->where('name', 'trainer')->value('id'),
            'name' => $name,
            'is_enabled' => 1,
            'team_id' => null,
            'email' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid('', true).'@example.test',
        ]);

        return TrainerProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $user->id,
            'is_enabled' => true,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function inboxRowFor(User $viewer, int $threadId): ?array
    {
        $this->actingInPartner($viewer);
        $this->grantPermission($viewer, 'messages.view');

        $list = collect(
            $this->getJson(route('chat.api.threads.index'))->assertOk()->json('threads')
        );

        $row = $list->firstWhere('id', $threadId);

        return $row === null ? null : (array) $row;
    }
}
