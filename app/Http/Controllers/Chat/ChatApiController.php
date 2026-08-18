<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Chat\ChatMessagesIndexRequest;
use App\Http\Requests\Chat\ChatUsersIndexRequest;
use App\Http\Requests\Chat\PresencePingRequest;
use App\Http\Requests\Chat\ReverbStatusRequest;
use App\Http\Requests\Chat\StoreChatMessageRequest;
use App\Http\Requests\Chat\StoreChatThreadRequest;
use App\Models\ChatThread;
use App\Services\Chat\ChatService;
use App\Services\Chat\ReverbHealth;
use App\Services\Chat\UserPresence;
use App\Services\PartnerContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ChatApiController extends AdminBaseController
{
    public function __construct(
        PartnerContext $partnerContext,
        private readonly ChatService $chat,
        private readonly ReverbHealth $reverbHealth,
        private readonly UserPresence $presence,
    ) {
        parent::__construct($partnerContext);
    }

    public function threads(): JsonResponse
    {
        $userId = (int) $this->currentUser()->id;

        return response()->json([
            'threads' => $this->chat->threadsForUser($userId),
            'unread_total' => $this->chat->unreadTotal($userId),
        ]);
    }

    public function unread(): JsonResponse
    {
        $userId = (int) $this->currentUser()->id;

        return response()->json([
            'unread_total' => $this->chat->unreadTotal($userId),
        ]);
    }

    public function presencePing(PresencePingRequest $request): JsonResponse
    {
        $this->presence->touch($this->currentUser());

        return response()->json([
            'ok' => true,
        ]);
    }

    public function reverbStatus(ReverbStatusRequest $request): JsonResponse
    {
        return response()->json($this->reverbHealth->snapshot());
    }

    public function thread(ChatThread $thread): JsonResponse
    {
        $this->assertParticipant($thread);
        $userId = (int) $this->currentUser()->id;
        $unreadTotal = $this->chat->markRead($thread, $userId);

        return response()->json(array_merge(
            $this->chat->threadPayload($thread->fresh(['participants.user:id,name,image_crop,last_seen_at']), $userId),
            ['unread_total' => $unreadTotal],
        ));
    }

    public function messages(ChatMessagesIndexRequest $request, ChatThread $thread): JsonResponse
    {
        $this->assertParticipant($thread);

        return response()->json(
            $this->chat->messages(
                $thread,
                (int) $this->currentUser()->id,
                $request->afterId(),
                $request->beforeId()
            )
        );
    }

    public function storeMessage(StoreChatMessageRequest $request, ChatThread $thread): JsonResponse|RedirectResponse
    {
        $this->assertParticipant($thread);

        $payload = $this->chat->storeMessage($thread, $this->currentUser(), $request->messageBody());

        if ($this->wantsJsonPayload($request)) {
            return response()->json($payload, 201);
        }

        return redirect()->route('chat.index')->with('status', 'Сообщение отправлено.');
    }

    public function storeThread(StoreChatThreadRequest $request): JsonResponse|RedirectResponse
    {
        $result = $this->chat->findOrCreatePrivateThread($this->currentUser(), $request->peerUserId());

        if ($this->wantsJsonPayload($request)) {
            return response()->json([
                'ok' => true,
                'created' => $result['created'],
                'thread_id' => $result['thread']['id'],
                'thread' => $result['thread'],
            ], $result['created'] ? 201 : 200);
        }

        return redirect()->route('chat.index');
    }

    public function users(ChatUsersIndexRequest $request): JsonResponse
    {
        $users = $this->chat->usersForPicker(
            (int) $this->currentUser()->id,
            $this->requirePartnerId(),
            $request->searchQuery()
        );

        return response()->json($users);
    }

    public function markRead(Request $request, ChatThread $thread): JsonResponse|RedirectResponse
    {
        $this->assertParticipant($thread);
        $unreadTotal = $this->chat->markRead($thread, (int) $this->currentUser()->id);

        if ($this->wantsJsonPayload($request)) {
            return response()->json([
                'ok' => true,
                'unread_total' => $unreadTotal,
            ]);
        }

        return redirect()->route('chat.index');
    }

    private function wantsJsonPayload(Request $request): bool
    {
        return $request->ajax() || $request->expectsJson();
    }

    private function assertParticipant(ChatThread $thread): void
    {
        abort_unless(
            $thread->hasParticipant((int) $this->currentUser()->id),
            403,
            'Нет доступа к этому диалогу.'
        );
    }
}
