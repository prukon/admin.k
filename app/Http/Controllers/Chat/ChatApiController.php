<?php

namespace App\Http\Controllers\Chat;

use App\Events\InboxBump;
use App\Events\MessageCreated;
use App\Events\ThreadReadUpdated;
use App\Events\ThreadUpdated;
use App\Events\Typing;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Cmgmyr\Messenger\Models\Thread;
use Cmgmyr\Messenger\Models\Message;
use Cmgmyr\Messenger\Models\Participant;
use App\Models\User;

class ChatApiController extends Controller
{
    private function assertParticipant(Thread $thread): void
    {
        $uid = Auth::id();
        abort_unless(
            $thread->participants()->where('user_id', $uid)->exists(),
            403,
            'Нет доступа к этому диалогу.'
        );
    }

    private function avatarUrl(?User $u): string
    {
        if (!$u) return '/img/default-avatar.png';
        if (!empty($u->image_crop)) {
            return '/storage/avatars/' . ltrim($u->image_crop, '/');
        }
        return '/img/default-avatar.png';
    }

    private function isMessageReadByOthers(Thread $thread, Message $message): bool
    {
        $created = $message->created_at;
        $others = $thread->participants()
            ->where('user_id', '<>', $message->user_id)
            ->get(['last_read']);
        if ($others->isEmpty()) return false;
        foreach ($others as $p) {
            if (empty($p->last_read) || $p->last_read->lt($created)) {
                return false;
            }
        }
        return true;
    }

    private function resolveThreadTitleForUser(Thread $thread, int $viewerId): string
    {
        $participants = $thread->participants()->with('user:id,name')->get();
        if ($participants->count() === 2) {
            $other = $participants->firstWhere('user_id', '<>', $viewerId) ?->user;
            return $other ?->name ?? 'Диалог';
        }
        return $thread->subject ?: 'Группа';
    }

    /** Левый список с последним сообщением + непрочитанные */
    public function threads(Request $request)
    {
        $uid = Auth::id();

        $threads = Thread::query()
            ->whereHas('participants', fn($q) => $q->where('user_id', $uid))
            ->select(['threads.id', 'threads.subject', 'threads.updated_at'])
            ->with(['participants.user:id,name,image_crop'])
            ->addSelect([
                'last_message_body' => Message::select('body')
                    ->whereColumn('thread_id', 'threads.id')
                    ->orderByDesc('id')
                    ->limit(1),
                'last_message_time' => Message::select('created_at')
                    ->whereColumn('thread_id', 'threads.id')
                    ->orderByDesc('id')
                    ->limit(1),
                'unread_count' => DB::table('messages')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('thread_id', 'threads.id')
                    ->whereRaw('messages.created_at > COALESCE((
                        SELECT last_read FROM participants 
                        WHERE participants.thread_id = threads.id AND participants.user_id = ?
                        LIMIT 1
                    ), "1970-01-01")', [$uid]),
            ])
            ->orderByDesc('last_message_time')
            ->paginate(100);

        $threads->getCollection()->transform(function ($t) use ($uid) {
            $title = $this->resolveThreadTitleForUser($t, $uid);

            $lastPreview = $t->last_message_body
                ? mb_strimwidth(strip_tags((string)$t->last_message_body), 0, 90, '…')
                : null;

            $lastTs = $t->last_message_time
                ? \Illuminate\Support\Carbon::parse($t->last_message_time)->toDateTimeString()
                : ($t->updated_at ?->toDateTimeString());

            $others = $t->participants->filter(fn($p) => (int)$p->user_id !== (int)$uid)->values();
            $avatarUser = $others->count() ? $others->first()->user : null;
            $avatar = $this->avatarUrl($avatarUser);

            $memberCount = $t->participants->count();

            return (object)[
                'id'               => $t->id,
                'title'            => $title,
                'avatar'           => $avatar,
                'last_message'     => $lastPreview,
                'last_message_time'=> $lastTs,
                'updated_at'       => $t->updated_at ?->toDateTimeString(),
                'member_count'     => $memberCount,
                'is_group'         => $memberCount > 2,
                'unread_count'     => (int)($t->unread_count ?? 0),
            ];
        });

