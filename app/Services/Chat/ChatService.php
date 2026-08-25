<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Events\InboxBump;
use App\Events\MessageCreated;
use App\Events\MessageReactionUpdated;
use App\Events\ThreadReadUpdated;
use App\Models\ChatMessage;
use App\Models\ChatMessageReaction;
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

    public const PEER_USER_COLUMNS = 'id,name,lastname,image_crop,last_seen_at,role_id';

    public function __construct(
        private readonly ChatSupportIdentity $support,
    ) {
    }

    public const MEMBERS_PAGE_SIZE = 15;

    public function unreadTotal(int $userId): int
    {
        return $this->unreadSum($userId, null);
    }

    public function unreadPrivateTotal(int $userId): int
    {
        return $this->unreadSum($userId, false);
    }

    public function unreadGroupTotal(int $userId): int
    {
        return $this->unreadSum($userId, true);
    }

    private function unreadSum(int $userId, ?bool $isGroup): int
    {
        $query = ChatParticipant::query()->where('user_id', $userId);
        if ($isGroup !== null && $this->hasIsGroupColumn()) {
            $query->whereHas('thread', function ($thread) use ($isGroup) {
                $thread->where('is_group', $isGroup);
            });
        }

        return (int) $query->sum('unread_count');
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
            if ($this->shouldHideThreadFromInbox($thread, $userId)) {
                continue;
            }
            $row = $this->serializeThread(
                $thread,
                $userId,
                (int) ($unreadByThread[$thread->id] ?? 0)
            );
            $peerId = $row['peer_id'] ?? null;
            if (empty($row['is_group']) && $peerId !== null) {
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
            'messages' => $this->serializeMessages($thread, $messages, $viewerId),
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

            return $this->serializeMessages(
                $thread,
                $query
                    ->limit(100)
                    ->get(['id', 'user_id', 'body', 'created_at'])
                    ->sortBy('id')
                    ->values(),
                $viewerId
            );
        }

        if ($beforeId !== null) {
            $query->where('id', '<', $beforeId);
        }

        return $this->serializeMessages(
            $thread,
            $query
                ->limit(40)
                ->get(['id', 'user_id', 'body', 'created_at'])
                ->reverse()
                ->values(),
            $viewerId
        );
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
                $this->restoreParticipantIfTrashed((int) $existing->id, (int) $actor->id);
                $this->restoreParticipantIfTrashed((int) $existing->id, $peerId);
                $existing = $existing->fresh(['participants.user:'.self::PEER_USER_COLUMNS]) ?? $existing;

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
     * @param  list<int>  $memberIds
     * @return array<string, mixed>
     */
    public function createGroupThread(User $actor, string $title, array $memberIds): array
    {
        $actorId = (int) $actor->id;
        $memberIds = array_values(array_unique(array_map('intval', $memberIds)));

        $stored = DB::transaction(function () use ($actorId, $title, $memberIds) {
            $lockIds = $memberIds;
            $lockIds[] = $actorId;
            $lockIds = array_values(array_unique($lockIds));
            sort($lockIds);
            User::query()->whereIn('id', $lockIds)->orderBy('id')->lockForUpdate()->get();

            $payload = ['subject' => $title];
            if ($this->hasIsGroupColumn()) {
                $payload['is_group'] = true;
            }
            $thread = ChatThread::query()->create($payload);

            ChatParticipant::query()->create([
                'thread_id' => $thread->id,
                'user_id' => $actorId,
                'last_read' => now(),
            ]);

            foreach ($memberIds as $memberId) {
                ChatParticipant::query()->create([
                    'thread_id' => $thread->id,
                    'user_id' => $memberId,
                ]);
            }

            $thread = $thread->fresh(['participants.user:'.self::PEER_USER_COLUMNS]);

            return [
                'thread' => $thread,
                'header' => $this->serializeThreadHeader($thread, $actorId),
            ];
        });

        $thread = $stored['thread'];
        foreach ($thread->participants as $participant) {
            $uid = (int) $participant->user_id;
            $this->safeBroadcast(new InboxBump($uid, $this->groupCreatedBumpPayload($thread, $uid)));
        }

        return $stored['header'];
    }

    public function userCanManageGroupMembers(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('superadmin');
    }

    /**
     * @return array<string, mixed>
     */
    public function groupMembersPage(ChatThread $thread, User $viewer, ?int $afterUserId = null, int $limit = self::MEMBERS_PAGE_SIZE): array
    {
        $limit = max(1, min(50, $limit));
        $hasRoleId = Schema::hasColumn('users', 'role_id');
        $hasRoles = Schema::hasTable('roles');

        $query = User::query()
            ->from('users')
            ->join('participants', function ($join) use ($thread) {
                $join->on('participants.user_id', '=', 'users.id')
                    ->where('participants.thread_id', (int) $thread->id)
                    ->whereNull('participants.deleted_at');
            });

        $selects = ['users.id', 'users.name', 'users.lastname', 'users.image_crop', 'users.role_id'];
        if ($hasRoleId && $hasRoles) {
            $query->leftJoin('roles', 'roles.id', '=', 'users.role_id');
            $selects[] = DB::raw('roles.name as role_name');
            $selects[] = DB::raw('roles.label as role_label');
            $this->support->constrainVisibleMembers($query);
        } else {
            $selects[] = DB::raw('NULL as role_name');
            $selects[] = DB::raw('NULL as role_label');
        }

        $query->orderBy('users.lastname')->orderBy('users.name')->orderBy('users.id');

        if ($afterUserId !== null) {
            $after = User::query()->whereKey($afterUserId)->first(['id', 'lastname', 'name']);
            if ($after) {
                $lastName = (string) ($after->lastname ?? '');
                $firstName = (string) ($after->name ?? '');
                $afterId = (int) $after->id;
                $query->where(function ($w) use ($lastName, $firstName, $afterId) {
                    $w->where('users.lastname', '>', $lastName)
                        ->orWhere(function ($w2) use ($lastName, $firstName) {
                            $w2->where('users.lastname', '=', $lastName)
                                ->where('users.name', '>', $firstName);
                        })
                        ->orWhere(function ($w3) use ($lastName, $firstName, $afterId) {
                            $w3->where('users.lastname', '=', $lastName)
                                ->where('users.name', '=', $firstName)
                                ->where('users.id', '>', $afterId);
                        });
                });
            }
        }

        $rows = $query->limit($limit + 1)->get($selects);
        $hasMore = $rows->count() > $limit;
        if ($hasMore) {
            $rows = $rows->take($limit);
        }

        $members = $rows->map(function ($user) {
            $isSupport = $this->support->isSupportUser($user);
            $fullName = $isSupport
                ? ChatSupportIdentity::DISPLAY_NAME
                : trim((string) ($user->full_name ?: $user->name));

            return [
                'id' => (int) $user->id,
                'avatar' => $this->avatarUrl($user->image_crop ?? null),
                'full_name' => $fullName !== '' ? $fullName : 'Клиент',
                'role_name' => $user->role_name,
                'role_label' => $isSupport
                    ? ChatSupportIdentity::DISPLAY_NAME
                    : $user->role_label,
            ];
        })->values()->all();

        $membersTotal = $this->visibleGroupMembersCount($thread);

        return [
            'thread' => [
                'id' => (int) $thread->id,
                'title' => $this->titleForViewer($thread, (int) $viewer->id),
                'avatar' => self::DEFAULT_AVATAR,
                'is_group' => true,
                'members_total' => $membersTotal,
                'header_subtitle' => $this->membersCountLabel($membersTotal),
            ],
            'can_manage' => $this->userCanManageGroupMembers($viewer),
            'members' => $members,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Добавить участников в групповой чат, не трогая уже состоящих (unread/last_read).
     *
     * @param  list<int>  $userIds
     */
    public function addUsersToGroupThread(ChatThread $thread, array $userIds): void
    {
        $userIds = array_values(array_unique(array_filter(
            array_map('intval', $userIds),
            fn (int $id) => $id > 0
        )));
        if ($userIds === []) {
            return;
        }

        $already = ChatParticipant::query()
            ->where('thread_id', (int) $thread->id)
            ->whereIn('user_id', $userIds)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $alreadyMap = array_flip($already);
        $toAdd = array_values(array_filter($userIds, fn (int $id) => ! isset($alreadyMap[$id])));
        if ($toAdd === []) {
            return;
        }

        $stored = DB::transaction(function () use ($thread, $toAdd) {
            User::query()->whereIn('id', $toAdd)->orderBy('id')->lockForUpdate()->get();

            foreach ($toAdd as $userId) {
                $this->restoreOrCreateParticipant((int) $thread->id, $userId);
            }

            return $thread->fresh(['participants.user:'.self::PEER_USER_COLUMNS]) ?? $thread;
        });

        foreach ($toAdd as $userId) {
            $this->safeBroadcast(new InboxBump($userId, $this->groupCreatedBumpPayload($stored, $userId)));
        }
    }

    public function removeUserFromAllThreads(int $userId): void
    {
        $threadIds = ChatParticipant::query()
            ->where('user_id', $userId)
            ->pluck('thread_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($threadIds === []) {
            return;
        }

        ChatParticipant::query()
            ->where('user_id', $userId)
            ->delete();

        $unreadTotal = $this->unreadTotal($userId);
        foreach ($threadIds as $threadId) {
            $this->safeBroadcast(new InboxBump($userId, [
                'thread_id' => $threadId,
                'removed' => true,
                'unread_total' => $unreadTotal,
            ]));
        }
    }

    /**
     * @param  list<int>  $memberIds
     * @return array<string, mixed>
     */
    public function addGroupParticipants(ChatThread $thread, User $actor, array $memberIds): array
    {
        $memberIds = array_values(array_unique(array_map('intval', $memberIds)));

        $this->addUsersToGroupThread($thread, $memberIds);

        $stored = $thread->fresh(['participants.user:'.self::PEER_USER_COLUMNS]) ?? $thread;

        return [
            'ok' => true,
            'message' => 'Участники добавлены.',
            'thread' => $this->serializeThreadHeader($stored, (int) $actor->id),
            'members_total' => $this->groupMembersCount($stored),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function removeGroupParticipant(ChatThread $thread, User $actor, User $target): array
    {
        $actorId = (int) $actor->id;
        $targetId = (int) $target->id;
        $leftSelf = $actorId === $targetId;

        $result = DB::transaction(function () use ($thread, $targetId) {
            $participant = ChatParticipant::query()
                ->where('thread_id', (int) $thread->id)
                ->where('user_id', $targetId)
                ->first();

            if ($participant) {
                $participant->delete();
            }

            $remaining = $this->groupMembersCount($thread);
            $threadDeleted = false;
            $isTeamChat = $this->hasTeamIdColumn() && $thread->team_id;
            if ($remaining === 0 && ! $isTeamChat) {
                $thread->delete();
                $threadDeleted = true;
            }

            return [
                'remaining' => $remaining,
                'thread_deleted' => $threadDeleted,
            ];
        });

        $this->safeBroadcast(new InboxBump($targetId, [
            'thread_id' => (int) $thread->id,
            'removed' => true,
            'unread_total' => $this->unreadTotal($targetId),
        ]));

        return [
            'ok' => true,
            'message' => $leftSelf ? 'Вы покинули группу.' : 'Участник удалён.',
            'left' => $leftSelf,
            'thread_deleted' => (bool) $result['thread_deleted'],
            'members_total' => (int) $result['remaining'],
        ];
    }

    /**
     * Soft-delete треда, всех участников и сообщений. Авто-чат учебной группы не трогаем.
     *
     * @return array<string, mixed>
     */
    public function deleteThread(ChatThread $thread, User $actor): array
    {
        $threadId = (int) $thread->id;

        $participantIds = DB::transaction(function () use ($thread, $threadId): array {
            $ids = ChatParticipant::query()
                ->where('thread_id', $threadId)
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $thread->forceFill(['last_message_id' => null])->save();
            ChatMessage::query()->where('thread_id', $threadId)->delete();
            ChatParticipant::query()->where('thread_id', $threadId)->delete();
            $thread->delete();

            return $ids;
        });

        foreach ($participantIds as $userId) {
            $this->safeBroadcast(new InboxBump($userId, [
                'thread_id' => $threadId,
                'removed' => true,
                'unread_total' => $this->unreadTotal($userId),
            ]));
        }

        return [
            'ok' => true,
            'message' => 'Чат удалён.',
            'thread_id' => $threadId,
        ];
    }

    public function groupMembersCount(ChatThread $thread): int
    {
        return (int) ChatParticipant::query()
            ->where('thread_id', (int) $thread->id)
            ->count();
    }

    /**
     * @return list<int>
     */
    public function groupMemberUserIds(ChatThread $thread): array
    {
        return ChatParticipant::query()
            ->where('thread_id', (int) $thread->id)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function restoreOrCreateParticipant(int $threadId, int $userId): void
    {
        $existing = ChatParticipant::withTrashed()
            ->where('thread_id', $threadId)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
                $payload = [
                    'unread_count' => 0,
                    'last_read' => null,
                ];
                if ($this->hasDraftColumn()) {
                    $payload['draft_body'] = null;
                }
                $existing->forceFill($payload)->save();
            }

            return;
        }

        ChatParticipant::query()->create([
            'thread_id' => $threadId,
            'user_id' => $userId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function updateLiveParticipant(int $threadId, int $userId, array $attributes): void
    {
        $row = ChatParticipant::query()
            ->where('thread_id', $threadId)
            ->where('user_id', $userId)
            ->first();

        if ($row) {
            $row->forceFill($attributes)->save();
        }
    }

    private function restoreParticipantIfTrashed(int $threadId, int $userId): void
    {
        $existing = ChatParticipant::withTrashed()
            ->where('thread_id', $threadId)
            ->where('user_id', $userId)
            ->first();

        if ($existing && $existing->trashed()) {
            $existing->restore();
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function storeMessage(ChatThread $thread, User $actor, string $body): array
    {
        $actorId = (int) $actor->id;

        $stored = DB::transaction(function () use ($thread, $actorId, $body, $actor) {
            $message = ChatMessage::query()->create([
                'thread_id' => $thread->id,
                'user_id' => $actorId,
                'body' => $body,
            ]);

            $thread->forceFill(['last_message_id' => $message->id])->save();

            ChatParticipant::query()
                ->where('thread_id', $thread->id)
                ->where('user_id', '<>', $actorId)
                ->increment('unread_count');

            $participantPayload = [
                'last_read' => now(),
                'unread_count' => 0,
            ];
            if ($this->hasDraftColumn()) {
                $participantPayload['draft_body'] = null;
            }
            $this->updateLiveParticipant((int) $thread->id, $actorId, $participantPayload);

            $thread->load(['participants.user:'.self::PEER_USER_COLUMNS, 'lastMessage']);

            return [
                'message' => $message,
                'payload' => $this->serializeMessage($thread, $message, $actorId, [], $actor),
            ];
        });

        $message = $stored['message'];
        $payload = $stored['payload'];
        $this->safeBroadcast(new MessageCreated((int) $thread->id, $payload));

        foreach ($thread->participants as $participant) {
            $uid = (int) $participant->user_id;
            $isSender = $uid === $actorId;
            $threadUnread = $isSender ? 0 : $this->unreadCountForThread($thread->id, $uid);

            $bump = [
                'thread_id' => (int) $thread->id,
                'title' => $this->titleForViewer($thread, $uid),
                'avatar' => $this->avatarForViewer($thread, $uid),
                'peer_id' => $this->peerId($thread, $uid),
                'peer_is_online' => $this->isPeerOnline($thread, $uid),
                'is_group' => $this->isGroupThread($thread),
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
        $this->updateLiveParticipant((int) $thread->id, $userId, [
            'last_read' => now(),
            'unread_count' => 0,
        ]);

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

        $this->updateLiveParticipant((int) $thread->id, $userId, [
            'draft_body' => $normalized === '' ? null : $normalized,
        ]);

        return $normalized;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function usersForPicker(int $actorId, int $partnerId, string $q = '', string $teamFilter = '', array $excludeUserIds = []): array
    {
        $query = User::query()
            ->from('users')
            ->where('users.partner_id', $partnerId)
            ->where('users.id', '<>', $actorId)
            ->where('users.is_enabled', 1);

        $excludeUserIds = array_values(array_unique(array_map('intval', $excludeUserIds)));
        if ($excludeUserIds !== []) {
            $query->whereNotIn('users.id', $excludeUserIds);
        }

        $hasRoleId = Schema::hasColumn('users', 'role_id');
        $hasRoles = Schema::hasTable('roles');
        if ($hasRoleId && $hasRoles) {
            $query->leftJoin('roles', 'roles.id', '=', 'users.role_id');
            $this->support->constrainExcludeSupportRole($query);
        }

        $selects = ['users.id', 'users.name', 'users.lastname', 'users.email', 'users.image_crop', 'users.parent_id', 'users.role_id'];
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

        $teamFilter = trim($teamFilter);
        if ($teamFilter !== '') {
            if ($teamFilter !== 'none' && ctype_digit($teamFilter) && (int) $teamFilter > 0) {
                $query->withSystemRoleUser();
            }
            $query->filterByStudentTeam($partnerId, $teamFilter);
        }

        $injectSupport = $this->support->shouldAppearInContacts($actorId, $q, $teamFilter, $excludeUserIds);
        $limit = $injectSupport ? 99 : 100;

        $rows = $query
            ->with(['parentProfile:id,lastname,firstname,middlename'])
            ->orderBy('users.lastname')
            ->orderBy('users.name')
            ->limit($limit)
            ->get($selects)
            ->map(fn ($user) => $this->serializePickerUser($user))
            ->values()
            ->all();

        if ($injectSupport) {
            $supportUser = $this->support->canonicalUser();
            if ($supportUser) {
                array_unshift($rows, $this->serializePickerUser($supportUser, true));
            }
        }

        return $rows;
    }

    /**
     * Карточка собеседника для модалки шапки диалога.
     *
     * @return array<string, mixed>
     */
    public function userCard(User $peer, int $partnerId): array
    {
        if ($this->support->isSupportUser($peer)) {
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
                'full_name' => ChatSupportIdentity::DISPLAY_NAME,
                'phone' => '',
                'parent_full_name' => '',
                'parent_phone' => '',
                'is_online' => $isOnline,
                'last_seen_at' => $peer->last_seen_at?->toDateTimeString(),
                'last_seen_label' => $lastSeenLabel,
                'team_title' => '',
            ];
        }

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
        $query = ChatThread::query()
            ->select('threads.*')
            ->join('participants as chat_p_me', function ($join) use ($userId) {
                $join->on('chat_p_me.thread_id', '=', 'threads.id')
                    ->where('chat_p_me.user_id', $userId);
            })
            ->join('participants as chat_p_peer', function ($join) use ($peerId) {
                $join->on('chat_p_peer.thread_id', '=', 'threads.id')
                    ->where('chat_p_peer.user_id', $peerId);
            })
            ->whereNotExists(function ($q) use ($userId, $peerId) {
                $q->selectRaw('1')
                    ->from('participants as chat_p_other')
                    ->whereColumn('chat_p_other.thread_id', 'threads.id')
                    ->whereNotIn('chat_p_other.user_id', [$userId, $peerId]);
            })
            ->with(['participants.user:'.self::PEER_USER_COLUMNS])
            ->orderByDesc('threads.updated_at')
            ->orderByDesc('threads.id');

        if ($this->hasIsGroupColumn()) {
            $query->where('threads.is_group', false);
        }

        return $query->first();
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

        $rows = ChatParticipant::query()
            ->where('user_id', $userId)
            ->whereIn('thread_id', $threadIds)
            ->pluck('unread_count', 'thread_id');

        return $rows->map(fn ($count) => (int) $count)->all();
    }

    private function unreadCountForThread(int $threadId, int $userId): int
    {
        return (int) ChatParticipant::query()
            ->where('thread_id', $threadId)
            ->where('user_id', $userId)
            ->value('unread_count');
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
            'is_group' => $this->isGroupThread($thread),
            'team_id' => $this->threadTeamId($thread),
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
        $isGroup = $this->isGroupThread($thread);
        $membersTotal = $isGroup ? $this->visibleGroupMembersCount($thread) : null;
        $presenceLabel = $isGroup
            ? ''
            : (new UserPresence())->dialogStatusLabel($this->peerUser($thread, $viewerId)?->last_seen_at);

        return [
            'id' => (int) $thread->id,
            'title' => $this->titleForViewer($thread, $viewerId),
            'avatar' => $this->avatarForViewer($thread, $viewerId),
            'peer_id' => $this->peerId($thread, $viewerId),
            'peer_is_online' => $this->isPeerOnline($thread, $viewerId),
            'is_group' => $isGroup,
            'team_id' => $this->threadTeamId($thread),
            'members_total' => $membersTotal,
            'peer_presence_label' => $presenceLabel,
            'header_subtitle' => $isGroup
                ? $this->membersCountLabel((int) $membersTotal)
                : $presenceLabel,
            'draft_body' => $this->viewerDraftBody($thread, $viewerId),
        ];
    }

    public function membersCountLabel(int $n): string
    {
        $n10 = $n % 10;
        $n100 = $n % 100;
        $word = 'участников';
        if ($n10 === 1 && $n100 !== 11) {
            $word = 'участник';
        } elseif ($n10 >= 2 && $n10 <= 4 && ($n100 < 12 || $n100 > 14)) {
            $word = 'участника';
        }

        return $n.' '.$word;
    }

    /**
     * @return array{ok: bool, message_id: int, reactions: list<array<string, mixed>>}
     */
    public function setMessageReaction(ChatThread $thread, ChatMessage $message, User $actor, string $emoji): array
    {
        $actorId = (int) $actor->id;

        DB::transaction(function () use ($message, $actorId, $emoji) {
            $row = ChatMessageReaction::query()
                ->where('message_id', $message->id)
                ->where('user_id', $actorId)
                ->lockForUpdate()
                ->first();

            if ($row) {
                $row->forceFill(['emoji' => $emoji])->save();

                return;
            }

            ChatMessageReaction::query()->create([
                'message_id' => $message->id,
                'user_id' => $actorId,
                'emoji' => $emoji,
                'created_at' => now(),
            ]);
        });

        $reactions = $this->reactionsForMessage((int) $message->id, $actorId);
        $this->broadcastReactions($thread, (int) $message->id);

        return [
            'ok' => true,
            'message_id' => (int) $message->id,
            'reactions' => $reactions,
        ];
    }

    /**
     * @return array{ok: bool, message_id: int, reactions: list<array<string, mixed>>}
     */
    public function removeMessageReaction(ChatThread $thread, ChatMessage $message, User $actor): array
    {
        ChatMessageReaction::query()
            ->where('message_id', $message->id)
            ->where('user_id', (int) $actor->id)
            ->delete();

        $reactions = $this->reactionsForMessage((int) $message->id, (int) $actor->id);
        $this->broadcastReactions($thread, (int) $message->id);

        return [
            'ok' => true,
            'message_id' => (int) $message->id,
            'reactions' => $reactions,
        ];
    }

    /**
     * @return array{author_avatar: string, author_name: string}
     */
    private function authorFieldsForUser(?User $user): array
    {
        if ($user === null) {
            return [
                'author_avatar' => self::DEFAULT_AVATAR,
                'author_name' => '',
            ];
        }

        $isSupport = $this->support->isSupportUser($user);
        $fullName = $isSupport
            ? ChatSupportIdentity::DISPLAY_NAME
            : trim((string) ($user->full_name ?: $user->name));

        return [
            'author_avatar' => $this->avatarUrl($user->image_crop ?? null),
            'author_name' => $fullName !== '' ? $fullName : 'Клиент',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMessage(ChatThread $thread, ChatMessage $message, int $viewerId, array $reactions = [], ?User $author = null): array
    {
        $isMine = (int) $message->user_id === $viewerId;
        $authorFields = $this->authorFieldsForUser($author);

        return [
            'id' => (int) $message->id,
            'user_id' => (int) $message->user_id,
            'body' => (string) $message->body,
            'created_at' => $message->created_at?->toDateTimeString(),
            'is_read' => $isMine ? $this->isReadByPeer($thread, $message) : null,
            'reactions' => $reactions,
            'author_avatar' => $authorFields['author_avatar'],
            'author_name' => $authorFields['author_name'],
        ];
    }

    /**
     * @param  iterable<int, ChatMessage>  $messages
     * @return list<array<string, mixed>>
     */
    private function serializeMessages(ChatThread $thread, iterable $messages, int $viewerId): array
    {
        $collection = collect($messages);
        $ids = $collection->map(fn (ChatMessage $message) => (int) $message->id)->all();
        $map = $this->reactionsPayloadByMessageId($ids, $viewerId);

        $userIds = $collection
            ->map(fn (ChatMessage $message) => (int) $message->user_id)
            ->unique()
            ->values()
            ->all();

        /** @var \Illuminate\Support\Collection<int, User> $authors */
        $authors = $userIds === []
            ? collect()
            : User::query()
                ->whereIn('id', $userIds)
                ->get(explode(',', self::PEER_USER_COLUMNS))
                ->keyBy('id');

        return $collection
            ->map(fn (ChatMessage $message) => $this->serializeMessage(
                $thread,
                $message,
                $viewerId,
                $map[(int) $message->id] ?? [],
                $authors->get((int) $message->user_id),
            ))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function reactionsForMessage(int $messageId, int $viewerId): array
    {
        return $this->reactionsPayloadByMessageId([$messageId], $viewerId)[$messageId] ?? [];
    }

    /**
     * @param  list<int>  $messageIds
     * @return array<int, list<array<string, mixed>>>
     */
    private function reactionsPayloadByMessageId(array $messageIds, int $viewerId): array
    {
        if ($messageIds === [] || ! $this->hasReactionsTable()) {
            return [];
        }

        $rows = ChatMessageReaction::query()
            ->whereIn('message_id', $messageIds)
            ->with(['user:'.self::PEER_USER_COLUMNS])
            ->orderBy('id')
            ->get();

        /** @var array<int, array<string, array{first_id: int, rows: list<ChatMessageReaction>}>> $grouped */
        $grouped = [];
        foreach ($rows as $row) {
            $mid = (int) $row->message_id;
            $emoji = (string) $row->emoji;
            if (! isset($grouped[$mid][$emoji])) {
                $grouped[$mid][$emoji] = [
                    'first_id' => (int) $row->id,
                    'rows' => [],
                ];
            }
            $grouped[$mid][$emoji]['rows'][] = $row;
        }

        $out = [];
        foreach ($grouped as $mid => $byEmoji) {
            uasort($byEmoji, fn (array $a, array $b) => $a['first_id'] <=> $b['first_id']);
            $chips = [];
            foreach ($byEmoji as $emoji => $pack) {
                $chips[] = $this->serializeReactionChip((string) $emoji, $pack['rows'], $viewerId);
            }
            $out[$mid] = $chips;
        }

        return $out;
    }

    /**
     * @param  list<ChatMessageReaction>  $rows
     * @return array<string, mixed>
     */
    private function serializeReactionChip(string $emoji, array $rows, int $viewerId): array
    {
        $users = [];
        $userIds = [];
        foreach ($rows as $row) {
            $uid = (int) $row->user_id;
            $userIds[] = $uid;
            $user = $row->user;
            $users[] = [
                'id' => $uid,
                'name' => $this->support->displayName($user, 'Участник'),
                'avatar' => $this->avatarUrl($user?->image_crop ?? null),
            ];
        }

        $count = count($userIds);

        return [
            'emoji' => $emoji,
            'count' => $count,
            'mine' => in_array($viewerId, $userIds, true),
            'user_ids' => $userIds,
            'users' => $count <= 3 ? $users : [],
        ];
    }

    private function broadcastReactions(ChatThread $thread, int $messageId): void
    {
        $reactions = $this->reactionsPayloadByMessageId([$messageId], 0)[$messageId] ?? [];
        $this->safeBroadcast(new MessageReactionUpdated((int) $thread->id, $messageId, $reactions));
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

    /**
     * @return array<string, mixed>
     */
    private function groupCreatedBumpPayload(ChatThread $thread, int $viewerId): array
    {
        return [
            'thread_id' => (int) $thread->id,
            'title' => $this->titleForViewer($thread, $viewerId),
            'avatar' => $this->avatarForViewer($thread, $viewerId),
            'peer_id' => $this->peerId($thread, $viewerId),
            'peer_is_online' => $this->isPeerOnline($thread, $viewerId),
            'is_group' => true,
            'last_message' => null,
            'last_message_time' => $thread->updated_at?->toDateTimeString(),
            'last_message_is_mine' => false,
            'last_message_is_read' => null,
            'unread_count' => $this->unreadCountForThread((int) $thread->id, $viewerId),
            'unread_total' => $this->unreadTotal($viewerId),
            'draft_body' => '',
        ];
    }

    private function titleForViewer(ChatThread $thread, int $viewerId): string
    {
        if ($this->isGroupThread($thread)) {
            $subject = trim((string) ($thread->subject ?? ''));

            return $subject !== '' ? $subject : 'Группа';
        }

        $peer = $this->peerUser($thread, $viewerId);
        if ($this->support->isSupportUser($peer)) {
            return ChatSupportIdentity::DISPLAY_NAME;
        }

        $fullName = trim((string) ($peer?->full_name ?: ''));

        return $fullName !== '' ? $fullName : 'Диалог';
    }

    private function avatarForViewer(ChatThread $thread, int $viewerId): string
    {
        if ($this->isGroupThread($thread)) {
            return self::DEFAULT_AVATAR;
        }

        $peer = $this->peerUser($thread, $viewerId);

        return $this->avatarUrl($peer?->image_crop);
    }

    private function peerId(ChatThread $thread, int $viewerId): ?int
    {
        if ($this->isGroupThread($thread)) {
            return null;
        }

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
        if ($this->isGroupThread($thread)) {
            return false;
        }

        $peer = $this->peerUser($thread, $viewerId);

        return $peer?->isOnline() ?? false;
    }

    private function isGroupThread(ChatThread $thread): bool
    {
        if ($this->hasIsGroupColumn() && (bool) $thread->is_group) {
            return true;
        }

        $count = $thread->relationLoaded('participants')
            ? $thread->participants->count()
            : (int) $thread->participants()->count();

        return $count > 2;
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

    private function hasReactionsTable(): bool
    {
        static $has = null;
        if ($has === null) {
            $has = Schema::hasTable('message_reactions');
        }

        return $has;
    }

    private function threadTeamId(ChatThread $thread): ?int
    {
        if (! $this->hasTeamIdColumn() || ! $thread->team_id) {
            return null;
        }

        return (int) $thread->team_id;
    }

    private function hasIsGroupColumn(): bool
    {
        static $has = null;
        if ($has === null) {
            $has = Schema::hasColumn('threads', 'is_group');
        }

        return $has;
    }

    private function hasTeamIdColumn(): bool
    {
        static $has = null;
        if ($has === null) {
            $has = Schema::hasColumn('threads', 'team_id');
        }

        return $has;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePickerUser(User $user, bool $asSupport = false): array
    {
        $support = $asSupport || $this->support->isSupportUser($user);

        return [
            'id' => (int) $user->id,
            'name' => $support
                ? ChatSupportIdentity::DISPLAY_NAME
                : (string) ($user->full_name ?: $user->name),
            'email' => $support ? '' : $user->email,
            'avatar' => $this->avatarUrl($user->image_crop ?? null),
            'role_name' => $support ? 'superadmin' : $user->role_name,
            'role_label' => $support
                ? ChatSupportIdentity::DISPLAY_NAME
                : $user->role_label,
            'team_title' => $support ? '' : $user->team_title,
            'is_online' => $user->isOnline(),
            'parent_full_name' => $support ? '' : (string) ($user->parent_full_name ?: ''),
        ];
    }

    private function shouldHideThreadFromInbox(ChatThread $thread, int $viewerId): bool
    {
        if ($this->isGroupThread($thread)) {
            return false;
        }

        $peer = $this->peerUser($thread, $viewerId);
        if ($peer === null || ! $this->support->isSupportUser($peer)) {
            return false;
        }

        return ! $this->support->isCanonicalUserId((int) $peer->id);
    }

    private function visibleGroupMembersCount(ChatThread $thread): int
    {
        if (! Schema::hasColumn('users', 'role_id')) {
            return $this->groupMembersCount($thread);
        }

        $query = User::query()
            ->from('users')
            ->join('participants', function ($join) use ($thread) {
                $join->on('participants.user_id', '=', 'users.id')
                    ->where('participants.thread_id', (int) $thread->id)
                    ->whereNull('participants.deleted_at');
            });
        $this->support->constrainVisibleMembers($query);

        return (int) $query->count('users.id');
    }
}
