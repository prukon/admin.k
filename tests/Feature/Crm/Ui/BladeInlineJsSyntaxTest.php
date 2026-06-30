<?php

namespace Tests\Feature\Crm\Ui;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Статическая проверка inline-JS в blade-модалках с AJAX-submit.
 * Ловит синтаксические ошибки (например, PHP elseif вместо JS else if),
 * из-за которых обработчик submit не регистрируется и форма уходит нативным POST.
 */
final class BladeInlineJsSyntaxTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string}>
     */
    public static function criticalModalBladePathsProvider(): iterable
    {
        yield 'create user modal' => ['includes/modal/createUser.blade.php'];
        yield 'edit user modal' => ['includes/modal/editUser.blade.php'];
        yield 'create team modal' => ['includes/modal/createTeam.blade.php'];
        yield 'edit team modal' => ['includes/modal/editTeam.blade.php'];
        yield 'setting prices users tab' => ['admin/SettingPrices/users.blade.php'];
        yield 'setting prices custom payments tab' => ['admin/SettingPrices/custom-payments.blade.php'];
        yield 'dashboard cabinet team switcher' => ['dashboard.blade.php'];
        yield 'districts index modals' => ['admin/districts/index.blade.php'];
        yield 'admin users page' => ['admin/user.blade.php'];
        yield 'generic multiselect partial' => ['partials/select2/generic-multiselect.blade.php'];
        yield 'schedule journal statuses settings' => ['admin/schedule/index.blade.php'];
        yield 'payment systems settings tab' => ['admin/setting/paymentSystem.blade.php'];
        yield 'tbank commissions settings tab' => ['admin/setting/tbankCommissions.blade.php'];
        yield 'school schedule calendar tab' => ['admin/lessonPackages/tabs/schoolSchedule.blade.php'];
        yield 'lesson package assignments tab' => ['admin/lessonPackages/tabs/assignments.blade.php'];
        yield 'club fee payment page' => ['payment/clubFee.blade.php'];
        yield 'legal entities index modals' => ['admin/legal-entities/index.blade.php'];
        yield 'legal entities show sm and crud forms' => ['admin/legal-entities/show.blade.php'];
        yield 'teams index legal entity column' => ['admin/team.blade.php'];
        yield 'account organization tab ajax form' => ['account/organizations.blade.php'];
        yield 'admin partner create edit modals' => ['includes/modal/editPartner.blade.php'];
    }

    #[DataProvider('criticalModalBladePathsProvider')]
    public function test_critical_modal_inline_scripts_have_valid_javascript_syntax(string $relativePath): void
    {
        $path = resource_path('views/' . $relativePath);
        $this->assertFileExists($path);

        $content = (string) file_get_contents($path);

        preg_match_all('/<script(?![^>]*\bsrc\b)[^>]*>(.*?)<\/script>/is', $content, $matches);

        $this->assertNotEmpty(
            $matches[1],
            "В {$relativePath} не найдено inline <script> для проверки"
        );

        foreach ($matches[1] as $index => $rawScript) {
            $js = $this->normalizeBladeScriptForSyntaxCheck($rawScript);

            if (trim($js) === '') {
                continue;
            }

            $tempFile = sys_get_temp_dir() . '/blade-js-' . uniqid('', true) . '.js';

            try {
                file_put_contents($tempFile, $js);

                $output = [];
                $exitCode = 0;
                exec('node --check ' . escapeshellarg($tempFile) . ' 2>&1', $output, $exitCode);

                $this->assertSame(
                    0,
                    $exitCode,
                    sprintf(
                        "JS syntax error in %s, script block #%d:\n%s\n--- script preview ---\n%s",
                        $relativePath,
                        $index + 1,
                        implode("\n", $output),
                        mb_substr($js, 0, 500)
                    )
                );
            } finally {
                @unlink($tempFile);
            }
        }
    }

    private function normalizeBladeScriptForSyntaxCheck(string $script): string
    {
        $js = $this->stripBladeJsonCalls($script);

        // Blade-выражения → placeholder без кавычек (часто внутри строк: "{{ asset(...) }}/").
        $js = preg_replace('/\{!!.*?!!\}/s', '__BLADE__', $js) ?? $js;
        $js = preg_replace('/\{\{.*?\}\}/s', '__BLADE__', $js) ?? $js;

        // Blade-директивы с аргументами в скобках (@if(...), @include(...), @can(...) и т.п.).
        $js = $this->stripBalancedBladeDirectiveCalls($js);

        // Однострочные blade-директивы (@csrf, @endforeach, @endif и т.п.) — убираем.
        $js = preg_replace('/^\s*@\w+.*$/m', '', $js) ?? $js;

        return $js;
    }

    /**
     * Заменяет @directive(...) на null с учётом вложенных скобок в аргументах.
     */
    private function stripBalancedBladeDirectiveCalls(string $script): string
    {
        $pos = 0;
        $len = strlen($script);

        while ($pos < $len) {
            $at = strpos($script, '@', $pos);
            if ($at === false) {
                break;
            }

            if (! preg_match('/@\w+/', $script, $match, 0, $at) || $match[0] === '') {
                $pos = $at + 1;

                continue;
            }

            $directiveEnd = $at + strlen($match[0]);
            $tail = substr($script, $directiveEnd);
            if (! preg_match('/^\s*\(/', $tail)) {
                $pos = $directiveEnd;

                continue;
            }

            $openParen = $directiveEnd + (int) strpos($tail, '(');
            $i = $openParen + 1;
            $depth = 1;

            while ($i < $len && $depth > 0) {
                $ch = $script[$i];
                if ($ch === '(') {
                    $depth++;
                } elseif ($ch === ')') {
                    $depth--;
                }
                $i++;
            }

            $script = substr($script, 0, $at).'null'.substr($script, $i);
            $pos = $at + 4;
            $len = strlen($script);
        }

        return $script;
    }

    private function stripBladeJsonCalls(string $script): string
    {
        $needle = '@json(';
        $pos = 0;

        while (($start = strpos($script, $needle, $pos)) !== false) {
            $open = $start + strlen($needle);
            $depth = 1;
            $i = $open;
            $len = strlen($script);

            while ($i < $len && $depth > 0) {
                $ch = $script[$i];
                if ($ch === '(') {
                    $depth++;
                } elseif ($ch === ')') {
                    $depth--;
                }
                $i++;
            }

            $script = substr($script, 0, $start) . 'null' . substr($script, $i);
            $pos = $start + 4;
        }

        return $script;
    }
}
