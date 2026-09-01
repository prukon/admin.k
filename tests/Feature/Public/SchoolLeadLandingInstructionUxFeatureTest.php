<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Support\RuPhone;
use App\Support\UrlQrCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Tests\Feature\Public\Concerns\ProvidesSchoolLeadLandingFixtures;
use Tests\TestCase;

/**
 * UX инструкции для родителей: QR ведёт на лендинг (не на саму инструкцию),
 * «Скачать PDF» качает PDF, печать скрывает кнопки, телефон не показывается пустым.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SchoolLeadLandingInstructionUxFeatureTest extends TestCase
{
    use ProvidesSchoolLeadLandingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchoolLeadLandingFixtures([
            'title' => 'Центр содействия развития спорта',
            'phone' => '+7 (966) 939-14-13',
        ]);
    }

    public function test_qr_encodes_landing_url_not_instruction_or_pdf(): void
    {
        Auth::logout();

        $slug = (string) $this->landingWidget->landing_slug;
        $landingUrl = route('lead.show', ['landingSlug' => $slug]);
        $instructionUrl = route('lead.instruction', ['landingSlug' => $slug]);
        $pdfUrl = route('lead.instruction.pdf', ['landingSlug' => $slug]);

        $html = $this->get($instructionUrl)->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/id="landing-qr"[^>]*data-url="'.preg_quote($landingUrl, '/').'"/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/id="landing-qr"[^>]*data-url="'.preg_quote($instructionUrl, '/').'"/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/id="landing-qr"[^>]*data-url="'.preg_quote($pdfUrl, '/').'"/',
            $html
        );
        $this->assertStringContainsString('Запись — Центр содействия развития спорта', $html);
        $this->assertStringContainsString('href="'.$landingUrl.'"', $html);
    }

    public function test_download_pdf_button_points_to_pdf_route_not_print_or_html(): void
    {
        Auth::logout();

        $slug = (string) $this->landingWidget->landing_slug;
        $instructionUrl = route('lead.instruction', ['landingSlug' => $slug]);
        $pdfUrl = route('lead.instruction.pdf', ['landingSlug' => $slug]);

        $html = $this->get($instructionUrl)->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<a class="pdf-btn" href="'.preg_quote($pdfUrl, '/').'">Скачать PDF<\/a>/',
            $html
        );
        $this->assertStringNotContainsString('href="'.$instructionUrl.'" class="pdf-btn"', $html);
        $this->assertStringContainsString('<button type="button" onclick="window.print()">Распечатать</button>', $html);

        $printPos = strpos($html, '>Распечатать</button>');
        $pdfPos = strpos($html, '>Скачать PDF</a>');
        $this->assertNotFalse($printPos);
        $this->assertNotFalse($pdfPos);
        $this->assertLessThan($pdfPos, $printPos);
    }

    public function test_print_stylesheet_hides_print_bar_including_pdf_button(): void
    {
        Auth::logout();

        $html = $this->get(route('lead.instruction', [
            'landingSlug' => $this->landingWidget->landing_slug,
        ]))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/@media print[\s\S]*?\.print-bar\s*\{\s*display:\s*none/',
            $html
        );
        $this->assertStringContainsString('@page', $html);
        $this->assertStringContainsString('size: A4', $html);
    }

    public function test_steps_render_in_application_contract_fill_payment_order(): void
    {
        Auth::logout();

        $html = $this->get(route('lead.instruction', [
            'landingSlug' => $this->landingWidget->landing_slug,
        ]))->assertOk()->getContent();

        $apply = strpos($html, '1. Оставьте заявку');
        $cabinet = strpos($html, '2. Получите доступ в личный кабинет');
        $contract = strpos($html, '3. Заполните и подпишите договор');
        $pay = strpos($html, '4. Оплата абонемента');

        $this->assertNotFalse($apply);
        $this->assertNotFalse($cabinet);
        $this->assertNotFalse($contract);
        $this->assertNotFalse($pay);
        $this->assertLessThan($cabinet, $apply);
        $this->assertLessThan($contract, $cabinet);
        $this->assertLessThan($pay, $contract);
    }

    public function test_valid_partner_phone_renders_as_tel_link(): void
    {
        Auth::logout();

        $formatted = RuPhone::formatForInput('+7 (966) 939-14-13');
        $html = $this->get(route('lead.instruction', [
            'landingSlug' => $this->landingWidget->landing_slug,
        ]))->assertOk()->getContent();

        $this->assertStringContainsString('просто позвоните нам', $html);
        $this->assertStringContainsString('href="tel:+79669391413"', $html);
        $this->assertStringContainsString($formatted, $html);
    }

    public function test_whitespace_only_phone_hides_phone_block(): void
    {
        Auth::logout();
        $this->landingPartner->update(['phone' => '   ']);

        $html = $this->get(route('lead.instruction', [
            'landingSlug' => $this->landingWidget->landing_slug,
        ]))->assertOk()->getContent();

        $this->assertStringNotContainsString('просто позвоните нам', $html);
        $this->assertStringNotContainsString('tel:+', $html);
        $this->assertStringContainsString('Мы всегда рядом и с радостью поможем.', $html);
    }

    public function test_incomplete_phone_shows_text_without_tel_link(): void
    {
        Auth::logout();
        $this->landingPartner->update(['phone' => '12345']);

        $html = $this->get(route('lead.instruction', [
            'landingSlug' => $this->landingWidget->landing_slug,
        ]))->assertOk()->getContent();

        $this->assertStringContainsString('просто позвоните нам', $html);
        $this->assertStringContainsString('12345', $html);
        $this->assertStringNotContainsString('href="tel:', $html);
        $this->assertStringContainsString('<strong>12345</strong>', $html);
    }

    public function test_qr_js_draws_from_data_url_with_ecc_m_and_skips_missing_library(): void
    {
        $path = resource_path('views/landing/partner-lead-instruction.blade.php');
        $this->assertFileExists($path);
        $blade = (string) file_get_contents($path);

        $this->assertStringContainsString("qrcode(0, 'M')", $blade);
        $this->assertStringContainsString("el.getAttribute('data-url')", $blade);
        $this->assertStringContainsString('qr.addData(url)', $blade);
        $this->assertStringContainsString('qr.createSvgTag', $blade);
        $this->assertStringContainsString("typeof qrcode !== 'function'", $blade);
        $this->assertStringContainsString('if (!url)', $blade);
        $this->assertSame(1, substr_count($blade, 'getElementById(\'landing-qr\')'));
        $this->assertStringContainsString("asset('js/qrcode-generator.min.js')", $blade);

        Auth::logout();
        $html = $this->get(route('lead.instruction', [
            'landingSlug' => $this->landingWidget->landing_slug,
        ]))->assertOk()->getContent();

        $scriptPos = strpos($html, "qrcode(0, 'M')");
        $this->assertNotFalse($scriptPos);
        $chunk = substr($html, $scriptPos, 700);
        $this->assertStringContainsString('qr.addData(url)', $chunk);
        $this->assertStringContainsString('qr.createSvgTag', $chunk);
        $this->assertStringContainsString("alt: 'QR-код записи'", $chunk);
    }

    public function test_header_shows_kidscrm_logo_and_footer_shows_service_tagline(): void
    {
        Auth::logout();

        $html = $this->get(route('lead.instruction', [
            'landingSlug' => $this->landingWidget->landing_slug,
        ]))->assertOk()->getContent();

        $logoPos = strpos($html, '<div class="brand-bar">');
        $headerPos = strpos($html, '<header class="sheet-header">');
        $footerPos = strpos($html, 'class="sheet-footer"');
        $printPos = strpos($html, 'class="print-bar"');

        $this->assertNotFalse($logoPos);
        $this->assertNotFalse($headerPos);
        $this->assertNotFalse($footerPos);
        $this->assertNotFalse($printPos);
        $this->assertLessThan($headerPos, $logoPos);
        $this->assertLessThan($printPos, $footerPos);
        $this->assertStringContainsString('alt="kidscrm.online"', $html);
        $this->assertStringContainsString('href="https://kidscrm.online/"', $html);
        $this->assertStringContainsString(
            'CRM для учёта детских секций, приёма оплат и онлайн-подписания договоров',
            $html
        );
        $this->assertStringNotContainsString('подписснияд', $html);
    }

    public function test_pdf_template_matches_html_partner_landing_and_embeds_qr_without_print_bar(): void
    {
        Auth::logout();

        $slug = (string) $this->landingWidget->landing_slug;
        $landingUrl = route('lead.show', ['landingSlug' => $slug]);

        $response = $this->get(route('lead.instruction', ['landingSlug' => $slug]))->assertOk();
        $view = $response->original;
        $this->assertInstanceOf(View::class, $view);
        $data = $view->getData();
        $this->assertSame($landingUrl, $data['landingUrl'] ?? null);
        $this->assertSame(
            route('lead.instruction.pdf', ['landingSlug' => $slug]),
            $data['pdfUrl'] ?? null
        );

        $data['qrPngDataUri'] = UrlQrCode::pngDataUri($landingUrl);
        $logoPath = public_path('img/logo.png');
        $this->assertFileExists($logoPath);
        $data['logoPngDataUri'] = 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath));
        $pdfHtml = view('landing.partner-lead-instruction-pdf', $data)->render();

        $this->assertStringContainsString('Центр содействия развития спорта', $pdfHtml);
        $this->assertStringContainsString($landingUrl, $pdfHtml);
        $this->assertStringContainsString('Запись — Центр содействия развития спорта', $pdfHtml);
        $this->assertStringContainsString('data:image/png;base64,', $pdfHtml);
        $this->assertStringContainsString('class="qr"', $pdfHtml);
        $this->assertStringContainsString('class="brand-bar"', $pdfHtml);
        $this->assertMatchesRegularExpression('/\.brand-bar a\s*\{[^}]*text-decoration:\s*none/s', $pdfHtml);
        $this->assertMatchesRegularExpression(
            '#<a href="https://kidscrm\.online/"><img src="data:image/png;base64,#',
            $pdfHtml
        );
        $this->assertStringContainsString('class="sheet-footer"', $pdfHtml);
        $this->assertStringContainsString('https://kidscrm.online/', $pdfHtml);
        $this->assertStringContainsString(
            'CRM для учёта детских секций, приёма оплат и онлайн-подписания договоров',
            $pdfHtml
        );
        $this->assertStringContainsString(RuPhone::formatForInput('+7 (966) 939-14-13'), $pdfHtml);
        $this->assertStringNotContainsString('Скачать PDF', $pdfHtml);
        $this->assertStringNotContainsString('Распечатать', $pdfHtml);
        $this->assertStringNotContainsString('window.print', $pdfHtml);
        $this->assertStringNotContainsString('print-bar', $pdfHtml);
        $this->assertStringNotContainsString('Чужая школа', $pdfHtml);
    }

    public function test_pdf_hides_phone_when_partner_phone_empty(): void
    {
        Auth::logout();
        $this->landingPartner->update(['phone' => null]);

        $response = $this->get(route('lead.instruction', [
            'landingSlug' => $this->landingWidget->landing_slug,
        ]))->assertOk();
        $view = $response->original;
        $this->assertInstanceOf(View::class, $view);
        $data = $view->getData();
        $data['qrPngDataUri'] = UrlQrCode::pngDataUri((string) $data['landingUrl']);

        $pdfHtml = view('landing.partner-lead-instruction-pdf', $data)->render();
        $this->assertStringNotContainsString('просто позвоните нам', $pdfHtml);
        $this->assertArrayHasKey('contactPhone', $data);
        $this->assertNull($data['contactPhone']);
    }

    public function test_server_qr_modules_match_js_generator_for_landing_url(): void
    {
        $slug = (string) $this->landingWidget->landing_slug;
        $landingUrl = route('lead.show', ['landingSlug' => $slug]);

        $php = UrlQrCode::modules($landingUrl);
        $js = $this->jsQrModules($landingUrl);

        $this->assertNotSame([], $php);
        $this->assertSame(count($php), count($js));
        $this->assertSame($php, $js);
    }

    public function test_server_qr_for_instruction_url_differs_from_landing_url(): void
    {
        $slug = (string) $this->landingWidget->landing_slug;
        $landingUrl = route('lead.show', ['landingSlug' => $slug]);
        $instructionUrl = route('lead.instruction', ['landingSlug' => $slug]);

        $this->assertNotSame(
            UrlQrCode::pngDataUri($landingUrl),
            UrlQrCode::pngDataUri($instructionUrl)
        );
    }

    /**
     * @return list<list<bool>>
     */
    private function jsQrModules(string $url): array
    {
        $lib = public_path('js/qrcode-generator.min.js');
        $this->assertFileExists($lib);

        $script = <<<'JS'
const fs = require('fs');
const vm = require('vm');
vm.runInThisContext(fs.readFileSync(process.argv[2], 'utf8'));
const url = process.argv[3];
const qr = qrcode(0, 'M');
qr.addData(url);
qr.make();
const n = qr.getModuleCount();
const modules = [];
for (let y = 0; y < n; y++) {
  const row = [];
  for (let x = 0; x < n; x++) {
    row.push(!!qr.isDark(y, x));
  }
  modules.push(row);
}
process.stdout.write(JSON.stringify(modules));
JS;

        $tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kidscrm-qr-js-'.uniqid('', true).'.cjs';
        file_put_contents($tmp, $script);

        try {
            $cmd = 'node '.escapeshellarg($tmp).' '.escapeshellarg($lib).' '.escapeshellarg($url).' 2>&1';
            $output = [];
            $exit = 0;
            exec($cmd, $output, $exit);
            $this->assertSame(0, $exit, implode("\n", $output));
            $decoded = json_decode(implode("\n", $output), true);
            $this->assertIsArray($decoded);

            $boolGrid = [];
            foreach ($decoded as $row) {
                $this->assertIsArray($row);
                $boolRow = [];
                foreach ($row as $cell) {
                    $boolRow[] = (bool) $cell;
                }
                $boolGrid[] = $boolRow;
            }

            return $boolGrid;
        } finally {
            @unlink($tmp);
        }
    }
}
