<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#tbank-deal-close-http-error-index совпадает с CloseSpDeal без 500.
 */
final class TbankDealCloseDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_close_deal_http_error_without_500(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="tbank-deal-close-http-error-index"', $html);
        $start = strpos($html, 'id="tbank-deal-close-http-error-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="ops-row-copy-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/tinkoff/deals/{deal}/close', $chunk);
        $this->assertStringContainsString('CloseSpDeal', $chunk);
        $this->assertStringContainsString('HTTP 500', $chunk);
        $this->assertStringContainsString('TinkoffApiClient', $chunk);
        $this->assertStringContainsString('throw: false', $chunk);
        $this->assertStringContainsString('http_status', $chunk);
        $this->assertStringContainsString('TinkoffDealController::close', $chunk);
        $this->assertStringContainsString('payload.deal_close', $chunk);
        $this->assertStringContainsString('errors.tinkoff', $chunk);
        $this->assertStringContainsString('data-error-for="tinkoff"', $chunk);
        $this->assertStringContainsString('TinkoffDealCloseRequest', $chunk);
        $this->assertStringContainsString('manage.payment.method.tbank', $chunk);
        $this->assertStringContainsString('TbankDealCloseTest', $chunk);
        $this->assertStringContainsString('TinkoffApiClientHttpFeatureTest', $chunk);
        $this->assertStringContainsString('test_tinkoff_http_error_is_recorded_at_the_call_site', $chunk);
        $this->assertStringContainsString('ops-till-missing-payout-index', $chunk);
    }

    public function test_live_code_matches_announced_close_deal_contract(): void
    {
        $client = (string) file_get_contents(dirname(__DIR__, 3).'/app/Services/Tinkoff/TinkoffApiClient.php');
        $this->assertStringContainsString('throw: false', $client);
        $this->assertStringContainsString('serverError()', $client);
        $this->assertStringContainsString("json['http_status']", $client);

        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/TinkoffDealController.php');
        $this->assertStringContainsString('TinkoffDealCloseRequest', $controller);
        $this->assertStringContainsString('closeDealUserMessage', $controller);
        $this->assertStringContainsString("withErrors(['tinkoff'", $controller);

        $request = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Requests/Tinkoff/TinkoffDealCloseRequest.php');
        $this->assertStringContainsString("errors()->add('tinkoff'", $request);
        $this->assertStringContainsString('manage.payment.method.tbank', $request);

        $blade = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/tinkoff/payments/show.blade.php');
        $this->assertStringContainsString('data-error-for="tinkoff"', $blade);
        $this->assertStringContainsString("\$errors->first('tinkoff')", $blade);
    }

    public function test_parent_docs_link_the_announcement(): void
    {
        $tbank = $this->docFile('tbank.html');
        $this->assertStringContainsString('/doc#tbank-deal-close-http-error-index', $tbank);
        $this->assertStringContainsString('TinkoffApiClientHttpFeatureTest', $tbank);
        $this->assertStringContainsString('data-error-for="tinkoff"', $tbank);

        $reports = $this->docFile('reports-admin.html');
        $this->assertStringContainsString('/doc#tbank-deal-close-http-error-index', $reports);

        $index = $this->docFile('index.html');
        $this->assertStringContainsString('/doc#tbank-deal-close-http-error-index', $index);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
