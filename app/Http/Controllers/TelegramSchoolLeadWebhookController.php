<?php

namespace App\Http\Controllers;

use App\Services\PartnerTelegramLinkService;
use App\Services\TelegramBotClient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TelegramSchoolLeadWebhookController extends Controller
{
    public function __construct(
        private readonly PartnerTelegramLinkService $linkService,
        private readonly TelegramBotClient $telegram,
    ) {
    }

    public function handle(Request $request): Response
    {
        $expectedSecret = (string) config('services.telegram.webhook_secret');
        if ($expectedSecret !== '') {
            $header = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');
            if (!hash_equals($expectedSecret, $header)) {
                abort(403);
            }
        }

        $message = $request->input('message');
        if (!is_array($message)) {
            return response('ok', 200);
        }

        $text = trim((string) ($message['text'] ?? ''));
        $chat = $message['chat'] ?? null;
        if ($text === '' || !is_array($chat) || !isset($chat['id'])) {
            return response('ok', 200);
        }

        $chatId = (string) $chat['id'];

        if (!preg_match('/^\/start(?:@[\w]+)?(?:\s+(.+))?$/iu', $text, $matches)) {
            return response('ok', 200);
        }

        $payload = isset($matches[1]) ? trim((string) $matches[1]) : '';
        if ($payload === '') {
            $this->telegram->sendMessage(
                $chatId,
                "Чтобы подключить уведомления о заявках с сайта, откройте ссылку «Подключить Telegram» в CRM (раздел «Виджет заявок»)."
            );

            return response('ok', 200);
        }

        $partner = $this->linkService->activateFromStartPayload($payload, $chatId);

        if ($partner) {
            $title = (string) ($partner->title ?: 'ваша организация');
            $this->telegram->sendMessage(
                $chatId,
                "Готово! Уведомления о новых заявках с сайта включены для «{$title}»."
            );
        } else {
            $this->telegram->sendMessage(
                $chatId,
                "Ссылка устарела или уже использована. Создайте новую в CRM: «Виджет заявок» → «Подключить Telegram»."
            );
        }

        return response('ok', 200);
    }
}
