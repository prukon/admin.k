<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\ContractEvent;
use App\Models\ContractSignRequest;
use App\Models\MyLog;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Services\Signatures\SignatureProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use App\Models\Partner;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Support\BuildsLogTable;


use App\Models\Team;
use App\Models\UserTableSetting;

class ContractsController extends Controller
{
    use BuildsLogTable;

    public function index2(Request $request)
    {
        $partnerId = $this->partnerId();


        $q = \App\Models\Contract::query()
            ->where('contracts.school_id', $partnerId) // <— критичное ограничение по партнёру
            ->when($request->status, fn($qq) => $qq->where('contracts.status', $request->status))
            ->when($request->group_id, fn($qq) => $qq->where('contracts.group_id', $request->group_id))
            // Подтягиваем имя ученика, телефон, email и название группы
            ->leftJoin('users', 'users.id', '=', 'contracts.user_id')
            ->leftJoin('teams', 'teams.id', '=', 'contracts.group_id') // group_id = users.team_id
            ->select([
                'contracts.*',
                'users.name  as user_name',
                'users.lastname  as user_lastname',   // <— добавили
                'users.phone as user_phone',
                'users.email as user_email',
                'teams.title as team_title',
            ])
            ->orderByDesc('contracts.id');

        $contracts = $q->paginate(20);

        return view('contracts.index', compact('contracts'));
    }

    public function index(Request $request)
    {
        $partnerId = $this->partnerId();

        // Все группы партнёра для фильтра по группе
        $allTeams = Team::where('partner_id', $partnerId)
            ->orderBy('order_by', 'asc')
            ->get();

        return view('contracts.index', compact('allTeams'));
    }

