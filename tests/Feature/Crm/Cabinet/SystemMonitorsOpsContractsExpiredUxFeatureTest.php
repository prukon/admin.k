<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Cabinet;

use App\Models\Contract;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * UX строки «Договоры»: первый кадр «…», ховер ученик+школа, leftover при 403, XSS в title.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SystemMonitorsOpsContractsExpiredUxFeatureTest extends SystemMonitorsTestCase
{
    public function test_first_open_contracts_row_is_ellipsis_not_db_counts(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => true])->save();
        $student = $this->createUserWithRole('user', $this->partner, [
            'lastname' => 'ПервыйКадр',
            'name' => 'Ученик',
            'email' => 'ops-contracts-first-html@example.test',
        ]);
        $this->makeContract($student, $this->partner, [
            'creation_mode' => Contract::CREATION_MODE_TEMPLATE,
            'status' => Contract::STATUS_AWAITING_CLIENT_FILL,
            'fill_expires_at' => now()->subHour(),
        ]);

        $html = $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('>Договоры</span>', $html);
        $this->assertMatchesRegularExpression('/data-role="contracts-fill-expired">…<\/span>/u', $html);
        $this->assertMatchesRegularExpression('/data-role="contracts-sms-expired">…<\/span>/u', $html);
        $this->assertStringContainsString('Просрочено заполнение в кабинете', $html);
        $this->assertStringContainsString('Статус expired у Подпислона', $html);
        $this->assertStringNotContainsString('data-role="contracts-fill-expired">1', $html);

        $opsStart = strpos($html, 'id="js-ops-monitors"');
        $this->assertNotFalse($opsStart);
        $opsEnd = strpos($html, '</script>', $opsStart);
        $this->assertNotFalse($opsEnd);
        $opsHtml = substr($html, $opsStart, $opsEnd - $opsStart);
        $this->assertStringNotContainsString('ПервыйКадр', $opsHtml);
        $this->assertStringNotContainsString('ops-contracts-first-html@example.test', $opsHtml);

        $welcomePos = strpos($html, '>Welcome</span>');
        $contractsPos = strpos($html, '>Договоры</span>');
        $this->assertNotFalse($welcomePos);
        $this->assertNotFalse($contractsPos);
        $this->assertGreaterThan($welcomePos, $contractsPos);
    }

    public function test_login_page_does_not_render_contracts_row(): void
    {
        Auth::logout();

        $html = $this->get(route('login'))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="js-ops-monitors"', $html);
        $this->assertStringNotContainsString('data-role="contracts-fill-expired"', $html);
        $this->assertStringNotContainsString('data-role="contracts-sms-expired"', $html);
    }

    public function test_overlay_paints_zero_not_dash_and_restores_default_hint(): void
    {
        $painted = $this->simulateContractsPaint([
            'ok' => true,
            'queue' => ['jobs' => 0, 'failed_jobs' => 0, 'overdue_payouts' => 0],
            'till' => ['overdue_payouts' => 0, 'failed_intents' => 0, 'fiscal_errors' => 0],
            'errors' => ['count' => 0],
            'gateways' => [],
            'auth' => ['failed_logins' => 0, 'failed_2fa' => 0],
            'welcome' => ['missing_count' => 0, 'last_user_id' => null],
            'contracts' => [
                'fill_expired_count' => 0,
                'fill_expired' => [],
                'sms_expired_count' => 0,
                'sms_expired' => [],
            ],
        ]);

        $this->assertSame('0', $painted['fill_text']);
        $this->assertSame('is-ok', $painted['fill_tone']);
        $this->assertSame('0', $painted['sms_text']);
        $this->assertSame('is-ok', $painted['sms_tone']);
        $this->assertNotSame('—', $painted['fill_text']);
        $this->assertSame(
            'Просрочено заполнение в кабинете: template + awaiting_client_fill, fill_expires_at в прошлом (все школы). Ховер: ученик, школа, срок заполнения',
            $painted['fill_title']
        );
        $this->assertSame(
            'Статус expired у Подпислона: ссылка SMS просрочена (все школы). Ховер: ученик, школа, дата обновления',
            $painted['sms_title']
        );
        $this->assertSame('', $painted['inner_html']);
        $this->assertSame('setAttribute', $painted['title_write_mode']);
    }

    public function test_overlay_paints_hover_with_student_school_and_and_more(): void
    {
        $painted = $this->simulateContractsPaint([
            'ok' => true,
            'queue' => ['jobs' => 0, 'failed_jobs' => 0, 'overdue_payouts' => 0],
            'till' => ['overdue_payouts' => 0, 'failed_intents' => 0, 'fiscal_errors' => 0],
            'errors' => ['count' => 0],
            'gateways' => [],
            'auth' => ['failed_logins' => 0, 'failed_2fa' => 0],
            'welcome' => ['missing_count' => 0, 'last_user_id' => null],
            'contracts' => [
                'fill_expired_count' => 21,
                'fill_expired' => [[
                    'name' => 'Третьяк Натан',
                    'school' => 'Исток',
                    'at' => 1789122717,
                ]],
                'sms_expired_count' => 1,
                'sms_expired' => [[
                    'name' => '#9',
                    'school' => 'Без школы',
                    'at' => 1789122717,
                ]],
            ],
        ]);

        $this->assertSame('21', $painted['fill_text']);
        $this->assertSame('is-bad', $painted['fill_tone']);
        $this->assertStringContainsString('Третьяк Натан', $painted['fill_title']);
        $this->assertStringContainsString('Исток', $painted['fill_title']);
        $this->assertStringContainsString('и ещё 20', $painted['fill_title']);
        $this->assertSame('1', $painted['sms_text']);
        $this->assertSame('is-bad', $painted['sms_tone']);
        $this->assertStringContainsString('#9', $painted['sms_title']);
        $this->assertStringContainsString('Без школы', $painted['sms_title']);
        $this->assertSame('', $painted['inner_html']);
    }

    public function test_xss_in_student_name_is_written_to_hint_title_not_inner_html(): void
    {
        $payload = '<img src=x onerror=alert(1)>';
        $painted = $this->simulateContractsPaint([
            'ok' => true,
            'queue' => ['jobs' => 0, 'failed_jobs' => 0, 'overdue_payouts' => 0],
            'till' => ['overdue_payouts' => 0, 'failed_intents' => 0, 'fiscal_errors' => 0],
            'errors' => ['count' => 0],
            'gateways' => [],
            'auth' => ['failed_logins' => 0, 'failed_2fa' => 0],
            'welcome' => ['missing_count' => 0, 'last_user_id' => null],
            'contracts' => [
                'fill_expired_count' => 1,
                'fill_expired' => [[
                    'name' => $payload,
                    'school' => $payload,
                    'at' => 1789122717,
                ]],
                'sms_expired_count' => 0,
                'sms_expired' => [],
            ],
        ]);

        $this->assertStringContainsString($payload, $painted['fill_title']);
        $this->assertSame('setAttribute', $painted['title_write_mode']);
        $this->assertSame('', $painted['inner_html']);
    }

    public function test_forbidden_refresh_clears_stale_contract_counts_and_hints(): void
    {
        $result = $this->simulateContractsForbiddenRefresh();

        $this->assertSame('3', $result['fill_after_ok']);
        $this->assertStringContainsString('leftover-student', $result['fill_title_after_ok']);
        $this->assertSame('—', $result['fill_after_forbidden']);
        $this->assertSame('—', $result['sms_after_forbidden']);
        $this->assertSame(
            'Просрочено заполнение в кабинете: template + awaiting_client_fill, fill_expires_at в прошлом (все школы). Ховер: ученик, школа, срок заполнения',
            $result['fill_title_after_forbidden']
        );
        $this->assertSame(
            'Статус expired у Подпислона: ссылка SMS просрочена (все школы). Ховер: ученик, школа, дата обновления',
            $result['sms_title_after_forbidden']
        );
        $this->assertStringNotContainsString('leftover-student', $result['fill_title_after_forbidden']);
        $this->assertSame('', $result['inner_html']);
        $this->assertSame(5000, $result['interval_ms']);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function simulateContractsPaint(array $payload): array
    {
        $bladePath = resource_path('views/includes/system_monitors/ops.blade.php');
        $this->assertFileExists($bladePath);

        $script = <<<'JS'
const fs = require('fs');
const blade = fs.readFileSync(process.argv[2], 'utf8');
const payload = JSON.parse(process.argv[3]);
const start = blade.indexOf('function monitorsOn()');
const end = blade.indexOf('function refresh()');
if (start < 0 || end <= start) {
    throw new Error('ops render helpers not found');
}
const src = blade.slice(start, end);
const nodes = {};
let titleWriteMode = '';
let innerHtml = '';
function makeWrap(defaultTitle) {
    const attrs = { title: defaultTitle, 'aria-label': defaultTitle };
    return {
        parentElement: {},
        getAttribute: function (name) {
            return Object.prototype.hasOwnProperty.call(attrs, name) ? attrs[name] : '';
        },
        setAttribute: function (name, value) {
            if (name === 'title') titleWriteMode = 'setAttribute';
            attrs[name] = String(value);
        },
        removeAttribute: function (name) { delete attrs[name]; },
        _attrs: attrs
    };
}
const fillWrap = makeWrap('Просрочено заполнение в кабинете: template + awaiting_client_fill, fill_expires_at в прошлом (все школы). Ховер: ученик, школа, срок заполнения');
const smsWrap = makeWrap('Статус expired у Подпислона: ссылка SMS просрочена (все школы). Ховер: ученик, школа, дата обновления');
function makeNode(role) {
    const classes = new Set();
    return {
        textContent: '…',
        classList: {
            remove: function () {
                Array.prototype.forEach.call(arguments, function (name) { classes.delete(name); });
            },
            add: function (name) { classes.add(name); }
        },
        tone: function () { return Array.from(classes).join(' '); },
        closest: function () {
            if (role === 'contracts-fill-expired') return fillWrap;
            if (role === 'contracts-sms-expired') return smsWrap;
            return null;
        }
    };
}
const root = {
    querySelector: function (sel) {
        const m = sel.match(/data-role="([^"]+)"/);
        if (!m) return null;
        if (!nodes[m[1]]) nodes[m[1]] = makeNode(m[1]);
        return nodes[m[1]];
    }
};
eval(src);
render(payload);
process.stdout.write(JSON.stringify({
    fill_text: nodes['contracts-fill-expired'].textContent,
    fill_tone: nodes['contracts-fill-expired'].tone(),
    sms_text: nodes['contracts-sms-expired'].textContent,
    sms_tone: nodes['contracts-sms-expired'].tone(),
    fill_title: fillWrap._attrs.title,
    sms_title: smsWrap._attrs.title,
    inner_html: innerHtml,
    title_write_mode: titleWriteMode
}));
JS;

        return $this->runNodeScript($script, [$bladePath, json_encode($payload, JSON_THROW_ON_ERROR)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function simulateContractsForbiddenRefresh(): array
    {
        $bladePath = resource_path('views/includes/system_monitors/ops.blade.php');
        $this->assertFileExists($bladePath);

        $script = <<<'JS'
const fs = require('fs');
const blade = fs.readFileSync(process.argv[2], 'utf8');
const start = blade.indexOf('(function () {');
const end = blade.lastIndexOf('})();');
if (start < 0 || end < 0) {
    throw new Error('ops monitors IIFE not found');
}
const src = blade.slice(start, end + 5);

const bodyClasses = new Set(['system-monitors-on']);
const intervals = [];
const listeners = {};
const nodes = {};
let innerHtml = '';
function makeWrap(defaultTitle) {
    const attrs = { title: defaultTitle, 'aria-label': defaultTitle };
    return {
        parentElement: {},
        getAttribute: function (name) {
            return Object.prototype.hasOwnProperty.call(attrs, name) ? attrs[name] : '';
        },
        setAttribute: function (name, value) { attrs[name] = String(value); },
        removeAttribute: function (name) { delete attrs[name]; },
        _attrs: attrs
    };
}
const fillWrap = makeWrap('Просрочено заполнение в кабинете: template + awaiting_client_fill, fill_expires_at в прошлом (все школы). Ховер: ученик, школа, срок заполнения');
const smsWrap = makeWrap('Статус expired у Подпислона: ссылка SMS просрочена (все школы). Ховер: ученик, школа, дата обновления');
function makeNode(role) {
    return {
        textContent: '…',
        classList: { remove: function () {}, add: function () {} },
        closest: function () {
            if (role === 'contracts-fill-expired') return fillWrap;
            if (role === 'contracts-sms-expired') return smsWrap;
            return null;
        }
    };
}
const root = {
    getAttribute: function () { return '/cabinet/system-monitors/ops'; },
    querySelector: function (sel) {
        const m = sel.match(/data-role="([^"]+)"/);
        if (!m) return null;
        if (!nodes[m[1]]) nodes[m[1]] = makeNode(m[1]);
        return nodes[m[1]];
    }
};
global.document = {
    getElementById: function (id) { return id === 'js-ops-monitors' ? root : null; },
    body: { classList: { contains: function (name) { return bodyClasses.has(name); } } },
    addEventListener: function (type, fn) { listeners[type] = fn; }
};
global.window = global;
global.setInterval = function (fn, ms) {
    const id = intervals.length + 1;
    intervals.push({ id: id, cleared: false, fn: fn, ms: ms });
    return id;
};
global.clearInterval = function (id) {
    intervals.forEach(function (row) { if (row.id === id) row.cleared = true; });
};

const okPayload = {
    ok: true,
    queue: { worker: { code: 'alive' }, scheduler: { code: 'alive' }, jobs: 0, failed_jobs: 0, overdue_payouts: 0 },
    till: { overdue_payouts: 0, failed_intents: 0, fiscal_errors: 0 },
    errors: { count: 0, last_class: null, top_class: null },
    gateways: {},
    auth: { failed_logins: 0, failed_2fa: 0 },
    welcome: { missing_count: 0, last_user_id: null },
    contracts: {
        fill_expired_count: 3,
        fill_expired: [{ name: 'leftover-student', school: 'Исток', at: 1 }],
        sms_expired_count: 2,
        sms_expired: [{ name: 'leftover-sms', school: 'Исток', at: 1 }]
    }
};
let fetchMode = 'ok';
global.fetch = function () {
    if (fetchMode === 'ok') {
        return Promise.resolve({
            ok: true,
            json: function () { return Promise.resolve(okPayload); }
        });
    }
    return Promise.resolve({
        ok: false,
        status: 403,
        json: function () { return Promise.resolve({ message: 'Forbidden' }); }
    });
};

(async function () {
    eval(src);
    await new Promise(function (resolve) { setTimeout(resolve, 20); });
    const fillAfterOk = nodes['contracts-fill-expired'].textContent;
    const fillTitleAfterOk = fillWrap._attrs.title;
    const tick = intervals.find(function (row) { return !row.cleared; });
    if (!tick || typeof tick.fn !== 'function') {
        throw new Error('ops poller not started');
    }
    fetchMode = 'forbidden';
    tick.fn();
    await new Promise(function (resolve) { setTimeout(resolve, 20); });
    process.stdout.write(JSON.stringify({
        fill_after_ok: fillAfterOk,
        fill_title_after_ok: fillTitleAfterOk,
        fill_after_forbidden: nodes['contracts-fill-expired'].textContent,
        sms_after_forbidden: nodes['contracts-sms-expired'].textContent,
        fill_title_after_forbidden: fillWrap._attrs.title,
        sms_title_after_forbidden: smsWrap._attrs.title,
        inner_html: innerHtml,
        interval_ms: tick.ms
    }));
})().catch(function (err) {
    process.stderr.write(String(err && err.stack ? err.stack : err));
    process.exit(1);
});
JS;

        return $this->runNodeScript($script, [$bladePath]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeContract(User $student, Partner $school, array $overrides = []): Contract
    {
        $suffix = (string) random_int(100000, 999999);

        return Contract::create(array_merge([
            'school_id' => $school->id,
            'user_id' => $student->id,
            'group_id' => null,
            'creation_mode' => Contract::CREATION_MODE_PDF,
            'source_pdf_path' => 'documents/2026/09/ops-ux-'.$suffix.'.pdf',
            'source_sha256' => hash('sha256', 'ops-ux-contract-'.$suffix),
            'provider' => 'podpislon',
            'status' => Contract::STATUS_DRAFT,
        ], $overrides));
    }

    /**
     * @param  list<string>  $args
     * @return array<string, mixed>
     */
    private function runNodeScript(string $script, array $args): array
    {
        $path = sys_get_temp_dir().'/ops-contracts-ux-'.uniqid('', true).'.cjs';
        file_put_contents($path, $script);

        try {
            $cmd = 'node '.escapeshellarg($path);
            foreach ($args as $arg) {
                $cmd .= ' '.escapeshellarg($arg);
            }
            $output = [];
            $exitCode = 0;
            exec($cmd.' 2>&1', $output, $exitCode);
            $raw = implode("\n", $output);
            $this->assertSame(0, $exitCode, $raw);
            $decoded = json_decode($raw, true);
            $this->assertIsArray($decoded, $raw);

            return $decoded;
        } finally {
            @unlink($path);
        }
    }
}
