<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Payments\TBank\Commissions;

use App\Models\User;
use App\Models\UserTableSetting;
use Tests\Feature\Crm\CrmTestCase;

/**
 * UX кнопки «Колонки» и персонального «Показать N» на /admin/settings/tbank-commissions:
 * порядок thead = чекбоксы = JS keys, дефолты, фильтры не пересоздают таблицу.
 *
 * @see TbankCommissionsColumnsSettingsAjaxContractFeatureTest
 * @see TbankCommissionsColumnsSettingsNonAjaxSafetyNetFeatureTest
 * @see TbankCommissionsColumnsSettingsFullAccessFeatureTest
 */
final class TbankCommissionsColumnsSettingsFeatureTest extends CrmTestCase
{
    private const TABLE_KEY = 'tbank_commissions_index';

    /** @var list<string> */
    private const COLUMN_KEYS = [
        'partner_title',
        'method',
        'acquiring_percent',
        'payout_percent',
        'platform_percent',
        'auto_payout',
        'payouts_30d',
        'is_enabled',
        'actions',
    ];

    /** @var list<string> */
    private const COLUMN_LABELS = [
        'Партнёр',
        'Метод',
        'Эквайринг банка',
        'Выплата банка',
        'Комиссия платформы',
        'Автовыплата',
        'Выплат за 30 дн.',
        'Активность',
        'Действия',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    public function test_first_open_shows_all_columns_checked_and_ten_rows_by_default(): void
    {
        UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', self::TABLE_KEY)
            ->delete();

        $html = $this->get(route('admin.setting.tbankCommissions'))
            ->assertOk()
            ->assertViewHas('tbankCommissionsPageLength', 10)
            ->getContent();

        $this->assertSame(1, substr_count($html, 'id="tbankCommissionsColumnsDropdown"'));
        $this->assertStringContainsString('>Колонки</span>', $html);
        $this->assertMatchesRegularExpression('/pageLength:\s*10\b/', $this->createChunk($html));
        $this->assertSame(1, substr_count($html, 'persistPageLength: true'));
        $this->assertSame(1, substr_count($html, "KidsCrmDataTable.create('#tbank-commissions-table'"));
        $this->assertStringContainsString('id="tbankCommissionsFiltersCollapse"', $html);
        $this->assertStringNotContainsString(
            'class="collapse show mb-2 mb-md-3" id="tbankCommissionsFiltersCollapse"',
            $html,
            'Без query-фильтров блок фильтров не должен быть раскрыт'
        );

        foreach (self::COLUMN_KEYS as $i => $key) {
            $this->assertMatchesRegularExpression(
                '/class="form-check-input column-toggle"[^>]*data-column-key="'.preg_quote($key, '/').'"[^>]*checked/',
                $html,
                'На первом открытии чекбокс «'.self::COLUMN_LABELS[$i].'» должен быть включён'
            );
        }

        $defaultsChunk = $this->jsObjectChunk($html, 'defaults:');
        foreach (self::COLUMN_KEYS as $key) {
            $this->assertStringContainsString($key.': true', $defaultsChunk);
        }
        $this->assertStringNotContainsString('rownum:', $defaultsChunk);
    }

    public function test_columns_menu_thead_and_datatable_keys_stay_in_the_same_order(): void
    {
        $html = $this->get(route('admin.setting.tbankCommissions'))->assertOk()->getContent();

        $this->assertSame(
            array_merge(['№'], self::COLUMN_LABELS),
            $this->commissionsTheadLabels($html)
        );

        $this->assertSequentialFragments($html, array_map(
            static fn (string $key): string => 'data-column-key="'.$key.'"',
            self::COLUMN_KEYS
        ), 'меню «Колонки»');

        $this->assertSequentialFragments($html, array_map(
            static fn (string $key): string => "key: '".$key."'",
            self::COLUMN_KEYS
        ), 'массив DataTable columns');

        $this->assertSame(count(self::COLUMN_KEYS), substr_count($html, 'class="form-check-input column-toggle"'));
        $this->assertStringNotContainsString('data-column-key="rownum"', $html);
        $this->assertStringNotContainsString('>№</label>', $html);
    }

    public function test_hidden_method_column_stays_hidden_by_key_and_does_not_shift_thead(): void
    {
        UserTableSetting::updateOrCreate(
            ['user_id' => $this->user->id, 'table_key' => self::TABLE_KEY],
            [
                'columns' => [
                    'partner_title' => true,
                    'method' => false,
                    'actions' => true,
                ],
            ]
        );

        $saved = $this->getJson(route('admin.setting.tbankCommissions.columns-settings.get'))
            ->assertOk()
            ->assertJsonPath('method', false)
            ->assertJsonPath('partner_title', true)
            ->json();

        $this->assertArrayNotHasKey(0, $saved);
        $this->assertArrayNotHasKey('acquiring_percent', $saved);

        $html = $this->get(route('admin.setting.tbankCommissions'))->assertOk()->getContent();
        $this->assertSame(
            array_merge(['№'], self::COLUMN_LABELS),
            $this->commissionsTheadLabels($html),
            'Сохранённая видимость не должна менять порядок thead — иначе после hide чужие столбцы «уедут»'
        );
        $this->assertStringContainsString("key: 'method'", $html);
        $this->assertStringContainsString('acquiring_percent: true', $this->jsObjectChunk($html, 'defaults:'));
    }

    public function test_reopening_page_with_filters_keeps_saved_show_by(): void
    {
        $this->postJson(route('admin.setting.tbankCommissions.columns-settings.save'), [
            'page_length' => 50,
        ])->assertOk();

        $html = $this->get(route('admin.setting.tbankCommissions', [
            'filter_partner_id' => $this->partner->id,
            'filter_method' => 'card',
        ]))
            ->assertOk()
            ->assertViewHas('tbankCommissionsPageLength', 50)
            ->getContent();

        $chunk = $this->createChunk($html);
        $this->assertMatchesRegularExpression('/pageLength:\s*50\b/', $chunk);
        $this->assertDoesNotMatchRegularExpression('/pageLength:\s*10\b/', $chunk);
        $this->assertStringContainsString('persistPageLength: true', $chunk);
        $this->assertStringContainsString('id="tbankCommissionsColumnsDropdown"', $html);
        $this->assertStringContainsString('class="collapse show mb-2 mb-md-3" id="tbankCommissionsFiltersCollapse"', $html);
    }

    public function test_open_create_flag_does_not_drop_columns_toolbar_or_show_by(): void
    {
        $this->postJson(route('admin.setting.tbankCommissions.columns-settings.save'), [
            'page_length' => 20,
        ])->assertOk();

        $html = $this->get(route('admin.setting.tbankCommissions', ['open_create' => 1]))
            ->assertOk()
            ->assertViewHas('tbankCommissionsPageLength', 20)
            ->getContent();

        $this->assertStringContainsString('id="tbankCommissionsColumnsDropdown"', $html);
        $this->assertStringContainsString('persistPageLength: true', $html);
        $this->assertMatchesRegularExpression('/pageLength:\s*20\b/', $this->createChunk($html));
        $this->assertSame(1, substr_count($html, "KidsCrmDataTable.create('#tbank-commissions-table'"));
        $this->assertStringContainsString('fromCreateRoute = true', $html);
    }

    public function test_edit_page_does_not_render_columns_dropdown_or_persist_show_by(): void
    {
        $rule = $this->seedTbankCommissionRule((int) $this->partner->id);

        $html = $this->get(route('admin.setting.tbankCommissions.edit', ['id' => $rule->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('id="tbankCommissionsColumnsDropdown"', $html);
        $this->assertStringNotContainsString('persistPageLength: true', $html);
        $this->assertStringNotContainsString("KidsCrmDataTable.create('#tbank-commissions-table'", $html);
        $this->assertStringContainsString('Правка правила #'.$rule->id, $html);
    }

    public function test_filter_submit_and_reset_reload_table_instead_of_recreating_it(): void
    {
        $html = $this->get(route('admin.setting.tbankCommissions'))->assertOk()->getContent();
        $createPos = strpos($html, "KidsCrmDataTable.create('#tbank-commissions-table'");
        $this->assertNotFalse($createPos);

        $submitPos = strpos($html, '$form.on(\'submit\'');
        $this->assertNotFalse($submitPos);
        $this->assertGreaterThan($createPos, $submitPos);
        $submitChunk = substr($html, $submitPos, 400);
        $this->assertStringContainsString('e.preventDefault()', $submitChunk);
        $this->assertStringContainsString('dtApi.reload({ keepPage: true })', $submitChunk);
        $this->assertStringNotContainsString('KidsCrmDataTable.create', $submitChunk);

        $resetPos = strpos($html, '$(\'#tbank-commissions-filters-reset\').on(\'click\'');
        $this->assertNotFalse($resetPos);
        $resetChunk = substr($html, $resetPos, 500);
        $this->assertStringContainsString('dtApi.reload()', $resetChunk);
        $this->assertStringNotContainsString('KidsCrmDataTable.create', $resetChunk);
        $this->assertStringNotContainsString('pageLength:', $resetChunk);
    }

    public function test_invalid_stored_show_by_falls_back_to_ten_and_does_not_impose_another_users_value(): void
    {
        $other = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
        ]);
        UserTableSetting::updateOrCreate(
            ['user_id' => $other->id, 'table_key' => self::TABLE_KEY],
            ['page_length' => 100]
        );
        UserTableSetting::updateOrCreate(
            ['user_id' => $this->foreignUser->id, 'table_key' => self::TABLE_KEY],
            ['page_length' => 50]
        );
        UserTableSetting::updateOrCreate(
            ['user_id' => $this->user->id, 'table_key' => self::TABLE_KEY],
            ['page_length' => 99]
        );

        $html = $this->get(route('admin.setting.tbankCommissions'))
            ->assertOk()
            ->assertViewHas('tbankCommissionsPageLength', 10)
            ->getContent();

        $this->assertMatchesRegularExpression('/pageLength:\s*10\b/', $this->createChunk($html));
        $this->assertDoesNotMatchRegularExpression('/pageLength:\s*100\b/', $this->createChunk($html));
        $this->assertDoesNotMatchRegularExpression('/pageLength:\s*50\b/', $this->createChunk($html));
    }

    public function test_saved_show_by_on_payments_does_not_change_commissions_show_by(): void
    {
        UserTableSetting::updateOrCreate(
            ['user_id' => $this->user->id, 'table_key' => 'reports_payments'],
            ['page_length' => 100]
        );

        $this->get(route('admin.setting.tbankCommissions'))
            ->assertOk()
            ->assertViewHas('tbankCommissionsPageLength', 10);

        $this->postJson(route('admin.setting.tbankCommissions.columns-settings.save'), [
            'page_length' => 50,
        ])->assertOk();

        $this->assertSame(
            100,
            UserTableSetting::query()
                ->where('user_id', $this->user->id)
                ->where('table_key', 'reports_payments')
                ->value('page_length')
        );
        $this->get(route('admin.setting.tbankCommissions'))
            ->assertOk()
            ->assertViewHas('tbankCommissionsPageLength', 50);
    }

    /**
     * @param  list<string>  $needles
     */
    private function assertSequentialFragments(string $haystack, array $needles, string $context): void
    {
        $offset = 0;
        foreach ($needles as $needle) {
            $pos = strpos($haystack, $needle, $offset);
            $this->assertNotFalse($pos, $context.': не найден «'.$needle.'»');
            $offset = $pos + strlen($needle);
        }
    }

    /**
     * @return list<string>
     */
    private function commissionsTheadLabels(string $html): array
    {
        $tablePos = strpos($html, 'id="tbank-commissions-table"');
        $this->assertNotFalse($tablePos);
        $theadStart = strpos($html, '<thead', $tablePos);
        $theadEnd = strpos($html, '</thead>', (int) $theadStart);
        $this->assertNotFalse($theadStart);
        $this->assertNotFalse($theadEnd);
        $thead = substr($html, $theadStart, $theadEnd - $theadStart);

        preg_match_all('/<th[^>]*>(.*?)<\/th>/s', $thead, $matches);
        $labels = [];
        foreach ($matches[1] as $raw) {
            $labels[] = trim(html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        return $labels;
    }

    private function createChunk(string $html): string
    {
        $pos = strpos($html, "KidsCrmDataTable.create('#tbank-commissions-table'");
        $this->assertNotFalse($pos);

        return substr($html, $pos, 4500);
    }

    private function jsObjectChunk(string $html, string $needle): string
    {
        $pos = strpos($html, $needle);
        $this->assertNotFalse($pos, $needle);

        return substr($html, $pos, 800);
    }
}
