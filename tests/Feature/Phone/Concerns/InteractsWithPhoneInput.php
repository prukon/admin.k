<?php

namespace Tests\Feature\Phone\Concerns;

use App\Services\PartnerContext;
use App\Support\RuPhone;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

trait InteractsWithPhoneInput
{
    protected function configureWritableCompiledViews(): void
    {
        $compiled = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'kidscrm_compiled_views_'
            . (string) Str::uuid();

        if (!is_dir($compiled)) {
            @mkdir($compiled, 0777, true);
        }
        @chmod($compiled, 0777);
        config(['view.compiled' => $compiled]);
    }

    protected function expectedCanonicalPhone(string $maskedOrRaw): string
    {
        $digits = RuPhone::normalizeDigits($maskedOrRaw);

        return $digits ? '+7' . substr($digits, 1) : '';
    }

    protected function assertSameNormalizedPhone(string $expected, ?string $actual): void
    {
        $this->assertSame(
            RuPhone::normalizeDigits($expected),
            RuPhone::normalizeDigits($actual),
        );
    }

    protected function randomRuPhoneDigits(): string
    {
        $suffix = str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);

        return '79' . $suffix;
    }

    protected function randomRuPhoneMasked(): string
    {
        return RuPhone::formatForInput($this->randomRuPhoneDigits());
    }

    protected function expectedPhoneInputValue(?string $storedPhone): string
    {
        return RuPhone::formatForInput($storedPhone);
    }

    protected function resetPartnerContextCache(): void
    {
        $this->app->forgetInstance(PartnerContext::class);
    }

    protected function assertPhoneMaskScriptsInHtml(string $html): void
    {
        $this->assertStringContainsString('jquery.inputmask', $html);
        $this->assertStringContainsString('PhoneInputMask', $html);
    }

    protected function assertCentralizedPhoneMaskAssetsInHtml(string $html): void
    {
        $this->assertPhoneMaskScriptsInHtml($html);
        $this->assertStringContainsString('js-phone-mask', $html);
        $this->assertStringContainsString('type="tel"', $html);
    }

    /**
     * @return list<string>
     */
    protected function bladeSourcesUsingPhoneInputPartial(): array
    {
        $matches = [];
        $viewsPath = resource_path('views');

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsPath)) as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());
            if (str_contains($contents, "includes.fields.phone-input")) {
                $matches[] = str_replace($viewsPath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        sort($matches);

        return $matches;
    }

    /**
     * @return list<string>
     */
    protected function findBladeViolationsForPhoneCentralization(): array
    {
        $violations = [];
        $viewsPath = resource_path('views');
        $allowedTelFiles = [
            'includes/fields/phone-input.blade.php',
        ];
        $allowedJsPhoneMaskFiles = [
            'includes/fields/phone-input.blade.php',
            'includes/scripts/phone-inputmask-init.blade.php',
        ];

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsPath)) as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $relative = str_replace($viewsPath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $relative = str_replace('\\', '/', $relative);
            $contents = (string) file_get_contents($file->getPathname());

            if (!in_array($relative, $allowedTelFiles, true)
                && preg_match('/<input[^>]*\btype=["\']tel["\']/i', $contents)) {
                $violations[] = $relative . ': standalone type="tel" input';
            }

            if (!in_array($relative, $allowedJsPhoneMaskFiles, true)
                && preg_match('/\bclass=["\'][^"\']*js-phone-mask/i', $contents)) {
                $violations[] = $relative . ': standalone js-phone-mask class';
            }

            if ($relative !== 'includes/scripts/phone-inputmask-init.blade.php'
                && preg_match('/\.inputmask\s*\(/', $contents)) {
                $violations[] = $relative . ': direct .inputmask() call';
            }
        }

        return $violations;
    }

    protected function assertHtmlContainsFormattedPhone(string $html, ?string $storedPhone): void
    {
        $formatted = $this->expectedPhoneInputValue($storedPhone);
        $canonical = $this->expectedCanonicalPhone((string) $storedPhone);

        $this->assertTrue(
            ($formatted !== '' && str_contains($html, 'value="' . $formatted . '"'))
            || ($canonical !== '' && str_contains($html, 'value="' . $canonical . '"'))
            || ($formatted !== '' && str_contains($html, $formatted)),
            'Phone value not found in HTML.'
        );
    }
}
