<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\Team;
use App\Models\User;
use App\Services\TeamUserSyncService;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Пустое поле «Сортировка» у группы (order_by = NULL): алфавит на обеих вкладках цен.
 * Ученики своего order_by не имеют и всегда идут по фамилии.
 */
final class SettingPricesAlphabetSortFeatureTest extends CrmTestCase
{
    private TeamUserSyncService $sync;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asAdmin();
        $this->grantLessonPackageTypePermissions();
        $this->sync = app(TeamUserSyncService::class);
    }

    public function test_monthly_teams_without_manual_order_are_alphabetical_by_title(): void
    {
        $yantar = $this->team('Янтарь');
        $zhuk = $this->team('Жук');
        $yolka = $this->team('Ёлка');
        $almaz = $this->team('алмаз');

        $html = $this->get(route('admin.settingPrices.indexMenu'))
            ->assertOk()
            ->assertViewIs('admin.SettingPrices.index');

        $this->assertSame(
            [$almaz->id, $yolka->id, $zhuk->id, $yantar->id],
            $html->viewData('allTeams')->pluck('id')->map(static fn ($id) => (int) $id)->all()
        );
        $this->assertHtmlOrder($html->getContent(), [
            '1. алмаз',
            '2. Ёлка',
            '3. Жук',
            '4. Янтарь',
        ]);
    }

    public function test_users_tab_without_manual_team_order_sorts_groups_by_title_and_students_by_lastname(): void
    {
        $yantar = $this->team('Янтарь');
        $zhuk = $this->team('Жук');
        $yolka = $this->team('Ёлка');
        $almaz = $this->team('алмаз');

        $yakovlev = $this->student('Яковлев', 'Пётр', $almaz);
        $yolkin = $this->student('Ёлкин', 'Илья', $almaz);
        $ivanovBoris = $this->student('Иванов', 'Борис', $almaz);
        $ivanovAnna = $this->student('Иванов', 'Анна', $almaz);
        $almazova = $this->student('алмазова', 'Анна', $almaz);

        $html = $this->get(route('admin.settingPrices.users'))
            ->assertOk()
            ->assertViewHas('activeTab', 'users');

        $this->assertSame(
            [$almaz->id, $yolka->id, $zhuk->id, $yantar->id],
            $html->viewData('allTeams')->pluck('id')->map(static fn ($id) => (int) $id)->all()
        );
        $this->assertSame(
            [$almazova->id, $yolkin->id, $ivanovAnna->id, $ivanovBoris->id, $yakovlev->id],
            $html->viewData('users')->pluck('id')->map(static fn ($id) => (int) $id)->all()
        );
        $this->assertHtmlOrder($html->getContent(), [
            '>алмаз</option>',
            '>Ёлка</option>',
            '>Жук</option>',
            '>Янтарь</option>',
            '1. алмазова Анна',
            '2. Ёлкин Илья',
            '3. Иванов Анна',
            '4. Иванов Борис',
            '5. Яковлев Пётр',
        ]);
    }

    public function test_monthly_students_of_unordered_team_are_alphabetical_by_lastname(): void
    {
        $team = $this->team('алмаз');
        $yakovlev = $this->student('Яковлев', 'Пётр', $team);
        $yolkin = $this->student('Ёлкин', 'Илья', $team);
        $ivanovBoris = $this->student('Иванов', 'Борис', $team);
        $ivanovAnna = $this->student('Иванов', 'Анна', $team);
        $almazova = $this->student('алмазова', 'Анна', $team);

        $response = $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->postJson(route('getTeamPrice'), [
            'teamId' => $team->id,
            'selectedDate' => 'Февраль 2026',
        ])->assertOk()->assertJsonPath('success', true);

        $expected = [$almazova->id, $yolkin->id, $ivanovAnna->id, $ivanovBoris->id, $yakovlev->id];
        $this->assertSame(
            $expected,
            collect($response->json('usersTeam'))->pluck('id')->map(static fn ($id) => (int) $id)->all()
        );
        $this->assertSame(
            $expected,
            collect($response->json('usersPrice'))->pluck('user_id')->map(static fn ($id) => (int) $id)->all()
        );
    }

    private function team(string $title): Team
    {
        return Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => $title,
            'order_by' => null,
            'deleted_at' => null,
        ]);
    }

    private function student(string $lastname, string $name, Team $team): User
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
            'lastname' => $lastname,
            'name' => $name,
        ]);
        $this->sync->syncTeamsForStudent($student, [(int) $team->id]);

        return $student;
    }

    /**
     * @param  list<string>  $needles
     */
    private function assertHtmlOrder(string $html, array $needles): void
    {
        $cursor = -1;
        foreach ($needles as $needle) {
            $pos = strpos($html, $needle);
            $this->assertNotFalse($pos, 'В HTML нет фрагмента: '.$needle);
            $this->assertGreaterThan($cursor, $pos, 'Фрагмент стоит не по алфавиту: '.$needle);
            $cursor = $pos;
        }
    }
}
