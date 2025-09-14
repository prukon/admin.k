<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\ContractEvent;
use App\Models\ContractSignRequest;
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


class ContractsController extends Controller
{


    private function currentPartnerId(): ?int
    {
        // приоритет: у юзера -> в сессии -> из запроса (fallback)
        return Auth::user()->partner_id
            ?? session('current_partner_id')
            ?? session('partner_id')
            ?? (request()->has('partner_id') ? (int)request()->get('partner_id') : null);
    }

    public function create()
    {
        $partnerId = $this->currentPartnerId();
        $partner   = $partnerId ? Partner::find($partnerId) : null;

        return view('contracts.create', compact('partner', 'partnerId'));
    }

    public function store2(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required','integer'],
            'pdf'     => ['required','file','mimes:pdf','max:10240'],
        ], [], ['pdf' => 'PDF-файл договора']);

        $partnerId = $this->currentPartnerId();
        abort_unless($partnerId, 403, 'Не выбран партнёр.');

        /** @var User $student */
        $student = User::query()
            ->where('id', $validated['user_id'])
            ->where('partner_id', $partnerId)
            ->where('is_enabled', 1)
            ->first();

        abort_unless($student, 422, 'Ученик не найден у текущего партнёра.');

        // У ученика только одна группа → берём её прямо из user.team_id
        $groupId = $student->team_id; // может быть null — это ок

        $path = $request->file('pdf')->store('documents/'.date('Y/m'));
        $sha  = hash_file('sha256', Storage::path($path));

        $contract = Contract::create([
            'school_id'       => $partnerId,
            'user_id'         => $student->id,
            'group_id'        => $groupId,
            'source_pdf_path' => $path,
            'source_sha256'   => $sha,
            'status'          => Contract::STATUS_DRAFT,
            'provider'        => 'podpislon',
        ]);

        ContractEvent::create([
            'contract_id' => $contract->id,
            'type'        => 'created',
            'payload_json'=> null,
        ]);

        return redirect()->route('contracts.show', $contract->id)
            ->with('success', 'Договор создан. Теперь можно отправить на подпись.');
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required','integer'],
            'pdf'     => ['required','file','mimes:pdf','max:10240'],
        ], [], ['pdf' => 'PDF-файл договора']);

        $partnerId = $this->currentPartnerId();
        abort_unless($partnerId, 403, 'Не выбран партнёр.');

        /** @var User $student */
        $student = User::query()
            ->where('id', $validated['user_id'])
            ->where('partner_id', $partnerId)
            ->where('is_enabled', 1)
            ->first();

        abort_unless($student, 422, 'Ученик не найден у текущего партнёра.');

        // комиссия за создание договора
        $fee = $this->createContractFee(); // 50.00 по умолчанию

        try {
            $contract = \DB::transaction(function () use ($request, $partnerId, $student, $fee) {
                // 1) Блокируем строку партнёра до конца транзакции
                /** @var Partner $partner */
//                $partner = Partner::whereKey($partnerId)->lockForUpdate()->firstOrFail();
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

                $path = $request->file('pdf')->store('documents/'.date('Y/m'));
                $sha  = hash_file('sha256', \Storage::path($path));

                // 4) Создаём договор
                $contract = Contract::create([
                    'school_id'       => $partnerId,
                    'user_id'         => $student->id,
                    'group_id'        => $groupId,
                    'source_pdf_path' => $path,
                    'source_sha256'   => $sha,
                    'status'          => Contract::STATUS_DRAFT,
                    'provider'        => 'podpislon',
                ]);

                // 5) Логируем события: списание и создание
                ContractEvent::create([
                    'contract_id'  => $contract->id,
                    'type'         => 'wallet_debited',
                    'payload_json' => json_encode([
                        'amount'   => number_format($fee, 2, '.', ''),
                        'currency' => 'RUB',
                        'partner_id' => $partnerId,
                        'balance_after' => number_format($partner->wallet_balance, 2, '.', ''),
                    ], JSON_UNESCAPED_UNICODE),
                ]);

                ContractEvent::create([
                    'contract_id'  => $contract->id,
                    'type'         => 'created',
                    'payload_json' => null,
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
            ->with('success', 'Договор создан. С баланса списано 50 ₽. Теперь можно отправить на подпись.');
    }



    // ------- AJAX: поиск учеников текущего партнёра для Select2 -------

    public function usersSearch(Request $request)
    {
        $q         = trim((string)$request->get('q', ''));
        $partnerId = $this->currentPartnerId();

        \Log::debug('[usersSearch] start', [
            'auth_user_id' => \Illuminate\Support\Facades\Auth::id(),
            'partnerId'    => $partnerId,
            'q'            => $q,
        ]);

        $users = \App\Models\User::query()
            ->when($partnerId, fn($qq) => $qq->where('users.partner_id', $partnerId))
        ->where('users.is_enabled', 1)
        ->leftJoin('teams', 'teams.id', '=', 'users.team_id')
        ->when($q !== '', function ($qq) use ($q) {
            $qq->where(function ($w) use ($q) {
                $w->where('users.name',     'like', "%{$q}%")
                    ->orWhere('users.lastname','like', "%{$q}%")   // ← поиск по фамилии
                    ->orWhere('users.phone',   'like', "%{$q}%")
                    ->orWhere('users.email',   'like', "%{$q}%");
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

    \Log::debug('[usersSearch] done', ['count' => $users->count()]);

    $results = $users->map(function ($u) {
        $fullname = trim($u->name . ' ' . ($u->lastname ?? '')); // Имя Фамилия
        return [
            'id'         => $u->id,
            'text'       => $fullname,         // ← Select2 будет показывать это
            'name'       => $u->name,          // на будущее
            'lastname'   => $u->lastname,      // на будущее
            'team_id'    => $u->team_id,
            'team_title' => $u->team_title,
        ];
    });

    return response()->json(['results' => $results]);
}

// ------- AJAX: вернуть группу(ы) выбранного ученика -------
    public function userGroup(Request $request)
    {
        $userId    = (int)$request->get('user_id');
        $partnerId = $this->currentPartnerId();

        \Log::debug('[userGroup] start', ['userId' => $userId, 'partnerId' => $partnerId]);

        abort_unless($partnerId, 403, 'Партнёр не выбран.');

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
                ->select('groups.id','groups.title')
                ->when(function ($q) {
                    // если есть pivot с флагом активности — оставь; иначе убери строку ниже
                }, function ($q) {
                    $q->wherePivot('is_active', 1);
                })
                ->orderBy('groups.title')
                ->get()
                ->map(fn($g)=>['id'=>$g->id,'title'=>$g->title])
            ->values()
                ->all();
    }

        \Log::debug('[userGroup] done', ['groups_count' => count($groups)]);
        return response()->json(['groups' => $groups]);
    }

    public function index(Request $request)
    {
        $q = \App\Models\Contract::query()
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

    public function show(Contract $contract)
    {
        $events   = $contract->events()->orderBy('id','desc')->get();
        $requests = $contract->signRequests()->orderBy('id','desc')->get();

        // Подтягиваем данные ученика + название группы (teams.title)
        $student   = \App\Models\User::select('id','name','lastname','phone','email','team_id')
            ->find($contract->user_id);

        $teamTitle = null;
        if ($student && $student->team_id) {
            $teamTitle = \Illuminate\Support\Facades\DB::table('teams')
                ->where('id', $student->team_id)
                ->value('title');
        }

        return view('contracts.show', compact('contract','events','requests','student','teamTitle'));
    }

    public function downloadOriginal(Contract $contract)
    {
        return Storage::download($contract->source_pdf_path, 'contract-'.$contract->id.'.pdf');
    }

    public function downloadSigned(Contract $contract)
    {
        abort_unless($contract->signed_pdf_path, 404);
        return Storage::download($contract->signed_pdf_path, 'contract-'.$contract->id.'-signed.pdf');
    }

    public function send(Contract $contract, Request $request, SignatureProvider $provider): \Illuminate\Http\JsonResponse
    {
        \Log::info('[contracts.send] start', [
            'contract_id' => $contract->id,
            'payload'     => $request->only('signer_name','signer_phone','ttl_hours'),
        ]);

        // --- валидация
        $validated = $request->validate([
            'signer_name'  => ['nullable','string','max:255'],
            'signer_phone' => ['required','string','max:32'],
            'ttl_hours'    => ['nullable','integer','min:1','max:168'],
        ]);

// НОРМАЛИЗАЦИЯ ТЕЛЕФОНА (RU): к 11 цифрам, ведущая 7
        $raw = (string) $validated['signer_phone'];
        $digits = preg_replace('/\D+/', '', $raw) ?: '';

        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            // ввели 10 цифр (мобильный) -> делаем 7XXXXXXXXXX
            $digits = '7' . $digits;
        } elseif (strlen($digits) === 11 && $digits[0] === '8') {
            // 8XXXXXXXXXX -> 7XXXXXXXXXX
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
            // Подпислон: status=15 и/или текст «Отправлен»
            return $code === 15 || str_contains($text, 'отправлен') || str_contains($text, 'sent');
        };

        $fetchDoc = function () use ($contract) {
            /** @var \App\Services\Signatures\Providers\PodpislonProvider $pod */
            $pod  = app(\App\Services\Signatures\Providers\PodpislonProvider::class);
            if (!$contract->provider_doc_id) return null;
            $list = $pod->list([(int)$contract->provider_doc_id], [], 1, true);
            return $list['items'][0] ?? null;
        };

        $pollForSent = function (callable $fetch) use ($isSentByProvider): ?array {
            // до 3 попыток получить "Отправлен" с небольшим ожиданием
            for ($i=0; $i<3; $i++) {
                $doc = $fetch();
                if ($isSentByProvider($doc)) return $doc;
                usleep(300_000); // 300 мс
        }
            return null;
        };

        $signingLinks = function () use ($contract): array {
            try {
                /** @var \App\Services\Signatures\Providers\PodpislonProvider $pod */
                $pod   = app(\App\Services\Signatures\Providers\PodpislonProvider::class);
                return $pod->getSigningLinks($contract);
            } catch (\Throwable $e) {
                return [];
            }
        };

        // Всегда пишем запись об отправке в историю (и для resend тоже)
        $sr = new \App\Models\ContractSignRequest([
            'signer_name'  => $validated['signer_name'] ?? null,
            'signer_phone' => $phone,
            'ttl_hours'    => $validated['ttl_hours'] ?? 72,
            'status'       => 'created',
        ]);
        $contract->signRequests()->save($sr);

        // ===== РЕСЕНД: документ уже существует у провайдера — НЕ создаём новый
        if ($contract->provider === 'podpislon' && $contract->provider_doc_id) {
            try {
                /** @var \App\Services\Signatures\Providers\PodpislonProvider $pod */
                $pod = app(\App\Services\Signatures\Providers\PodpislonProvider::class);
                $res = $pod->resendForContract($contract); // официальный ресенд

                // Подтверждаем фактическую отправку у провайдера
                $doc = $pollForSent($fetchDoc);

                if ($doc) {
                    $sr->status = 'sent'; $sr->save();
                    // контракт мог быть в другом статусе → обновим на sent, если ещё не signed/opened и т.п.
                    if (!in_array($contract->status, [
                        \App\Models\Contract::STATUS_SIGNED,
                        \App\Models\Contract::STATUS_OPENED,
                    ], true)) {
                        $contract->status = \App\Models\Contract::STATUS_SENT;
                        $contract->save();
                    }

                    // Событие "resend" — ОК (НЕ пишем "sent" здесь, чтобы не дублировать с провайдером)
                    ContractEvent::create([
                        'contract_id'  => $contract->id,
                        'type'         => 'resend',
                        'payload_json' => json_encode(['res'=>$res,'doc'=>$doc], JSON_UNESCAPED_UNICODE),
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'SMS отправлена',
                        'status'  => 'sent',
                    ], 200);
                }

                // Не подтвердили отправку — ошибка
                $sr->status = 'failed'; $sr->save();
                $links = $signingLinks();
                ContractEvent::create([
                    'contract_id'  => $contract->id,
                    'type'         => 'resend_failed',
                    'payload_json' => json_encode(['res'=>$res,'links'=>$links], JSON_UNESCAPED_UNICODE),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Провайдер не подтвердил отправку SMS.',
                    'code'    => 'resend_not_sent',
                    'links'   => $links,
                ], 422);

            } catch (\Throwable $e) {
                $sr->status = 'failed'; $sr->save();
                $links = $signingLinks();
                ContractEvent::create([
                    'contract_id'  => $contract->id,
                    'type'         => 'resend_failed',
                    'payload_json' => json_encode(['error'=>$e->getMessage(),'links'=>$links], JSON_UNESCAPED_UNICODE),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка повторной отправки: '.$e->getMessage(),
                    'code'    => 'resend_exception',
                    'links'   => $links,
                ], 422);
            }
        }

        // ===== ПЕРВАЯ ОТПРАВКА: создаём документ у провайдера
        try {
            $res = $provider->send($contract, $sr);

            // Подтверждаем фактическую отправку у провайдера
            $doc = $pollForSent($fetchDoc);

            if ($doc) {
                $sr->status = 'sent'; $sr->save();
                $contract->status = \App\Models\Contract::STATUS_SENT; $contract->save();

                // ВАЖНО: НЕ пишем здесь событие "sent", его пишет провайдер (чтобы не было дубля)
                \Log::info('[contracts.send] ok', [
                    'contract_id'     => $contract->id,
                    'provider_doc_id' => $contract->provider_doc_id,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'SMS отправлена',
                    'status'  => 'sent',
                ], 200);
            }

            // провайдер не подтвердил «отправлено» — ошибка
            $sr->status = 'failed'; $sr->save();
            $contract->status = \App\Models\Contract::STATUS_FAILED; $contract->save();

            $links = $signingLinks();
            ContractEvent::create([
                'contract_id'  => $contract->id,
                'type'         => 'failed',
                'payload_json' => json_encode(['res'=>$res,'links'=>$links], JSON_UNESCAPED_UNICODE),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Провайдер не подтвердил отправку SMS.',
                'code'    => 'send_not_sent',
                'links'   => $links,
            ], 422);

        } catch (\Throwable $e) {
            $sr->status = 'failed'; $sr->save();
            $contract->status = \App\Models\Contract::STATUS_FAILED; $contract->save();

            ContractEvent::create([
                'contract_id'  => $contract->id,
                'type'         => 'failed',
                'payload_json' => json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE),
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
            'url'     => $url,
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
            'status'   => $resp->status(),
            'ok'       => $resp->ok(),
            'ms'       => $elapsed,
            'resp_hdr' => $this->maskHeaders($resp->headers()),
            'body'     => $this->clip($resp->body()),
            'curl'     => $this->clip($curlDebug, 4000),
        ]);

        if (!$resp->ok()) {
            return ['ok' => false, 'error' => 'HTTP '.$resp->status().' '.$this->clip($resp->body())];
        }

        $j  = $this->safeJson($resp);
        $ok = (bool)($j['status'] ?? $j['ok'] ?? $j['success'] ?? false);

        if ($ok) {
            return ['ok' => true, 'data' => $j];
        }
        return ['ok' => false, 'error' => (string)($j['message'] ?? 'Resend failed'), 'data'=>$j];
    }

    public function revoke(Contract $contract, SignatureProvider $provider)
    {
        try {
            $provider->revoke($contract);

            $contract->status = Contract::STATUS_REVOKED;
            $contract->save();

            ContractEvent::create([
                'contract_id' => $contract->id,
                'type'        => 'revoked',
                'payload_json'=> null,
            ]);

            return response()->json(['message'=>'Подписание отозвано','status'=>'revoked']);
        } catch (\Throwable $e) {
            ContractEvent::create([
                'contract_id' => $contract->id,
                'type'        => 'failed',
                'payload_json'=> json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE),
            ]);

            return response()->json(['message'=>$e->getMessage()], 422);
        }
    }

    public function status(Contract $contract, SignatureProvider $provider)
    {
        try {
            $data = $provider->getStatus($contract);
            $status = $this->mapProviderStatus($data['status'] ?? null);

            if ($status && $status !== $contract->status) {
                $contract->status = $status;
                $contract->save();

                ContractEvent::create([
                    'contract_id' => $contract->id,
                    'type'        => 'status_sync',
                    'payload_json'=> json_encode($data, JSON_UNESCAPED_UNICODE),
                ]);

                if ($status === Contract::STATUS_SIGNED && !$contract->signed_pdf_path) {
                    $this->downloadAndAttachSigned($contract, $provider);
                }
            }

            return response()->json(['status' => $contract->status, 'raw'=>$data]);
        } catch (\Throwable $e) {
            return response()->json(['message'=>$e->getMessage()], 422);
        }
    }

    protected function mapProviderStatus(?string $s): ?string
    {
        return match($s) {
        'sent'    => Contract::STATUS_SENT,
            'opened'  => Contract::STATUS_OPENED,
            'signed'  => Contract::STATUS_SIGNED,
            'expired' => Contract::STATUS_EXPIRED,
            'revoked' => Contract::STATUS_REVOKED,
            'failed'  => Contract::STATUS_FAILED,
            default   => null,
        };
    }

    protected function downloadAndAttachSigned(Contract $contract, SignatureProvider $provider): void
    {
        $file = $provider->downloadSigned($contract);
        $path = 'documents/'.date('Y/m').'/'.$file['filename'];
        Storage::put($path, $file['content']);
        $contract->signed_pdf_path = $path;
        $contract->signed_at = now();
        $contract->save();

        ContractEvent::create([
            'contract_id' => $contract->id,
            'type'        => 'signed_pdf_saved',
            'payload_json'=> json_encode(['path'=>$path], JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function sendEmail2(Contract $contract, \Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'email' => ['required','email']
        ]);

        try {
            $to   = $validated['email'];
            $path = $contract->source_pdf_path ? Storage::path($contract->source_pdf_path) : null;

            // простое письмо с вложением оригинального PDF (если он есть)
            Mail::raw('Договор во вложении.', function ($message) use ($to, $contract, $path) {
                $message->to($to)
                    ->subject('Договор #'.$contract->id);
                if ($path && is_file($path)) {
                    $message->attach($path, [
                        'as'   => 'contract-'.$contract->id.'.pdf',
                        'mime' => 'application/pdf',
                    ]);
                }
            });

            ContractEvent::create([
                'contract_id'  => $contract->id,
                'type'         => 'email_sent',
                'payload_json' => json_encode(['to' => $to], JSON_UNESCAPED_UNICODE),
            ]);

            return response()->json(['message' => 'Отправлено на email']);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function sendEmail(Contract $contract, Request $request)
    {
        $validated = $request->validate([
            'email'  => ['required','email'],
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
                $subject    = 'Подписанный договор #' . $contract->id;
                $body       = 'Подписанный договор во вложении.';
            } else {
                if (!$contract->source_pdf_path || !is_file(Storage::path($contract->source_pdf_path))) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Исходный файл не найден.',
                    ], 422);
                }
                $attachPath = Storage::path($contract->source_pdf_path);
                $attachName = 'contract-' . $contract->id . '.pdf';
                $subject    = 'Договор #' . $contract->id;
                $body       = 'Договор во вложении.';
            }

            Mail::raw($body, function ($message) use ($to, $subject, $attachPath, $attachName) {
                $message->to($to)->subject($subject);
                $message->attach($attachPath, [
                    'as'   => $attachName,
                    'mime' => 'application/pdf',
                ]);
            });

            ContractEvent::create([
                'contract_id'  => $contract->id,
                'type'         => $sendSigned ? 'email_signed_sent' : 'email_sent',
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
        return (float) (config('billing.contract_create_fee') ?? 50.00);
    }


    public function checkBalance(Request $request)
    {
        $partnerId = $this->currentPartnerId();
        abort_unless($partnerId, 403, 'Партнёр не выбран.');

        $fee = (float) (config('billing.contract_create_fee') ?? 50.00);

        $balance = \App\Models\Partner::whereKey($partnerId)->value('wallet_balance');
        if ($balance === null) {
            return response()->json([
                'ok'      => false,
                'message' => 'Партнёр не найден.',
            ], 404);
        }

        if ((float)$balance >= $fee) {
            return response()->json([
                'ok'      => true,
                'balance' => (float)$balance,
                'fee'     => $fee,
            ]);
        }

        return response()->json([
            'ok'      => false,
            'message' => 'Недостаточно средств для создания договора.',
            'balance' => (float)$balance,
            'fee'     => $fee,
        ], 422);
    }

}
