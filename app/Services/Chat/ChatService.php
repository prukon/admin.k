<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Events\InboxBump;
use App\Events\MessageCreated;
use App\Events\ThreadReadUpdated;
use App\Models\ChatMessage;
use App\Models\ChatParticipant;
use App\Models\ChatThread;
use App\Models\User;
use App\Support\UserTeamQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ChatService
{
    public const DEFAULT_AVATAR = '/img/default-avatar.png';

    public const PEER_USER_COLUMNS = 'id,name,lastname,image_crop,last_seen_at';

    public function unreadTotal(int $userId): int
    {
        return (int) ChatMessage::query()
            ->whereIn('thread_id', $this->threadIdsForUser($userId))
            ->where('user_id', '<>', $userId)
            ->whereRaw(
                'messages.created_at > COALESCE((
                    SELECT last_read FROM participants
                    WHERE participants.thread_id = messages.thread_id
                      AND participants.user_id = ?
                      AND participants.deleted_at IS NULL
                    LIMIT 1
                ), ?)',
                [$userId, '1970-01-01 00:00:00']
            )
            ->count();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function threadsForUser(int $userId): array
    {
        $threads = ChatThread::query()
            ->whereHas('participants', fn ($q) => $q->where('user_id', $userId))
            ->with(['participants.user:'.self::PEER_USER_COLUMNS])
            ->with('lastMessage')
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get();

        $unreadByThread = $this->unreadCountsByThread($userId, $threads->pluck('id')->all());

        $seenPeers = [];
        $unique = [];
        foreach ($threads as $thread) {
            $row = $this->serializeThread(
                $thread,
                $userId,
                (int) ($unreadByThread[$thread->id] ?? 0)
            );
            $peerId = $row['peer_id'] ?? null;
            if ($peerId !== null) {
                if (isset($seenPeers[$peerId])) {
                    continue;
                }
                $seenPeers[$peerId] = true;
            }
            $unique[] = $row;
        }

        return $unique;
    }

    /**
     * @return array{thread: array<string, mixed>, messages: list<array<string, mixed>>}
     */
    public function threadPayload(ChatThread $thread, int $viewerId, int $limit = 40): array
    {
        $messages = $thread->messages()
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'user_id', 'body', 'created_at'])
            ->reverse()
            ->values();

        return [
            'thread' => $this->serializeThreadHeader($thread, $viewerId),
            'messages' => $messages
                ->map(fn (ChatMessage $message) => $this->serializeMessage($thread, $message, $viewerId))
                ->all(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function messages(ChatThread $thread, int $viewerId, ?int $afterId, ?int $beforeId): array
    {
        $query = $thread->messages()->orderByDesc('id');

        if ($afterId !== null) {
            $query->where('id', '>', $afterId);

            return $query
                ->limit(100)
                ->get(['id', 'user_id', 'body', 'created_at'])
                ->sortBy('id')
                ->values()
                ->map(fn (ChatMessage $message) => $this->serializeMessage($thread, $message, $viewerId))
                ->all();
        }

        if ($beforeId !== null) {
            $query->where('id', '<', $beforeId);
        }

        return $query
            ->limit(40)
            ->get(['id', 'user_id', 'body', 'created_at'])
            ->reverse()
            ->values()
            ->map(fn (ChatMessage $message) => $this->serializeMessage($thread, $message, $viewerId))
            ->all();
    }

    /**
     * @return array{thread: array<string, mixed>, created: bool}
     */
    public function findOrCreatePrivateThread(User $actor, int $peerId): array
    {
        return DB::transaction(function () use ($actor, $peerId) {
            $lockIds = [(int) $actor->id, $peerId];
            sort($lockIds);
            User::query()->whereIn('id', $lockIds)->orderBy('id')->lockForUpdate()->get();

            $existing = $this->findPrivateThread((int) $actor->id, $peerId);
            if ($existing) {
                return [
                    'thread' => $this->serializeThreadHeader($existing, (int) $actor->id),
                    'created' => false,
                ];
            }

            $thread = ChatThread::query()->create(['subject' => null]);

            ChatParticipant::query()->create([
                'thread_id' => $thread->id,
                'user_id' => (int) $actor->id,
                'last_read' => now(),
            ]);

            ChatParticipant::query()->create([
                'thread_id' => $thread->id,
                'user_id' => $peerId,
            ]);

            $thread = $thread->fresh(['participants.user:'.self::PEER_USER_COLUMNS]);

            return [
                'thread' => $this->serializeThreadHeader($thread, (int) $actor->id),
                'created' => true,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function storeMessage(ChatThread $thread, User $actor, string $body): array
    {
        $message = ChatMessage::query()->create([
            'thread_id' => $thread->id,
            'user_id' => (int) $actor->id,
            'body' => $body,
        ]);

        $participantPayload = ['last_read' => now()];
        if ($this->hasDraftColumn()) {
            $participantPayload['draft_body'] = null;
        }
        ChatParticipant::query()->updateOrCreate(
            ['thread_id' => $thread->id, 'user_id' => (int) $actor->id],
            $participantPayload
        );

        $thread->touch();
        $thread->load(['participants.user:'.self::PEER_USER_COLUMNS, 'lastMessage']);

        $payload = $this->serializeMessage($thread, $message, (int) $actor->id);
        $this->safeBroadcast(new MessageCreated((int) $thread->id, $payload));

        foreach ($thread->participants as $participant) {
            $uid = (int) $participant->user_id;
            $isSender = $uid === (int) $actor->id;
            $threadUnread = $isSender ? 0 : $this->unreadCountForThread($thread->id, $uid);

            $bump = [
                'thread_id' => (int) $thread->id,
                'title' => $this->titleForViewer($thread, $uid),
                'avatar' => $this->avatarForViewer($thread, $uid),
                'peer_id' => $this->peerId($thread, $uid),
                'peer_is_online' => $this->isPeerOnline($thread, $uid),
                'last_message' => $this->preview($body),
                'last_message_time' => $message->created_at?->toDateTimeString(),
                'last_message_is_mine' => $isSender,
                'last_message_is_read' => $isSender ? false : null,
                'unread_count' => $threadUnread,
                'unread_total' => $this->unreadTotal($uid),
            ];
            if ($isSender) {
                $bump['draft_body'] = '';
            }
            $this->safeBroadcast(new InboxBump($uid, $bump));
        }

        return $payload;
    }

    public function markRead(ChatThread $thread, int $userId): int
    {
        ChatParticipant::query()->updateOrCreate(
            ['thread_id' => $thread->id, 'user_id' => $userId],
            ['last_read' => now()]
        );

        $unreadTotal = $this->unreadTotal($userId);
        if (! $thread->relationLoaded('participants')) {
            $thread->load('participants:id,thread_id,user_id');
        }
        $inboxUserIds = $thread->participants
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $this->safeBroadcast(new ThreadReadUpdated((int) $thread->id, $userId, $unreadTotal, $inboxUserIds));

        return $unreadTotal;
    }

    public function saveDraft(ChatThread $thread, int $userId, string $body): string
    {
        $normalized = trim($body);
        if (! $this->hasDraftColumn()) {
            return $normalized;
        }

        ChatParticipant::query()->updateOrCreate(
            ['thread_id' => (int) $thread->id, 'user_id' => $userId],
            ['draft_body' => $normalized === '' ? null : $normalized]
        );

        return $normalized;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function usersForPicker(int $actorId, int $partnerId, string $q = ''): array
    {
        $query = User::query()
            ->from('users')
            ->where('users.partner_id', $partnerId)
            ->where('users.id', '<>', $actorId)
            ->where('users.is_enabled', 1);

        $hasRoleId = Schema::hasColumn('users', 'role_id');
        $hasRoles = Schema::hasTable('roles');
        if ($hasRoleId && $hasRoles) {
            $query->leftJoin('roles', 'roles.id', '=', 'users.role_id');
        }

        $selects = ['users.id', 'users.name', 'users.lastname', 'users.email', 'users.image_crop', 'users.parent_id'];
        if (Schema::hasColumn('users', 'last_seen_at')) {
            $selects[] = 'users.last_seen_at';
        }
        if ($hasRoleId && $hasRoles) {
            $selects[] = DB::raw('roles.name as role_name');
            $selects[] = DB::raw('roles.label as role_label');
        } else {
            $selects[] = DB::raw('NULL as role_name');
            $selects[] = DB::raw('NULL as role_label');
        }

        $selects[] = DB::raw(UserTeamQuery::sqlStudentTeamTitlesSubquery($partnerId).' as team_title');

        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($w) use ($like) {
                $w->where('users.name', 'like', $like)
                    ->orWhere('users.lastname', 'like', $like)
                    ->orWhere('users.email', 'like', $like)
                    ->orWhereHas('parentProfile', function ($parentQuery) use ($like) {
                        $parentQuery->where('lastname', 'like', $like)
                            ->orWhere('firstname', 'like', $like);
                    });
            });
        }

        return $query
            ->with(['parentProfile:id,lastname,firstname,middlename'])
            ->orderBy('users.lastname')
            ->orderBy('users.name')
            ->limit(100)
            ->get($selects)
            ->map(function ($user) {
                return [
                    'id' => (int) $user->id,
                    'name' => (string) ($user->full_name ?: $user->name),
                    'email' => $user->email,
                    'avatar' => $this->avatarUrl($user->image_crop ?? null),
                    'role_name' => $user->role_name,
                    'role_label' => $user->role_label,
                    'team_title' => $user->team_title,
                    'is_online' => $user->isOnline(),
                    'parent_full_name' => (string) ($user->parent_full_name ?: ''),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Карточка собеседника для модалки шапки диалога.
     *
     * @return array<string, mixed>
     */
    public function userCard(User $peer, int $partnerId): array
    {
        $peer->loadMissing('parentProfile');

        $teamTitle = (string) (User::query()
            ->from('users')
            ->where('users.id', (int) $peer->id)
            ->selectRaw(UserTeamQuery::sqlStudentTeamTitlesSubquery($partnerId).' as team_title')
            ->value('team_title') ?? '');

        $fullName = trim((string) $peer->full_name);
        if ($fullName === '') {
            $fullName = trim((string) ($peer->name ?? ''));
        }

        $isOnline = $peer->isOnline();
        $lastSeenLabel = '-';
        if ($isOnline) {
            $lastSeenLabel = 'онлайн';
        } elseif ($peer->last_seen_at) {
            $lastSeenLabel = $peer->last_seen_at
                ->timezone((string) config('app.timezone'))
                ->format('d.m.Y H:i');
        }

        return [
            'id' => (int) $peer->id,
            'avatar' => $this->avatarUrl($peer->image_crop ?? null),
            'full_name' => $fullName,
            'phone' => trim((string) ($peer->phone ?? '')),
            'parent_full_name' => (string) ($peer->parent_full_name ?: ''),
            'parent_phone' => trim((string) ($peer->parentProfile?->phone ?? '')),
            'is_online' => $isOnline,
            'last_seen_at' => $peer->last_seen_at?->toDateTimeString(),
            'last_seen_label' => $lastSeenLabel,
            'team_title' => $teamTitle,
        ];
    }

    public function avatarUrl(?string $imageCrop): string
    {
        if ($imageCrop === null || $imageCrop === '') {
            return self::DEFAULT_AVATAR;
        }

        return '/storage/avatars/'.ltrim($imageCrop, '/');
    }

    public function findPrivateThread(int $userId, int $peerId): ?ChatThread
    {
        return ChatThread::query()
            ->has('participants', '=', 2)
            ->whereHas('participants', fn ($q) => $q->where('user_id', $userId))
            ->whereHas('participants', fn ($q) => $q->where('user_id', $peerId))
            ->with(['participants.user:'.self::PEER_USER_COLUMNS])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();
    }

    private function safeBroadcast(object $event): void
    {
        try {
            event($event);
        } catch (Throwable $e) {
            Log::warning('Chat broadcast skipped', [
                'event' => $event::class,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  list<int>  $threadIds
     * @return array<int, int>
     */
    private function unreadCountsByThread(int $userId, array $threadIds): array
    {
        if ($threadIds === []) {
            return [];
        }

        $rows = ChatMessage::query()
            ->selectRaw('thread_id, COUNT(*) as unread_count')
            ->whereIn('thread_id', $threadIds)
            ->where('user_id', '<>', $userId)
            ->whereRaw(
                'messages.created_at > COALESCE((
                    SELECT last_read FROM participants
                    WHERE participants.thread_id = messages.thread_id
                      AND participants.user_id = ?
                      AND participants.deleted_at IS NULL
                    LIMIT 1
                ), ?)',
                [$userId, '1970-01-01 00:00:00']
            )
            ->groupBy('thread_id')
            ->pluck('unread_count', 'thread_id');

        return $rows->map(fn ($count) => (int) $count)->all();
    }

    private function unreadCountForThread(int $threadId, int $userId): int
    {
        return (int) ChatMessage::query()
            ->where('thread_id', $threadId)
            ->where('user_id', '<>', $userId)
            ->whereRaw(
                'messages.created_at > COALESCE((
                    SELECT last_read FROM participants
                    WHERE participants.thread_id = ?
                      AND participants.user_id = ?
                      AND participants.deleted_at IS NULL
                    LIMIT 1
                ), ?)',
                [$threadId, $userId, '1970-01-01 00:00:00']
            )
            ->count();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function threadIdsForUser(int $userId)
    {
        return ChatParticipant::query()
            ->where('user_id', $userId)
            ->select('thread_id');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeThread(ChatThread $thread, int $viewerId, int $unreadCount): array
    {
        $last = $thread->lastMessage;
        $lastIsMine = $last !== null && (int) $last->user_id === $viewerId;

        return [
            'id' => (int) $thread->id,
            'title' => $this->titleForViewer($thread, $viewerId),
            'avatar' => $this->avatarForViewer($thread, $viewerId),
            'peer_id' => $this->peerId($thread, $viewerId),
            'peer_is_online' => $this->isPeerOnline($thread, $viewerId),
            'last_message' => $last ? $this->preview((string) $last->body) : null,
            'last_message_time' => $last?->created_at?->toDateTimeString()
                ?? $thread->updated_at?->toDateTimeString(),
            'last_message_is_mine' => $lastIsMine,
            'last_message_is_read' => $lastIsMine ? $this->isReadByPeer($thread, $last) : null,
            'unread_count' => $unreadCount,
            'draft_body' => $this->viewerDraftBody($thread, $viewerId),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeThreadHeader(ChatThread $thread, int $viewerId): array
    {
        $thread->load(['participants.user:'.self::PEER_USER_COLUMNS]);

        return [
            'id' => (int) $thread->id,
            'title' => $this->titleForViewer($thread, $viewerId),
            'avatar' => $this->avatarForViewer($thread, $viewerId),
            'peer_id' => $this->peerId($thread, $viewerId),
            'peer_is_online' => $this->isPeerOnline($thread, $viewerId),
            'draft_body' => $this->viewerDraftBody($thread, $viewerId),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMessage(ChatThread $thread, ChatMessage $message, int $viewerId): array
    {
        $isMine = (int) $message->user_id === $viewerId;

        return [
            'id' => (int) $message->id,
            'user_id' => (int) $message->user_id,
            'body' => (string) $message->body,
            'created_at' => $message->created_at?->toDateTimeString(),
            'is_read' => $isMine ? $this->isReadByPeer($thread, $message) : null,
        ];
    }

    private function isReadByPeer(ChatThread $thread, ChatMessage $message): bool
    {
        $created = $message->created_at;
        if (! $created) {
            return false;
        }

        $others = $thread->relationLoaded('participants')
            ? $thread->participants->where('user_id', '<>', $message->user_id)
            : $thread->participants()->where('user_id', '<>', $message->user_id)->get();

        if ($others->isEmpty()) {
            return false;
        }

        foreach ($others as $participant) {
            if (empty($participant->last_read) || $participant->last_read->lt($created)) {
                return false;
            }
        }

        return true;
    }

    private function titleForViewer(ChatThread $thread, int $viewerId): string
    {
        $peer = $this->peerUser($thread, $viewerId);
        $fullName = trim((string) ($peer?->full_name ?: ''));

        return $fullName !== '' ? $fullName : 'Диалог';
    }

    private function avatarForViewer(ChatThread $thread, int $viewerId): string
    {
        $peer = $this->peerUser($thread, $viewerId);

        return $this->avatarUrl($peer?->image_crop);
    }

    private function peerId(ChatThread $thread, int $viewerId): ?int
    {
        $peer = $this->peerUser($thread, $viewerId);

        return $peer ? (int) $peer->id : null;
    }

    private function peerUser(ChatThread $thread, int $viewerId): ?User
    {
        $participants = $thread->relationLoaded('participants')
            ? $thread->participants
            : $thread->participants()->with('user:'.self::PEER_USER_COLUMNS)->get();

        $other = $participants->firstWhere('user_id', '<>', $viewerId);

        return $other?->user;
    }

    private function isPeerOnline(ChatThread $thread, int $viewerId): bool
    {
        $peer = $this->peerUser($thread, $viewerId);

        return $peer?->isOnline() ?? false;
    }

    private function preview(string $body): string
    {
        return mb_strimwidth(strip_tags($body), 0, 90, '…');
    }

    private function viewerDraftBody(ChatThread $thread, int $viewerId): string
    {
        if (! $this->hasDraftColumn()) {
            return '';
        }

        $participants = $thread->relationLoaded('participants')
            ? $thread->participants
            : $thread->participants()->get();

        $mine = $participants->firstWhere('user_id', $viewerId);

        return trim((string) ($mine?->draft_body ?? ''));
    }

    private function hasDraftColumn(): bool
    {
        static $has = null;
        if ($has === null) {
            $has = Schema::hasColumn('participants', 'draft_body');
        }

        return $has;
    }
}
