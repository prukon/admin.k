<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use App\Models\ChatThread;
use App\Models\Location;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamUserSyncService;

/**
 * P1: нативный POST без X-Requested-With — 302 на раздел, запись и чат есть, не сырой JSON 200.
 *
 * UX-баг: если JS модалки не перехватил submit, браузер не должен остаться на белом JSON.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatTeamGroupThreadNonAjaxSafetyNetFeatureTest extends ChatTestCase
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
        $this->grantPermission($this->user, 'schedule.view');
        $this->grantPermission($this->user, 'locations.view');
        $this->grantPermission($this->user, 'messages.view');
    }

    public function test_non_ajax_store_team_redirects_to_teams_and_creates_chat_even_when_inactive(): void
    {
        $title = 'НативСоздать '.uniqid('', true);
        $response = $this->from(route('admin.team.index'))
            ->post(route('admin.team.store'), $this->teamStorePayload($title, [
                'is_enabled' => 0,
            ]));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Нативный POST группы не должен быть белым JSON 200');
        $response->assertRedirect(route('admin.team.index'));

        $team = Team::query()->where('title', $title)->first();
        $this->assertNotNull($team);
        $this->assertSame(0, (int) $team->is_enabled);
        $thread = $this->teamThread($team);
        $this->assertTrue((bool) $thread->is_group);
        $this->assertSame($title, $thread->subject);
        $this->assertUserInThread($this->user, $thread);
    }

    public function test_json_post_without_xhr_header_redirects_and_still_creates_chat(): void
    {
        $title = 'JsonБезXhr '.uniqid('', true);
        $response = $this->postJson(route('admin.team.store'), $this->teamStorePayload($title));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'postJson без X-Requested-With не ajax() — не сырой 200');
        $response->assertRedirect(route('admin.team.index'));
        $this->assertNotNull($this->teamThread(Team::query()->where('title', $title)->firstOrFail()));
    }

    public function test_non_ajax_empty_title_redirects_with_title_error_and_creates_no_chat(): void
    {
        $response = $this->from(route('admin.team.index'))
            ->post(route('admin.team.store'), $this->teamStorePayload(''));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertRedirect(route('admin.team.index'));
        $response->assertSessionHasErrors(['title']);
        $this->assertNoTeamChatCreated();
    }

    public function test_non_ajax_store_student_with_team_ids_redirects_and_adds_to_chat(): void
    {
        $team = $this->storeTeamViaAjax('УченикНатив '.uniqid('', true));
        $email = 'nonajax-stu-'.uniqid('', true).'@example.test';

        $response = $this->from(route('admin.user1'))
            ->post(route('admin.user.store'), $this->studentStorePayload($email, [
                'team_ids' => [$team->id],
            ]));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Нативное создание ученика не белый JSON 200');
        $response->assertRedirect(route('admin.user1'));

        $student = User::query()->where('email', $email)->firstOrFail();
        $this->assertUserInThread($student, $this->teamThread($team));
    }

    public function test_non_ajax_sync_teams_from_schedule_redirects_and_adds_student_to_chat(): void
    {
        $team = $this->storeTeamViaAjax('ЖурналНатив '.uniqid('', true));
        $student = $this->makePeer('SchedStu_');

        $response = $this->from(route('schedule.index'))
            ->post(route('user.sync.teams', $student), [
                'team_ids' => [$team->id],
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertRedirect(route('schedule.index'));
        $this->assertUserInThread($student, $this->teamThread($team));
    }

    public function test_non_ajax_store_trainer_with_team_ids_redirects_and_adds_to_chat(): void
    {
        $team = $this->storeTeamViaAjax('ТренерНатив '.uniqid('', true));
        $email = 'nonajax-tr-'.uniqid('', true).'@example.test';

        $response = $this->from(route('admin.trainers.index'))
            ->post(route('admin.trainers.store'), [
                'lastname' => 'Натив',
                'name' => 'ТренерЧат',
                'email' => $email,
                'password' => 'password123',
                'is_enabled' => 1,
                'team_ids' => [$team->id],
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertRedirect(route('admin.trainers.index'));

        $trainer = User::query()->where('email', $email)->firstOrFail();
        $this->assertUserInThread($trainer, $this->teamThread($team));
    }

    public function test_non_ajax_cabinet_attach_redirects_and_adds_student_to_chat(): void
    {
        $location = Location::factory()->forPartner((int) $this->partner->id)->create();
        $current = $this->storeTeamViaAjax('ЛкТекущая '.uniqid('', true), [
            'location_id' => $location->id,
        ]);
        $candidate = $this->storeTeamViaAjax('ЛкНовая '.uniqid('', true), [
            'location_id' => $location->id,
        ]);
        $student = $this->makePeer('CabStu_');
        app(TeamUserSyncService::class)->attachTeamForStudent($student, (int) $current->id);
        $this->grantPermission($student, 'account.user.team.update');
        $this->grantPermission($student, 'dashboard.view');

        $this->actingInPartner($student);
        $response = $this->from(route('dashboard'))
            ->post(route('cabinet.teams.attach'), [
                'team_id' => $candidate->id,
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Нативный attach не белый JSON 200');
        $this->assertTrue($response->isRedirect());
        $this->assertUserInThread($student, $this->teamThread($candidate));
        $this->assertUserInThread($student, $this->teamThread($current));
    }

    public function test_non_ajax_rename_team_keeps_chat_title_and_does_not_duplicate(): void
    {
        $title = 'ИмяЧата '.uniqid('', true);
        $team = $this->storeTeamViaAjax($title);
        $newTitle = 'НовоеИмя '.uniqid('', true);

        $response = $this->from(route('admin.team.index'))
            ->patch(route('admin.team.update', $team->id), [
                'title' => $newTitle,
                'is_enabled' => 1,
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame('', trim((string) $response->getContent()));
        $team->refresh();
        $this->assertSame($newTitle, $team->title);
        $this->assertSame($title, $this->teamThread($team)->subject);
        $this->assertSame(1, ChatThread::query()->where('team_id', $team->id)->count());
    }
}
