<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use App\Models\Team;
use App\Models\User;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * P1: фильтр контактов чата по группе — HTTP/API, права, 422 под полем, изоляция партнёра.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatContactsTeamFilterFeatureTest extends ChatTestCase
{
    public function test_guest_cannot_open_chat_or_filter_contacts_by_team(): void
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        Auth::logout();

        $page = $this->get(route('chat.index'));
        $this->assertNotSame(500, $page->getStatusCode());
        $page->assertRedirect();

        $json = $this->getJson(route('chat.api.users', ['team_id' => $team->id]));
        $this->assertNotSame(500, $json->getStatusCode());
        $json->assertUnauthorized();

        $html = $this->get(route('chat.api.users', ['team_id' => $team->id]));
        $this->assertNotSame(500, $html->getStatusCode());
        $this->assertTrue($html->isRedirect(), 'Гость HTML GET /chat/api/users должен редиректить');
        $this->assertGuest();
    }

    public function test_user_without_messages_view_cannot_filter_contacts_by_team(): void
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $denied = $this->createUserWithoutPermission('messages.view', $this->partner);
        $this->actingInPartner($denied);

        $page = $this->get(route('chat.index'));
        $this->assertSame(403, $page->getStatusCode());

        $json = $this->getJson(route('chat.api.users', ['team_id' => $team->id]));
        $this->assertSame(403, $json->getStatusCode());

        $html = $this->get(route('chat.api.users', ['team_id' => $team->id]));
        $this->assertSame(403, $html->getStatusCode());
    }

    public function test_user_with_messages_view_can_open_contacts_and_filter_by_own_team(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'ЧатДоступГруппа_'.uniqid('', true),
        ]);
        $inTeam = $this->makePeer('InTeamAccess_', ['team_id' => null]);
        $outTeam = $this->makePeer('OutTeamAccess_', ['team_id' => null]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($inTeam, [(int) $team->id]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($outTeam, []);

        $page = $this->get(route('chat.index'));
        $this->assertSame(200, $page->getStatusCode());
        $page->assertSee('id="contactsTeamFilter"', false);
        $page->assertSee((string) $team->title, false);

        $json = $this->getJson(route('chat.api.users', ['team_id' => $team->id]));
        $this->assertSame(200, $json->getStatusCode());
        $this->assertNotSame('', trim((string) $json->getContent()));
        $ids = collect($json->json())->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertContains((int) $inTeam->id, $ids);
        $this->assertNotContains((int) $outTeam->id, $ids);
    }

    public function test_native_get_contacts_filter_returns_json_list_not_empty_page(): void
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $inTeam = $this->makePeer('NativeFilter_');
        app(TeamUserSyncService::class)->syncTeamsForStudent($inTeam, [(int) $team->id]);

        $response = $this->get(route('chat.api.users', ['team_id' => $team->id]));
        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertOk();
        $this->assertStringContainsString(
            'application/json',
            (string) $response->headers->get('content-type')
        );
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertTrue(array_is_list($response->json()));
        $this->assertNotNull(collect($response->json())->firstWhere('id', $inTeam->id));
    }

    public function test_native_invalid_team_filter_does_not_return_empty_200_or_server_error(): void
    {
        $response = $this->from(route('chat.index'))
            ->get(route('chat.api.users', ['team_id' => 'abc']));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Невалидный team_id не должен отдавать список 200');

        if ($response->isRedirect()) {
            $response->assertSessionHasErrors(['team_id']);

            return;
        }

        $response
            ->assertStatus(422)
            ->assertJsonPath('errors.team_id.0', 'Выберите группу из списка.');
    }

    public function test_invalid_team_filter_returns_russian_error_under_team_id(): void
    {
        $this->getJson(route('chat.api.users', ['team_id' => 'abc']))
            ->assertStatus(422)
            ->assertJsonPath('errors.team_id.0', 'Выберите группу из списка.');

        $this->getJson(route('chat.api.users', ['team_id' => 0]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_id']);

        $this->getJson(route('chat.api.users', ['team_id' => '-1']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_id']);

        $this->getJson(route('chat.api.users', ['team_id' => str_repeat('1', 21)]))
            ->assertStatus(422)
            ->assertJsonPath('errors.team_id.0', 'Некорректное значение фильтра по группе.');

        $this->getJson(route('chat.api.users', ['team_id' => ['1']]))
            ->assertStatus(422)
            ->assertJsonPath('errors.team_id.0', 'Группа должна быть текстом.');
    }

    public function test_deleted_or_foreign_team_filter_returns_field_error_not_empty_list(): void
    {
        $deleted = Team::factory()->create(['partner_id' => $this->partner->id]);
        $deleted->delete();
        $foreign = Team::factory()->create(['partner_id' => $this->foreignPartner->id]);

        $this->getJson(route('chat.api.users', ['team_id' => $deleted->id]))
            ->assertStatus(422)
            ->assertJsonPath('errors.team_id.0', 'Выберите группу из списка.');

        $this->getJson(route('chat.api.users', ['team_id' => $foreign->id]))
            ->assertStatus(422)
            ->assertJsonPath('errors.team_id.0', 'Выберите группу из списка.');
    }

    public function test_filtering_by_group_shows_only_students_of_that_group(): void
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $other = Team::factory()->create(['partner_id' => $this->partner->id]);

        $student = $this->makePeer('StudIn_');
        $otherStudent = $this->makePeer('StudOut_');
        $noTeam = $this->makePeer('StudNone_');
        $trainer = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('trainer'),
            'is_enabled' => 1,
            'name' => 'TrainerFilter_'.uniqid('', true),
        ]);
        $admin = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('admin'),
            'is_enabled' => 1,
            'name' => 'AdminFilter_'.uniqid('', true),
        ]);

        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $team->id]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($otherStudent, [(int) $other->id]);
        DB::table('team_user')->insert([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'user_id' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ids = collect($this->getJson(route('chat.api.users', ['team_id' => $team->id]))->assertOk()->json())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertContains((int) $student->id, $ids);
        $this->assertNotContains((int) $otherStudent->id, $ids);
        $this->assertNotContains((int) $noTeam->id, $ids);
        $this->assertNotContains((int) $trainer->id, $ids);
        $this->assertNotContains((int) $admin->id, $ids);
        $this->assertNotContains((int) $this->user->id, $ids);
    }

    public function test_without_team_filter_trainers_still_appear_in_contacts(): void
    {
        $trainer = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('trainer'),
            'is_enabled' => 1,
            'name' => 'TrainerAll_'.uniqid('', true),
        ]);

        $all = collect($this->getJson(route('chat.api.users'))->assertOk()->json());
        $this->assertNotNull($all->firstWhere('id', $trainer->id));

        $empty = collect($this->getJson(route('chat.api.users', ['team_id' => '']))->assertOk()->json());
        $this->assertNotNull($empty->firstWhere('id', $trainer->id));
    }

    public function test_none_filter_shows_people_without_groups_including_staff(): void
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $inTeam = $this->makePeer('HasTeam_');
        $noTeam = $this->makePeer('NoGroup_');
        $trainer = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('trainer'),
            'is_enabled' => 1,
            'name' => 'TrainerNone_'.uniqid('', true),
        ]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($inTeam, [(int) $team->id]);

        $ids = collect($this->getJson(route('chat.api.users', ['team_id' => 'none']))->assertOk()->json())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertContains((int) $noTeam->id, $ids);
        $this->assertContains((int) $trainer->id, $ids);
        $this->assertNotContains((int) $inTeam->id, $ids);
    }

    public function test_student_in_two_groups_appears_when_filtering_either(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id]);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);
        $kid = $this->makePeer('TwoTeams_');
        app(TeamUserSyncService::class)->syncTeamsForStudent($kid, [(int) $teamA->id, (int) $teamB->id]);

        $this->assertNotNull(
            collect($this->getJson(route('chat.api.users', ['team_id' => $teamA->id]))->assertOk()->json())
                ->firstWhere('id', $kid->id)
        );
        $this->assertNotNull(
            collect($this->getJson(route('chat.api.users', ['team_id' => $teamB->id]))->assertOk()->json())
                ->firstWhere('id', $kid->id)
        );
    }

    public function test_disabled_student_and_self_are_hidden_even_when_filtering_by_their_group(): void
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $disabled = $this->makePeer('OffInTeam_', ['is_enabled' => 0]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($disabled, [(int) $team->id]);
        DB::table('team_user')->insert([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'user_id' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ids = collect($this->getJson(route('chat.api.users', ['team_id' => $team->id]))->assertOk()->json())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertNotContains((int) $disabled->id, $ids);
        $this->assertNotContains((int) $this->user->id, $ids);
    }

    public function test_search_and_team_filter_together_do_not_leak_other_group(): void
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $other = Team::factory()->create(['partner_id' => $this->partner->id]);
        $match = $this->makePeer('ComboIn_', ['lastname' => 'УникКомбо_'.uniqid('', true)]);
        $sameLastOtherTeam = $this->makePeer('ComboOut_', ['lastname' => $match->lastname]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($match, [(int) $team->id]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($sameLastOtherTeam, [(int) $other->id]);

        $rows = collect($this->getJson(route('chat.api.users', [
            'team_id' => $team->id,
            'q' => $match->lastname,
        ]))->assertOk()->json());

        $this->assertNotNull($rows->firstWhere('id', $match->id));
        $this->assertNull($rows->firstWhere('id', $sameLastOtherTeam->id));
    }

    public function test_contacts_filter_wrong_methods_are_not_empty_200(): void
    {
        foreach (['POST', 'PATCH', 'DELETE'] as $method) {
            $json = $this->json($method, route('chat.api.users'), ['team_id' => 'none']);
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON не 500');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' не пустой 200');
            $this->assertSame(405, $json->getStatusCode(), $method.' должен быть 405');

            $html = $this->call($method, route('chat.api.users'), ['team_id' => 'none']);
            $this->assertNotSame(500, $html->getStatusCode(), $method.' HTML не 500');
            $this->assertNotSame(200, $html->getStatusCode(), $method.' HTML не пустой 200');
            $this->assertSame(405, $html->getStatusCode(), $method.' HTML должен быть 405');
        }
    }

    public function test_contacts_modal_lists_groups_in_order_and_defaults_to_all(): void
    {
        $later = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'order_by' => 20,
            'title' => 'ЧатПозже_'.uniqid('', true),
        ]);
        $earlier = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'order_by' => 1,
            'title' => 'ЧатРаньше_'.uniqid('', true),
        ]);
        $deleted = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'ЧатУдалена_'.uniqid('', true),
        ]);
        $deleted->delete();
        $foreign = Team::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'title' => 'ЧатЧужая_'.uniqid('', true),
        ]);

        $html = $this->get(route('chat.index'))->assertOk()->getContent();
        $modalStart = strpos($html, 'id="contactsModal"');
        $this->assertNotFalse($modalStart);
        $modalEnd = strpos($html, 'id="peerCardModal"');
        $this->assertNotFalse($modalEnd);
        $modal = substr($html, $modalStart, $modalEnd - $modalStart);

        $filterPos = strpos($modal, 'id="contactsTeamFilter"');
        $teamErrPos = strpos($modal, 'id="contactsTeamError"');
        $searchPos = strpos($modal, 'id="contactsSearch"');
        $allPos = strpos($modal, 'Все группы');
        $nonePos = strpos($modal, 'Без группы');
        $earlyPos = strpos($modal, (string) $earlier->title);
        $latePos = strpos($modal, (string) $later->title);

        $this->assertNotFalse($filterPos);
        $this->assertNotFalse($teamErrPos);
        $this->assertNotFalse($searchPos);
        $this->assertLessThan($teamErrPos, $filterPos, 'Ошибка группы — под селектом');
        $this->assertLessThan($searchPos, $teamErrPos, 'Поиск — после фильтра и его ошибки');
        $this->assertLessThan($nonePos, $allPos, 'Сначала «Все группы», потом «Без группы»');
        $this->assertLessThan($earlyPos, $nonePos);
        $this->assertLessThan($latePos, $earlyPos, 'Группы в селекте по order_by');
        $this->assertStringContainsString('value=""', $modal);
        $this->assertStringContainsString('value="none"', $modal);
        $this->assertStringContainsString('value="'.(int) $earlier->id.'"', $modal);
        $this->assertDoesNotMatchRegularExpression(
            '/<option[^>]+selected/i',
            $modal,
            'При первом открытии страницы ни одна группа не selected — дефолт «Все группы»'
        );
        $this->assertStringNotContainsString((string) $deleted->title, $modal);
        $this->assertStringNotContainsString((string) $foreign->title, $modal);
        $this->assertStringNotContainsString('modal-xl', $modal);
        $this->assertStringNotContainsString('modal-fullscreen', $modal);
    }
}
