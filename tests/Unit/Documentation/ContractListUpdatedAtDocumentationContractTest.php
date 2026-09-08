<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#contract-list-updated-at-index совпадает со списком договоров:
 * колонка «Обновлён» — последнее событие журнала, не contracts.updated_at.
 */
final class ContractListUpdatedAtDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_list_updated_at_from_journal(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="contract-list-updated-at-index"', $html);
        $start = strpos($html, 'id="contract-list-updated-at-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="school-leads-sticky-header-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/client-contracts', $chunk);
        $this->assertStringContainsString('колонка «Обновлён»', $chunk);
        $this->assertStringContainsString('не поле <code>contracts.updated_at</code>', $chunk);
        $this->assertStringContainsString('contract_events', $chunk);
        $this->assertStringContainsString("orderBy('id', 'desc')", $chunk);
        $this->assertStringContainsString('d.m.Y H:i:s', $chunk);
        $this->assertStringContainsString('JSON-ключ DataTables по-прежнему <code>updated_at</code>', $chunk);
        $this->assertStringContainsString('событие', $chunk);
        $this->assertStringContainsString('<code>created</code>', $chunk);
        $this->assertStringContainsString('ContractCreationService', $chunk);
        $this->assertStringContainsString('last_event_at is null, last_event_at', $chunk);
        $this->assertStringContainsString('ContractTableController', $chunk);
        $this->assertStringContainsString('contracts §1.3', $chunk);
        $this->assertStringContainsString('ContractsTableAndColumnsTest', $chunk);
        $this->assertStringContainsString('ContractsDocumentsSectionUiFeatureTest', $chunk);
        $this->assertStringContainsString('ContractListUpdatedAtDocumentationContractTest', $chunk);
        $this->assertStringContainsString('/doc#contract-list-updated-at-index', $chunk);
        $this->assertStringNotContainsString('threads.updated_at', $chunk);
    }

    public function test_related_doc_pages_link_announcement_and_describe_journal(): void
    {
        $contracts = $this->docFile('contracts.html');

        $this->assertStringContainsString('/doc#contract-list-updated-at-index', $contracts);
        $this->assertStringContainsString('id="contract-list-updated-at"', $contracts);
        $this->assertStringContainsString('id="contract-events-journal"', $contracts);
        $this->assertStringContainsString('Журнал событий', $contracts);
        $this->assertStringContainsString('#eventsAccordion', $contracts);
        $this->assertStringContainsString('#colUpdatedAt', $contracts);
        $this->assertStringContainsString("orderBy('id', 'desc')", $contracts);
        $this->assertStringContainsString('ContractCreationService', $contracts);
        $this->assertStringContainsString('не <code>contracts.updated_at</code>', $contracts);
        $this->assertStringContainsString('ContractsTableAndColumnsTest.php', $contracts);
        $this->assertStringContainsString('ContractListUpdatedAtDocumentationContractTest.php', $contracts);
    }

    public function test_catalog_and_controller_title_mention_list_updated_at(): void
    {
        $index = $this->docFile('index.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('/doc#contract-list-updated-at-index', $index);
        $this->assertStringContainsString('колонка «Обновлён»', $index);
        $this->assertStringContainsString('последнее событие журнала', $index);

        $this->assertStringContainsString('колонка «Обновлён» = последнее событие журнала (не contracts.updated_at)', $controller);
    }

    public function test_table_controller_and_views_match_documented_last_event(): void
    {
        $root = dirname(__DIR__, 3);
        $controller = (string) file_get_contents($root.'/app/Http/Controllers/Contracts/ContractTableController.php');
        $index = (string) file_get_contents($root.'/resources/views/contracts/index.blade.php');
        $show = (string) file_get_contents($root.'/resources/views/contracts/show.blade.php');
        $creation = (string) file_get_contents($root.'/app/Services/Contracts/ContractCreationService.php');
        $showController = (string) file_get_contents($root.'/app/Http/Controllers/Contracts/ContractsController.php');

        $this->assertStringContainsString('last_event_at', $controller);
        $this->assertStringContainsString('from contract_events as last_ce', $controller);
        $this->assertStringContainsString('order by last_ce.id desc', $controller);
        $this->assertStringContainsString('formatLastEventAt($contract->last_event_at ?? null)', $controller);
        $this->assertStringContainsString('last_event_at is null, last_event_at', $controller);
        $this->assertStringNotContainsString("orderBy('contracts.updated_at'", $controller);

        $this->assertStringContainsString('<th>Обновлён</th>', $index);
        $this->assertStringContainsString('data-column-key="updated_at"', $index);
        $this->assertStringContainsString('for="colUpdatedAt">Обновлён</label>', $index);

        $this->assertStringContainsString('id="eventsAccordion"', $show);
        $this->assertStringContainsString('Журнал событий', $show);
        $this->assertStringContainsString("\$e->created_at->format('d.m.Y H:i:s')", $show);

        $this->assertStringContainsString("'type'         => 'created'", $creation);

        $this->assertStringContainsString("\$contract->events()->orderBy('id', 'desc')->get()", $showController);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
