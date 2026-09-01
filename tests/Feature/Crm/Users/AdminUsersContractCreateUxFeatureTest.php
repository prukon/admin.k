<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users;

use App\Models\Contract;

/**
 * P1: UX колонки «Договор» на /admin/users — три состояния ячейки, плюс у signed,
 * lockUser из строки, скрытый user_id при disabled Select2, негатив на свободный поиск на /client-contracts.
 *
 * UX-баги до фикса: пустая ячейка без договора; серая PDF-иконка у черновика;
 * ученик оставался редактируемым после клика по строке.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class AdminUsersContractCreateUxFeatureTest extends AdminUsersContractCreateTestCase
{
    public function test_first_open_of_users_page_embeds_create_modal_and_unlocked_student_select(): void
    {
        $this->actingAsUsersViewer(withContractsView: true);

        $html = $this->get(route('admin.user1'))
            ->assertOk()
            ->assertSee('id="createContractModal"', false)
            ->assertSee('id="user_id"', false)
            ->assertSee('id="contract-create-form"', false)
            ->assertSee('id="btn-save" type="button" class="btn btn-primary">Создать договор</button>', false)
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<select name="user_id"\s+id="user_id"/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<select name="user_id"[^>]*\bdisabled\b/',
            $html,
            'на первом HTML ученик не задисейблен — lockUser включается только при openModal из строки'
        );

        $fromSelect = strstr($html, '<select name="user_id"') ?: '';
        $markupBeforeScripts = str_contains($fromSelect, '<script')
            ? substr($fromSelect, 0, (int) strpos($fromSelect, '<script'))
            : $fromSelect;
        $this->assertStringNotContainsString(
            'id="user_id_locked"',
            $markupBeforeScripts,
            'hidden #user_id_locked появляется только после lockUser, не в исходной разметке формы'
        );
        $this->assertStringContainsString('js-open-create-contract-from-user', $html);
        $this->assertStringContainsString('{ lockUser: true }', $html);
        $this->assertStringContainsString('id="users-signed-contract-hint-tpl"', $html);
        $this->assertStringContainsString('data-kids-tooltip-hint', $html);
        $this->assertStringContainsString('users-contract-add-btn', $html);
        $this->assertStringContainsString('fa-plus', $html);
        $this->assertStringContainsString('Создать ещё один договор', $html);
    }

    public function test_cell_without_contract_renders_create_button_instead_of_empty_cell(): void
    {
        $this->actingAsUsersViewer(withContractsView: true);

        $student = $this->createStudent(['lastname' => 'БезДоговораUx']);
        $row = $this->fetchUsersDataRow('БезДоговораUx');
        $this->assertNotNull($row);
        $this->assertArrayNotHasKey('latest_contract', $row);

        $html = $this->renderContractCellHtml([
            'id' => $student->id,
            'name' => $student->full_name,
        ]);

        $this->assertStringContainsString('Создать договор', $html);
        $this->assertStringContainsString('js-open-create-contract-from-user', $html);
        $this->assertStringContainsString('data-user-id="' . $student->id . '"', $html);
        $this->assertStringNotContainsString('fa-file-pdf', $html);
        $this->assertStringNotContainsString('fa-plus', $html);
        $this->assertStringNotContainsString('Создать ещё один договор', $html);
        $this->assertStringNotContainsString('Посмотреть черновик', $html);
        $this->assertNotSame('', trim($html));
    }

    public function test_unsigned_contract_renders_view_draft_button_not_gray_pdf_icon(): void
    {
        $this->actingAsUsersViewer(withContractsView: true);

        $student = $this->createStudent(['lastname' => 'ЧерновикUx']);
        $contract = $this->createContractForUser($student, Contract::STATUS_DRAFT);
        $row = $this->fetchUsersDataRow('ЧерновикUx');
        $this->assertNotNull($row);
        $this->assertSame(Contract::STATUS_DRAFT, $row['latest_contract']['status']);

        $html = $this->renderContractCellHtml([
            'id' => $student->id,
            'latest_contract' => $row['latest_contract'],
        ]);

        $this->assertStringContainsString('Посмотреть черновик', $html);
        $this->assertStringContainsString($row['latest_contract']['url'], $html);
        $this->assertStringNotContainsString('Создать договор', $html);
        $this->assertStringNotContainsString('fa-file-pdf', $html);
        $this->assertStringNotContainsString('fa-plus', $html);
        $this->assertStringNotContainsString('Создать ещё один договор', $html);
        $this->assertStringNotContainsString('#6c757d', $html);

        $this->get($row['latest_contract']['url'])->assertOk();
        $this->assertSame(route('contracts.show', $contract->id), $row['latest_contract']['url']);
    }

    public function test_sent_contract_is_treated_as_draft_not_as_create_or_signed_icon(): void
    {
        $html = $this->renderContractCellHtml([
            'id' => 42,
            'latest_contract' => [
                'url' => 'https://example.test/client-contracts/42',
                'status' => Contract::STATUS_SENT,
                'status_label' => 'Отправлено',
            ],
        ]);

        $this->assertStringContainsString('Посмотреть черновик', $html);
        $this->assertStringContainsString('https://example.test/client-contracts/42', $html);
        $this->assertStringNotContainsString('Создать договор', $html);
        $this->assertStringNotContainsString('fa-file-pdf', $html);
        $this->assertStringNotContainsString('fa-plus', $html);
        $this->assertStringNotContainsString('Создать ещё один договор', $html);
    }

    public function test_any_unsigned_status_renders_view_draft_not_create_or_pdf_icon(): void
    {
        $unsigned = [
            Contract::STATUS_AWAITING_CLIENT_FILL,
            Contract::STATUS_GENERATING_PDF,
            Contract::STATUS_OPENED,
            Contract::STATUS_EXPIRED,
            Contract::STATUS_REVOKED,
            Contract::STATUS_FAILED,
        ];

        foreach ($unsigned as $status) {
            $html = $this->renderContractCellHtml([
                'id' => 7,
                'latest_contract' => [
                    'url' => 'https://example.test/client-contracts/7',
                    'status' => $status,
                    'status_label' => $status,
                ],
            ]);

            $this->assertStringContainsString('Посмотреть черновик', $html, $status);
            $this->assertStringNotContainsString('Создать договор', $html, $status);
            $this->assertStringNotContainsString('fa-file-pdf', $html, $status);
            $this->assertStringNotContainsString('fa-plus', $html, $status);
            $this->assertStringNotContainsString('Создать ещё один договор', $html, $status);
        }
    }

    public function test_latest_contract_without_url_falls_back_to_create_button(): void
    {
        $html = $this->renderContractCellHtml([
            'id' => 9,
            'latest_contract' => [
                'status' => Contract::STATUS_DRAFT,
                'status_label' => 'Черновик',
            ],
        ]);

        $this->assertStringContainsString('Создать договор', $html);
        $this->assertStringContainsString('js-open-create-contract-from-user', $html);
        $this->assertStringNotContainsString('Посмотреть черновик', $html);
        $this->assertStringNotContainsString('fa-plus', $html);
        $this->assertStringNotContainsString('Создать ещё один договор', $html);
    }

    public function test_signed_contract_renders_pdf_icon_not_create_or_draft_button(): void
    {
        $this->actingAsUsersViewer(withContractsView: true);

        $student = $this->createStudent(['lastname' => 'ПодписанUx']);
        $this->createContractForUser($student, Contract::STATUS_SIGNED);
        $row = $this->fetchUsersDataRow('ПодписанUx');
        $this->assertNotNull($row);
        $this->assertSame(Contract::STATUS_SIGNED, $row['latest_contract']['status']);

        $html = $this->renderContractCellHtml([
            'id' => $student->id,
            'latest_contract' => $row['latest_contract'],
        ]);

        $this->assertStringContainsString('fa-file-pdf', $html);
        $this->assertStringContainsString('#0d6efd', $html);
        $this->assertStringContainsString('Статус: Подписано', $html);
        $this->assertStringContainsString('kids-tooltip-hint', $html);
        $this->assertStringContainsString('data-kids-tooltip-hint', $html);
        $this->assertStringContainsString('ulp-assignment-paid-tooltip', $html);
        $this->assertStringContainsString('fa-plus', $html);
        $this->assertStringContainsString('users-contract-add-btn', $html);
        $this->assertStringContainsString('Создать ещё один договор', $html);
        $this->assertStringContainsString('js-open-create-contract-from-user', $html);
        $this->assertStringContainsString('data-user-id="' . $student->id . '"', $html);
        $this->assertStringContainsString('users-contract-cell', $html);
        $this->assertSame(1, preg_match('/<a href="[^"]*" class="users-contract-icon-link">(.*?)<\/a>/s', $html, $linkMatch));
        $this->assertStringContainsString('fa-file-pdf', $linkMatch[1]);
        $this->assertStringNotContainsString('fa-plus', $linkMatch[1], 'плюс — сосед PDF-ссылки, не внутри неё');
        $this->assertStringNotContainsString('js-dt-cell-ellipsis-tooltip', $html);
        $this->assertStringNotContainsString('>Создать договор</button>', $html);
        $this->assertStringNotContainsString('Посмотреть черновик', $html);
    }

    public function test_signed_plus_is_type_button_outside_pdf_link_and_shares_create_handler(): void
    {
        $this->actingAsUsersViewer(withContractsView: true);

        $student = $this->createStudent(['lastname' => 'ПлюсПодписанUx']);
        $this->createContractForUser($student, Contract::STATUS_SIGNED);
        $row = $this->fetchUsersDataRow('ПлюсПодписанUx');
        $this->assertNotNull($row);

        $html = $this->renderContractCellHtml([
            'id' => $student->id,
            'latest_contract' => $row['latest_contract'],
        ]);

        $this->assertSame(
            1,
            preg_match(
                '/<button type="button"[^>]*\busers-contract-add-btn\b[^>]*>.*?<\/button>/s',
                $html,
                $btnMatch
            )
        );
        $plus = $btnMatch[0];
        $this->assertStringContainsString('js-open-create-contract-from-user', $plus);
        $this->assertStringContainsString('data-user-id="' . $student->id . '"', $plus);
        $this->assertStringContainsString('data-kids-tooltip-hint', $plus);
        $this->assertStringContainsString('title="Создать ещё один договор"', $plus);
        $this->assertStringContainsString('aria-label="Создать ещё один договор"', $plus);
        $this->assertStringContainsString('fa-plus', $plus);
        $this->assertStringContainsString('#0d6efd', $plus);
        $this->assertStringNotContainsString('users-contract-icon-link', $plus);

        $usersJs = $this->usersBladeSource();
        $this->assertStringContainsString(
            "$('#users-table').on('click', '.js-open-create-contract-from-user'",
            $usersJs
        );
        $this->assertStringContainsString('event.stopPropagation()', $usersJs);
        $this->assertStringContainsString('event.preventDefault()', $usersJs);
        $this->assertStringContainsString(
            'window.KidsCrmContractCreate.openModal(preselected, { lockUser: true })',
            $usersJs
        );
    }

    public function test_latest_unsigned_wins_over_older_signed_for_cell_state(): void
    {
        $this->actingAsUsersViewer(withContractsView: true);

        $student = $this->createStudent(['lastname' => 'СмешанныеUx']);
        $this->createContractForUser($student, Contract::STATUS_SIGNED, now()->subDay());
        $latest = $this->createContractForUser($student, Contract::STATUS_DRAFT);

        $row = $this->fetchUsersDataRow('СмешанныеUx');
        $this->assertNotNull($row);
        $this->assertSame(Contract::STATUS_DRAFT, $row['latest_contract']['status']);
        $this->assertSame(route('contracts.show', $latest->id), $row['latest_contract']['url']);

        $html = $this->renderContractCellHtml([
            'id' => $student->id,
            'latest_contract' => $row['latest_contract'],
        ]);
        $this->assertStringContainsString('Посмотреть черновик', $html);
        $this->assertStringNotContainsString('fa-file-pdf', $html);
        $this->assertStringNotContainsString('fa-plus', $html);
        $this->assertStringNotContainsString('Создать ещё один договор', $html);
    }

    public function test_opening_from_client_row_locks_student_and_keeps_user_id_for_submit(): void
    {
        $usersJs = $this->usersBladeSource();
        $modalJs = $this->contractCreateModalSource();

        $this->assertStringContainsString(
            'window.KidsCrmContractCreate.openModal(preselected, { lockUser: true })',
            $usersJs
        );

        $lockFn = $this->extractJsFunction($modalJs, 'setContractUserSelectLocked');
        $this->assertStringContainsString("\$userSelect.prop('disabled', !!locked)", $lockFn);
        $this->assertStringContainsString('id="user_id_locked"', $lockFn);
        $this->assertStringContainsString('name="user_id"', $lockFn);
        $this->assertStringContainsString("\$userSelect.removeAttr('name')", $lockFn);
        $this->assertStringContainsString("\$userSelect.attr('name', 'user_id')", $lockFn);

        $openModalPos = strpos($modalJs, 'window.KidsCrmContractCreate.openModal = function');
        $this->assertNotFalse($openModalPos);
        $openChunk = substr($modalJs, $openModalPos, 700);
        $resetPos = strpos($openChunk, 'resetCreateContractForm()');
        $lockAssignPos = strpos($openChunk, 'lockPreselectedUser = !!(options && options.lockUser)');
        $this->assertNotFalse($resetPos);
        $this->assertNotFalse($lockAssignPos);
        $this->assertLessThan(
            $lockAssignPos,
            $resetPos,
            'повторное открытие сначала сбрасывает lock, затем ставит его заново под нового ученика'
        );

        $resetFn = $this->extractJsFunction($modalJs, 'resetCreateContractForm');
        $this->assertStringContainsString('setContractUserSelectLocked(false)', $resetFn);
        $this->assertStringContainsString('lockPreselectedUser = false', $resetFn);

        $this->assertStringContainsString(
            'lockPreselectedUser = !!(options && options.lockUser) && !!activePreselectedUser',
            $modalJs,
            'lockUser без предзаполненного ученика не должен запирать поле'
        );

        $usersClick = $this->extractJsFunction($usersJs, 'openCreateContractFromUser');
        $this->assertStringContainsString('{ lockUser: true }', $usersClick);
        $this->assertStringContainsString('event.preventDefault()', $usersJs);
        $this->assertStringContainsString(".js-open-create-contract-from-user", $usersJs);
    }

    public function test_locking_student_select_moves_user_id_to_hidden_input_so_submit_keeps_value(): void
    {
        $lockFn = $this->extractJsFunction(
            $this->contractCreateModalSource(),
            'setContractUserSelectLocked'
        );

        $json = $this->runNodeScript(
            $lockFn . "\n" . <<<'JS'
const state = { disabled: false, name: 'user_id', value: '17', hidden: null };
function $(sel) {
    if (sel === '#user_id') {
        return {
            length: 1,
            prop(key, val) {
                if (arguments.length > 1) { state.disabled = !!val; return this; }
                return state.disabled;
            },
            val() { return state.value; },
            removeAttr(attr) { if (attr === 'name') { state.name = null; } return this; },
            attr(attr, val) {
                if (arguments.length > 1) { if (attr === 'name') { state.name = val; } return this; }
                return attr === 'name' ? state.name : undefined;
            },
            after(html) { state.hidden = { html: String(html), value: '' }; },
        };
    }
    if (sel === '#user_id_locked') {
        return {
            get length() { return state.hidden ? 1 : 0; },
            val(v) {
                if (!state.hidden) { return this; }
                if (arguments.length) { state.hidden.value = v; return this; }
                return state.hidden.value;
            },
            remove() { state.hidden = null; return this; },
        };
    }
    return { length: 0 };
}
setContractUserSelectLocked(true);
const locked = {
    selectDisabled: state.disabled,
    selectName: state.name,
    hiddenHtml: state.hidden && state.hidden.html,
    hiddenValue: state.hidden && state.hidden.value,
};
setContractUserSelectLocked(false);
const unlocked = {
    selectDisabled: state.disabled,
    selectName: state.name,
    hidden: state.hidden,
};
process.stdout.write(JSON.stringify({ locked, unlocked }));
JS
        );

        $payload = json_decode($json, true);
        $this->assertTrue($payload['locked']['selectDisabled']);
        $this->assertNull($payload['locked']['selectName']);
        $this->assertStringContainsString('id="user_id_locked"', (string) $payload['locked']['hiddenHtml']);
        $this->assertStringContainsString('name="user_id"', (string) $payload['locked']['hiddenHtml']);
        $this->assertSame('17', $payload['locked']['hiddenValue']);

        $this->assertFalse($payload['unlocked']['selectDisabled']);
        $this->assertSame('user_id', $payload['unlocked']['selectName']);
        $this->assertNull($payload['unlocked']['hidden']);
    }

    public function test_opening_from_lead_row_also_locks_student_on_both_js_paths(): void
    {
        $leads = (string) file_get_contents(resource_path('views/admin/school-leads/tabs/leads.blade.php'));
        $this->assertSame(
            2,
            substr_count($leads, 'KidsCrmContractCreate.openModal(preselected, { lockUser: true })'),
            'оба пути (из модалки лида после hidden и из колонки таблицы) должны lockUser'
        );
        $this->assertStringNotContainsString(
            'KidsCrmContractCreate.openModal(preselected);',
            $leads
        );
    }

    public function test_contracts_toolbar_create_does_not_lock_student_select(): void
    {
        $index = (string) file_get_contents(resource_path('views/contracts/index.blade.php'));
        $this->assertStringContainsString('data-bs-target="#createContractModal"', $index);
        $this->assertStringNotContainsString('lockUser: true', $index);

        $modalJs = $this->contractCreateModalSource();
        $this->assertStringContainsString('let lockPreselectedUser = false;', $modalJs);
        $this->assertStringContainsString('allowClear: !lockPreselectedUser', $modalJs);
    }

    public function test_preselected_user_from_clients_row_uses_id_name_and_parent(): void
    {
        $fn = $this->extractJsFunction($this->usersBladeSource(), 'buildContractPreselectedUser');
        $row = json_encode([
            'id' => 17,
            'name' => 'Иванов Иван',
            'parent' => 'Петрова Анна',
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $json = $this->runNodeScript(
            $fn . "\nprocess.stdout.write(JSON.stringify(buildContractPreselectedUser({$row})));"
        );
        $payload = json_decode($json, true);
        $this->assertSame(17, $payload['id']);
        $this->assertSame('Иванов Иван', $payload['text']);
        $this->assertSame('Петрова Анна', $payload['parent_full_name']);

        $empty = $this->runNodeScript(
            $fn . "\nprocess.stdout.write(JSON.stringify(buildContractPreselectedUser(null)));"
        );
        $this->assertSame('null', $empty);
    }

    public function test_manager_without_contracts_view_does_not_see_create_button_or_modal(): void
    {
        $this->actingAsUsersViewer(withContractsView: false);

        $this->get(route('admin.user1'))
            ->assertOk()
            ->assertDontSee('id="createContractModal"', false)
            ->assertDontSee('js-open-create-contract-from-user', false)
            ->assertDontSee('openCreateContractFromUser', false)
            ->assertDontSee('Посмотреть черновик', false)
            ->assertDontSee('id="users-signed-contract-hint-tpl"', false)
            ->assertDontSee('users-contract-add-btn', false)
            ->assertDontSee('Создать ещё один договор', false);
    }
}
