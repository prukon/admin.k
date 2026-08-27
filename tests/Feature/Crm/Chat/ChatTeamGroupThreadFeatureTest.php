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
use App\Services\Chat\TeamGroupChatService;
use App\Services\TeamUserSyncService;

final class ChatTeamGroupThreadFeatureTest extends ChatTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->asAdmin();
        $this->grantPermission($this->user, 'groups.view');
        $this->grantPermission($this->user, 'trainers.view');
        $this->grantPermission($this->user, 'users.view');
        $this->grantPermission($this->user, 'users.role.update');
    }

    public function test_store_team_creates_group_chat_with_admins_and_superadmin(): void
    {
        $superadmin = $this->makeEnabledSuperadmin();
        $otherAdmin = $this->createUserWithRole('admin', $this->partner, [
            'name' => 'Второй',
            'lastname' => 'Админ',
            'is_enabled' => 1,
            'team_id' => null,
        ]);
        $disabledAdmin = $this->createUserWithRole('admin', $this->partner, [
            'name' => 'Выкл',
            'lastname' => 'Админ',
            'is_enabled' => 0,
            'team_id' => null,
        ]);

        $title = 'ЧатГруппа '.uniqid('', true);
        $this->postJson('/admin/teams', $this->teamStorePayload($title), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('message', 'Группа создана успешно');

        $team = Team::query()->where('title', $title)->firstOrFail();
        $thread = $this->teamThread($team);

        $this->assertTrue((bool) $thread->is_group);
        $this->assertSame($title, $thread->subject);
        $this->assertUserInThread($this->user, $thread);
        $this->assertUserInThread($otherAdmin, $thread);
        $this->assertUserInThread($superadmin, $thread);
        $this->assertUserNotInThread($disabledAdmin, $thread);
        $this->assertSame(
            1,
            ChatThread::query()->where('team_id', $team->id)->count()
        );
    }

    public function test_renaming_team_does_not_rename_chat_and_renaming_chat_does_not_rename_team(): void
    {
        $title = 'ИмяГруппы '.uniqid('', true);
        $team = $this->storeTeam($title);
        $thread = $this->teamThread($team);

        $this->patchJson('/admin/team/'.$team->id, [
            'title' => 'Новое имя группы '.uniqid('', true),
            'is_enabled' => 1,
        ])->assertOk();

        $team->refresh();
        $thread->refresh();
        $this->assertSame($title, $thread->subject);
        $this->assertNotSame($title, $team->title);

        $thread->forceFill(['subject' => 'Чат переименован'])->save();
        $team->refresh();
        $this->assertNotSame('Чат переименован', $team->title);
    }

    public function test_student_joins_all_team_chats_and_stays_after_detach_and_team_delete(): void
    {
        $teamA = $this->storeTeam('КомандаА '.uniqid('', true));
        $teamB = $this->storeTeam('КомандаБ '.uniqid('', true));
        $student = $this->makePeer('TeamChatStu_', ['team_id' => null]);

        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [
            (int) $teamA->id,
            (int) $teamB->id,
        ]);

        $this->assertUserInThread($student, $this->teamThread($teamA));
        $this->assertUserInThread($student, $this->teamThread($teamB));

        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $teamA->id]);
        $this->assertUserInThread($student, $this->teamThread($teamB));

        $this->deleteJson('/admin/team/'.$teamA->id)->assertOk();
        $this->assertNotNull(ChatThread::query()->where('team_id', $teamA->id)->first());
        $this->assertUserInThread($student, $this->teamThread($teamA));
        $this->assertTrue($teamA->fresh()->trashed());
    }

    public function test_disabled_student_is_not_added_and_enable_puts_them_into_team_chats_only(): void
    {
        $team = $this->storeTeam('ВыклУченик '.uniqid('', true));
        $student = $this->makePeer('DisStu_', ['team_id' => null, 'is_enabled' => 0]);
        $peer = $this->makePeer('PrivPeer_', ['team_id' => null]);
        $private = $this->createThreadForUsers([(int) $student->id, (int) $peer->id], 'Личка');

        app(TeamUserSyncService::class)->attachTeamForStudent($student, (int) $team->id);
        $this->assertUserNotInThread($student, $this->teamThread($team));

        $student->forceFill(['is_enabled' => 1])->save();
        ChatParticipant::query()
            ->where('thread_id', $private->id)
            ->where('user_id', $student->id)
            ->delete();

        $student->forceFill(['is_enabled' => 0])->save();
        $this->assertUserNotInThread($student, $this->teamThread($team));
        $this->assertUserNotInThread($student, $private);

        $student->forceFill(['is_enabled' => 1])->save();
        $this->assertUserInThread($student, $this->teamThread($team));
        $this->assertUserNotInThread($student, $private);
    }

    public function test_disable_removes_user_from_all_chats_including_one_to_one(): void
    {
        $team = $this->storeTeam('Отключение '.uniqid('', true));
        $student = $this->makePeer('KickStu_', ['team_id' => null]);
        $peer = $this->makePeer('KickPeer_', ['team_id' => null]);
        app(TeamUserSyncService::class)->attachTeamForStudent($student, (int) $team->id);
        $private = $this->createThreadForUsers([(int) $student->id, (int) $peer->id], 'Личка2');

        $this->assertUserInThread($student, $this->teamThread($team));
        $this->assertUserInThread($student, $private);

        $student->forceFill(['is_enabled' => 0])->save();
        $this->assertUserNotInThread($student, $this->teamThread($team));
        $this->assertUserNotInThread($student, $private);
        $this->assertNull($private->fresh()->deleted_at);
        $this->assertNull($this->teamThread($team)->deleted_at);
    }

    public function test_trainer_is_added_on_bind_and_kept_after_unbind(): void
    {
        $team = $this->storeTeam('ТренерЧат '.uniqid('', true));
        $profile = $this->makeTrainerProfile('ЧатТренер');

        $this->patchJson('/admin/team/'.$team->id, [
            'title' => $team->title,
            'is_enabled' => 1,
            'trainer_profile_ids' => [$profile->id],
        ])->assertOk();

        $trainerUser = User::query()->findOrFail((int) $profile->user_id);
        $this->assertUserInThread($trainerUser, $this->teamThread($team));

        $this->patchJson('/admin/team/'.$team->id, [
            'title' => $team->title,
            'is_enabled' => 1,
            'trainer_profile_ids' => [],
        ])->assertOk();

        $this->assertDatabaseMissing('team_trainer', [
            'team_id' => $team->id,
            'trainer_profile_id' => $profile->id,
        ]);
        $this->assertUserInThread($trainerUser, $this->teamThread($team));
    }

    public function test_new_admin_joins_existing_team_chats_demote_keeps_membership(): void
    {
        $team = $this->storeTeam('АдминЧат '.uniqid('', true));

        $this->postJson(route('admin.administrators.store'), [
            'name' => 'Новый',
            'lastname' => 'АдминЧат',
            'email' => 'team-chat-admin-'.uniqid('', true).'@example.test',
            'is_enabled' => 1,
        ], $this->ajaxHeaders())->assertOk();

        $newAdmin = User::query()
            ->where('lastname', 'АдминЧат')
            ->where('partner_id', $this->partner->id)
            ->firstOrFail();
        $this->assertUserInThread($newAdmin, $this->teamThread($team));

        $newAdmin->forceFill(['role_id' => $this->roleId('user')])->save();
        $this->assertUserInThread($newAdmin, $this->teamThread($team));
    }

    public function test_promote_to_admin_adds_to_all_partner_team_chats(): void
    {
        $teamA = $this->storeTeam('ПромоА '.uniqid('', true));
        $teamB = $this->storeTeam('ПромоБ '.uniqid('', true));
        $staff = $this->createUserWithRole('user', $this->partner, [
            'name' => 'Станет',
            'lastname' => 'Админом',
            'is_enabled' => 1,
            'team_id' => null,
        ]);

        $this->assertUserNotInThread($staff, $this->teamThread($teamA));

        $staff->forceFill(['role_id' => $this->roleId('admin')])->save();
        $this->assertUserInThread($staff, $this->teamThread($teamA));
        $this->assertUserInThread($staff, $this->teamThread($teamB));
    }

    public function test_superadmin_joins_team_chats_of_all_partners(): void
    {
        $foreignAdmin = $this->createUserWithRole('admin', $this->foreignPartner, [
            'is_enabled' => 1,
            'team_id' => null,
        ]);
        $this->actingInPartner($foreignAdmin, $this->foreignPartner);
        $this->grantPermission($foreignAdmin, 'groups.view', (int) $this->foreignPartner->id);

        $foreignTitle = 'Чужая '.uniqid('', true);
        $this->postJson('/admin/teams', $this->teamStorePayload($foreignTitle), $this->ajaxHeaders())->assertOk();
        $foreignTeam = Team::query()->where('title', $foreignTitle)->firstOrFail();

        $this->actingInPartner($this->user, $this->partner);
        $ownTeam = $this->storeTeam('Своя '.uniqid('', true));

        $superadmin = $this->makeEnabledSuperadmin();
        $this->assertUserInThread($superadmin, $this->teamThread($ownTeam));
        $this->assertUserInThread($superadmin, $this->teamThread($foreignTeam));
    }

    public function test_only_canonical_superadmin_is_seeded_into_team_chat(): void
    {
        $canonical = $this->makeEnabledSuperadmin();
        $extra = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('superadmin'),
            'name' => 'Лишний',
            'lastname' => 'СуперЧат',
            'is_enabled' => 1,
            'team_id' => null,
        ]);

        $title = 'ОдинСа '.uniqid('', true);
        $this->postJson('/admin/teams', $this->teamStorePayload($title), $this->ajaxHeaders())
            ->assertOk();

        $team = Team::query()->where('title', $title)->firstOrFail();
        $thread = $this->teamThread($team);
        $this->assertUserInThread($canonical, $thread);
        $this->assertUserNotInThread($extra, $thread);
    }

    public function test_backfill_creates_chat_for_factory_team_with_current_members(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Старая '.uniqid('', true),
        ]);
        $this->assertNull(ChatThread::query()->where('team_id', $team->id)->first());

        $student = $this->makePeer('BackfillStu_', ['team_id' => null]);
        \Illuminate\Support\Facades\DB::table('team_user')->insert([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'user_id' => $student->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $created = app(TeamGroupChatService::class)->backfillMissing();
        $this->assertGreaterThanOrEqual(1, $created);

        $thread = $this->teamThread($team);
        $this->assertUserInThread($student, $thread);
        $this->assertUserInThread($this->user, $thread);
    }

    public function test_leaving_last_member_does_not_delete_team_chat(): void
    {
        $this->grantPermission($this->user, 'messages.view');
        $team = $this->storeTeam('Последний '.uniqid('', true));
        $thread = $this->teamThread($team);

        $memberIds = ChatParticipant::query()
            ->where('thread_id', $thread->id)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        foreach ($memberIds as $memberId) {
            if ($memberId === (int) $this->user->id) {
                continue;
            }
            $other = User::query()->find($memberId);
            if ($other) {
                $this->deleteJson(route('chat.api.threads.participants.destroy', [$thread, $other]))
                    ->assertOk();
            }
        }

        $this->deleteJson(route('chat.api.threads.participants.destroy', [$thread, $this->user]))
            ->assertOk()
            ->assertJsonPath('thread_deleted', false);

        $this->assertNull($thread->fresh()->deleted_at);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function teamStorePayload(string $title, array $extra = []): array
    {
        return array_merge([
            'title' => $title,
            'default_duration_minutes' => 60,
            'order_by' => 10,
            'is_enabled' => 1,
            'weekdays' => Weekday::query()->take(1)->pluck('id')->all(),
        ], $extra);
    }

    private function storeTeam(string $title): Team
    {
        $this->postJson('/admin/teams', $this->teamStorePayload($title), $this->ajaxHeaders())->assertOk();

        return Team::query()->where('title', $title)->firstOrFail();
    }

    /**
     * @return array<string, string>
     */
    private function ajaxHeaders(): array
    {
        return ['X-Requested-With' => 'XMLHttpRequest'];
    }

    private function teamThread(Team $team): ChatThread
    {
        $thread = ChatThread::query()->where('team_id', $team->id)->first();
        $this->assertNotNull($thread, 'Нет группового чата для учебной группы '.$team->id);

        return $thread;
    }

    private function assertUserInThread(User $user, ChatThread $thread): void
    {
        $this->assertTrue(
            ChatParticipant::query()
                ->where('thread_id', $thread->id)
                ->where('user_id', $user->id)
                ->exists(),
            'Ожидали пользователя '.$user->id.' в чате '.$thread->id
        );
    }

    private function assertUserNotInThread(User $user, ChatThread $thread): void
    {
        $this->assertFalse(
            ChatParticipant::query()
                ->where('thread_id', $thread->id)
                ->where('user_id', $user->id)
                ->exists(),
            'Не ожидали пользователя '.$user->id.' в чате '.$thread->id
        );
    }

    private function makeEnabledSuperadmin(): User
    {
        return User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('superadmin'),
            'name' => 'Супер',
            'lastname' => 'АдминЧат',
            'is_enabled' => 1,
            'team_id' => null,
        ]);
    }

    private function makeTrainerProfile(string $name): TrainerProfile
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
}
