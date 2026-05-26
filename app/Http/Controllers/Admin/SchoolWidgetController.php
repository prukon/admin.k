<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Services\PartnerContext;
use App\Services\PartnerTelegramLinkService;
use App\Services\PartnerWidgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class SchoolWidgetController extends Controller
{
    public function __construct(
        private readonly PartnerContext $partnerContext,
        private readonly PartnerWidgetService $widgetService,
        private readonly PartnerTelegramLinkService $telegramLinkService,
    ) {
    }

    public function index(): View
    {
        return $this->renderTabsWidget();
    }

    public function widgetTab(): View
    {
        return $this->renderTabsWidget();
    }

    public function createTelegramLink(): JsonResponse
    {
        $partner = $this->resolveCurrentPartner();

        if (!config('services.telegram.bot_token')) {
            return response()->json([
                'message' => 'Telegram-бот платформы не настроен.',
            ], 503);
        }

        $link = $this->telegramLinkService->createLinkForPartner(
            (int) $partner->id,
            auth()->id()
        );

        if (empty($link['url'])) {
            return response()->json([
                'message' => 'Не настроен TELEGRAM_BOT_USERNAME.',
            ], 503);
        }

        return response()->json([
            'message'    => 'Откройте ссылку в Telegram и нажмите «Старт».',
            'url'        => $link['url'],
            'expires_at' => $link['expires_at'],
        ]);
    }

    public function disconnectTelegram(): JsonResponse
    {
        $partner = $this->resolveCurrentPartner();
        $this->telegramLinkService->disconnect($partner);

        return response()->json([
            'message' => 'Telegram отключён.',
        ]);
    }

    private function resolveCurrentPartner(): Partner
    {
        $partnerId = $this->partnerContext->partnerId();

        if (!$partnerId) {
            abort(403, 'Партнёр не выбран.');
        }

        return Partner::query()->findOrFail($partnerId);
    }

    private function renderTabsWidget(): View
    {
        $partner = $this->resolveCurrentPartner();

        $widget = $this->widgetService->ensureForPartner((int) $partner->id);

        $widgetUrl = route('widget.school-lead.show', ['widgetKey' => $widget->widget_key]);
        $iframeCode = sprintf(
            '<iframe src="%s" width="100%%" height="420" style="border:0;" loading="lazy" title="Заявка"></iframe>',
            e($widgetUrl)
        );

        $botUsername = ltrim((string) config('services.telegram.bot_username'), '@');
        $botName = (string) config('services.telegram.bot_name');
        $telegramBotUrl = $botUsername !== '' ? 'https://t.me/' . $botUsername : null;

        return view('admin.school-leads.index', [
            'activeTab'                      => 'widget',
            'widget'                         => $widget,
            'widgetUrl'                      => $widgetUrl,
            'iframeCode'                     => $iframeCode,
            'partner'                        => $partner,
            'telegramBotUsername'            => $botUsername,
            'telegramBotName'                => $botName,
            'telegramBotUrl'                 => $telegramBotUrl,
            'telegramConfigured'             => (bool) config('services.telegram.bot_token'),
        ]);
    }
}
