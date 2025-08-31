<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Schema;
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

    /** true, если все другие участники прочитали сообщение. */
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

    /** Заголовок треда для текущего пользователя. */
    private function resolveThreadTitleForUser(Thread $thread, int $viewerId): string
    {
        $participants = $thread->participants()->with('user:id,name')->get();
        if ($participants->count() === 2) {
            $other = $participants->firstWhere('user_id', '<>', $viewerId)?->user;
            return $other?->name ?? 'Диалог';
        }
        return $thread->subject ?: 'Группа';
    }

    /** Список тредов слева. */
    public function threads2(Request $request)
    {
        $uid = Auth::id();

        $threads = Thread::query()
            ->whereHas('participants', fn($q) => $q->where('user_id', $uid))
            ->latest('updated_at')
        ->with([
            'messages' => function ($q) { $q->latest('id')->limit(1); },
            'participants.user:id,name,image_crop'
        ])
        ->select(['id', 'subject', 'updated_at'])
        ->paginate(100);

        $threads->getCollection()->transform(function ($t) use ($uid) {
            $title = $this->resolveThreadTitleForUser($t, $uid);

            $last = $t->messages->first();
            $lastPreview = $last ? mb_strimwidth(strip_tags((string)$last->body), 0, 90, '…') : null;
            $lastTs = $last?->created_at?->toDateTimeString() ?? $t->updated_at?->toDateTimeString();

            $others = $t->participants->filter(fn($p) => (int)$p->user_id !== (int)$uid)->values();
            $avatarUser = $others->count() ? $others->first()->user : null;
            $avatar = $this->avatarUrl($avatarUser);

            return (object)[
                'id'                => $t->id,
                'title'             => $title,
                'avatar'            => $avatar,
                'last_message'      => $lastPreview,
                'last_message_time' => $lastTs,
                'updated_at'        => $t->updated_at?->toDateTimeString(),
            ];
//            return response()->json([
//                'thread' => [
//                    'id'          => $thread->id,
//                    'subject'     => $title,
//                    'online'      => $onlineText,
//                    'is_group'    => $thread->participants()->count() > 2,
//                    'member_count'=> $thread->participants()->count(),
//                ],
//                'messages' => $messages,
//            ]);


        });

        return response()->json($threads);
    }

    /** Список тредов (левый список) — с корректным последним сообщением для КАЖДОГО чата */
    public function threads(Request $request)
    {
        $uid = Auth::id();

        $threads = Thread::query()
            ->whereHas('participants', fn($q) => $q->where('user_id', $uid))
        ->select([
        'threads.id',
        'threads.subject',
        'threads.updated_at',
    ])
        // подтянем пользователей для аватарок
        ->with(['participants.user:id,name,image_crop'])
        // субзапросы на последнее сообщение в этом треде
        ->addSelect([
            'last_message_body' => Message::select('body')
                ->whereColumn('thread_id', 'threads.id')
                ->orderByDesc('id')
                ->limit(1),
            'last_message_time' => Message::select('created_at')
                ->whereColumn('thread_id', 'threads.id')
                ->orderByDesc('id')
                ->limit(1),
        ])
        // сортируем по времени последнего сообщения
        ->orderByDesc('last_message_time')
        ->paginate(100);

    $threads->getCollection()->transform(function ($t) use ($uid) {
        $title = $this->resolveThreadTitleForUser($t, $uid);

        $lastPreview = $t->last_message_body
            ? mb_strimwidth(strip_tags((string)$t->last_message_body), 0, 90, '…')
            : null;

        $lastTs = $t->last_message_time
            ? \Illuminate\Support\Carbon::parse($t->last_message_time)->toDateTimeString()
            : ($t->updated_at?->toDateTimeString());

        $others = $t->participants->filter(fn($p) => (int)$p->user_id !== (int)$uid)->values();
        $avatarUser = $others->count() ? $others->first()->user : null;
        $avatar = $this->avatarUrl($avatarUser);

        $memberCount = $t->participants->count();

        return (object)[
            'id'                => $t->id,
            'title'             => $title,
            'avatar'            => $avatar,
            'last_message'      => $lastPreview,
            'last_message_time' => $lastTs,
            'updated_at'        => $t->updated_at?->toDateTimeString(),
            'member_count'      => $memberCount,
            'is_group'          => $memberCount > 2,
        ];
    });

    return response()->json($threads);
}

    /** Заголовок чата + последние 30 сообщений. */
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
        $onlineText = null;

        // пометить прочитанным текущему
        $participant = $thread->participants()->where('user_id', $uid)->first();
        if ($participant) {
            $participant->last_read = now();
            $participant->save();
        }