    /**
     * DataTables серверный endpoint для списка договоров.
     * Возвращает JSON в формате, понятном DataTables.
     */
    public function data(Request $request)
    {
        $partnerId = $this->partnerId();

        $validated = $request->validate([
            'status'       => 'nullable|string',
            'group_id'     => 'nullable|string',   // id или 'none'
            'search_value' => 'nullable|string',   // строка поиска (имя/фамилия/телефон/email)
            'draw'         => 'nullable|integer',
            'start'        => 'nullable|integer',
            'length'       => 'nullable|integer',
        ]);

        $statusFilter   = $validated['status'] ?? null;
        $groupFilter    = $validated['group_id'] ?? null;
        $searchValue    = $validated['search_value'] ?? null;

        // Базовый запрос по партнёру
        $baseQuery = Contract::query()
            ->where('contracts.school_id', $partnerId)
            // джойним пользователя и группу один раз
            ->leftJoin('users', 'users.id', '=', 'contracts.user_id')
            ->leftJoin('teams', 'teams.id', '=', 'contracts.group_id')
            ->select([
                'contracts.*',
                'users.name as user_name',
                'users.lastname as user_lastname',
                'users.phone as user_phone',
                'users.email as user_email',
                'teams.title as team_title',
            ]);

        // Фильтр по статусу (значения статусов подправишь под свои)
        if (!empty($statusFilter)) {
            $baseQuery->where('contracts.status', $statusFilter);
        }

        // Фильтр по группе: id / none / пусто
        if ($groupFilter !== null && $groupFilter !== '') {
            if ($groupFilter === 'none') {
                $baseQuery->whereNull('contracts.group_id');
            } else {
                $baseQuery->where('contracts.group_id', $groupFilter);
            }
        }

        // Поиск по имени, фамилии, телефону, email
        if (!empty($searchValue)) {
            $like = '%' . $searchValue . '%';
            $baseQuery->where(function ($q) use ($like) {
                $q->where('users.name', 'like', $like)
                    ->orWhere('users.lastname', 'like', $like)
                    ->orWhere('users.phone', 'like', $like)
                    ->orWhere('users.email', 'like', $like);
            });
        }

        // Общее количество записей по партнёру (без фильтров)
        $totalRecords = Contract::where('school_id', $partnerId)->count();

        // Количество записей с учётом фильтров
        $filteredQuery   = clone $baseQuery;
        $recordsFiltered = $filteredQuery->count();

        // --- СОРТИРОВКА ДЛЯ DataTables ---
        $orderColumnIndex = $request->input('order.0.column');
        $orderDir         = $request->input('order.0.dir', 'asc');

        if ($orderColumnIndex !== null) {
            switch ((int)$orderColumnIndex) {
                case 0: // # — нумерация, игнорируем, ставим дефолт
                    $baseQuery->orderByDesc('contracts.id');
                    break;
                case 1: // Имя
                    $baseQuery->orderBy('users.name', $orderDir);
                    break;
                case 2: // Фамилия
                    $baseQuery->orderBy('users.lastname', $orderDir);
                    break;
                case 3: // Группа
                    $baseQuery->orderBy('teams.title', $orderDir);
                    break;
                case 4: // Телефон
                    $baseQuery->orderBy('users.phone', $orderDir);
                    break;
                case 5: // Email
                    $baseQuery->orderBy('users.email', $orderDir);
                    break;
                case 6: // Статус (по contracts.status)
                    $baseQuery->orderBy('contracts.status', $orderDir);
                    break;
                case 7: // Обновлён
                    $baseQuery->orderBy('contracts.updated_at', $orderDir);
                    break;
                case 8: // Действия — игнорируем, ставим дефолт
                default:
                    $baseQuery->orderByDesc('contracts.id');
                    break;
            }
        } else {
            // дефолт — последние договоры первыми
            $baseQuery->orderByDesc('contracts.id');
        }

        // Пагинация DataTables
        $start  = $validated['start'] ?? 0;
        $length = $validated['length'] ?? 20;

        $contracts = $baseQuery
            ->skip($start)
            ->take($length)
            ->get();

        $data = $contracts->map(function (Contract $contract) {
            return [
                'id'                 => $contract->id,
                'user_name'          => $contract->user_name ?: '—',
                'user_lastname'      => $contract->user_lastname ?: '—',
                'team_title'         => $contract->team_title ?: '—',
                'user_phone'         => $contract->user_phone ?: '—',
                'user_email'         => $contract->user_email ?: '—',
                'status_label'       => $contract->status_ru ?? '',           // аксессоры из модели
                'status_badge_class' => $contract->status_badge_class ?? '',
                'updated_at'         => $contract->updated_at
                    ? $contract->updated_at->format('d.m.Y H:i:s')
                    : '',
            ];
        })->toArray();

        return response()->json([
            'draw'            => (int)($validated['draw'] ?? 0),
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    /**
     * Вернуть настройки колонок для текущего пользователя
     * для таблицы "contracts_index".
     */
    public function getColumnsSettings()
    {
        $userId   = Auth::id();
        $settings = UserTableSetting::where('user_id', $userId)
            ->where('table_key', 'contracts_index')
            ->first();

        $columns = $settings?->columns;
        if (!is_array($columns)) {
            $columns = [];
        }

        return response()->json($columns);
    }

    /**
     * Сохранить настройки колонок для текущего пользователя
     * для таблицы "contracts_index".
     *
     * Ожидает в запросе: columns: { user_name: true, ... }
     */
    public function saveColumnsSettings(Request $request)
    {
        $userId = Auth::id();

        $data = $request->validate([
            'columns' => 'required|array',
        ]);

        $rawColumns = $data['columns'];
        $normalized = [];

        foreach ($rawColumns as $key => $value) {
            $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($bool === null) {
                $bool = false;
            }
            $normalized[$key] = $bool;
        }

        UserTableSetting::updateOrCreate(
            [
                'user_id'   => $userId,
                'table_key' => 'contracts_index',
            ],
            [
                'columns' => $normalized,
            ]
        );

        return response()->json([
            'success' => true,
        ]);
    }


    // единая точка входа
    private function partner(): \App\Models\Partner {
        $p = app('current_partner');
        abort_unless($p, 403, 'Партнёр не выбран.');
        return $p;
    }
    private function partnerId(): int {
        return $this->partner()->id;
    }

    public function create()
    {

        $partner   = app('current_partner');
        $partnerId = app('current_partner')->id;

        return view('contracts.create', compact('partner', 'partnerId'));

    }

    public function store(Request $request)
    {
        $partner   = $this->partner();
        $partnerId = $partner->id;

        $validated = $request->validate([
            'user_id' => ['required', 'integer'],
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ], [], ['pdf' => 'PDF-файл договора']);


        /** @var User $student */
        $student = User::query()
            ->where('id', $validated['user_id'])
            ->where('partner_id', $partnerId)
            ->where('is_enabled', 1)
            ->first();

        abort_unless($student, 422, 'Ученик не найден у текущего партнёра.');

        // TODO сделать управление стоимостью договора в настройках партнера
        // комиссия за создание договора
        $fee = $this->createContractFee(); // 70.00 по умолчанию
        try {
            $contract = \DB::transaction(function () use ($request, $partnerId, $student, $fee) {
                // 1) Блокируем строку партнёра до конца транзакции
                /** @var Partner $partner */
                $partner = app('current_partner');

                if ($partner->wallet_balance < $fee) {
                    // Денег нет — кидаем валидационную ошибку (422)
                    throw ValidationException::withMessages([
                        'wallet' => 'Недостаточно средств для создания договора.',
                    ]);
                }

                // 2) Списываем деньги
                // (используем точное десятичное поле DECIMAL(12,2), простого вычитания достаточно)
                $partner->wallet_balance = $partner->wallet_balance - $fee;
                $partner->save();
                Cache::forget("partner_balance_{$partner->id}");

                // 3) Готовим данные договора
                $groupId = $student->team_id; // может быть null — это ок

                $path = $request->file('pdf')->store('documents/' . date('Y/m'));
                $sha = hash_file('sha256', \Storage::path($path));

                // 4) Создаём договор
                $contract = Contract::create([
                    'school_id' => $partnerId,
                    'user_id' => $student->id,
                    'group_id' => $groupId,
                    'source_pdf_path' => $path,
                    'source_sha256' => $sha,
                    'status' => Contract::STATUS_DRAFT,
                    'provider' => 'podpislon',
                ]);

                // 5) Логируем события: списание и создание
                ContractEvent::create([
                    'contract_id' => $contract->id,
                    'author_id'    => Auth::id(), // ← добавили
                    'type' => 'Списание баланса за создание договора',
                    'payload_json' => json_encode([
                        'amount' => number_format($fee, 2, '.', ''),
                        'currency' => 'RUB',
                        'partner_id' => $partnerId,
                        'balance_after' => number_format($partner->wallet_balance, 2, '.', ''),
                    ], JSON_UNESCAPED_UNICODE),
                ]);

                ContractEvent::create([
                    'contract_id' => $contract->id,
                    'author_id'    => Auth::id(), // ← добавили
                    'type' => 'created',
                    'payload_json' => null,
                ]);

                // создания договора
                MyLog::create([
                    'type' => 500,
                    'action' => 500,
                    'user_id'   => $student->id,
                    'target_type' => 'App\Models\Contract',
                    'target_id' => $partner->id,
                    'target_label' => $partner->title,
                    'description' => ("Договор создан: № " . $contract->id ),
                    'created_at' => now(),
                ]);

                return $contract;
            });

        } catch (ValidationException $e) {
            // Денег не хватило — вернёмся назад с ошибкой 422
            return back()
                ->withErrors($e->errors())
                ->withInput()
                ->with('error', 'Недостаточно средств для создания договора.');
        }

        return redirect()->route('contracts.show', $contract->id)
            ->with('success', 'Договор создан. С баланса списано 70 ₽. Теперь можно отправить на подпись.');
    } 
    // ------- AJAX: поиск учеников текущего партнёра для Select2 -------
    public function usersSearch(Request $request)
    {
        $q = trim((string)$request->get('q', ''));
        $partnerId = $this->partnerId();



        $users = \App\Models\User::query()
            ->when($partnerId, fn($qq) => $qq->where('users.partner_id', $partnerId))
            ->where('users.is_enabled', 1)
            ->leftJoin('teams', 'teams.id', '=', 'users.team_id')
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('users.name', 'like', "%{$q}%")
                        ->orWhere('users.lastname', 'like', "%{$q}%")   // ← поиск по фамилии
                        ->orWhere('users.phone', 'like', "%{$q}%")
                        ->orWhere('users.email', 'like', "%{$q}%");
                });
            })
            ->orderBy('users.lastname')    // чуть приятнее сортировка
            ->orderBy('users.name')
            ->limit(50)
            ->get([
                'users.id',
                'users.name',
                'users.lastname',          // ← добавили
                'users.team_id',
                'teams.title as team_title',
            ]);


