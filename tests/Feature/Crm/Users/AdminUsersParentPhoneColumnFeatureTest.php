<?php

namespace Tests\Feature\Crm\Users;

use App\Models\ParentProfile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Колонка «Телефон родителя» и переименование «Телефон» → «Телефон ученика» на /admin/users.
 *
 * @see /docs/documentation/admin-users.html §2 (список) / §2.1 (родитель)
 */
final class AdminUsersParentPhoneColumnFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    private function grantPermission(User $actor, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $actor->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private function actingAsUsersViewer(bool $withContractsView = false): User
    {
        $actor = $this->createUserWithoutPermission('contracts.view', $this->partner);
        $this->actingAs($actor);
        $this->grantPermission($actor, 'users.view');

        if ($withContractsView) {
            $this->grantPermission($actor, 'contracts.view');
        }

        return $actor;
    }

    private function studentRoleId(): int
    {
        return (int) Role::query()->where('name', 'user')->value('id');
    }

    private function createStudent(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
        ], $attributes));
    }

    /**
     * @return list<string>
     */
    private function usersListColumnKeys(bool $withContracts, bool $canViewSex, bool $canViewComment): array
    {
        $keys = ['rownum', 'avatar', 'name', 'parent', 'parent_phone'];

        if ($withContracts) {
            $keys[] = 'contract';
        }

        $keys[] = 'teams';
        $keys[] = 'birthday';

        if ($canViewSex) {
            $keys[] = 'sex';
        }

        if ($canViewComment) {
            $keys[] = 'comment';
        }

        return array_merge($keys, ['email', 'phone', 'status_label', 'actions']);
    }

    private function usersListColumnIndex(string $key, bool $withContracts = false): int
    {
        /** @var User $actor */
        $actor = Auth::user();
        $keys = $this->usersListColumnKeys(
            $withContracts,
            (bool) $actor?->can('users.sex'),
            (bool) $actor?->can('users.comment'),
        );

        $index = array_search($key, $keys, true);
        $this->assertNotFalse($index, "Column key '{$key}' not found in users list columns map");

        return (int) $index;
    }

    private function fetchUsersDataRowById(int $userId): ?array
    {
        $response = $this->getJson('/admin/users/data?draw=1&start=0&length=100&id=' . $userId);

        $response->assertOk();

        return collect($response->json('data'))->firstWhere('id', $userId);
    }

    // --- Доступ ---

    public function test_guest_cannot_open_users_page_or_parent_phone_data(): void
    {
        Auth::logout();

        $this->get(route('admin.user1'))->assertRedirect();
        $this->getJson('/admin/users/data?draw=1')->assertUnauthorized();
        $this->getJson(route('admin.users.table-settings.get'))->assertUnauthorized();
        $this->postJson(route('admin.users.table-settings.save'), [
            'columns' => ['parent_phone' => true],
        ])->assertUnauthorized();
    }

    public function test_manager_without_users_view_gets_403_on_parent_phone_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->actingAs($actor);

        $this->get(route('admin.user1'))->assertForbidden();
        $this->getJson('/admin/users/data?draw=1&start=0&length=10')->assertForbidden();
        $this->getJson(route('admin.users.table-settings.get'))->assertForbidden();
        $this->postJson(route('admin.users.table-settings.save'), [
            'columns' => ['parent_phone' => false],
        ])->assertForbidden();
    }

    public function test_manager_with_users_view_can_load_page_and_parent_phone_data(): void
    {
        $this->actingAsUsersViewer();

        $this->get(route('admin.user1'))->assertOk();

        $this->getJson('/admin/users/data?draw=1&start=0&length=10')
            ->assertOk()
            ->assertJsonStructure([
                'draw',
                'recordsTotal',
                'recordsFiltered',
                'data',
            ]);
    }

    // --- Blade / UX: заголовки, порядок, тумблеры ---

    public function test_users_page_shows_parent_phone_after_parent_and_renames_student_phone(): void
    {
        $this->actingAsUsersViewer(withContractsView: false);

        $html = $this->get(route('admin.user1'))
            ->assertOk()
            ->assertViewIs('admin.user')
            ->getContent();

        $tablePos = strpos($html, 'id="users-table"');
        $this->assertNotFalse($tablePos);
        $theadSnippet = substr($html, $tablePos, 2500);

        $parentPos = strpos($theadSnippet, '<th>Родитель</th>');
        $parentPhonePos = strpos($theadSnippet, '<th>Телефон родителя</th>');
        $teamsPos = strpos($theadSnippet, '<th>Группа</th>');
        $studentPhonePos = strpos($theadSnippet, '<th>Телефон ученика</th>');

        $this->assertNotFalse($parentPos);
        $this->assertNotFalse($parentPhonePos);
        $this->assertNotFalse($teamsPos);
        $this->assertNotFalse($studentPhonePos);
        $this->assertLessThan($parentPhonePos, $parentPos);
        $this->assertLessThan($teamsPos, $parentPhonePos);
        $this->assertLessThan($studentPhonePos, $teamsPos);

        // UX-баг: старый заголовок «Телефон» путает с телефоном родителя
        $this->assertStringNotContainsString('<th>Телефон</th>', $theadSnippet);
        $this->assertStringNotContainsString('>Договор</th>', $theadSnippet);

        $this->assertStringContainsString('id="colParentPhone"', $html);
        $this->assertStringContainsString('data-column-key="parent_phone"', $html);
        $this->assertStringContainsString('for="colParentPhone">Телефон родителя</label>', $html);
        $this->assertStringContainsString('for="colPhone">Телефон ученика</label>', $html);
    }

    public function test_users_page_keeps_parent_phone_before_contract_when_contracts_view_granted(): void
    {
        $this->actingAsUsersViewer(withContractsView: true);

        $html = $this->get(route('admin.user1'))
            ->assertOk()
            ->assertViewHas('canViewContracts', true)
            ->getContent();

        $tablePos = strpos($html, 'id="users-table"');
        $this->assertNotFalse($tablePos);
        $theadSnippet = substr($html, $tablePos, 2500);

        $parentPos = strpos($theadSnippet, '<th>Родитель</th>');
        $parentPhonePos = strpos($theadSnippet, '<th>Телефон родителя</th>');
        $contractPos = strpos($theadSnippet, '<th>Договор</th>');
        $teamsPos = strpos($theadSnippet, '<th>Группа</th>');

        $this->assertNotFalse($parentPos);
        $this->assertNotFalse($parentPhonePos);
        $this->assertNotFalse($contractPos);
        $this->assertNotFalse($teamsPos);
        $this->assertLessThan($parentPhonePos, $parentPos);
        $this->assertLessThan($contractPos, $parentPhonePos);
        $this->assertLessThan($teamsPos, $contractPos);

        $parentTogglePos = strpos($html, 'data-column-key="parent"');
        $parentPhoneTogglePos = strpos($html, 'data-column-key="parent_phone"');
        $contractTogglePos = strpos($html, 'data-column-key="contract"');

        $this->assertNotFalse($parentTogglePos);
        $this->assertNotFalse($parentPhoneTogglePos);
        $this->assertNotFalse($contractTogglePos);
        $this->assertLessThan($parentPhoneTogglePos, $parentTogglePos);
        $this->assertLessThan($contractTogglePos, $parentPhoneTogglePos);
    }

    public function test_users_page_js_columns_contract_places_parent_phone_after_parent(): void
    {
        $this->actingAsUsersViewer(withContractsView: true);

        $html = $this->get(route('admin.user1'))->assertOk()->getContent();

        $parentKeyPos = strpos($html, "key: 'parent'");
        $parentPhoneKeyPos = strpos($html, "key: 'parent_phone'");
        $contractKeyPos = strpos($html, "key: 'contract'");
        $phoneKeyPos = strpos($html, "key: 'phone'");

        $this->assertNotFalse($parentKeyPos);
        $this->assertNotFalse($parentPhoneKeyPos);
        $this->assertNotFalse($contractKeyPos);
        $this->assertNotFalse($phoneKeyPos);
        $this->assertLessThan($parentPhoneKeyPos, $parentKeyPos);
        $this->assertLessThan($contractKeyPos, $parentPhoneKeyPos);
        $this->assertLessThan($phoneKeyPos, $contractKeyPos);

        $this->assertStringContainsString('parent_phone: true', $html);
        $this->assertStringContainsString("data: 'parent_phone'", $html);
        $this->assertStringContainsString("data: 'phone'", $html);
    }

    // --- DataTables JSON ---

    public function test_users_data_returns_parent_phone_separately_from_student_phone(): void
    {
        $this->actingAsUsersViewer();

        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Родителев',
            'firstname'  => 'Иван',
            'phone'      => '+79001110001',
        ]);

        $student = $this->createStudent([
            'lastname'  => 'РазделениеТелефонов',
            'name'      => 'Ученик',
            'parent_id' => $parent->id,
            'phone'     => '+79002220002',
        ]);

        $row = $this->fetchUsersDataRowById($student->id);

        $this->assertNotNull($row);
        $this->assertArrayHasKey('parent_phone', $row);
        $this->assertArrayHasKey('phone', $row);
        $this->assertSame('+7 (900) 111-00-01', $row['parent_phone']);
        $this->assertSame('+7 (900) 222-00-02', $row['phone']);
        $this->assertNotSame($row['phone'], $row['parent_phone']);
    }

    public function test_users_data_shows_empty_parent_phone_when_student_has_no_parent(): void
    {
        $this->actingAsUsersViewer();

        $student = $this->createStudent([
            'lastname'  => 'БезРодителя',
            'name'      => 'Ученик',
            'parent_id' => null,
            'phone'     => '+79003330003',
        ]);

        $row = $this->fetchUsersDataRowById($student->id);

        $this->assertNotNull($row);
        $this->assertSame('', $row['parent_phone']);
        $this->assertSame('+7 (900) 333-00-03', $row['phone']);
    }

    public function test_users_data_shows_empty_parent_phone_when_parent_phone_is_null(): void
    {
        $this->actingAsUsersViewer();

        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'БезТелефона',
            'firstname'  => 'Родитель',
            'middlename' => null,
            'phone'      => null,
        ]);

        $student = $this->createStudent([
            'lastname'  => 'РодительБезТелефона',
            'name'      => 'Ученик',
            'parent_id' => $parent->id,
        ]);

        $row = $this->fetchUsersDataRowById($student->id);

        $this->assertNotNull($row);
        $this->assertSame('БезТелефона Родитель', $row['parent']);
        $this->assertSame('', $row['parent_phone']);
    }

    public function test_users_filter_finds_student_by_parent_phone_fragment(): void
    {
        $this->actingAsUsersViewer();

        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'ПоискПоТелефону',
            'firstname'  => 'Родитель',
            'phone'      => '+79004445566',
        ]);

        $student = $this->createStudent([
            'lastname'  => 'НайденПоРодительскому',
            'name'      => 'Ученик',
            'parent_id' => $parent->id,
            'phone'     => '+79009998877',
        ]);

        $other = $this->createStudent([
            'lastname'  => 'ЧужойПоиск',
            'name'      => 'Ученик',
            'parent_id' => null,
            'phone'     => '+79001112233',
        ]);

        $ids = collect(
            $this->getJson('/admin/users/data?draw=1&start=0&length=50&name=9004445566')
                ->assertOk()
                ->json('data')
        )->pluck('id')->all();

        $this->assertContains($student->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }

    public function test_name_filter_by_parent_fio_does_not_return_student_without_that_parent(): void
    {
        $this->actingAsUsersViewer();

        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'УникальныйРодитТел',
            'firstname'  => 'А',
            'phone'      => '+79005550101',
        ]);

        $withParentPhone = $this->createStudent([
            'lastname'  => 'ЕстьРодитТел',
            'name'      => 'A',
            'parent_id' => $parent->id,
            'phone'     => '+79006660101',
        ]);

        $onlyStudentPhone = $this->createStudent([
            'lastname'  => 'ТолькоУченичТел',
            'name'      => 'B',
            'parent_id' => null,
            'phone'     => '+79005550101',
        ]);

        $ids = collect(
            $this->getJson('/admin/users/data?draw=1&start=0&length=50&name=УникальныйРодитТел')
                ->assertOk()
                ->json('data')
        )->pluck('id')->all();

        $this->assertContains($withParentPhone->id, $ids);
        $this->assertNotContains($onlyStudentPhone->id, $ids);
    }

    public function test_name_filter_by_shared_digits_matches_both_parent_phone_and_student_phone(): void
    {
        $this->actingAsUsersViewer();

        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'ОбщийНомерРодит',
            'firstname'  => 'А',
            'phone'      => '+79005550202',
        ]);

        $withParentPhone = $this->createStudent([
            'lastname'  => 'ОбщийНомерРебёнок',
            'name'      => 'A',
            'parent_id' => $parent->id,
            'phone'     => '+79006660202',
        ]);

        $onlyStudentPhone = $this->createStudent([
            'lastname'  => 'ОбщийНомерСам',
            'name'      => 'B',
            'parent_id' => null,
            'phone'     => '+79005550202',
        ]);

        $phoneIds = collect(
            $this->getJson('/admin/users/data?draw=1&start=0&length=50&name=79005550202')
                ->assertOk()
                ->json('data')
        )->pluck('id')->all();

        $this->assertContains($withParentPhone->id, $phoneIds);
        $this->assertContains($onlyStudentPhone->id, $phoneIds);
    }

    public function test_users_data_sorts_by_parent_phone_column(): void
    {
        $this->actingAsUsersViewer(withContractsView: false);

        $parentA = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'СортТелА',
            'firstname'  => 'A',
            'phone'      => '+79001000001',
        ]);
        $parentB = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'СортТелБ',
            'firstname'  => 'B',
            'phone'      => '+79002000002',
        ]);

        $studentA = $this->createStudent([
            'lastname'  => 'СортParentPhone',
            'name'      => 'A',
            'parent_id' => $parentA->id,
        ]);
        $studentB = $this->createStudent([
            'lastname'  => 'СортParentPhone',
            'name'      => 'B',
            'parent_id' => $parentB->id,
        ]);

        $col = $this->usersListColumnIndex('parent_phone', withContracts: false);

        $ids = collect(
            $this->getJson(
                "/admin/users/data?draw=1&start=0&length=100&name=СортParentPhone&order[0][column]={$col}&order[0][dir]=asc"
            )
                ->assertOk()
                ->json('data')
        )->pluck('id')->all();

        $posA = array_search($studentA->id, $ids, true);
        $posB = array_search($studentB->id, $ids, true);

        $this->assertNotFalse($posA);
        $this->assertNotFalse($posB);
        $this->assertLessThan($posB, $posA, 'При asc меньший parent_phone должен быть раньше');
    }

    public function test_users_data_does_not_leak_foreign_partner_by_parent_phone_search(): void
    {
        $this->actingAsUsersViewer();

        $foreignParent = ParentProfile::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'lastname'   => 'ЧужойПартнерТел',
            'firstname'  => 'X',
            'phone'      => '+79007770101',
        ]);

        User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'role_id'    => $this->studentRoleId(),
            'parent_id'  => $foreignParent->id,
            'lastname'   => 'ЧужойУченикТел',
        ]);

        $json = $this->getJson('/admin/users/data?draw=1&start=0&length=50&name=79007770101')
            ->assertOk()
            ->json();

        $this->assertSame([], $json['data'] ?? []);
    }

    // --- Columns settings ---

    public function test_columns_settings_persist_parent_phone_visibility(): void
    {
        $actor = $this->actingAsUsersViewer();

        $this->postJson(route('admin.users.table-settings.save'), [
            'columns' => [
                'name'         => true,
                'parent'       => true,
                'parent_phone' => false,
                'phone'        => true,
            ],
        ])->assertOk()->assertJsonPath('success', true);

        $this->getJson(route('admin.users.table-settings.get'))
            ->assertOk()
            ->assertJsonPath('parent_phone', false)
            ->assertJsonPath('phone', true);

        $this->postJson(route('admin.users.table-settings.save'), [
            'columns' => [
                'parent_phone' => true,
                'phone'        => false,
            ],
        ])->assertOk();

        $this->getJson(route('admin.users.table-settings.get'))
            ->assertOk()
            ->assertJsonPath('parent_phone', true)
            ->assertJsonPath('phone', false);

        $this->assertDatabaseHas('user_table_settings', [
            'user_id'   => $actor->id,
            'table_key' => 'users_index',
        ]);
    }

    public function test_columns_settings_validation_rejects_non_array_columns_payload(): void
    {
        $this->actingAsUsersViewer();

        $this->postJson(route('admin.users.table-settings.save'), [
            'columns' => 'parent_phone',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['columns']);
    }

    // --- Smoke: endpoint не отдаёт 500 ---

    public function test_parent_phone_related_endpoints_return_ok_not_empty_200_for_authorized_manager(): void
    {
        $this->actingAsUsersViewer(withContractsView: true);

        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'phone'      => '+79008880101',
        ]);
        $student = $this->createStudent([
            'lastname'  => 'SmokeParentPhone',
            'parent_id' => $parent->id,
        ]);

        $urls = [
            route('admin.user1'),
            '/admin/users/data?draw=1&start=0&length=10',
            '/admin/users/data?draw=1&start=0&length=10&name=79008880101',
            '/admin/users/data?draw=1&start=0&length=10&id=' . $student->id,
            '/admin/users/data?draw=1&start=0&length=10&order[0][column]=4&order[0][dir]=asc',
            route('admin.users.table-settings.get'),
        ];

        foreach ($urls as $url) {
            $isJsonEndpoint = str_contains($url, '/admin/users/data')
                || str_contains($url, 'columns-settings');

            $response = $isJsonEndpoint ? $this->getJson($url) : $this->get($url);
            $response->assertOk();
            $this->assertNotSame('', (string) $response->getContent());
        }

        $this->postJson(route('admin.users.table-settings.save'), [
            'columns' => ['parent_phone' => true, 'phone' => true],
        ])->assertOk();
    }
}
