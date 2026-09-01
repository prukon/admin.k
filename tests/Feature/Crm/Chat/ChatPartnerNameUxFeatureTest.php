<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

/**
 * UX модалок «Контакт» и «Группа»: название партнёра под именем, не во вкладке «Аккаунт».
 * Серверный JSON 200 недостаточен — прогоняем реальный chat.js.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatPartnerNameUxFeatureTest extends ChatTestCase
{
    public function test_contact_modal_shows_partner_under_name_and_hides_empty(): void
    {
        $ui = $this->simulatePartnerNameUi();

        $full = (string) $ui['card_full']['html'];
        $this->assertStringContainsString('peer-card-name', $full);
        $this->assertStringContainsString('Иванов Иван', $full);
        $this->assertStringContainsString('peer-card-partner', $full);
        $this->assertStringContainsString('Школа Альфа', $full);
        $namePos = strpos($full, 'peer-card-name');
        $partnerPos = strpos($full, 'peer-card-partner');
        $phonePos = strpos($full, 'Телефон');
        $this->assertNotFalse($namePos);
        $this->assertNotFalse($partnerPos);
        $this->assertNotFalse($phonePos);
        $this->assertLessThan($partnerPos, $namePos, 'Партнёр сразу под ФИО');
        $this->assertLessThan($phonePos, $partnerPos, 'Партнёр выше блока «Телефон»');

        $empty = (string) $ui['card_empty']['html'];
        $this->assertStringNotContainsString('peer-card-partner', $empty);
        $this->assertStringNotContainsString('Школа Альфа', $empty);
        $this->assertSame(5, substr_count($empty, 'peer-card-row'));
    }

    public function test_contact_partner_name_is_escaped_and_account_tab_hides_it(): void
    {
        $ui = $this->simulatePartnerNameUi();

        $xss = (string) $ui['card_xss']['html'];
        $this->assertStringContainsString('peer-card-partner', $xss);
        $this->assertStringContainsString('&lt;img&gt;', $xss);
        $this->assertStringNotContainsString('<img>', $xss);

        $account = (string) $ui['account']['html'];
        $this->assertStringContainsString('peer-card-name', $account);
        $this->assertStringContainsString('Свой Аккаунт', $account);
        $this->assertStringNotContainsString('peer-card-partner', $account);
        $this->assertStringNotContainsString('Школа Аккаунта', $account);
    }

    public function test_group_modal_shows_partner_under_title_and_hides_empty(): void
    {
        $ui = $this->simulatePartnerNameUi();

        $this->assertSame('Сборная', (string) $ui['group_full']['title']);
        $this->assertSame('Школа Альфа', (string) $ui['group_full']['partner']);
        $this->assertSame('', (string) $ui['group_full']['display']);
        $this->assertSame('3 участника', (string) $ui['group_full']['count']);

        $this->assertSame('', (string) $ui['group_empty']['partner']);
        $this->assertSame('none', (string) $ui['group_empty']['display']);

        $this->assertSame('&lt;img&gt;', (string) $ui['group_xss']['partner_html']);
        $this->assertStringNotContainsString('<img>', (string) $ui['group_xss']['partner_html']);
        $this->assertSame('', (string) $ui['group_xss']['display']);
    }

    /**
     * @return array<string, mixed>
     */
    private function simulatePartnerNameUi(): array
    {
        $chatJs = resource_path('js/chat.js');
        $this->assertFileExists($chatJs);

        $script = <<<'JS'
const fs = require('fs');
const chatJs = fs.readFileSync(process.argv[2], 'utf8');

function extractFn(src, name) {
    const needle = 'function ' + name + '(';
    const start = src.indexOf(needle);
    if (start < 0) throw new Error('missing ' + name);
    const brace = src.indexOf('{', start);
    let depth = 0;
    for (let j = brace; j < src.length; j++) {
        if (src[j] === '{') depth++;
        else if (src[j] === '}') {
            depth--;
            if (depth === 0) return src.slice(start, j + 1);
        }
    }
    throw new Error('unclosed ' + name);
}

function makeEl(tag) {
    const el = {
        tagName: String(tag || 'div').toUpperCase(),
        style: {},
        _text: '',
        _html: '',
        get textContent() { return this._text; },
        set textContent(v) {
            this._text = v == null ? '' : String(v);
            this._html = this._text
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        },
        get innerHTML() { return this._html || this._text; },
        set innerHTML(v) { this._html = v == null ? '' : String(v); }
    };
    return el;
}

const els = {
    peerCardBody: makeEl('div'),
    accountCardBody: makeEl('div'),
    groupCardPartner: makeEl('div'),
    groupCardTitle: makeEl('div'),
    groupCardCount: makeEl('div')
};
els.groupCardPartner.style.display = 'none';

global.document = {
    getElementById(id) { return els[id] || null; },
    createElement(tag) { return makeEl(tag); }
};

eval(extractFn(chatJs, 'escapeHtml'));
eval(extractFn(chatJs, 'dashText'));
eval(extractFn(chatJs, 'telHref'));
eval(extractFn(chatJs, 'phoneHtml'));
eval(extractFn(chatJs, 'renderPeerCard'));
eval(extractFn(chatJs, 'setGroupCardPartner'));
eval(extractFn(chatJs, 'membersCountLabel'));

renderPeerCard({
    full_name: 'Иванов Иван',
    phone: '+79005556677',
    parent_full_name: 'Сидоров',
    parent_phone: '',
    last_seen_label: 'онлайн',
    team_title: 'Группа А',
    partner_name: 'Школа Альфа'
});
const card_full = { html: els.peerCardBody.innerHTML };

renderPeerCard({
    full_name: 'Петров Пётр',
    phone: '',
    parent_full_name: '',
    parent_phone: '',
    last_seen_label: '',
    team_title: '',
    partner_name: '   '
});
const card_empty = { html: els.peerCardBody.innerHTML };

renderPeerCard({
    full_name: 'Иванов Иван',
    partner_name: '<img>'
});
const card_xss = { html: els.peerCardBody.innerHTML };

renderPeerCard({
    full_name: 'Свой Аккаунт',
    partner_name: 'Школа Аккаунта'
}, 'accountCardBody');
const account = { html: els.accountCardBody.innerHTML };

setGroupCardPartner('Школа Альфа');
els.groupCardTitle.textContent = 'Сборная';
els.groupCardCount.textContent = membersCountLabel(3);
const group_full = {
    title: els.groupCardTitle.textContent,
    partner: els.groupCardPartner.textContent,
    display: els.groupCardPartner.style.display || '',
    count: els.groupCardCount.textContent
};

setGroupCardPartner('');
const group_empty = {
    partner: els.groupCardPartner.textContent,
    display: els.groupCardPartner.style.display || ''
};

setGroupCardPartner('<img>');
const group_xss = {
    partner_html: els.groupCardPartner.innerHTML,
    display: els.groupCardPartner.style.display || ''
};

process.stdout.write(JSON.stringify({
    card_full, card_empty, card_xss, account, group_full, group_empty, group_xss
}));
JS;

        $path = sys_get_temp_dir().'/chat-partner-name-ux-'.uniqid('', true).'.cjs';
        file_put_contents($path, $script);

        try {
            $output = [];
            $exitCode = 0;
            exec(
                'node '.escapeshellarg($path).' '.escapeshellarg($chatJs).' 2>&1',
                $output,
                $exitCode
            );
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