        $results = $users->map(function ($u) {
//            $fullname = trim($u->name . ' ' . ($u->lastname ?? '')); // Имя Фамилия
            $fullname = trim(($u->lastname ?? '') . ' ' . $u->name);

            return [
                'id' => $u->id,
                'text' => $fullname,         // ← Select2 будет показывать это
                'name' => $u->name,          // на будущее
                'lastname' => $u->lastname,      // на будущее
                'team_id' => $u->team_id,
                'team_title' => $u->team_title,
            ];
        });

        return response()->json(['results' => $results]);
    }
// ------- AJAX: вернуть группу(ы) выбранного ученика -------
    public function userGroup(Request $request)
    {
        $userId = (int)$request->get('user_id');
        $partnerId = $this->partnerId();



        $student = User::query()
            ->where('id', $userId)
            ->where('partner_id', $partnerId)
            ->where('is_enabled', 1)
            ->first();

        if (!$student) {
            \Log::warning('[userGroup] student not found or disabled', ['userId' => $userId]);
            return response()->json(['groups' => []]);
        }

        $groups = [];
        if (method_exists($student, 'groups')) {
            $groups = $student->groups()
                ->select('groups.id', 'groups.title')
                ->when(function ($q) {
                    // если есть pivot с флагом активности — оставь; иначе убери строку ниже
                }, function ($q) {
                    $q->wherePivot('is_active', 1);
                })
                ->orderBy('groups.title')
                ->get()
                ->map(fn($g) => ['id' => $g->id, 'title' => $g->title])
                ->values()
                ->all();
        }

        \Log::debug('[userGroup] done', ['groups_count' => count($groups)]);
        return response()->json(['groups' => $groups]);
    }

    public function show(Contract $contract)
    {
        abort_unless($contract->school_id === $this->partnerId(), 403, 'Нет доступа к договору этого партнёра.');

        $events = $contract->events()->orderBy('id', 'desc')->get();
        $requests = $contract->signRequests()->orderBy('id', 'desc')->get();

        // Подтягиваем данные ученика + название группы (teams.title)
        $student = \App\Models\User::select('id', 'name', 'lastname', 'phone', 'email', 'team_id')
            ->find($contract->user_id);

        $teamTitle = null;
        if ($student && $student->team_id) {
            $teamTitle = \Illuminate\Support\Facades\DB::table('teams')
                ->where('id', $student->team_id)
                ->value('title');
        }

        return view('contracts.show', compact('contract', 'events', 'requests', 'student', 'teamTitle'));
    }
    public function downloadOriginal(Contract $contract) {
        abort_unless($contract->school_id === $this->partnerId(), 403);
        return Storage::download($contract->source_pdf_path, 'contract-' . $contract->id . '.pdf');
    }
    public function downloadSigned(Contract $contract) {
        abort_unless($contract->school_id === $this->partnerId(), 403);
        abort_unless($contract->signed_pdf_path, 404);
        return Storage::download($contract->signed_pdf_path, 'contract-' . $contract->id . '-signed.pdf');
    }


    public function send(Contract $contract, Request $request, SignatureProvider $provider)
    {
        abort_unless($contract->school_id === $this->partnerId(), 403, 'Нет доступа к договору этого партнёра.');

        // [LOGGING] вспомогательный замыкатель для MyLog
        $partnerId = app('current_partner')->id ?? null;
        $authorId  = \Auth::id();
        $userFullName = $contract->student_full_name ?? 'Неизвестный пользователь';
//        $writeMyLog = function ( $action, $targetType, $targetId, $targetLabel, $lines) use ($partnerId, $authorId, $userFullName) {
//            try {
//                $description = implode("\n", $lines);
//                \App\Models\MyLog::create([
//                    'type'         => 2, // user-логи
//                    'action'       => $action,
//                    'partner_id'   => $partnerId,
//                    'author_id'    => $authorId,
//                    'target_type'  => $targetType,
//                    'target_id'    => $targetId,
//                    'target_label' => $userFullName,
//                    'description'  => $description,
//                ]);
//            } catch (\Throwable $e) {
//                \Log::error('[contracts.send][MyLog] fail', ['error' => $e->getMessage()]);
//            }
//        };

        $validated = $request->validate([
            'signer_lastname'   => ['required', 'string', 'max:100'],
            'signer_firstname'  => ['required', 'string', 'max:100'],
            'signer_middlename' => ['nullable', 'string', 'max:100'],
            'signer_phone'      => ['required', 'string', 'max:32'],
            'ttl_hours'         => ['nullable', 'integer', 'min:1', 'max:168'],
        ]);

        // Собираем ФИО для хранения и передачи в провайдер
        $signerFio = trim(
            preg_replace('/\s+/', ' ',
                ($validated['signer_lastname'] ?? '') . ' ' .
                ($validated['signer_firstname'] ?? '') . ' ' .
                ($validated['signer_middlename'] ?? '')
            )
        );

        // НОРМАЛИЗАЦИЯ ТЕЛЕФОНА (RU): к 11 цифрам, ведущая 7
        $raw    = (string)$validated['signer_phone'];
        $digits = preg_replace('/\D+/', '', $raw) ?: '';

        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            $digits = '7' . $digits;
        } elseif (strlen($digits) === 11 && $digits[0] === '8') {
            $digits[0] = '7';
        }

        // финальная проверка
        if (strlen($digits) !== 11 || $digits[0] !== '7') {
            return response()->json([
                'success' => false,
                'message' => 'Укажите номер в формате +7 (XXX) XXX-XX-XX.',
                'code'    => 'phone_invalid'
            ], 422);
        }

        $phone = $digits; // только цифры, 11 знаков, начинается с 7

        // ===== helpers =====
        $isSentByProvider = function (?array $doc): bool {
            if (!$doc) return false;
            $code = $doc['status'] ?? null;
            $code = is_numeric($code) ? (int)$code : null;
            $text = mb_strtolower((string)($doc['status_text'] ?? ''));
            return $code === 15 || str_contains($text, 'отправлен') || str_contains($text, 'sent');
        };

        $fetchDoc = function () use ($contract) {
            /** @var \App\Services\Signatures\Providers\PodpislonProvider $pod */
            $pod = app(\App\Services\Signatures\Providers\PodpislonProvider::class);
            if (!$contract->provider_doc_id) return null;
            $list = $pod->list([(int)$contract->provider_doc_id], [], 1, true);
            return $list['items'][0] ?? null;
        };

        $pollForSent = function (callable $fetch) use ($isSentByProvider): ?array {
            for ($i = 0; $i < 3; $i++) {
                $doc = $fetch();
                if ($isSentByProvider($doc)) return $doc;
                usleep(300_000); // 300 мс
            }
            return null;
        };

        $signingLinks = function () use ($contract): array {
            try {
                /** @var \App\Services\Signatures\Providers\PodpislonProvider $pod */
                $pod = app(\App\Services\Signatures\Providers\PodpislonProvider::class);
                return $pod->getSigningLinks($contract);
            } catch (\Throwable $e) {
                return [];
            }
        };

        // Всегда пишем запись об отправке в историю (и для resend тоже)
        $sr = new \App\Models\ContractSignRequest([
            'signer_name'       => $signerFio,
            'signer_lastname'   => $validated['signer_lastname'] ?? null,
            'signer_firstname'  => $validated['signer_firstname'] ?? null,
            'signer_middlename' => $validated['signer_middlename'] ?? null,
            'signer_phone'      => $phone,
            'ttl_hours'         => $validated['ttl_hours'] ?? 72,
            'status'            => 'created',
        ]);
        $contract->signRequests()->save($sr);

        // [LOGGING] добавление: создан запрос на подпись
