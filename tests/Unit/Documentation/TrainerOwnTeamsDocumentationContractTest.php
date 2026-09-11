<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#groups-own-index совпадает с правом groups.own и TrainerOwnTeamsScope.
 */
final class TrainerOwnTeamsDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_groups_own(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="groups-own-index"', $html);
        $start = strpos($html, 'id="groups-own-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="reports-tbank-payments-view-modes-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('groups.own', $chunk);
        $this->assertStringContainsString('Только свои группы (тренер)', $chunk);
        $this->assertStringContainsString('role_base_permissions', $chunk);
        $this->assertStringContainsString('team_trainer', $chunk);
        $this->assertStringContainsString('trainer_profiles', $chunk);
        $this->assertStringContainsString('Gate::before', $chunk);
        $this->assertStringContainsString('ученики своих групп', $chunk);
        $this->assertStringContainsString('422', $chunk);
        $this->assertStringContainsString('merge', $chunk);
        $this->assertStringContainsString('TrainerOwnTeamsScope', $chunk);
        $this->assertStringContainsString('AllowedActorTeam', $chunk);
        $this->assertStringContainsString('2026_09_11_021700_add_groups_own_permission.php', $chunk);
        $this->assertStringContainsString('TrainerOwnTeamsScopeFeatureTest', $chunk);
        $this->assertStringContainsString('TrainerOwnTeamsAccessFeatureTest', $chunk);
        $this->assertStringContainsString('TrainerOwnTeamsAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('TrainerOwnTeamsNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('TrainerOwnTeamsMarkupFeatureTest', $chunk);
        $this->assertStringContainsString('TrainerOwnTeamsFullAccessFeatureTest', $chunk);
        $this->assertStringContainsString('BladeInlineJsSyntaxTest', $chunk);
        $this->assertStringContainsString('TrainerOwnTeamsDocumentationContractTest', $chunk);
        $this->assertStringContainsString('/docs/documentation/groups-own', $chunk);
        $this->assertStringContainsString('/docs/documentation/admin-trainers#groups-own', $chunk);
        $this->assertStringContainsString('partners-permissions', $chunk);
    }

    public function test_groups_own_page_and_related_docs(): void
    {
        $page = $this->docFile('groups-own.html');
        $index = $this->docFile('index.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('id="when"', $page);
        $this->assertStringContainsString('TrainerOwnTeamsScope', $page);
        $this->assertStringContainsString('AllowedActorTeam', $page);
        $this->assertStringContainsString('is_visible=1', $page);
        $this->assertStringContainsString('sort_order=42', $page);
        $this->assertStringContainsString('mergeSubmittedStudentTeamIds', $page);
        $this->assertStringContainsString('TrainerOwnTeamsScopeFeatureTest', $page);
        $this->assertStringContainsString('TrainerOwnTeamsAccessFeatureTest', $page);
        $this->assertStringContainsString('TrainerOwnTeamsAjaxContractFeatureTest', $page);
        $this->assertStringContainsString('TrainerOwnTeamsNonAjaxSafetyNetFeatureTest', $page);
        $this->assertStringContainsString('TrainerOwnTeamsMarkupFeatureTest', $page);
        $this->assertStringContainsString('TrainerOwnTeamsFullAccessFeatureTest', $page);
        $this->assertStringContainsString('test_groups_own_team_filters_and_edit_modal_fill_from_json_on_both_open_paths', $page);
        $this->assertStringContainsString('/doc#groups-own-index', $page);

        $this->assertStringContainsString('href="/docs/documentation/groups-own"', $index);
        $this->assertStringContainsString("'groups-own'", $controller);

        $trainers = $this->docFile('admin-trainers.html');
        $this->assertStringContainsString('id="groups-own"', $trainers);
        $this->assertStringContainsString('/docs/documentation/groups-own', $trainers);

        $partners = $this->docFile('partners-permissions.html');
        $this->assertStringContainsString('groups.own', $partners);
        $this->assertStringContainsString('не входит', $partners);

        $users = $this->docFile('admin-users.html');
        $this->assertStringContainsString('groups.own', $users);

        $teams = $this->docFile('admin-teams.html');
        $this->assertStringContainsString('groups.own', $teams);

        $journal = $this->docFile('schedule-journal.html');
        $this->assertStringContainsString('groups.own', $journal);

        $chat = $this->docFile('chat.html');
        $this->assertStringContainsString('groups.own', $chat);

        $cabinet = $this->docFile('dashboard-cabinet.html');
        $this->assertStringContainsString('groups.own', $cabinet);
    }

    public function test_live_code_matches_documented_groups_own(): void
    {
        $root = dirname(__DIR__, 3);
        $seeder = (string) file_get_contents($root.'/database/seeders/PermissionSeeder.php');
        $config = (string) file_get_contents($root.'/config/role_base_permissions.php');
        $hints = (string) file_get_contents($root.'/config/permission_capability_hints.php');
        $gate = (string) file_get_contents($root.'/app/Providers/AuthServiceProvider.php');
        $scope = (string) file_get_contents($root.'/app/Services/TrainerOwnTeamsScope.php');
        $rule = (string) file_get_contents($root.'/app/Rules/AllowedActorTeam.php');
        $migration = (string) file_get_contents(
            $root.'/database/migrations/2026_09_11_021700_add_groups_own_permission.php'
        );

        $this->assertStringContainsString("'name' => 'groups.own'", $seeder);
        $this->assertStringContainsString("'is_visible' => 1", $seeder);
        $this->assertStringContainsString("'sort_order' => 42", $seeder);
        $this->assertStringContainsString('Только свои группы (тренер)', $seeder);

        $this->assertSame(
            0,
            preg_match_all("/^\\s+'groups\\.own',/m", $config)
        );

        $this->assertStringContainsString("'groups.own' => [", $hints);
        $this->assertStringContainsString("Gate::define('groups.own'", $gate);
        $this->assertStringContainsString('PERMISSION = \'groups.own\'', $scope);
        $this->assertStringContainsString('function mergeSubmittedStudentTeamIds', $scope);
        $this->assertStringContainsString('Выберите группу из списка.', $rule);
        $this->assertStringContainsString("'is_visible'          => 1", $migration);
        $this->assertStringContainsString('groups.own', $migration);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
