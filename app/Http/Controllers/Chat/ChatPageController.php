<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Services\Chat\ChatService;
use App\Services\PartnerContext;
use Illuminate\View\View;

class ChatPageController extends Controller
{
    public function __construct(
        private readonly PartnerContext $partnerContext,
        private readonly ChatService $chat,
    ) {
    }

    public function index(): View
    {
        $partnerId = (int) ($this->partnerContext->partnerId() ?? 0);
        $contactTeams = $partnerId > 0
            ? Team::query()
                ->where('partner_id', $partnerId)
                ->orderBy('order_by')
                ->orderBy('title')
                ->get(['id', 'title'])
            : collect();

        $userId = (int) auth()->id();

        return view('chat.index', [
            'contactTeams' => $contactTeams,
            'chatUnreadCount' => $this->chat->unreadTotal($userId),
            'chatPrivateUnreadCount' => $this->chat->unreadPrivateTotal($userId),
            'chatGroupUnreadCount' => $this->chat->unreadGroupTotal($userId),
        ]);
    }
}
