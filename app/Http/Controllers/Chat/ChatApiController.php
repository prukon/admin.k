<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Chat\ChatMessagesIndexRequest;
use App\Http\Requests\Chat\ChatParticipantsIndexRequest;
use App\Http\Requests\Chat\ChatUsersIndexRequest;
use App\Http\Requests\Chat\ChatUserShowRequest;
use App\Http\Requests\Chat\DestroyChatMessageReactionRequest;
use App\Http\Requests\Chat\DestroyChatThreadRequest;
use App\Http\Requests\Chat\PresencePingRequest;
use App\Http\Requests\Chat\ReverbStatusRequest;
use App\Http\Requests\Chat\SaveChatDraftRequest;
use App\Http\Requests\Chat\StoreChatGroupParticipantsRequest;
use App\Http\Requests\Chat\StoreChatGroupThreadRequest;
use App\Http\Requests\Chat\StoreChatMessageReactionRequest;
use App\Http\Requests\Chat\StoreChatMessageRequest;
use App\Http\Requests\Chat\StoreChatThreadRequest;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
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
            $this->chat->threadPayload($thread, $userId),
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

    public function upsertMessageReaction(
        StoreChatMessageReactionRequest $request,
        ChatThread $thread,
        ChatMessage $message
    ): JsonResponse|RedirectResponse {
        $this->assertMessageInThread($thread, $message);

        $payload = $this->chat->setMessageReaction($thread, $message, $this->currentUser(), $request->emoji());

        if ($this->wantsJsonPayload($request)) {
            return response()->json($payload);
        }

        return redirect()->route('chat.index')->with('status', 'Реакция сохранена.');
    }

    public function destroyMessageReaction(
        DestroyChatMessageReactionRequest $request,
        ChatThread $thread,
        ChatMessage $message
    ): JsonResponse|RedirectResponse {
        $this->assertMessageInThread($thread, $message);

        $payload = $this->chat->removeMessageReaction($thread, $message, $this->currentUser());

        if ($this->wantsJsonPayload($request)) {
            return response()->json($payload);
        }

        return redirect()->route('chat.index')->with('status', 'Реакция снята.');
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

    public function storeGroupThread(StoreChatGroupThreadRequest $request): JsonResponse|RedirectResponse
    {
        $thread = $this->chat->createGroupThread(
            $this->currentUser(),
            $request->groupTitle(),
            $request->memberIds()
        );

        if ($this->wantsJsonPayload($request)) {
            return response()->json([
                'ok' => true,
                'created' => true,
                'thread_id' => $thread['id'],
                'thread' => $thread,
            ], 201);
        }

        return redirect()->route('chat.index');
    }

    public function users(ChatUsersIndexRequest $request): JsonResponse
    {
        $excludeIds = [];
        $excludeThreadId = $request->excludeThreadId();
        if ($excludeThreadId !== null) {
            $excludeThread = ChatThread::query()->find($excludeThreadId);
            if ($excludeThread) {
                $excludeIds = $this->chat->groupMemberUserIds($excludeThread);
            }
        }

        $users = $this->chat->usersForPicker(
            (int) $this->currentUser()->id,
            $this->requirePartnerId(),
            $request->searchQuery(),
            $request->teamFilter(),
            $excludeIds
        );

        return response()->json($users);
    }

    public function showUser(ChatUserShowRequest $request, User $user): JsonResponse
    {
        return response()->json(
            $this->chat->userCard($user, $this->requirePartnerId())
        );
    }

    public function participants(ChatParticipantsIndexRequest $request, ChatThread $thread): JsonResponse
    {
        $this->assertParticipant($thread);
        $this->assertGroupThread($thread);

        return response()->json(
            $this->chat->groupMembersPage(
                $thread,
                $this->currentUser(),
                $request->afterUserId(),
                ChatService::MEMBERS_PAGE_SIZE,
                $this->requirePartnerId()
            )
        );
    }

    public function storeParticipants(StoreChatGroupParticipantsRequest $request, ChatThread $thread): JsonResponse|RedirectResponse
    {
        $this->assertParticipant($thread);
        $this->assertGroupThread($thread);
        $this->assertCanManageGroupMembers();

        $payload = $this->chat->addGroupParticipants($thread, $this->currentUser(), $request->memberIds());

        if ($this->wantsJsonPayload($request)) {
            return response()->json($payload);
        }

        return redirect()->route('chat.index')->with('status', (string) ($payload['message'] ?? 'Участники добавлены.'));
    }

    public function destroyParticipant(Request $request, ChatThread $thread, User $user): JsonResponse|RedirectResponse
    {
        $this->assertParticipant($thread);
        $this->assertGroupThread($thread);

        $actor = $this->currentUser();
        $isSelf = (int) $actor->id === (int) $user->id;
        if (! $isSelf) {
            $this->assertCanManageGroupMembers();
        }

        if (! $thread->hasParticipant((int) $user->id)) {
            if ($this->wantsJsonPayload($request)) {
                return response()->json([
                    'message' => 'Этот пользователь не состоит в группе.',
                    'errors' => ['user' => ['Этот пользователь не состоит в группе.']],
                ], 422);
            }

            return redirect()->route('chat.index')->withErrors(['user' => 'Этот пользователь не состоит в группе.']);
        }

        $payload = $this->chat->removeGroupParticipant($thread, $actor, $user);

        if ($this->wantsJsonPayload($request)) {
            return response()->json($payload);
        }

        return redirect()->route('chat.index')->with('status', (string) ($payload['message'] ?? 'Готово.'));
    }

    public function destroyThread(DestroyChatThreadRequest $request, ChatThread $thread): JsonResponse|RedirectResponse
    {
        $payload = $this->chat->deleteThread($thread, $this->currentUser());

        if ($this->wantsJsonPayload($request)) {
            return response()->json($payload);
        }

        return redirect()->route('chat.index')->with('status', (string) ($payload['message'] ?? 'Чат удалён.'));
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

    public function saveDraft(SaveChatDraftRequest $request, ChatThread $thread): JsonResponse|RedirectResponse
    {
        $this->assertParticipant($thread);
        $draft = $this->chat->saveDraft($thread, (int) $this->currentUser()->id, $request->draftBody());

        if ($this->wantsJsonPayload($request)) {
            return response()->json([
                'ok' => true,
                'draft_body' => $draft,
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

    private function assertMessageInThread(ChatThread $thread, ChatMessage $message): void
    {
        abort_unless(
            (int) $message->thread_id === (int) $thread->id,
            404
        );
    }

    private function assertGroupThread(ChatThread $thread): void
    {
        abort_unless(
            (bool) $thread->is_group,
            403,
            'Это не групповой чат.'
        );
    }

    private function assertCanManageGroupMembers(): void
    {
        abort_unless(
            $this->chat->userCanManageGroupMembers($this->currentUser()),
            403,
            'Добавлять и удалять участников может только администратор.'
        );
    }
}
