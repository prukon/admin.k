<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use App\Models\ChatParticipant;
use App\Models\ChatThread;
use App\Models\Location;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\DB;

/**
 * P1: AJAX-контракт (X-Requested-With) — JSON, errors[field], чат создаётся/не создаётся.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatTeamGroupThreadAjaxContractFeatureTest extends ChatTestCase
{
    use InteractsWithTeamGroupChats;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asAdmin();
        $this->grantPermission($this->user, 'groups.view');
        $this->grantPermission($this->user, 'trainers.view');
        $this->grantPermission($this->user, 'users.view');
        $this->grantPermission($this->user, 'users.group.update');
        $this->grantPermission($this->user, 'users.role.update');
        $this->grantPermission($this->user, 'users.activity.update');
        $this->grantPermission($this->user, 'users.name.update');
        $this->grantPermission($this->user, 'schedule.view');
        $this->grantPermission($this->user, 'locations.view');
        $this->grantPermission($this->user, 'messages.view');
    }

    public function test_ajax_store_team_returns_message_and_creates_group_chat(): void
    {
        $title = 'AjaxГруппа '.uniqid('', true);
        $this->postJson(
            route('admin.team.store'),
            $this->teamStorePayload($title),
            $this->teamChatAjaxHeaders()
        )
            ->assertOk()
            ->assertJsonPath('message', 'Группа создана успешно')
            ->assertJsonStructure(['message', 'team' => ['id', 'title']]);

        $team = Team::query()->where('title', $title)->firstOrFail();
        $thread = $this->teamThread($team);
        $this->assertTrue((bool) $thread->is_group);
        $this->assertSame($title, $thread->subject);
    }

    public function test_ajax_empty_title_returns_title_error_and_does_not_create_chat(): void
    {
        $this->postJson(
            route('admin.team.store'),
            $this->teamStorePayload(''),
            $this->teamChatAjaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);

        $this->assertNoTeamChatCreated();
    }

    public function test_ajax_duplicate_title_returns_title_error_and_does_not_create_second_chat(): void
    {
        $title = 'Дубль '.uniqid('', true);
        $team = $this->storeTeamViaAjax($title);

        $this->postJson(
            route('admin.team.store'),
            $this->teamStorePayload($title),
            $this->teamChatAjaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);

        $this->assertSame(1, ChatThread::query()->where('team_id', $team->id)->count());
        $this->assertSame(1, ChatThread::query()->whereNotNull('team_id')->count());
    }

    public function test_ajax_invalid_trainer_returns_field_error_and_creates_no_chat(): void
    {
        $this->postJson(
            route('admin.team.store'),
            $this->teamStorePayload('Тренер422 '.uniqid('', true), [
                'trainer_profile_ids' => [999999],
            ]),
            $this->teamChatAjaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['trainer_profile_ids.0']);

        $this->assertNoTeamChatCreated();
    }

    public function test_ajax_store_with_trainer_puts_trainer_into_chat_without_trainer_does_not(): void
    {
        $profile = $this->makeTrainerProfile('AjaxТренер');
        $withTrainer = $this->storeTeamViaAjax('СТренером '.uniqid('', true), [
            'trainer_profile_ids' => [$profile->id],
        ]);
        $this->assertUserInThread($profile->user, $this->teamThread($withTrainer));

        $without = $this->storeTeamViaAjax('БезТренера '.uniqid('', true));
        $this->assertUserNotInThread($profile->user, $this->teamThread($without));
    }

    public function test_ajax_rename_team_does_not_rename_chat_or_create_second_thread(): void
    {
        $title = 'ЧатИмя '.uniqid('', true);
        $team = $this->storeTeamViaAjax($title);

        $this->patchJson(route('admin.team.update', $team->id), [
            'title' => 'ГруппаИмя '.uniqid('', true),
            'is_enabled' => 1,
        ], $this->teamChatAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('message', 'Группа успешно обновлена');

        $this->assertSame($title, $this->teamThread($team)->subject);
        $this->assertSame(1, ChatThread::query()->where('team_id', $team->id)->count());
    }

    public function test_ajax_delete_team_keeps_chat_and_members(): void
    {
        $team = $this->storeTeamViaAjax('Удалить '.uniqid('', true));
        $student = $this->makePeer('DelStu_');
        app(TeamUserSyncService::class)->attachTeamForStudent($student, (int) $team->id);
        $thread = $this->teamThread($team);

        $this->deleteJson(route('admin.team.delete', $team))
            ->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertNotNull(ChatThread::query()->where('team_id', $team->id)->first());
        $this->assertNull($thread->fresh()->deleted_at);
        $this->assertUserInThread($student, $thread);
        $this->assertTrue($team->fresh()->trashed());
    }

    public function test_ajax_create_student_with_team_ids_adds_to_chat_validation_does_not(): void
    {
        $team = $this->storeTeamViaAjax('СторУченик '.uniqid('', true));
        $email = 'ajax-stu-'.uniqid('', true).'@example.test';

        $this->postJson(
            route('admin.user.store'),
            $this->studentStorePayload($email, ['team_ids' => [$team->id]]),
            $this->teamChatAjaxHeaders()
        )
            ->assertOk()
            ->assertJsonStructure(['message', 'user' => ['id']]);

        $student = User::query()->where('email', $email)->firstOrFail();
        $this->assertUserInThread($student, $this->teamThread($team));

        $before = ChatParticipant::query()->where('thread_id', $this->teamThread($team)->id)->count();
        $this->postJson(
            route('admin.user.store'),
            $this->studentStorePayload('bad-'.uniqid('', true).'@example.test', [
                'name' => '',
                'team_ids' => [$team->id],
            ]),
            $this->teamChatAjaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
        $this->assertSame(
            $before,
            ChatParticipant::query()->where('thread_id', $this->teamThread($team)->id)->count()
        );
    }

    public function test_ajax_update_student_teams_adds_to_new_chat_and_keeps_old(): void
    {
        $teamA = $this->storeTeamViaAjax('АпдА '.uniqid('', true));
        $teamB = $this->storeTeamViaAjax('АпдБ '.uniqid('', true));
        $student = $this->makePeer('UpdStu_');
        app(TeamUserSyncService::class)->attachTeamForStudent($student, (int) $teamA->id);

        $this->patchJson(route('admin.user.update', $student), [
            'name' => $student->name,
            'lastname' => $student->lastname,
            'is_enabled' => 1,
            'team_ids' => [$teamB->id],
        ], $this->teamChatAjaxHeaders())->assertOk();

        $this->assertUserInThread($student, $this->teamThread($teamA));
        $this->assertUserInThread($student, $this->teamThread($teamB));
    }

    public function test_ajax_disable_student_removes_from_all_chats_enable_returns_only_team_chat(): void
    {
        $team = $this->storeTeamViaAjax('ВыклHTTP '.uniqid('', true));
        $student = $this->makePeer('HttpDis_');
        $peer = $this->makePeer('HttpPeer_');
        app(TeamUserSyncService::class)->attachTeamForStudent($student, (int) $team->id);
        $private = $this->createThreadForUsers([(int) $student->id, (int) $peer->id], 'ЛичкаHTTP');

        $this->patchJson(route('admin.user.update', $student), [
            'name' => 'Выкл',
            'lastname' => 'Ученик',
            'is_enabled' => 0,
        ], $this->teamChatAjaxHeaders())->assertOk();

        $this->assertUserNotInThread($student, $this->teamThread($team));
        $this->assertUserNotInThread($student, $private);
        $this->assertNull($private->fresh()->deleted_at);

        $this->patchJson(route('admin.user.update', $student), [
            'name' => 'Выкл',
            'lastname' => 'Ученик',
            'is_enabled' => 1,
        ], $this->teamChatAjaxHeaders())->assertOk();

        $this->assertUserInThread($student, $this->teamThread($team));
        $this->assertUserNotInThread($student, $private);
    }

    public function test_ajax_promote_user_to_admin_adds_to_all_school_team_chats(): void
    {
        $teamA = $this->storeTeamViaAjax('ПромА '.uniqid('', true));
        $teamB = $this->storeTeamViaAjax('ПромБ '.uniqid('', true));
        $staff = $this->createUserWithRole('user', $this->partner, [
            'name' => 'Станет',
            'lastname' => 'АдминомHTTP',
            'is_enabled' => 1,
            'team_id' => null,
        ]);

        $this->patchJson(route('admin.user.update', $staff), [
            'name' => $staff->name,
            'lastname' => $staff->lastname,
            'is_enabled' => 1,
            'role_id' => $this->roleId('admin'),
        ], $this->teamChatAjaxHeaders())->assertOk();

        $this->assertUserInThread($staff->fresh(), $this->teamThread($teamA));
        $this->assertUserInThread($staff->fresh(), $this->teamThread($teamB));
    }

    public function test_ajax_create_admin_joins_existing_team_chats(): void
    {
        $team = $this->storeTeamViaAjax('НовАдмин '.uniqid('', true));

        $this->postJson(route('admin.administrators.store'), [
            'name' => 'Новый',
            'lastname' => 'АдминAjax',
            'email' => 'team-chat-admin-'.uniqid('', true).'@example.test',
            'is_enabled' => 1,
        ], $this->teamChatAjaxHeaders())->assertOk();

        $newAdmin = User::query()
            ->where('lastname', 'АдминAjax')
            ->where('partner_id', $this->partner->id)
            ->firstOrFail();
        $this->assertUserInThread($newAdmin, $this->teamThread($team));
    }

    public function test_ajax_store_trainer_with_team_ids_adds_to_chat(): void
    {
        $team = $this->storeTeamViaAjax('ТренAjax '.uniqid('', true));
        $email = 'ajax-tr-'.uniqid('', true).'@example.test';

        $this->postJson(route('admin.trainers.store'), [
            'lastname' => 'AjaxTr',
            'name' => 'Чат',
            'email' => $email,
            'password' => 'password123',
            'is_enabled' => 1,
            'team_ids' => [$team->id],
        ], $this->teamChatAjaxHeaders())->assertOk();

        $trainer = User::query()->where('email', $email)->firstOrFail();
        $this->assertUserInThread($trainer, $this->teamThread($team));
    }

    public function test_ajax_schedule_sync_teams_adds_student_invalid_team_returns_field_error(): void
    {
        $team = $this->storeTeamViaAjax('ЖурнAjax '.uniqid('', true));
        $student = $this->makePeer('AjaxSched_');

        $this->postJson(route('user.sync.teams', $student), [
            'team_ids' => [$team->id],
        ], $this->teamChatAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['team_ids', 'teams_label']);

        $this->assertUserInThread($student, $this->teamThread($team));

        $this->postJson(route('user.sync.teams', $student), [
            'team_ids' => [999999],
        ], $this->teamChatAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_ids.0']);
        $this->assertUserInThread($student, $this->teamThread($team));
    }

    public function test_ajax_cabinet_attach_adds_to_chat_invalid_team_does_not(): void
    {
        $location = Location::factory()->forPartner((int) $this->partner->id)->create();
        $current = $this->storeTeamViaAjax('КабТек '.uniqid('', true), ['location_id' => $location->id]);
        $candidate = $this->storeTeamViaAjax('КабНов '.uniqid('', true), ['location_id' => $location->id]);
        $student = $this->makePeer('AjaxCab_');
        app(TeamUserSyncService::class)->attachTeamForStudent($student, (int) $current->id);
        $this->grantPermission($student, 'account.user.team.update');

        $this->actingInPartner($student);
        $this->postJson(route('cabinet.teams.attach'), [
            'team_id' => $candidate->id,
        ])->assertOk()->assertJsonPath('success', true);
        $this->assertUserInThread($student, $this->teamThread($candidate));

        $this->postJson(route('cabinet.teams.attach'), [
            'team_id' => 999999,
        ])->assertStatus(422)->assertJsonValidationErrors(['team_id']);
    }

    public function test_ajax_inbox_lists_team_chat_as_group_with_null_peer_and_classmate_opens_private(): void
    {
        $title = 'Инбокс '.uniqid('', true);
        $team = $this->storeTeamViaAjax($title);
        $student = $this->makePeer('InboxStu_');
        app(TeamUserSyncService::class)->attachTeamForStudent($student, (int) $team->id);
        $thread = $this->teamThread($team);

        $row = $this->inboxRowFor($this->user, (int) $thread->id);
        $this->assertNotNull($row);
        $this->assertTrue((bool) $row['is_group']);
        $this->assertNull($row['peer_id']);
        $this->assertSame($title, $row['title']);
        $this->assertSame(0, (int) $row['unread_count']);

        $this->actingInPartner($this->user);
        $created = $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $student->id,
        ]);
        $this->assertContains($created->getStatusCode(), [200, 201]);
        $this->assertNotSame((int) $thread->id, (int) $created->json('thread_id'));
        $created->assertJsonPath('thread.is_group', false);
        $created->assertJsonPath('thread.peer_id', $student->id);
    }

    public function test_custom_role_is_not_seeded_into_team_chat(): void
    {
        $custom = new Role();
        $custom->name = 'custom-chat-'.uniqid('', true);
        $custom->label = 'Кастом';
        $custom->is_sistem = 0;
        $custom->is_visible = 1;
        $custom->save();
        DB::table('partner_role')->insert([
            'partner_id' => $this->partner->id,
            'role_id' => $custom->id,
        ]);
        $staff = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $custom->id,
            'is_enabled' => 1,
            'team_id' => null,
        ]);

        $team = $this->storeTeamViaAjax('КастомРоль '.uniqid('', true));
        $this->assertUserNotInThread($staff, $this->teamThread($team));
    }
}
