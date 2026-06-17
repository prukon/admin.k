<?php

namespace Tests\Feature\Crm\Contracts;

use App\Models\ParentProfile;
use App\Models\Team;
use App\Models\User;

/**
 * ФИО родителя в модалке «Создать договор»: отображение, предзаполнение, AJAX-поиск учеников.
 */
class ContractCreateModalParentFeatureTest extends ContractsFeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['billing.contract_create_fee' => 70.00]);
        $this->partner->wallet_balance = 500;
        $this->partner->save();
    }

    /** @test */
    public function index_renders_parent_full_name_field_in_create_modal(): void
    {
        $this->get(route('contracts.index'))
            ->assertOk()
            ->assertSee('for="parent_full_name_display"', false)
            ->assertSee('id="parent_full_name_display"', false)
            ->assertSee('>ФИО родителя</label>', false)
            ->assertSee('id="parent_full_name_display"', false)
            ->assertSee('value="—"', false);
    }

    /** @test */
    public function index_renders_parent_full_name_js_helpers(): void
    {
        $html = $this->get(route('contracts.index'))->getContent();

        $this->assertStringContainsString('function setParentFullNameDisplay', $html);
        $this->assertStringContainsString('setParentFullNameDisplay(d.parent_full_name)', $html);
        $this->assertStringContainsString('setParentFullNameDisplay(activePreselectedUser.parent_full_name)', $html);
        $this->assertStringContainsString("setParentFullNameDisplay('')", $html);
    }

    /** @test */
    public function index_form_fields_order_is_student_parent_group_partner(): void
    {
        $html = $this->get(route('contracts.index'))->getContent();

        $studentPos = strpos($html, 'for="user_id"');
        $parentPos = strpos($html, 'for="parent_full_name_display"');
        $groupPos = strpos($html, 'for="group_id_select"');
        $partnerPos = strpos($html, '>Партнёр</label>');

        $this->assertNotFalse($studentPos);
        $this->assertNotFalse($parentPos);
        $this->assertNotFalse($groupPos);
        $this->assertNotFalse($partnerPos);
        $this->assertLessThan($parentPos, $studentPos);
        $this->assertLessThan($groupPos, $parentPos);
        $this->assertLessThan($partnerPos, $groupPos);
    }

    /** @test */
    public function preselected_user_includes_parent_full_name_when_parent_exists(): void
    {
        $parent = $this->createParent('Сидоров', 'Сидор', 'Сидорович');
        $student = $this->createStudentWithParent($parent, 'Артём', 'Учеников');

        $this->get(route('contracts.index', ['user_id' => $student->id]))
            ->assertOk()
            ->assertViewHas('shouldOpenCreateModal', true)
            ->assertViewHas('preselectedUser', function ($pre) use ($student) {
                return is_array($pre)
                    && (int) $pre['id'] === $student->id
                    && ($pre['parent_full_name'] ?? null) === 'Сидоров Сидор Сидорович';
            });
    }

    /** @test */
    public function preselected_user_has_null_parent_full_name_when_student_has_no_parent(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'parent_id'  => null,
            'is_enabled' => 1,
            'name'       => 'Безродный',
            'lastname'   => 'Ученик',
        ]);

        $this->get(route('contracts.index', ['user_id' => $student->id]))
            ->assertOk()
            ->assertViewHas('preselectedUser', function ($pre) use ($student) {
                return is_array($pre)
                    && (int) $pre['id'] === $student->id
                    && array_key_exists('parent_full_name', $pre)
                    && $pre['parent_full_name'] === null;
            });
    }

    /** @test */
    public function preselected_user_json_in_page_includes_parent_full_name(): void
    {
        $parent = $this->createParent('Козлов', 'Козма', 'Козьмич');
        $student = $this->createStudentWithParent($parent, 'Сын', 'Козлов');

        $html = $this->get(route('contracts.index', ['user_id' => $student->id]))->getContent();

        $this->assertStringContainsString('"parent_full_name":"\u041a\u043e\u0437\u043b\u043e\u0432 \u041a\u043e\u0437\u043c\u0430 \u041a\u043e\u0437\u044c\u043c\u0438\u0447"', $html);
    }

    /** @test */
    public function users_search_includes_parent_full_name_when_parent_exists(): void
    {
        $parent = $this->createParent('Иванов', 'Иван', 'Иванович');
        $student = $this->createStudentWithParent($parent, 'Пётр', 'Поисков');

        $match = $this->findUserSearchResult('Поисков', $student->id);

        $this->assertSame('Иванов Иван Иванович', $match['parent_full_name'] ?? null);
    }

    /** @test */
    public function users_search_returns_null_parent_full_name_when_student_has_no_parent(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'parent_id'  => null,
            'is_enabled' => 1,
            'name'       => 'Сирота',
            'lastname'   => 'Поисков',
        ]);

        $match = $this->findUserSearchResult('Поисков', $student->id);

        $this->assertArrayHasKey('parent_full_name', $match);
        $this->assertNull($match['parent_full_name']);
    }

    /** @test */
    public function users_search_with_empty_query_returns_parent_full_name(): void
    {
        $parent = $this->createParent('Пустой', 'Запрос', null);
        $student = $this->createStudentWithParent($parent, 'Ученик', 'ПустойЗапрос');

        $match = $this->findUserSearchResult('', $student->id);

        $this->assertSame('Пустой Запрос', $match['parent_full_name'] ?? null);
    }

    /** @test */
    public function users_search_json_structure_includes_parent_full_name_key(): void
    {
        $this->createStudentWithParent(
            $this->createParent('Структура', 'Тест', null),
            'Ученик',
            'Структурный'
        );

        $this->getJson(route('contracts.users.search', ['q' => 'Структурный']))
            ->assertOk()
            ->assertJsonStructure([
                'results' => [
                    [
                        'id',
                        'text',
                        'name',
                        'lastname',
                        'team_id',
                        'team_title',
                        'parent_full_name',
                    ],
                ],
            ]);
    }

    /** @test */
    public function users_search_builds_parent_full_name_without_middle_name(): void
    {
        $parent = $this->createParent('Петров', 'Пётр', null);
        $student = $this->createStudentWithParent($parent, 'Сын', 'ПетровСын');

        $match = $this->findUserSearchResult('ПетровСын', $student->id);

        $this->assertSame('Петров Пётр', $match['parent_full_name'] ?? null);
    }

    /** @test */
    public function users_search_excludes_soft_deleted_parent_profile(): void
    {
        $parent = $this->createParent('Удалён', 'Родитель', 'Профиль');
        $student = $this->createStudentWithParent($parent, 'Ученик', 'УдалённыйРод');

        $parent->delete();

        $match = $this->findUserSearchResult('УдалённыйРод', $student->id);

        $this->assertArrayHasKey('parent_full_name', $match);
        $this->assertNull($match['parent_full_name']);
    }

    /** @test */
    public function users_search_does_not_find_student_by_parent_name(): void
    {
        $parent = $this->createParent('УникальныйРодитель', 'Поиск', 'ТолькоОтображение');
        $student = $this->createStudentWithParent($parent, 'Ученик', 'ДругойУченик');

        $response = $this->getJson(route('contracts.users.search', ['q' => 'УникальныйРодитель']))
            ->assertOk()
            ->json();

        $ids = collect($response['results'] ?? [])->pluck('id')->map(fn ($id) => (int) $id);

        $this->assertFalse($ids->contains($student->id));
    }

    /** @test */
    public function users_search_does_not_return_foreign_partner_students(): void
    {
        $foreignParent = ParentProfile::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'lastname'   => 'Чужой',
            'firstname'  => 'Родитель',
        ]);

        $foreignStudent = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'parent_id'  => $foreignParent->id,
            'is_enabled' => 1,
            'name'       => 'Чужой',
            'lastname'   => 'УченикИзоляция',
        ]);

        $response = $this->getJson(route('contracts.users.search', ['q' => 'УченикИзоляция']))
            ->assertOk()
            ->json();

        $ids = collect($response['results'] ?? [])->pluck('id')->map(fn ($id) => (int) $id);

        $this->assertFalse($ids->contains($foreignStudent->id));
    }

    /** @test */
    public function index_ignores_foreign_user_id_for_parent_prefill(): void
    {
        $foreignParent = ParentProfile::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'lastname'   => 'Чужой',
            'firstname'  => 'Родитель',
        ]);

        $foreignStudent = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'parent_id'  => $foreignParent->id,
            'is_enabled' => 1,
        ]);

        $this->get(route('contracts.index', ['user_id' => $foreignStudent->id]))
            ->assertOk()
            ->assertViewHas('preselectedUser', null);
    }

    private function createParent(string $lastname, string $firstname, ?string $middlename): ParentProfile
    {
        return ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => $lastname,
            'firstname'  => $firstname,
            'middlename' => $middlename,
        ]);
    }

    private function createStudentWithParent(
        ParentProfile $parent,
        string $name,
        string $lastname,
        ?Team $team = null,
    ): User {
        return User::factory()->create([
            'partner_id' => $this->partner->id,
            'parent_id'  => $parent->id,
            'team_id'    => $team?->id,
            'is_enabled' => 1,
            'name'       => $name,
            'lastname'   => $lastname,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function findUserSearchResult(string $query, int $studentId): array
    {
        $response = $this->getJson(route('contracts.users.search', ['q' => $query]))
            ->assertOk()
            ->json();

        $match = collect($response['results'] ?? [])
            ->first(fn ($row) => (int) ($row['id'] ?? 0) === $studentId);

        $this->assertNotNull($match, 'Ученик не найден в результатах users-search');

        return $match;
    }
}
