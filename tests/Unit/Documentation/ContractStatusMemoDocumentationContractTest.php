<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#contract-status-memo-index совпадает с кнопкой «Памятка»
 * на /client-contracts и timeline как на карточке платежа T‑Bank.
 */
final class ContractStatusMemoDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_contract_status_memo(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="contract-status-memo-index"', $html);
        $start = strpos($html, 'id="contract-status-memo-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="partner-oferta-pdf-permission-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/client-contracts', $chunk);
        $this->assertStringContainsString('#contractStatusMemoModal', $chunk);
        $this->assertStringContainsString('Памятка', $chunk);
        $this->assertStringContainsString('contract-memo-timeline', $chunk);
        $this->assertStringContainsString('tinkoff/payments/partials/timeline.blade.php', $chunk);
        $this->assertStringContainsString('modal-dialog', $chunk);
        $this->assertStringContainsString('не</b> modal-xl', $chunk);
        $this->assertStringContainsString('ContractPathTimelineBuilder', $chunk);
        $this->assertStringContainsString('max-width: min(1100px, 96vw)', $chunk);
        $this->assertStringContainsString('Два блока', $chunk);
        $this->assertStringNotContainsString('Путь с готовым PDF', $chunk);
        $this->assertStringContainsString('Путь с формой клиенту', $chunk);
        $this->assertStringContainsString('Админ отправил договор родителю', $chunk);
        $this->assertStringContainsString('Сформировать договор', $chunk);
        $this->assertStringContainsString('Отправлено СМС', $chunk);
        $this->assertStringContainsString('Открыто СМС', $chunk);
        $this->assertStringContainsString('ждём заполнения', $chunk);
        $this->assertStringContainsString('Подписать договор', $chunk);
        $this->assertStringContainsString('нумерованн', $chunk);
        $this->assertStringContainsString('в своих кабинетах', $chunk);
        $this->assertStringContainsString('последним шагом ввести код из СМС', $chunk);
        $this->assertStringContainsString('generating_pdf', $chunk);
        $this->assertStringContainsString('бейдж', $chunk);
        $this->assertStringContainsString('schoolStatusLabel', $chunk);
        $this->assertStringContainsString('school_status_ru', $chunk);
        $this->assertStringContainsString('Contract::$STATUS_RU', $chunk);
        $this->assertStringContainsString('аудит', $chunk);
        $this->assertStringContainsString('кабинет', $chunk);
        $this->assertStringContainsString('creation_mode', $chunk);
        $this->assertStringContainsString('/client-contracts/{id}', $chunk);
        $this->assertStringContainsString('js-contract-path-open', $chunk);
        $this->assertStringContainsString('(посмотреть)', $chunk);
        $this->assertStringContainsString('#contractPathModal', $chunk);
        $this->assertStringContainsString('path_title', $chunk);
        $this->assertStringContainsString('path_steps', $chunk);
        $this->assertStringContainsString('Другие статусы', $chunk);
        $this->assertStringContainsString('contracts §4.2', $chunk);
        $this->assertStringContainsString('ContractStatusMemoFeatureTest', $chunk);
        $this->assertStringContainsString('ContractSchoolStatusLabelFeatureTest', $chunk);
        $this->assertStringContainsString('ContractSchoolStatusLabelTest', $chunk);
        $this->assertStringContainsString('ContractPathTimelineBuilderTest', $chunk);
        $this->assertStringContainsString('ContractStatusMemoDocumentationContractTest', $chunk);
        $this->assertStringContainsString('fa-book-open', $chunk);
        $this->assertStringNotContainsString('modal-fullscreen', $chunk);
    }

    public function test_related_doc_pages_link_announcement_and_live_markup_matches(): void
    {
        $contracts = $this->docFile('contracts.html');

        $this->assertStringContainsString('/doc#contract-status-memo-index', $contracts);
        $this->assertStringContainsString('id="contract-status-memo"', $contracts);
        $this->assertStringContainsString('#contractStatusMemoModal', $contracts);
        $this->assertStringContainsString('contract-memo-timeline', $contracts);
        $this->assertStringContainsString('max-width: min(1100px, 96vw)', $contracts);
        $this->assertStringContainsString('Путь с готовым PDF', $contracts);
        $this->assertStringContainsString('creation_mode=pdf', $contracts);
        $this->assertStringContainsString('Отправлено СМС', $contracts);
        $this->assertStringContainsString('Открыто СМС', $contracts);
        $this->assertStringContainsString('последним шагом ввести код из СМС', $contracts);
        $this->assertStringContainsString('в своих кабинетах', $contracts);
        $this->assertStringContainsString('нумерованн', $contracts);
        $this->assertStringContainsString('ещё не заполнил данные', $contracts);
        $this->assertStringContainsString('Сформировать договор', $contracts);
        $this->assertStringContainsString('js-contract-path-open', $contracts);
        $this->assertStringContainsString('(посмотреть)', $contracts);
        $this->assertStringContainsString('status-memo-modal.blade.php', $contracts);
        $this->assertStringContainsString('#contractPathModal', $contracts);
        $this->assertStringContainsString('schoolStatusLabel', $contracts);
        $this->assertStringContainsString('school_status_ru', $contracts);
        $this->assertStringContainsString('ContractSchoolStatusLabelFeatureTest', $contracts);

        $fill = $this->docFile('account-contract-fill.html');
        $this->assertStringContainsString('schoolStatusLabel', $fill);
        $this->assertStringContainsString('AccountDocumentsController', $fill);
        $this->assertStringContainsString('Отправлено» / «Открыт', $fill);

        $users = $this->docFile('admin-users.html');
        $this->assertStringContainsString('Contract::$STATUS_RU', $users);
        $this->assertStringContainsString('schoolStatusLabel', $users);
        $this->assertStringContainsString('Отправлено» / «Открыто', $users);

        $leads = $this->docFile('school-leads-widget.html');
        $this->assertStringContainsString('Contract::$STATUS_RU', $leads);
        $this->assertStringContainsString('schoolStatusLabel', $leads);
        $this->assertStringContainsString('Отправлено СМС', $leads);

        $root = dirname(__DIR__, 3);
        $index = (string) file_get_contents($root.'/resources/views/contracts/index.blade.php');
        $modal = (string) file_get_contents($root.'/resources/views/contracts/partials/status-memo-modal.blade.php');
        $pathModal = (string) file_get_contents($root.'/resources/views/contracts/partials/status-path-modal.blade.php');
        $timeline = (string) file_get_contents($root.'/resources/views/contracts/partials/status-memo-timeline.blade.php');
        $styles = (string) file_get_contents($root.'/resources/views/contracts/partials/status-memo-timeline-styles.blade.php');
        $builder = (string) file_get_contents($root.'/app/Services/Contracts/ContractPathTimelineBuilder.php');
        $table = (string) file_get_contents($root.'/app/Http/Controllers/Contracts/ContractTableController.php');
        $contractModel = (string) file_get_contents($root.'/app/Models/Contract.php');
        $show = (string) file_get_contents($root.'/resources/views/contracts/show.blade.php');

        $this->assertStringContainsString('data-bs-target="#contractStatusMemoModal"', $index);
        $this->assertStringContainsString('fa-book-open', $index);
        $this->assertStringContainsString("include('contracts.partials.status-memo-modal')", $index);
        $this->assertStringContainsString("include('contracts.partials.status-path-modal')", $index);
        $this->assertStringContainsString('js-contract-path-open', $index);
        $this->assertStringContainsString('(посмотреть)', $index);
        $this->assertStringContainsString('renderContractPathTimeline', $index);
        $this->assertStringNotContainsString('lockUser: true', $index);
        $this->assertStringContainsString('Contract::schoolStatusLabel', $index);
        $this->assertStringContainsString('value="signed">Подписан', $index);
        $this->assertStringContainsString('value="revoked">Отозван', $index);
        $this->assertStringContainsString('value="awaiting_client_fill">Ожидает заполнения', $index);

        $this->assertStringContainsString('id="contractStatusMemoModal"', $modal);
        $this->assertStringContainsString('class="modal-dialog"', $modal);
        $this->assertStringNotContainsString('modal-xl', $modal);
        $this->assertStringNotContainsString('modal-lg', $modal);
        $this->assertStringNotContainsString('modal-fullscreen', $modal);
        $this->assertStringNotContainsString('Путь с готовым PDF', $modal);
        $this->assertStringNotContainsString('Создание договора стоит', $modal);
        $this->assertStringContainsString('Путь с формой клиенту', $modal);
        $this->assertStringContainsString('Другие статусы', $modal);
        $this->assertStringContainsString('ContractPathTimelineBuilder', $modal);
        $this->assertStringContainsString('showArrows', $modal);

        $this->assertStringContainsString('id="contractPathModal"', $pathModal);
        $this->assertStringContainsString('id="contractPathModalBody"', $pathModal);
        $this->assertStringContainsString('class="modal-dialog"', $pathModal);
        $this->assertStringNotContainsString('modal-xl', $pathModal);
        $this->assertStringNotContainsString('modal-lg', $pathModal);

        $this->assertStringContainsString('contract-memo-timeline__step--{{ $step[\'state\'] }}', $timeline);
        $this->assertStringContainsString('contract-memo-timeline__arrow', $timeline);
        $this->assertStringContainsString('→', $timeline);

        $this->assertStringContainsString('#contractStatusMemoModal .modal-dialog', $styles);
        $this->assertStringContainsString('#contractPathModal .modal-dialog', $styles);
        $this->assertStringContainsString('max-width: min(1100px, 96vw)', $styles);
        $this->assertStringContainsString('white-space: pre-line', $styles);
        $this->assertStringContainsString('text-align: left', $styles);
        $this->assertStringNotContainsString('modal-lg', $styles);

        $this->assertStringContainsString('Админ отправил договор родителю', $builder);
        $this->assertStringContainsString('1. Списалось 70', $builder);
        $this->assertStringContainsString('3. В кабинете у родителя', $builder);
        $this->assertStringContainsString('1. Родитель ещё не заполнил данные. Ждём заполнения.', $builder);
        $this->assertStringContainsString('1. Заполненный договор готов, но ещё не подписан.', $builder);
        $this->assertStringContainsString('4. После этого ему будет отправлена SMS на подпись.', $builder);
        $this->assertStringContainsString('Contract::schoolStatusLabel(Contract::STATUS_SENT)', $builder);
        $this->assertStringContainsString('Contract::schoolStatusLabel(Contract::STATUS_OPENED)', $builder);
        $this->assertStringContainsString('последним шагом ввести код из СМС', $builder);
        $this->assertStringContainsString('в своих кабинетах', $builder);
        $this->assertStringNotContainsString('Можно скачать подписанный PDF.', $builder);
        $this->assertStringNotContainsString('SMS ушло.', $builder);
        $this->assertStringNotContainsString('Родитель заполнил данные и нажал', $builder);
        $this->assertStringNotContainsString('Родитель заполняет форму в кабинете', $builder);
        $this->assertStringNotContainsString('Клиент открыл документ, ещё не подписал.', $builder);
        $this->assertStringContainsString('function schematicTemplateSteps', $builder);
        $this->assertStringContainsString('function build(Contract $contract)', $builder);

        $this->assertStringContainsString('path_steps', $table);
        $this->assertStringContainsString('pathTimelineBuilder->build', $table);
        $this->assertStringContainsString('school_status_ru', $table);
        $this->assertStringContainsString('school_status_ru', $contracts);

        $this->assertStringContainsString('function schoolStatusLabel', $contractModel);
        $this->assertStringContainsString("'Отправлено СМС'", $contractModel);
        $this->assertStringContainsString("'Открыто СМС'", $contractModel);
        $this->assertStringContainsString("self::STATUS_SENT    => 'Отправлено'", $contractModel);
        $this->assertStringContainsString("self::STATUS_OPENED  => 'Открыто'", $contractModel);

        $this->assertStringContainsString('$contract->school_status_ru', $show);
        $this->assertStringNotContainsString('$contract->status_ru }}', $show);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