//        $writeMyLog(
//            510, // contract_sign_request_created
//            \App\Models\ContractSignRequest::class,
//            $sr->id,
////            $user->id,
//            $signerFio ?: ('Запрос #' . $sr->id),
//            [
//                'Запрос на подпись создан',
//                'ФИО: ' . $signerFio,
//                'Телефон: ' . $phone,
//                'TTL (часы): ' . ($validated['ttl_hours'] ?? 72),
//                'Договор: ' . 'Договор #' . $contract->id,
//            ]
//        );

        MyLog::create([
            'type' => 500,
            'action' => 510,
            'user_id' => $contract->user_id,
            'target_type' => 'App\Models\Contract',
            'target_id' => $contract->id,
            'target_label' => "Договор № {$contract->id}",
            'description' =>
                "Запрос на подпись создан!!!\n" .
                "ФИО: {$signerFio}\n" .
                "Телефон: {$phone}\n" .
                "TTL (часы): " . ($validated['ttl_hours'] ?? 72) . "\n" .
                "Договор: Договор #{$contract->id}",
            'created_at' => now(),
        ]);




        // ===== РЕСЕНД
        if ($contract->provider === 'podpislon' && $contract->provider_doc_id) {
            try {
                /** @var \App\Services\Signatures\Providers\PodpislonProvider $pod */
                $pod = app(\App\Services\Signatures\Providers\PodpislonProvider::class);
                $res = $pod->resendForContract($contract);

                $doc = $pollForSent($fetchDoc);

                if ($doc) {
                    // фиксируем изменения статусов (одной записью)
                    $changes = [];

                    // SignRequest: created -> sent
                    $oldSrStatus = $sr->status;
                    $sr->status = 'sent';
                    $sr->save();
                    if ($oldSrStatus !== $sr->status) {
                        $changes[] = 'Статус запроса: "' . $oldSrStatus . '" → "' . $sr->status . '"';
                    }

                    // Contract: если не signed/opened — ставим sent
                    if (!in_array($contract->status, [
                        \App\Models\Contract::STATUS_SIGNED,
                        \App\Models\Contract::STATUS_OPENED,
                    ], true)) {
                        $oldContractStatus = $contract->status;
                        $contract->status  = \App\Models\Contract::STATUS_SENT;
                        $contract->save();

                        if ($oldContractStatus !== $contract->status) {
                            $changes[] = 'Статус договора: "' . $oldContractStatus . '" → "' . $contract->status . '"';
                        }
                    }

                    // [LOGGING] изменение: статус(ы) после resend
                    if ($changes) {
//                        $writeMyLog(
//                            511, // contract_resend_sent
//                            \App\Models\Contract::class,
//                            $contract->id,
//                            'Договор #' . $contract->id,
//                            $changes
//                        );
                        // создания договора
                        MyLog::create([
                            'type' => 500,
                            'action' => 511,
                            'user_id' => $contract->user_id,
                            'target_type' => 'App\Models\Contract',
                            'target_id' => $contract->id,
                            'target_label' => "Договор № {$contract->id}",
                            'description' => $changes,
                            'created_at' => now(),
                        ]);
                    }

                    \App\Models\ContractEvent::create([
                        'contract_id'  => $contract->id,
                        'author_id'    => \Auth::id(),
                        'type'         => 'resend',
                        'payload_json' => json_encode(['res' => $res, 'doc' => $doc], JSON_UNESCAPED_UNICODE),
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'SMS отправлена',
                        'status'  => 'sent',
                    ], 200);
                }

                // не подтвердили отправку
                $oldSrStatus = $sr->status;
                $sr->status  = 'failed';
                $sr->save();

                $links = $signingLinks();
                \App\Models\ContractEvent::create([
                    'contract_id'  => $contract->id,
                    'author_id'    => \Auth::id(),
                    'type'         => 'resend_failed',
                    'payload_json' => json_encode(['res' => $res, 'links' => $links], JSON_UNESCAPED_UNICODE),
                ]);

                // [LOGGING] изменение: статус запроса failed
//                $writeMyLog(
//                    512, // contract_resend_failed
//                    \App\Models\ContractSignRequest::class,
//                    $sr->id,
//                    $signerFio ?: ('Запрос #' . $sr->id),
//                    [
//                        'Статус запроса: "' . $oldSrStatus . '" → "' . $sr->status . '"',
//                        'Договор: ' . 'Договор #' . $contract->id,
//                    ]
//                );

                MyLog::create([
                    'type' => 500,
                    'action' => 512,
                    'user_id' => $contract->user_id,
                    'target_type' => 'App\Models\Contract',
                    'target_id' => $contract->id,
                    'target_label' => "Договор № {$contract->id}",
                    'description' =>   [
                        'Статус запроса: "' . $oldSrStatus . '" → "' . $sr->status . '"',
                        'Договор: ' . 'Договор #' . $contract->id,
                    ],
                    'created_at' => now(),
                ]);





                return response()->json([
                    'success' => false,
                    'message' => 'Провайдер не подтвердил отправку SMS.',
                    'code'    => 'resend_not_sent',
                    'links'   => $links,
                ], 422);

            } catch (\Throwable $e) {
                $oldSrStatus = $sr->status;
                $sr->status  = 'failed';
                $sr->save();

                $links = $signingLinks();
                \App\Models\ContractEvent::create([
                    'contract_id'  => $contract->id,
                    'author_id'    => \Auth::id(),
                    'type'         => 'resend_failed',
                    'payload_json' => json_encode(['error' => $e->getMessage(), 'links' => $links], JSON_UNESCAPED_UNICODE),
                ]);

                // [LOGGING] изменение: исключение при resend
//                $writeMyLog(
//                    512, // contract_resend_failed
//                    \App\Models\ContractSignRequest::class,
//                    $sr->id,
//                    $signerFio ?: ('Запрос #' . $sr->id),
//                    [
//                        'Статус запроса: "' . $oldSrStatus . '" → "' . $sr->status . '"',
//                        'Ошибка: ' . $e->getMessage(),
//                        'Договор: ' . 'Договор #' . $contract->id,
//                    ]
//                );

                MyLog::create([
                    'type' => 500,
                    'action' => 512,
                    'user_id' => $contract->user_id,
                    'target_type' => 'App\Models\Contract',
                    'target_id' => $contract->id,
                    'target_label' => "Договор № {$contract->id}",
                    'description' =>
                        "Статус запроса: \"{$oldSrStatus}\" → \"{$sr->status}\"\n" .
                        "Ошибка: {$e->getMessage()}\n" .
                        "Договор: Договор #{$contract->id}",
                    'created_at' => now(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка повторной отправки: ' . $e->getMessage(),
                    'code'    => 'resend_exception',
                    'links'   => $links,
                ], 422);
            }
        }

        // ===== ПЕРВАЯ ОТПРАВКА
        try {
            $res = $provider->send($contract, $sr);

            $doc = $pollForSent($fetchDoc);

            if ($doc) {
                $changes = [];

                // SR: created -> sent
                $oldSrStatus = $sr->status;
                $sr->status  = 'sent';
                $sr->save();
                if ($oldSrStatus !== $sr->status) {
                    $changes[] = 'Статус запроса: "' . $oldSrStatus . '" → "' . $sr->status . '"';
                }

                // Contract: -> sent
                $oldContractStatus = $contract->status;
                $contract->status  = \App\Models\Contract::STATUS_SENT;
                $contract->save();
                if ($oldContractStatus !== $contract->status) {
                    $changes[] = 'Статус договора: "' . $oldContractStatus . '" → "' . $contract->status . '"';
                }

                // [LOGGING] изменение: успешная отправка
                if ($changes) {
//                    $writeMyLog(
//                        513, // contract_send_sent
//                        \App\Models\Contract::class,
//                        $contract->id,
//                        'Договор #' . $contract->id,
//                        $changes
//                    );


                    MyLog::create([
                        'type' => 500,
                        'action' => 513,
                        'user_id' => $contract->user_id,
                        'target_type' => 'App\Models\Contract',
                        'target_id' => $contract->id,
                        'target_label' => "Договор № {$contract->id}",
                        'description' => $changes,
                        'created_at' => now(),
                    ]);

                }


                return response()->json([
                    'success' => true,
                    'message' => 'SMS отправлена',
                    'status'  => 'sent',
                ], 200);
            }

            // не подтвердили "отправлено"
            $oldSrStatus      = $sr->status;
            $sr->status       = 'failed';
            $sr->save();

            $oldContractStatus = $contract->status;
            $contract->status  = \App\Models\Contract::STATUS_FAILED;
            $contract->save();

            $links = $signingLinks();
            \App\Models\ContractEvent::create([
                'contract_id'  => $contract->id,
                'author_id'    => \Auth::id(),
                'type'         => 'failed',
                'payload_json' => json_encode(['res' => $res, 'links' => $links], JSON_UNESCAPED_UNICODE),
            ]);

            // [LOGGING] изменение: не удалось подтвердить отправку
//            $writeMyLog(
//                514, // contract_send_failed
//                \App\Models\Contract::class,
//                $contract->id,
//                'Договор #' . $contract->id,
//                [
//                    'Статус запроса: "' . $oldSrStatus . '" → "' . $sr->status . '"',
//                    'Статус договора: "' . $oldContractStatus . '" → "' . $contract->status . '"',
//                ]
//            );

            MyLog::create([
                'type' => 500,
                'action' => 514,
                'user_id' => $contract->user_id,
                'target_type' => 'App\Models\Contract',
                'target_id' => $contract->id,
                'target_label' => "Договор № {$contract->id}",
                'description' =>
                    "Статус запроса: \"{$oldSrStatus}\" → \"{$sr->status}\"\n" .
                    "Статус договора: \"{$oldContractStatus}\" → \"{$contract->status}\"",
                'created_at' => now(),
            ]);


            return response()->json([
                'success' => false,
                'message' => 'Провайдер не подтвердил отправку SMS.',
                'code'    => 'send_not_sent',
                'links'   => $links,
            ], 422);

        } catch (\Throwable $e) {
            $oldSrStatus      = $sr->status;
            $sr->status       = 'failed';
            $sr->save();

            $oldContractStatus = $contract->status;
            $contract->status  = \App\Models\Contract::STATUS_FAILED;
            $contract->save();

            \App\Models\ContractEvent::create([
                'contract_id'  => $contract->id,
                'author_id'    => \Auth::id(),
                'type'         => 'failed',
                'payload_json' => json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE),
            ]);

            // [LOGGING] изменение: исключение при отправке
//            $writeMyLog(
//                514, // contract_send_failed
//                \App\Models\Contract::class,
//                $contract->id,
//                'Договор #' . $contract->id,
//                [
//                    'Статус запроса: "' . $oldSrStatus . '" → "' . $sr->status . '"',
//                    'Статус договора: "' . $oldContractStatus . '" → "' . $contract->status . '"',
//                    'Ошибка: ' . $e->getMessage(),
//                ]
//            );

            MyLog::create([
                'type' => 500,
                'action' => 514,
                'user_id' => $contract->user_id,
                'target_type' => 'App\Models\Contract',
                'target_id' => $contract->id,
                'target_label' => "Договор № {$contract->id}",
                'description' =>
                    "Статус запроса: \"{$oldSrStatus}\" → \"{$sr->status}\"\n" .
                    "Статус договора: \"{$oldContractStatus}\" → \"{$contract->status}\"\n" .
                    "Ошибка: {$e->getMessage()}",

                'created_at' => now(),
            ]);



            \Log::error('[contracts.send] fail', [
                'contract_id' => $contract->id,
                'error'       => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code'    => 'send_failed',
            ], 422);
        }
    }


    /**
     * Официальный ресенд SMS по package (+необязательный sid подписанта).
     * Возвращает ['ok'=>bool, 'data'=>mixed] при успехе или ['ok'=>false, 'error'=>string].
     */
    public function resend(string $package, ?string $sid = null): array
    {
        $url = rtrim($this->baseUrl, '/') . '/repeat-send'; // эндпоинт Подпислона для повтора отправки
        $payload = ['package' => $package];
        if ($sid) $payload['sid'] = $sid;

        [$http, $debug] = $this->makeHttpWithDebug();

        $t0 = microtime(true);
        Log::info('PODPISLON: RESEND start', [
            'url' => $url,
            'payload' => $payload,
            'headers' => array_keys($this->headers()),
        ]);

        // у Подпислона этот метод принимает form-urlencoded
        $resp = $http->asForm()->post($url, $payload);

        $elapsed = round((microtime(true) - $t0) * 1000);
        rewind($debug);
        $curlDebug = stream_get_contents($debug);
        fclose($debug);

        Log::info('PODPISLON: RESEND response', [
            'status' => $resp->status(),
            'ok' => $resp->ok(),
            'ms' => $elapsed,
            'resp_hdr' => $this->maskHeaders($resp->headers()),
            'body' => $this->clip($resp->body()),
            'curl' => $this->clip($curlDebug, 4000),
        ]);

        if (!$resp->ok()) {
            return ['ok' => false, 'error' => 'HTTP ' . $resp->status() . ' ' . $this->clip($resp->body())];
        }

        $j = $this->safeJson($resp);
        $ok = (bool)($j['status'] ?? $j['ok'] ?? $j['success'] ?? false);

        if ($ok) {
            return ['ok' => true, 'data' => $j];
        }
        return ['ok' => false, 'error' => (string)($j['message'] ?? 'Resend failed'), 'data' => $j];
    }
    public function revoke(Contract $contract, SignatureProvider $provider)
    {
        abort_unless($contract->school_id === $this->partnerId(), 403, 'Нет доступа к договору этого партнёра.');

        try {
            $provider->revoke($contract);

            $contract->status = Contract::STATUS_REVOKED;
            $contract->save();

            ContractEvent::create([
                'contract_id' => $contract->id,
                'author_id'    => Auth::id(),
                'type' => 'revoked',
                'payload_json' => null,
            ]);

            return response()->json(['message' => 'Подписание отозвано', 'status' => 'revoked']);
        } catch (\Throwable $e) {
            ContractEvent::create([
                'contract_id' => $contract->id,
                'author_id'    => Auth::id(),
                'type' => 'failed',
                'payload_json' => json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE),
            ]);

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function status(Contract $contract, SignatureProvider $provider)
    {
        abort_unless($contract->school_id === $this->partnerId(), 403, 'Нет доступа к договору этого партнёра.');

        try {
            $data = $provider->getStatus($contract);
            $status = $this->mapProviderStatus($data['status'] ?? null);

            if ($status && $status !== $contract->status) {
                $contract->status = $status;
                $contract->save();

                ContractEvent::create([
                    'contract_id' => $contract->id,
                    'author_id'    => Auth::id(),
                    'type' => 'status_sync',
                    'payload_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
                ]);

                if ($status === Contract::STATUS_SIGNED && !$contract->signed_pdf_path) {
                    $this->downloadAndAttachSigned($contract, $provider);
                }
            }

            return response()->json(['status' => $contract->status, 'raw' => $data]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    protected function mapProviderStatus(?string $s): ?string
    {
        return match ($s) {
            'sent' => Contract::STATUS_SENT,
            'opened' => Contract::STATUS_OPENED,
            'signed' => Contract::STATUS_SIGNED,
            'expired' => Contract::STATUS_EXPIRED,
            'revoked' => Contract::STATUS_REVOKED,
            'failed' => Contract::STATUS_FAILED,
            default => null,
        };
    }
    protected function downloadAndAttachSigned(Contract $contract, SignatureProvider $provider): void
    {
        abort_unless($contract->school_id === $this->partnerId(), 403, 'Нет доступа к договору этого партнёра.');

        $file = $provider->downloadSigned($contract);
        $path = 'documents/' . date('Y/m') . '/' . $file['filename'];
        Storage::put($path, $file['content']);
        $contract->signed_pdf_path = $path;
        $contract->signed_at = now();
        $contract->save();

        ContractEvent::create([
            'contract_id' => $contract->id,
            'author_id'    => Auth::id(),
            'type' => 'signed_pdf_saved',
            'payload_json' => json_encode(['path' => $path], JSON_UNESCAPED_UNICODE),
        ]);
    }
    public function sendEmail(Contract $contract, Request $request)
    {
        abort_unless($contract->school_id === $this->partnerId(), 403, 'Нет доступа к договору этого партнёра.');

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'signed' => ['nullable'], // 0/1/true/false
        ]);

        $sendSigned = filter_var($request->input('signed'), FILTER_VALIDATE_BOOLEAN);

        try {
            $to = $validated['email'];

            // выбираем, что прикладывать
            if ($sendSigned) {
                if (!$contract->signed_pdf_path || !is_file(Storage::path($contract->signed_pdf_path))) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Подписанный файл не найден.',
                    ], 422);
                }
                $attachPath = Storage::path($contract->signed_pdf_path);
                $attachName = 'contract-' . $contract->id . '-signed.pdf';
                $subject = 'Подписанный договор #' . $contract->id;
                $body = 'Подписанный договор во вложении.';
            } else {
                if (!$contract->source_pdf_path || !is_file(Storage::path($contract->source_pdf_path))) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Исходный файл не найден.',
                    ], 422);
                }
                $attachPath = Storage::path($contract->source_pdf_path);
                $attachName = 'contract-' . $contract->id . '.pdf';
                $subject = 'Договор #' . $contract->id;
                $body = 'Договор во вложении.';
            }

            Mail::raw($body, function ($message) use ($to, $subject, $attachPath, $attachName) {
                $message->to($to)->subject($subject);
                $message->attach($attachPath, [
                    'as' => $attachName,
                    'mime' => 'application/pdf',
                ]);
            });

            ContractEvent::create([
                'contract_id' => $contract->id,
                'author_id'    => Auth::id(),
                'type' => $sendSigned ? 'email_signed_sent' : 'email_sent',
                'payload_json' => json_encode(['to' => $to], JSON_UNESCAPED_UNICODE),
            ]);

            return response()->json([
                'success' => true,
                'message' => $sendSigned
                    ? 'Подписанный договор отправлен на e-mail'
                    : 'Отправлено на e-mail',
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
    private function createContractFee(): float
    {
        return (float)(config('billing.contract_create_fee') ?? 70.00);
    }
    public function checkBalance(Request $request)
    {
        $partnerId = $this->partnerId();
        $fee = (float)(config('billing.contract_create_fee') ?? 70.00);

// свежий баланс из БД (не объект из контейнера)
        $balance = \App\Models\Partner::whereKey($partnerId)->value('wallet_balance');

        if ($balance === null) {
            return response()->json([
                'ok' => false,
                'message' => 'Партнёр не найден.',
            ], 404);
        }

        if ((float)$balance >= $fee) {
            return response()->json([
                'ok' => true,
                'balance' => (float)$balance,
                'fee' => $fee,
            ]);
        }

        return response()->json([
            'ok' => false,
            'message' => 'Недостаточно средств для создания договора.',
            'balance' => (float)$balance,
            'fee' => $fee,
        ], 422);
    }
//    Юзер
    public function myDocuments(Request $request)
    {
        $user = Auth::user();
        $partners = $user->partners ?? collect();

        // фильтр по статусу (опционально: ?status=signed и т.п.)
        $status = $request->string('status')->toString();

//        $contracts = Contract::query()
//            ->where('user_id', $user->id)
//            ->when($status, fn($q) => $q->where('status', $status))
//            ->orderByDesc('id')
//            ->paginate(12);

        $contracts = Contract::query()
            ->where('user_id', $user->id)
            ->with(['user', 'team', 'lastSignRequest'])
            ->when($status, fn($q) => $q->where('status', $status))
            ->orderByDesc('id')
            ->paginate(12);



        // для удобного рендера бейджей
        $statusMap = [
            'draft'   => ['label' => 'Черновик',        'class' => 'secondary'],
            'sent'    => ['label' => 'Отправлено',      'class' => 'info'],
            'opened'  => ['label' => 'Открыт',          'class' => 'warning'],
            'signed'  => ['label' => 'Подписан',        'class' => 'success'],
            'expired' => ['label' => 'Истёк срок',      'class' => 'dark'],
            'revoked' => ['label' => 'Отозван',         'class' => 'dark'],
            'failed'  => ['label' => 'Ошибка',          'class' => 'danger'],
        ];

        return view('account.index', [
            'activeTab' => 'myDocuments',
            'user'      => $user,
            'partners'  => $partners,
            'contracts' => $contracts,
            'statusMap' => $statusMap,
            'currentStatus' => $status,
        ]);
    }
    public function myDocumentRequests(int $contractId)
    {
        $contract = Contract::query()
            ->where('id', $contractId)
            ->where('user_id', Auth::id())
            ->with(['signRequests' => fn($q) => $q->orderByDesc('id')])
            ->firstOrFail();

        return response()->json([
            'requests' => $contract->signRequests->map(fn($r) => [
                'id'        => $r->id,
                'signer'    => $r->signer_name,
                'phone'     => $r->signer_phone,
                'status'    => $r->status_ru,
                'badge'     => $r->status_badge_class,
                'created'   => $r->created_at?->format('d.m.Y H:i'),
            ]),
        ]);
    }
}