        return response()->json($threads);
    }

    /** Заголовок + последние 30 сообщений */
    public function thread(Request $request, Thread $thread)
    {
        $this->assertParticipant($thread);
        $uid = Auth::id();

        $messages = $thread->messages()
            ->latest('id')
            ->take(30)
            ->with('user:id,name')
            ->get(['id', 'user_id', 'body', 'created_at'])
            ->reverse()
            ->values()
            ->map(function ($m) use ($thread, $uid) {
                $isMine = (int)$m->user_id === (int)$uid;
                return [
                    'id'         => $m->id,
                    'user_id'    => $m->user_id,
                    'body'       => $m->body,
                    'created_at' => $m->created_at->toDateTimeString(),
                    'is_read'    => $isMine ? $this->isMessageReadByOthers($thread, $m) : null,
                ];
            });

        $title = $this->resolveThreadTitleForUser($thread, $uid);

        // пометить прочитанным
        $participant = $thread->participants()->where('user_id', $uid)->first();
        if ($participant) {
            $participant->last_read = now();
            $participant->save();
            event(new ThreadReadUpdated($thread->id, $uid));
        }

        return response()->json([
            'thread' => [
                'id'           => $thread->id,
                'subject'      => $title,
                'online'       => '',
                'is_group'     => $thread->participants()->count() > 2,
                'member_count' => $thread->participants()->count(),
            ],
            'messages' => $messages,
        ]);
    }

    /** История/скролл вверх */
    public function messages(Request $request, Thread $thread)
    {
        $this->assertParticipant($thread);
        $uid = Auth::id();

        $request->validate([
            'after_id'  => ['nullable', 'integer', 'min:1'],
            'before_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = $thread->messages()->orderBy('id', 'desc');

        if ($request->filled('after_id')) {
            $query->where('id', '>', (int)$request->after_id);
            $list = $query->take(100)->with('user:id,name')->get(['id', 'user_id', 'body', 'created_at']);

            return response()->json($list->map(function ($m) use ($thread, $uid) {
                $isMine = (int)$m->user_id === (int)$uid;
                return [
                    'id'         => $m->id,
                    'user_id'    => $m->user_id,
                    'body'       => $m->body,
                    'created_at' => $m->created_at->toDateTimeString(),
                    'is_read'    => $isMine ? $this->isMessageReadByOthers($thread, $m) : null,
                ];
            })->values());
        }

        if ($request->filled('before_id')) {
            $query->where('id', '<', (int)$request->before_id);
        }

        $messages = $query->take(30)->with('user:id,name')->get(['id', 'user_id', 'body', 'created_at'])
            ->reverse()->values()
            ->map(function ($m) use ($thread, $uid) {
                $isMine = (int)$m->user_id === (int)$uid;
                return [
                    'id'         => $m->id,
                    'user_id'    => $m->user_id,
                    'body'       => $m->body,
                    'created_at' => $m->created_at->toDateTimeString(),
                    'is_read'    => $isMine ? $this->isMessageReadByOthers($thread, $m) : null,
                ];
            });

        return response()->json($messages);
    }

    /** Создание сообщения + broadcast (актуальная) */
    public function storeMessage(Request $request, Thread $thread)
    {
        $this->assertParticipant($thread);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:5000'],
        ], [], ['body' => 'текст сообщения']);

        // 1) Создаём сообщение
        $msg = $thread->messages()->create([
            'user_id' => Auth::id(),
            'body'    => $data['body'],
        ]);

        // 2) Обновляем last_read для отправителя
        Participant::updateOrCreate(
            ['thread_id' => $thread->id, 'user_id' => Auth::id()],
            ['last_read' => now()]
        );

        $thread->touch();

        // 3) Событие в сам тред (для активного окна чата)
        event(new MessageCreated($thread->id, [
            'id'         => $msg->id,
            'user_id'    => $msg->user_id,
            'body'       => $msg->body,
            'created_at' => $msg->created_at->toDateTimeString(),
            'is_read'    => null,
        ]));

        // 4) Общие поля для превью/инбокса
        $recipientIds = $thread->participants()->pluck('user_id')->all(); // [int...]
        $recipientsExceptSender = array_values(array_filter(
            $recipientIds,
            fn ($uid) => (int)$uid !== (int)$msg->user_id
        ));

        $lastPreview  = mb_strimwidth(strip_tags((string)$msg->body), 0, 90, '…');
        $membersCount = count($recipientIds);
        $isGroup      = $membersCount > 2;

        // 5) Инбокс-бамп (левый список для получателей)
// 5) Инбокс-бамп
        foreach ($recipientsExceptSender as $uid) {
            $isActive = (int)$uid === (int)Auth::id() && $thread->id === request()->route('thread')->id;

            event(new InboxBump((int)$uid, [
                'thread_id'         => (int) $thread->id,
                'last_message'      => $lastPreview,
                'last_message_time' => $msg->created_at->toDateTimeString(),
                // если чат активен у юзера — не увеличиваем счётчик
                'increment_unread'  => $isActive ? false : true,
                'title'             => $this->resolveThreadTitleForUser($thread, (int)$uid),
                'avatar'            => $thread->avatar_url ?? null,
                'member_count'      => $membersCount,
                'is_group'          => $isGroup,
                'recipients'        => $recipientIds,
            ]));
        }


        // 6) ThreadUpdated (опционально)
        foreach ($recipientsExceptSender as $uid) {
            event(new ThreadUpdated($thread->id, [
                'title'             => $this->resolveThreadTitleForUser($thread, (int)$uid),
                'last_message'      => $lastPreview,
                'last_message_time' => $msg->created_at->toDateTimeString(),
                'member_count'      => $membersCount,
                'is_group'          => $isGroup,
                'recipients'        => $recipientIds,
                'increment_unread'  => true,
            ]));
        }

        // 7) Ответ отправителю
        return response()->json([
            'id'         => $msg->id,
            'user_id'    => $msg->user_id,
            'body'       => $msg->body,
            'created_at' => $msg->created_at->toDateTimeString(),
            'is_read'    => true,
        ], 201);
    }

    /** Создать 1-на-1 или группу */
    public function storeThread(Request $request)
    {
        $data = $request->validate([
            'type'     => ['required', Rule::in(['private', 'group'])],
            'subject'  => ['nullable', 'string', 'max:120'],
            'members'  => ['required', 'array', 'min:1'],
            'members.*'=> ['integer', 'exists:users,id'],
        ]);

        $uid = Auth::id();
        $subject = $data['type'] === 'group'
            ? ($data['subject'] ?? 'Группа')
            : 'Диалог';

        $thread = Thread::create(['subject' => $subject]);

        $others = collect($data['members'])
            ->filter(fn($id) => (int)$id !== (int)$uid)
            ->unique()
            ->values();

        Participant::create([
            'thread_id' => $thread->id,
            'user_id'   => $uid,
            'last_read' => now(),
        ]);

        foreach ($others as $memberId) {
            Participant::create([
                'thread_id' => $thread->id,
                'user_id'   => (int)$memberId,
            ]);
        }

        $thread->touch();

        return response()->json(['ok' => true, 'thread_id' => $thread->id], 201);
    }

    /** Пользователи для модалок (+ роль и last_seen_at) */
    public function users(Request $request)
    {
        $uid = Auth::id();
        $q = trim((string)$request->get('q', ''));

        $qb = User::query()->from('users')->where('users.id', '<>', $uid);

        // ограничение по partner_id (если есть такая колонка)
        if (Schema::hasColumn('users', 'partner_id')) {
            $myPartnerId = User::where('id', $uid)->value('partner_id');
            if ($myPartnerId !== null) {
                $qb->where('users.partner_id', $myPartnerId);
            }
        }

        // join роли, если в users есть внешний ключ и таблица roles существует
        $hasRoleId = Schema::hasColumn('users', 'role_id');
        $hasRoles  = Schema::hasTable('roles');

        if ($hasRoleId && $hasRoles) {
            $qb->leftJoin('roles', 'roles.id', '=', 'users.role_id');
        }




  // выбор «last_seen_at» безопасно: если колонки нет — подставим users.updated_at
        $selects = ['users.id', 'users.name', 'users.email', 'users.image_crop'];
        if ($hasRoleId && $hasRoles) {
            $selects[] = DB::raw('roles.name as role_name');
            $selects[] = DB::raw('roles.label as role_label');
        } else {
            $selects[] = DB::raw('NULL as role_name');
            $selects[] = DB::raw('NULL as role_label');
        }

// teams join: users.team_id -> teams.id, иначе fallback по partner_id
        $hasTeams = Schema::hasTable('teams');
        $hasUserTeamId = Schema::hasColumn('users', 'team_id');
        if ($hasTeams && $hasUserTeamId) {
            $qb->leftJoin('teams', 'teams.id', '=', 'users.team_id');
            $selects[] = DB::raw('teams.title as team_title');
        } elseif ($hasTeams && Schema::hasColumn('users', 'partner_id')) {
            $qb->leftJoin('teams', function($join){
                $join->on('teams.partner_id', '=', 'users.partner_id')
                    ->where('teams.is_enabled', '=', 1);
            });
            $selects[] = DB::raw('teams.title as team_title');
        } else {
            $selects[] = DB::raw('NULL as team_title');
        }

        if (Schema::hasColumn('users', 'last_seen_at')) {
            $selects[] = DB::raw('users.last_seen_at as last_seen_at');
        } elseif (Schema::hasColumn('users', 'last_login_at')) {
            $selects[] = DB::raw('users.last_login_at as last_seen_at');
        } else {
            $selects[] = DB::raw('users.updated_at as last_seen_at');
        }






        // поиск
        if ($q !== '') {
            $qb->where(function ($w) use ($q) {
                $w->where('users.name', 'like', "%{$q}%")
                    ->orWhere('users.email', 'like', "%{$q}%");
            });
        }

        $users = $qb->orderBy('users.name')->limit(100)->get($selects);

        $users = $users->map(function ($u) {
            return [
                'id'           => $u->id,
                'name'         => $u->name,
                'email'        => $u->email,
                'avatar'       => $this->avatarUrl($u),
                'role_name'    => $u->role_name,
                'role_label'   => $u->role_label,
                'team_title'   => $u->team_title, // <— добавлено
                'last_seen_at' => optional($u->last_seen_at)->toDateTimeString() ?? (is_string($u->last_seen_at) ? $u->last_seen_at : null),
            ];
        })->values();

        return response()->json($users);
    }

    /** Участники группы (без изменения формата) */
    public function members(Request $request, Thread $thread)
    {
        $this->assertParticipant($thread);

        $members = $thread->participants()
            ->with('user:id,name,email,image_crop')
            ->get()
            ->map(function ($p) {
                $u = $p->user;
                return [
                    'id'     => $u->id,
                    'name'   => $u->name,
                    'email'  => $u->email,
                    'avatar' => $this->avatarUrl($u),
                ];
            })->values();

        return response()->json([
            'thread_id'    => $thread->id,
            'member_count' => $members->count(),
            'members'      => $members,
        ]);
    }

    /** Добавить участников в группу */
    public function addMembers(Request $request, Thread $thread)
    {
        $this->assertParticipant($thread);

        $data = $request->validate([
            'members'   => ['required', 'array', 'min:1'],
            'members.*' => ['integer', 'exists:users,id'],
        ]);

        $existingIds = $thread->participants()->pluck('user_id')->all();
        $toAdd = collect($data['members'])
            ->map(fn($i)=>(int)$i)
            ->filter(fn($id)=>!in_array($id, $existingIds, true))
            ->unique()
            ->values();

        foreach ($toAdd as $uid) {
            Participant::create([
                'thread_id' => $thread->id,
                'user_id'   => $uid,
            ]);
        }

        $thread->touch();

        return response()->json(['ok' => true, 'added' => $toAdd], 201);
    }

    /** «Печатает…» */
    public function typing(Request $request, Thread $thread)
    {
        $this->assertParticipant($thread);
        $data = $request->validate([
            'is_typing' => ['required', 'boolean'],
        ]);
        event(new Typing($thread->id, Auth::id(), (bool)$data['is_typing']));
        return response()->json(['ok' => true]);
    }

    /** Пометка прочитанных */
    public function markRead(Request $request, Thread $thread)
    {
        $this->assertParticipant($thread);
        $uid = Auth::id();
        Participant::updateOrCreate(
            ['thread_id' => $thread->id, 'user_id' => $uid],
            ['last_read' => now()]
        );
        event(new ThreadReadUpdated($thread->id, $uid));
        return response()->json(['ok' => true]);
    }
}