//        return response()->json([
//            'thread'   => [
//                'id'      => $thread->id,
//                'subject' => $title,
//                'online'  => $onlineText,
//            ],
//            'messages' => $messages,
//        ]);

        return response()->json([
            'thread' => [
                'id'          => $thread->id,
                'subject'     => $title,
                'online'      => $onlineText,
                'is_group'    => $thread->participants()->count() > 2,
                'member_count'=> $thread->participants()->count(),
            ],
            'messages' => $messages,
        ]);
    }

    /** Выгрузка сообщений (поллинг/история). */
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

    /** СОЗДАНИЕ СООБЩЕНИЯ */
    public function storeMessage(Request $request, Thread $thread)
    {
        $this->assertParticipant($thread);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:5000'],
        ], [], ['body' => 'текст сообщения']);

        $msg = Message::create([
            'thread_id' => $thread->id,
            'user_id'   => Auth::id(),
            'body'      => $data['body'],
        ]);

        // автор прочитал
        Participant::updateOrCreate(
            ['thread_id' => $thread->id, 'user_id' => Auth::id()],
            ['last_read' => now()]
        );

        $thread->touch();

        return response()->json([
            'id'         => $msg->id,
            'user_id'    => $msg->user_id,
            'body'       => $msg->body,
            'created_at' => $msg->created_at->toDateTimeString(),
            'is_read'    => true,
        ], 201);
    }

    /** Создать 1-на-1 или группу. */
    public function storeThread(Request $request)
    {
        $data = $request->validate([
            'type'       => ['required', Rule::in(['private', 'group'])],
            'subject'    => ['nullable', 'string', 'max:120'],
            'members'    => ['required', 'array', 'min:1'],
            'members.*'  => ['integer', 'exists:users,id'],
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

    /** Пользователи для модалок (живой поиск + фильтр по partner_id). */
    public function users(Request $request)
    {
        $uid = Auth::id();
        $q = trim((string)$request->get('q', ''));

        $qb = User::query()->where('id', '<>', $uid);

        if (Schema::hasColumn('users', 'partner_id')) {
            $myPartnerId = User::where('id', $uid)->value('partner_id');
            if ($myPartnerId !== null) {
                $qb->where('partner_id', $myPartnerId);
            }
        }

        if ($q !== '') {
            $qb->where(function($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        $users = $qb->orderBy('name')->limit(100)
            ->get(['id','name','email','image_crop']);

        $users = $users->map(function($u){
            return [
                'id'    => $u->id,
                'name'  => $u->name,
                'email' => $u->email,
                'avatar'=> $this->avatarUrl($u),
            ];
        })->values();

        return response()->json($users);
    }

    /** Список участников треда (для модалки инфо о группе). */
    public function members(Request $request, Thread $thread)
    {
        $this->assertParticipant($thread);

        $members = $thread->participants()
            ->with('user:id,name,email,image_crop')
            ->get()
            ->map(function($p){
                $u = $p->user;
                return [
                    'id'     => $u->id,
                    'name'   => $u->name,
                    'email'  => $u->email,
                    'avatar' => $this->avatarUrl($u),
                ];
            })->values();

        return response()->json([
            'thread_id'     => $thread->id,
            'member_count'  => $members->count(),
            'members'       => $members,
        ]);
    }

    /** Добавить участников в группу. */
    public function addMembers(Request $request, Thread $thread)
    {
        $this->assertParticipant($thread);

        $data = $request->validate([
            'members'   => ['required','array','min:1'],
            'members.*' => ['integer','exists:users,id'],
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

    return response()->json(['ok'=>true, 'added'=>$toAdd], 201);
}

}
