<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\SchoolLead;
use App\Models\User;
use App\Models\UserTableSetting;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Порядок колонок таблицы «Заявки»: № → ребёнок → родитель → статус → объект → секция → телефон → остальные;
 * без колонки «Действия» и без DELETE заявки.
 */
final class SchoolLeadsTableColumnsFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asAdmin();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
    }

    public function test_leads_table_columns_follow_child_parent_status_location_team_phone_order(): void
    {
        $html = $this->get(route('admin.school-leads'))->assertOk()->getContent();

        $tablePos = strpos($html, 'id="leads-table"');
        $this->assertNotFalse($tablePos);
        $theadStart = strpos($html, '<thead>', $tablePos);
        $theadEnd = strpos($html, '</thead>', (int) $theadStart);
        $this->assertNotFalse($theadStart);
        $this->assertNotFalse($theadEnd);
        $thead = substr($html, $theadStart, $theadEnd - $theadStart);

        $this->assertSequentialFragments($thead, [
            '<th>№</th>',
            '<th>ФИО ребенка</th>',
            '<th>ФИО родителя</th>',
            'lead-status-col-header',
            '<th>Объект</th>',
            '<th>Секция</th>',
            '<th>Телефон родителя</th>',
            '<th>Договор</th>',
            '<th>Email родителя</th>',
            '<th>Дата рождения</th>',
            '<th>Район</th>',
            '<th>Особые условия</th>',
            '<th>UTM / источник</th>',
            '<th>Страница</th>',
            '<th>Комментарий</th>',
        ], 'thead таблицы заявок');

        $this->assertSequentialFragments($html, [
            'data-column-key="child_full_name"',
            'data-column-key="name"',
            'data-column-key="status"',
            'data-column-key="location"',
            'data-column-key="team_title"',
            'data-column-key="phone"',
            'data-column-key="contract"',
            'data-column-key="parent_email"',
            'data-column-key="child_birthday"',
            'data-column-key="district"',
            'data-column-key="child_flags"',
            'data-column-key="utm"',
            'data-column-key="page_url"',
            'data-column-key="comment"',
        ], 'меню «Колонки»');

        $this->assertSequentialFragments($html, [
            "key: 'child_full_name'",
            "key: 'name'",
            "key: 'status'",
            "key: 'location'",
            "key: 'team_title'",
            "key: 'phone'",
            "key: 'contract'",
            "key: 'parent_email'",
            "key: 'child_birthday'",
            "key: 'district'",
            "key: 'child_flags'",
            "key: 'utm'",
            "key: 'page_url'",
            "key: 'comment'",
        ], 'массив DataTable columns');

        $this->assertTheadMatchesActiveDataTableColumns($html);
        $this->assertSame(
            [
                '№',
                'ФИО ребенка',
                'ФИО родителя',
                'Статус',
                'Объект',
                'Секция',
                'Телефон родителя',
                'Договор',
                'Email родителя',
                'Дата рождения',
                'Район',
                'Особые условия',
                'UTM / источник',
                'Страница',
                'Комментарий',
            ],
            $this->leadsTheadLabels($html)
        );
    }

    public function test_thead_column_count_matches_datatable_columns_so_table_does_not_break(): void
    {
        $html = $this->get(route('admin.school-leads'))->assertOk()->getContent();

        $this->assertTheadMatchesActiveDataTableColumns($html);
    }

    public function test_viewer_without_location_and_contract_rights_sees_status_then_section_then_phone(): void
    {
        $this->actingAsSchoolLeadsViewer();

        $html = $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertViewHas('canViewLocations', false)
            ->assertViewHas('canViewDistricts', false)
            ->assertViewHas('canViewContracts', false)
            ->assertViewHas('canCreateUserFromLead', false)
            ->getContent();

        $this->assertSame(
            [
                '№',
                'ФИО ребенка',
                'ФИО родителя',
                'Статус',
                'Секция',
                'Телефон родителя',
                'Email родителя',
                'Дата рождения',
                'Особые условия',
                'UTM / источник',
                'Страница',
                'Комментарий',
            ],
            $this->leadsTheadLabels($html)
        );

        $this->assertStringNotContainsString('data-column-key="location"', $html);
        $this->assertStringNotContainsString('data-column-key="contract"', $html);
        $this->assertStringNotContainsString('<th>Объект</th>', $this->leadsThead($html));
        $this->assertStringNotContainsString('<th>Договор</th>', $this->leadsThead($html));
        $this->assertStringNotContainsString('<th>Район</th>', $this->leadsThead($html));

        $this->assertTheadMatchesActiveDataTableColumns($html);
    }

    public function test_without_locations_view_location_column_is_not_imposed_between_status_and_section(): void
    {
        $this->actingAsSchoolLeadsViewer();

        $html = $this->get(route('admin.school-leads'))->assertOk()->getContent();
        $thead = $this->leadsThead($html);

        $statusPos = strpos($thead, 'lead-status-col-header');
        $teamPos = strpos($thead, '<th>Секция</th>');
        $locationPos = strpos($thead, '<th>Объект</th>');

        $this->assertNotFalse($statusPos);
        $this->assertNotFalse($teamPos);
        $this->assertFalse($locationPos);
        $this->assertLessThan($teamPos, $statusPos);
    }

    public function test_hidden_phone_stays_hidden_by_key_and_does_not_shift_other_columns(): void
    {
        UserTableSetting::updateOrCreate(
            [
                'user_id'   => $this->user->id,
                'table_key' => 'school_leads_index',
            ],
            [
                'columns' => [
                    'child_full_name' => true,
                    'name'            => true,
                    'status'          => true,
                    'phone'           => false,
                    'team_title'      => true,
                    'utm'             => false,
                ],
            ]
        );

        $saved = $this->getJson(route('admin.school-leads.columns-settings.get'))
            ->assertOk()
            ->assertJsonPath('phone', false)
            ->assertJsonPath('child_full_name', true)
            ->assertJsonPath('name', true)
            ->assertJsonPath('utm', false)
            ->json();

        $this->assertArrayHasKey('phone', $saved);
        $this->assertArrayNotHasKey(0, $saved);
        $this->assertArrayNotHasKey(1, $saved);
        $this->assertArrayNotHasKey(4, $saved);

        $html = $this->get(route('admin.school-leads'))->assertOk()->getContent();

        $this->assertSame(
            [
                '№',
                'ФИО ребенка',
                'ФИО родителя',
                'Статус',
                'Объект',
                'Секция',
                'Телефон родителя',
                'Договор',
                'Email родителя',
                'Дата рождения',
                'Район',
                'Особые условия',
                'UTM / источник',
                'Страница',
                'Комментарий',
            ],
            $this->leadsTheadLabels($html),
            'Сохранённая видимость не должна менять порядок thead — иначе после смены порядка чужие столбцы «уедут»'
        );

        $this->assertSequentialFragments($html, [
            'data-column-key="status"',
            'data-column-key="location"',
            'data-column-key="team_title"',
            'data-column-key="phone"',
        ], 'меню «Колонки» при скрытом телефоне');

        $this->assertStringContainsString("phone: true", $html);
        $this->assertStringContainsString("toggleSelector: '.school-leads-column-toggle'", $html);
        $this->assertMatchesRegularExpression(
            '#admin\\\\?/school-leads\\\\?/columns-settings#',
            $html
        );
    }

    public function test_datatable_child_and_parent_names_stay_on_their_own_fields_after_reorder(): void
    {
        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Виджет Родитель',
            'phone'                 => '+7 900 850-50-01',
            'parent_lastname'       => 'Иванов',
            'parent_firstname'      => 'Пётр',
            'child_lastname'        => 'Иванов',
            'child_firstname'       => 'Семён',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 50,
            'search' => ['value' => '850-50-01'],
        ]))->assertOk();

        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertNotSame(500, $response->getStatusCode());

        $row = collect($response->json('data'))->firstWhere('phone', '+7 900 850-50-01');
        $this->assertIsArray($row);
        $this->assertSame('Иванов Пётр', $row['parent_full_name']);
        $this->assertSame('Иванов Семён', $row['child_full_name']);
        $this->assertSame('+7 900 850-50-01', $row['parent_phone'] ?? $row['phone']);
        $this->assertArrayHasKey('status_label', $row);
        $this->assertArrayHasKey('team_title', $row);
    }

    public function test_column_toggles_use_named_keys_not_numeric_indexes(): void
    {
        $html = $this->get(route('admin.school-leads'))->assertOk()->getContent();

        $this->assertStringContainsString('data-column-key="child_full_name"', $html);
        $this->assertStringContainsString('data-column-key="name"', $html);
        $this->assertStringContainsString('data-column-key="phone"', $html);
        $this->assertStringNotContainsString('data-column-key="0"', $html);
        $this->assertStringNotContainsString('data-column-index="phone"', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/columnsSettings:\s*\{[^}]*defaults:\s*\[[^\]]*\]/s',
            $html,
            'defaults видимости должны быть объектом {key: bool}, а не массивом по индексу'
        );
    }

    /**
     * @param list<string> $needles
     */
    private function assertSequentialFragments(string $haystack, array $needles, string $context): void
    {
        $previous = -1;
        $previousNeedle = '';

        foreach ($needles as $needle) {
            $pos = strpos($haystack, $needle);
            $this->assertNotFalse($pos, "{$context}: не найден фрагмент «{$needle}»");
            if ($previous >= 0) {
                $this->assertLessThan(
                    $pos,
                    $previous,
                    "{$context}: ожидался порядок «{$previousNeedle}» → «{$needle}»"
                );
            }
            $previous = $pos;
            $previousNeedle = $needle;
        }
    }

    public function test_leads_table_has_no_actions_column_or_delete_controls(): void
    {
        $html = $this->get(route('admin.school-leads'))->assertOk()->getContent();

        $this->assertStringNotContainsString("key: 'actions'", $html);
        $this->assertStringNotContainsString('delete-lead', $html);
        $this->assertStringNotContainsString('deleteLeadModal', $html);
        $this->assertStringNotContainsString('slColActions', $html);
        $this->assertStringNotContainsString('data-column-key="actions"', $html);

        $tablePos = strpos($html, 'id="leads-table"');
        $this->assertNotFalse($tablePos);
        $this->assertStringNotContainsString('<th>Действия</th>', substr($html, $tablePos, 1500));

        $this->assertStringContainsString("linkClass: 'edit-lead'", $html);
    }

    public function test_destroy_route_is_not_registered(): void
    {
        $this->assertNull(Route::getRoutes()->getByName('admin.school-leads.destroy'));
    }

    public function test_delete_school_lead_url_returns_405_and_does_not_soft_delete(): void
    {
        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Не удалять',
            'phone'                 => '+7 900 830-30-01',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $this->deleteJson('/admin/school-leads/' . $lead->id)
            ->assertStatus(405);

        $this->assertNull($lead->fresh()->deleted_at);
        $this->assertDatabaseHas('school_leads', [
            'id'         => $lead->id,
            'deleted_at' => null,
        ]);
    }

    private function actingAsSchoolLeadsViewer(): User
    {
        $now = now();
        $roleId = (int) DB::table('roles')->insertGetId([
            'name'       => 'test_leads_cols_viewer_'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6)),
            'label'      => 'Test leads columns viewer',
            'is_sistem'  => 0,
            'order_by'   => 0,
            'is_visible' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $actor = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
        ]);

        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $roleId,
            'permission_id' => $this->permissionId('schoolLeads.view'),
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        return $actor;
    }

    private function leadsThead(string $html): string
    {
        $tablePos = strpos($html, 'id="leads-table"');
        $this->assertNotFalse($tablePos, 'Таблица #leads-table не найдена');
        $theadStart = strpos($html, '<thead>', $tablePos);
        $theadEnd = strpos($html, '</thead>', (int) $theadStart);
        $this->assertNotFalse($theadStart);
        $this->assertNotFalse($theadEnd);

        return substr($html, $theadStart, $theadEnd - $theadStart);
    }

    /**
     * @return list<string>
     */
    private function leadsTheadLabels(string $html): array
    {
        preg_match_all('/<th\b[^>]*>(.*?)<\/th>/s', $this->leadsThead($html), $matches);

        return array_values(array_map(
            static fn (string $inner): string => trim(html_entity_decode(strip_tags($inner))),
            $matches[1]
        ));
    }

    private function jsBoolFlag(string $html, string $name): bool
    {
        $matched = preg_match('/var\s+'.preg_quote($name, '/').'\s*=\s*(true|false)\s*;/', $html, $matches);
        $this->assertSame(1, $matched, "JS-флаг {$name} не найден в HTML");

        return $matches[1] === 'true';
    }

    private function assertTheadMatchesActiveDataTableColumns(string $html): void
    {
        $thCount = count($this->leadsTheadLabels($html));

        $canViewLocations = $this->jsBoolFlag($html, 'canViewLocations');
        $canViewDistricts = $this->jsBoolFlag($html, 'canViewDistricts');
        $canShowLeadClientColumn = $this->jsBoolFlag($html, 'canShowLeadClientColumn');

        $createPos = strpos($html, "KidsCrmDataTable.create('#leads-table'");
        $this->assertNotFalse($createPos, 'KidsCrmDataTable.create(#leads-table) не найден');
        $columnsPos = strpos($html, 'columns: [', $createPos);
        $this->assertNotFalse($columnsPos);
        $columnsEnd = strpos($html, "key: 'comment'", $columnsPos);
        $this->assertNotFalse($columnsEnd);
        $columnsSource = substr($html, $columnsPos, ($columnsEnd + 80) - $columnsPos);

        preg_match_all("/key:\s*'([^']+)'/", $columnsSource, $keyMatches);
        $keys = $keyMatches[1];
        $this->assertNotEmpty($keys);

        $activeKeys = array_values(array_filter($keys, function (string $key) use ($canViewLocations, $canViewDistricts, $canShowLeadClientColumn): bool {
            if ($key === 'location') {
                return $canViewLocations;
            }
            if ($key === 'district') {
                return $canViewDistricts;
            }
            if ($key === 'contract') {
                return $canShowLeadClientColumn;
            }

            return true;
        }));

        $activeCount = 1 + count($activeKeys);

        $this->assertSame(
            $thCount,
            $activeCount,
            'Число <th> в thead должно совпадать с активными DataTable columns (после when). Иначе DataTables ломает таблицу.'
        );
    }
}
