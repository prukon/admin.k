<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

/**
 * UX: пикер справа в поле, крупные emoji-only, чипы реакций как в Telegram.
 */
final class ChatReactionUxFeatureTest extends ChatTestCase
{
    public function test_composer_button_sits_inside_input_field_on_the_right(): void
    {
        $html = $this->get(route('chat.index'))->assertOk()->getContent();
        $fieldStart = strpos($html, 'chat-composer-field');
        $this->assertNotFalse($fieldStart);
        $field = substr($html, $fieldStart, 900);
        $inputPos = strpos($field, 'id="msgInput"');
        $btnPos = strpos($field, 'id="emojiBtn"');
        $pickerPos = strpos($field, 'id="emojiPicker"');
        $this->assertNotFalse($inputPos);
        $this->assertNotFalse($btnPos);
        $this->assertNotFalse($pickerPos);
        $this->assertGreaterThan($inputPos, $btnPos, 'Кнопка смайла справа в поле, после input');

        $css = (string) file_get_contents(resource_path('css/chat.css'));
        $this->assertStringContainsString('.chat-composer-field #msgInput { padding-right: 2.6rem; }', $css);
        $this->assertStringContainsString('right: 6px; top: 50%', $css);
    }

    public function test_client_helpers_match_telegram_reaction_and_big_emoji_rules(): void
    {
        $ui = $this->simulateReactionUi();

        $this->assertTrue($ui['big']['one']);
        $this->assertTrue($ui['big']['three']);
        $this->assertFalse($ui['big']['four']);
        $this->assertFalse($ui['big']['text']);
        $this->assertStringContainsString('is-big-emoji-1', $ui['big']['class_one']);
        $this->assertSame('', $ui['big']['class_text']);

        $this->assertStringContainsString('👍 ок', $ui['insert']);
        $this->assertStringContainsString('hidden', $ui['empty_reactions']);
        $this->assertStringContainsString('msg-reaction-avatars', $ui['chip_three']);
        $this->assertStringContainsString('default-avatar', $ui['chip_three']);
        $this->assertStringNotContainsString('msg-reaction-count', $ui['chip_three']);
        $this->assertStringContainsString('msg-reaction-count', $ui['chip_four']);
        $this->assertStringContainsString('>4<', $ui['chip_four']);
        $this->assertStringNotContainsString('msg-reaction-avatars', $ui['chip_four']);
        $this->assertStringContainsString('is-mine', $ui['chip_mine']);
        $this->assertStringContainsString('&lt;img', $ui['chip_xss']);
        $this->assertStringNotContainsString('<img onerror', $ui['chip_xss']);
    }

    public function test_javascript_wires_picker_reaction_socket_and_composer_button(): void
    {
        $js = (string) file_get_contents(resource_path('js/chat.js'));
        $this->assertStringContainsString("getElementById('emojiBtn')", $js);
        $this->assertStringContainsString('parseEmojiJson(', $js);
        $this->assertStringContainsString('insertComposerEmoji(', $js);
        $this->assertStringContainsString("listen('.message.reaction'", $js);
        $this->assertStringContainsString("stopListening('.message.reaction')", $js);
        $this->assertStringContainsString("'/messages/' + messageId + '/reaction'", $js);
        $this->assertStringContainsString("fieldError(res.data, 'emoji')", $js);
        $this->assertStringContainsString('msgReactionError', $js);
        $this->assertStringContainsString('is-big-emoji', $js);
        $this->assertStringContainsString('msg-react-btn', $js);
        $this->assertStringContainsString('if (count >= 4)', $js);
        $this->assertStringContainsString('emojiBtn.disabled = !on', $js);
    }

    /**
     * @return array<string, mixed>
     */
    private function simulateReactionUi(): array
    {
        $chatJs = resource_path('js/chat.js');
        $this->assertFileExists($chatJs);

        $script = <<<'JS'
const fs = require('fs');
const src = fs.readFileSync(process.argv[2], 'utf8');

function extractFn(name) {
    const needle = 'function ' + name + '(';
    const start = src.indexOf(needle);
    if (start < 0) {
        throw new Error('missing ' + name);
    }
    const brace = src.indexOf('{', start);
    let depth = 0;
    for (let j = brace; j < src.length; j++) {
        const ch = src[j];
        if (ch === '{') depth++;
        else if (ch === '}') {
            depth--;
            if (depth === 0) {
                return src.slice(start, j + 1);
            }
        }
    }
    throw new Error('unclosed ' + name);
}

function escapeHtml(value) {
    return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

const me = 7;
eval(extractFn('emojiSeqRe'));
eval(extractFn('emojiOnlyCount'));
eval(extractFn('isBigEmojiMessage'));
eval(extractFn('bigEmojiClass'));
eval(extractFn('insertComposerEmoji'));
eval(extractFn('viewerHasReaction'));
eval(extractFn('reactionChipHtml'));
eval(extractFn('reactionsHtml'));

const input = { value: '👍', selectionStart: 2, selectionEnd: 2, setSelectionRange() {}, focus() {}, dispatchEvent() {} };
insertComposerEmoji(input, ' ок');

const three = reactionChipHtml({
    emoji: '👍',
    count: 3,
    mine: false,
    user_ids: [1, 2, 3],
    users: [
        { id: 1, name: 'А', avatar: '/img/default-avatar.png' },
        { id: 2, name: 'Б', avatar: '/img/default-avatar.png' },
        { id: 3, name: 'В', avatar: '/img/default-avatar.png' }
    ]
});
const four = reactionChipHtml({
    emoji: '👍',
    count: 4,
    mine: false,
    user_ids: [1, 2, 3, 4],
    users: []
});
const mineChip = reactionChipHtml({
    emoji: '❤️',
    count: 1,
    mine: true,
    user_ids: [7],
    users: [{ id: 7, name: 'Я', avatar: '/img/default-avatar.png' }]
});
const xss = reactionChipHtml({
    emoji: '🔥',
    count: 1,
    mine: false,
    user_ids: [2],
    users: [{ id: 2, name: '<img onerror=alert(1)>', avatar: 'javascript:alert(1)' }]
});

process.stdout.write(JSON.stringify({
    big: {
        one: isBigEmojiMessage('👍'),
        three: isBigEmojiMessage('👍❤️🔥'),
        four: isBigEmojiMessage('👍❤️🔥🎉'),
        text: isBigEmojiMessage('привет'),
        class_one: bigEmojiClass('👍'),
        class_text: bigEmojiClass('привет')
    },
    insert: input.value,
    empty_reactions: reactionsHtml([]),
    chip_three: three,
    chip_four: four,
    chip_mine: mineChip,
    chip_xss: xss
}));
JS;

        $tmp = sys_get_temp_dir().'/chat-reaction-ux-'.uniqid('', true).'.cjs';
        file_put_contents($tmp, $script);
        $json = shell_exec('node '.escapeshellarg($tmp).' '.escapeshellarg($chatJs));
        @unlink($tmp);
        $this->assertNotFalse($json);
        $decoded = json_decode((string) $json, true);
        $this->assertIsArray($decoded);

        return $decoded;
    }
}
