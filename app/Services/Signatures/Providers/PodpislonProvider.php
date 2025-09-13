<?php

namespace App\Services\Signatures\Providers;

use App\Models\Contract;
use App\Models\ContractEvent;
use App\Models\ContractSignRequest;
use App\Services\Signatures\SignatureProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use App\Models\User;



class PodpislonProvider implements SignatureProvider
{
    protected string $baseUrl;
    protected string $apiKey;
    protected bool $httpDebug;
    protected string $uploadStrategy;


    public function __construct()
    {
        $this->baseUrl = rtrim((string)config('services.podpislon.base_url'), '/');
        $this->apiKey = (string)config('services.podpislon.key');
        $this->httpDebug = (bool)config('services.podpislon.http_debug', false); // <— ДОБАВИТЬ
        $this->uploadStrategy = (string)config('services.podpislon.upload_strategy', 'auto'); // auto|multipart|json
    }


    protected function headers(): array
    {
        return [
            'X-Api-Key' => $this->apiKey,
            'Accept' => 'application/json',
            // Content-Type ставим точечно в вызовах (multipart/json/form)
        ];
    }

    protected function redactKey(?string $key): string
    {
        if (!$key) return 'EMPTY';
        return substr($key, 0, 6) . '…' . substr($key, -4);
    }


    public function revoke(Contract $contract): void
    {
        // Подпислон API не поддерживает отзыв подписи.
        $this->logEvent($contract, 'revoke_not_supported', [
            'provider' => 'podpislon',
        ]);

        throw new LogicException('Подпислон: отзыв подписи не поддерживается API.');
        // Если не хочешь ронять поток:
        // Log::warning('Подпислон: revoke не поддерживается API', ['contract_id' => $contract->id]);
        // return;
    }


    public function send(Contract $contract, ContractSignRequest $request): array
    {
        if (!$this->baseUrl || !$this->apiKey) {
            throw new \RuntimeException('PODPISLON: пустые base_url или api_key (.env).');
        }

        // 0) Префлайт, чтобы сразу увидеть 401/сеть
        try {
            $infoResp = Http::withHeaders($this->headers())
                ->withOptions([
                    'on_stats' => function ($s) use ($contract) {
                        Log::debug('PODPISLON: GET /get-info stats', [
                            'contract_id' => $contract->id,
                            'time_ms'     => (int)($s->getTransferTime() * 1000),
                            'url'         => (string)$s->getEffectiveUri(),
                        ]);
                    },
                ])->get(rtrim($this->baseUrl,'/').'/get-info');

            Log::info('PODPISLON: GET /get-info', [
                'status' => $infoResp->status(),
                'ok'     => $infoResp->ok(),
                'body'   => $this->clip($infoResp->body()),
            ]);
        } catch (\Throwable $e) {
            Log::warning('PODPISLON: /get-info preflight error', ['error' => $e->getMessage()]);
        }

        // 1) Файл
        $filePath = Storage::path($contract->source_pdf_path);
        if (!is_file($filePath)) {
            throw new \RuntimeException("PODPISLON: файл не найден: {$filePath}");
        }
        $fileSize = filesize($filePath) ?: 0;

        // 2) Контакт — ИМЯ и ФАМИЛИЯ строго из БД users
        $user = null;
        try {
            if ($contract->user_id) {
                $user = \App\Models\User::select('id','name','lastname','phone')->find($contract->user_id);
            }
        } catch (\Throwable $e) {
            Log::warning('PODPISLON: cannot load user', [
                'contract_id' => $contract->id,
                'user_id'     => $contract->user_id,
                'error'       => $e->getMessage(),
            ]);
        }

        $firstName   = $user?->name ?? 'Подписант';
        $lastName    = $user?->lastname ?? 'Безфамильный';
        $signerPhone = $request->signer_phone; // телефон из формы

        $contact = [
            'name'        => $firstName,
            'last_name'   => $lastName,   // обязательное поле в Подпислоне
            'second_name' => '',
            'phone'       => $signerPhone,
        ];

        $url = rtrim($this->baseUrl, '/').'/add-document';

        Log::info('PODPISLON: add-document prepare', [
            'contract_id'   => $contract->id,
            'url'           => $url,
            'file_exists'   => true,
            'file_size'     => $fileSize,
            'file_sha256'   => $contract->source_sha256,
            'contact_name'  => $contact['name'],
            'contact_last'  => $contact['last_name'],
            'contact_phone' => $contact['phone'],
            'headers'       => array_keys($this->headers()),
        ]);

        // 3) Попытка №1 — PUT + multipart (имя поля файла строго "file")
        try {
            $req = Http::withHeaders($this->headers())
                ->retry(2, 500)
                ->asMultipart()
                ->attach('file', fopen($filePath, 'r'), basename($filePath));

            if ($this->httpDebug ?? false) {
                $req = $req->withOptions([
                    'debug'    => true,
                    'on_stats' => fn($s) => Log::debug('PODPISLON: add-document multipart stats', [
                        'time_ms' => (int)($s->getTransferTime() * 1000),
                        'url'     => (string)$s->getEffectiveUri(),
                    ]),
                ]);
            }

            $payload = [
                ['name'=>'name',        'contents'=>$contact['name']],
                ['name'=>'last_name',   'contents'=>$contact['last_name']],
                ['name'=>'second_name', 'contents'=>$contact['second_name']],
                ['name'=>'phone',       'contents'=>$contact['phone']],
                ['name'=>'agreement',   'contents'=>'Y'],
            ];

            $resp = $req->put($url, $payload);

            Log::info('PODPISLON: add-document (multipart) response', [
                'status'  => $resp->status(),
                'ok'      => $resp->ok(),
                'headers' => $resp->headers(),
                'body'    => $this->clip($resp->body()),
            ]);

            if ($resp->ok()) {
                $data   = $this->safeJson($resp);
                $result = $data['result'] ?? null;

                if (is_int($result)) {
                    $contract->provider_doc_id = $result;
                } elseif (is_array($result)) {
                    $firstId = is_int(reset($result)) ? reset($result) : ($result['id'] ?? null);
                    if ($firstId) $contract->provider_doc_id = $firstId;
                }
                if (!$contract->provider_doc_id) {
                    Log::warning('PODPISLON: add-document ok, но id не распознан', ['result'=>$result,'json'=>$data]);
                }
                $contract->save();

                $request->provider_request_id = (string) ($contract->provider_doc_id ?: Str::uuid());
                $request->status = 'sent';
                $request->save();

                $this->logEvent($contract, 'sent', [
                    'provider_doc_id'     => $contract->provider_doc_id,
                    'provider_request_id' => $request->provider_request_id,
                    'resp'                => $data,
                ]);

                return [
                    'provider_doc_id'     => $contract->provider_doc_id,
                    'provider_request_id' => $request->provider_request_id,
                    'raw'                 => $data,
                ];
            }

            // если не ok — падаем в catch и сделаем fallback
            throw new \RuntimeException("HTTP {$resp->status()} ".$this->clip($resp->body()));
        } catch (\Throwable $e) {
            Log::warning('PODPISLON: add-document (multipart) failed, will fallback to JSON', [
                'contract_id' => $contract->id,
                'error'       => $e->getMessage(),
            ]);
        }

        // 4) Попытка №2 — PUT + application/json (base64)
        $b64   = base64_encode(file_get_contents($filePath));
        $fname = basename($filePath);

        $json = [
            'name'        => $contact['name'],
            'last_name'   => $contact['last_name'],
            'second_name' => $contact['second_name'],
            'phone'       => $contact['phone'],
            'agreement'   => 'Y',
            'file'        => [$b64],
            'fileName'    => [$fname],
        ];

        $req2 = Http::withHeaders($this->headers() + ['Content-Type' => 'application/json'])
            ->retry(2, 500);

        if ($this->httpDebug ?? false) {
            $req2 = $req2->withOptions([
                'debug'    => true,
                'on_stats' => fn($s) => Log::debug('PODPISLON: add-document json stats', [
                    'time_ms' => (int)($s->getTransferTime() * 1000),
                    'url'     => (string)$s->getEffectiveUri(),
                ]),
            ]);
        }

        Log::info('PODPISLON: add-document (json) payload', [
            'file_b64_len' => strlen($b64),
            'file_name'    => $fname,
            'name'         => $json['name'],
            'last_name'    => $json['last_name'],
            'phone'        => $json['phone'],
        ]);

        $resp2 = $req2->put($url, $json);

        Log::info('PODPISLON: add-document (json) response', [
            'status'  => $resp2->status(),
            'ok'      => $resp2->ok(),
            'headers' => $resp2->headers(),
            'body'    => $this->clip($resp2->body()),
        ]);

        if (!$resp2->ok()) {
            throw new \RuntimeException('Ошибка /add-document: HTTP '.$resp2->status().' '.$this->clip($resp2->body()));
        }

        $data2   = $this->safeJson($resp2);
        $result2 = $data2['result'] ?? null;

        if (is_int($result2)) {
            $contract->provider_doc_id = $result2;
        } elseif (is_array($result2)) {
            $firstId = is_int(reset($result2)) ? reset($result2) : ($result2['id'] ?? null);
            if ($firstId) $contract->provider_doc_id = $firstId;
        }
        if (!$contract->provider_doc_id) {
            Log::warning('PODPISLON: add-document ok, но id не распознан (json)', ['result'=>$result2,'json'=>$data2]);
        }
        $contract->save();

        $request->provider_request_id = (string) ($contract->provider_doc_id ?: Str::uuid());
        $request->status = 'sent';
        $request->save();

        $this->logEvent($contract, 'sent', [
            'provider_doc_id'     => $contract->provider_doc_id,
            'provider_request_id' => $request->provider_request_id,
            'resp'                => $data2,
        ]);

        return [
            'provider_doc_id'     => $contract->provider_doc_id,
            'provider_request_id' => $request->provider_request_id,
            'raw'                 => $data2,
        ];
    }
 

    public function getStatus(Contract $contract): array
    {
        if (!$contract->provider_doc_id) return ['status' => 'draft'];

        $url = rtrim($this->baseUrl, '/') . '/';
        $resp = Http::withHeaders($this->headers())
            ->retry(2, 500)
            ->asJson()
            ->post($url, [
                'ids' => [(int)$contract->provider_doc_id],
            ]);

        Log::info('PODPISLON: POST / (getStatus by id)', [
            'status' => $resp->status(),
            'ok' => $resp->ok(),
            'body' => $this->clip($resp->body())
        ]);

        if (!$resp->ok()) {
            $this->logEvent($contract, 'failed_status', ['http_status' => $resp->status(), 'body' => $resp->json() ?: $resp->body()]);
            throw new \RuntimeException('Ошибка статуса: HTTP ' . $resp->status() . ' ' . $this->clip($resp->body()));
        }

        $arr = $this->safeJson($resp);
        // Ожидаем массив документов; берём первый
        $doc = $arr[0] ?? null;
        return $doc ?: ['raw' => $arr];
    }


    public function downloadSigned(Contract $contract): array
    {
        if (!$contract->provider_doc_id) {
            throw new \RuntimeException('Нет provider_doc_id');
        }

        $url = rtrim($this->baseUrl, '/') . '/get-file';
        $resp = Http::withHeaders($this->headers())
            ->retry(2, 500)
            ->asForm() // application/x-www-form-urlencoded
            ->post($url, ['id' => (int)$contract->provider_doc_id]);

        Log::info('PODPISLON: POST /get-file', [
            'status' => $resp->status(),
            'ok' => $resp->ok(),
            'len' => strlen($resp->body() ?? '')
        ]);

        if (!$resp->ok()) {
            $this->logEvent($contract, 'failed_download', ['http_status' => $resp->status(), 'body' => $resp->json() ?: $resp->body()]);
            throw new \RuntimeException('Ошибка /get-file: HTTP ' . $resp->status() . ' ' . $this->clip($resp->body()));
        }

        $j = $this->safeJson($resp);
        $b64 = $j['result'] ?? null;
        if (!$b64) {
            throw new \RuntimeException('В ответе /get-file нет поля result (base64).');
        }

        $binary = base64_decode($b64, true);
        if ($binary === false) {
            throw new \RuntimeException('Не удалось декодировать base64 из /get-file.');
        }

        $filename = 'contract-' . $contract->id . '-signed.pdf';
        return ['filename' => $filename, 'content' => $binary];
    }

    /**
     * Базовый PendingRequest с таймаутами и cURL debug.
     * Возвращает массив: [PendingRequest $http, resource $debugStream]
     */
    protected function makeHttpWithDebug(): array
    {
        $debug = fopen('php://temp', 'w+');

        $http = Http::withHeaders($this->headers() + [
                'User-Agent' => 'Kidslink/Podpislon/1.0',
            ])
            ->timeout(20)
            ->connectTimeout(5)
            ->retry(2, 500) // 3 попытки (1 + 2 ретрая)
            ->withOptions([
                'debug' => $debug, // cURL отладка
                // 'http_errors' => false, // мы сами обрабатываем коды
            ]);

        return [$http, $debug];
    }

    /** Маскируем чувствительные заголовки в логах. */
    protected function maskHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $k => $v) {
            $val = is_array($v) ? implode(';', $v) : (string)$v;
            if (stripos($k, 'X-Api-Key') !== false) {
                $val = $this->redactKey($this->apiKey);
            }
            $out[$k] = $val;
        }
        return $out;
    }


    /* ===== helpers ===== */

    protected function logEvent(Contract $contract, string $type, $payload): void
    {
        try {
            ContractEvent::create([
                'contract_id' => $contract->id,
                'type' => $type,
                'payload_json' => $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
            ]);
        } catch (\Throwable $e) {
            Log::error('PODPISLON: не удалось записать ContractEvent', [
                'contract_id' => $contract->id,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** Безопасное получение JSON (если провайдер вернул не-JSON — положим строку). */
    protected function safeJson($resp): array
    {
        try {
            $j = $resp->json();
            if (is_array($j)) return $j;
        } catch (\Throwable $_) {
        }
        return ['raw' => $resp->body()];
    }

    /** Обрезка длинных тел для логов. */
    protected function clip(?string $s, int $len = 1000): string
    {
        if ($s === null) return '';
        if (mb_strlen($s) <= $len) return $s;
        return mb_substr($s, 0, $len) . '…[clipped]';
    }


    public function list(array $ids = [], array $filter = [], int $page = 1, bool $withPackage = true): array
    {
        $url = rtrim($this->baseUrl, '/') . '/';
        $query = [];
        if ($withPackage) $query['expand'] = 'package';
        if ($page > 1) $query['page'] = (string)$page;

        $rid = 'LIST-' . Str::uuid()->toString();
        [$http, $debug] = $this->makeHttpWithDebug();

        $t0 = microtime(true);
        Log::info('PODPISLON: LIST start', [
            'rid' => $rid,
            'url' => $url,
            'query' => $query,
            'payload' => ['ids' => $ids ?: null, 'filter' => $filter ?: null],
            'headers' => array_keys($this->headers()),
        ]);

        $resp = $http
            ->withQueryParameters($query)
            ->asJson()
            ->post($url, [
                'ids' => $ids ?: null,
                'filter' => $filter ?: null,
            ]);

        $elapsed = round((microtime(true) - $t0) * 1000);

        rewind($debug);
        $curlDebug = stream_get_contents($debug);
        fclose($debug);

        Log::info('PODPISLON: LIST response', [
            'rid' => $rid,
            'status' => $resp->status(),
            'ok' => $resp->ok(),
            'ms' => $elapsed,
            'resp_hdr' => $this->maskHeaders($resp->headers()),
            'body' => $this->clip($resp->body()),
            'curl' => $this->clip($curlDebug, 4000),
        ]);

        if (!$resp->ok()) {
            throw new \RuntimeException('Ошибка списка: HTTP ' . $resp->status() . ' ' . $this->clip($resp->body()));
        }

        return [
            'items' => $this->safeJson($resp),
            'meta' => [
                'x-pagination-current-page' => $resp->header('x-pagination-current-page'),
                'x-pagination-page-count' => $resp->header('x-pagination-page-count'),
                'x-pagination-per-page' => $resp->header('x-pagination-per-page'),
                'x-pagination-total-count' => $resp->header('x-pagination-total-count'),
            ],
        ];
    }


    public function getCompanyInfo(): array
    {
        $resp = Http::withHeaders($this->headers())->retry(2, 500)->get(rtrim($this->baseUrl, '/') . '/get-info');
        if (!$resp->ok()) throw new \RuntimeException('Ошибка /get-info: HTTP ' . $resp->status());
        return $this->safeJson($resp);
    }

//    public function getPaySystems(): array
//    {
//        $resp = Http::withHeaders($this->headers())->retry(2, 500)->get(rtrim($this->baseUrl, '/') . '/pay-systems');
//        if (!$resp->ok()) throw new \RuntimeException('Ошибка /pay-systems: HTTP ' . $resp->status());
//        return $this->safeJson($resp);
//    }

//    public function revokeWithReason(Contract $contract, ?string $reason = null): void
//    {
//        $this->logEvent($contract, 'revoke_not_supported', [
//            'provider' => 'podpislon',
//            'reason' => $reason,
//        ]);
//        throw new LogicException('Подпислон: отзыв подписи не поддерживается API.');
//    }

    public function resendForContract(Contract $contract): array
    {
        if (!$contract->provider_doc_id) {
            throw new \RuntimeException('Нет provider_doc_id');
        }

        // Получаем документ с expand=package
        $list = $this->list([$contract->provider_doc_id], [], 1, true);
        $doc = $list['items'][0] ?? null;

        if (!$doc) {
            throw new \RuntimeException('Документ не найден у провайдера');
        }

        $package = $doc['package'] ?? null; // ВАЖНО: нужен package, а не id
        if (!$package) {
            throw new \RuntimeException('У документа нет package (expand=package не вернул значение)');
        }

        // Берём SID первого контакта, если есть
        $sid = null;
        if (!empty($doc['contacts']) && isset($doc['contacts'][0]['sid'])) {
            $sid = $doc['contacts'][0]['sid']; // это SID, а не телефон
        }

        return $this->resend($package, $sid);
    }


    /**
     * Возвращает массив ссылок на подписание из документа:
     * [
     *   ['sid' => '...', 'phone' => '...', 'link' => 'https://podpislon.ru/sign/...'],
     *   ...
     * ]
     */
    public function getSigningLinks(Contract $contract): array
    {
        if (!$contract->provider_doc_id) {
            throw new \RuntimeException('Нет provider_doc_id');
        }

        $rid = 'LINKS-' . Str::uuid()->toString();
        Log::info('PODPISLON: LINKS start', ['rid' => $rid, 'provider_doc_id' => $contract->provider_doc_id]);

        $list = $this->list([$contract->provider_doc_id], [], 1, true);
        $doc = $list['items'][0] ?? null;

        if (!$doc) {
            Log::warning('PODPISLON: LINKS doc not found', ['rid' => $rid]);
            return [];
        }

        $links = [];
        foreach (($doc['contacts'] ?? []) as $c) {
            $links[] = [
                'sid' => $c['sid'] ?? null,
                'phone' => $c['phone'] ?? null,
                'link' => $c['link'] ?? null,
            ];
        }

        Log::info('PODPISLON: LINKS done', ['rid' => $rid, 'count' => count($links)]);
        return $links;
    }


    public function resendSmart(Contract $contract): array
    {
        if (!$contract->provider_doc_id) {
            throw new \RuntimeException('Нет provider_doc_id');
        }

        $rid = 'RESENDSMART-' . Str::uuid()->toString();
        Log::info('PODPISLON: RESENDSMART start', ['rid' => $rid, 'provider_doc_id' => $contract->provider_doc_id]);

        // 1) достаём документ целиком (expand=package)
        $list = $this->list([$contract->provider_doc_id], [], 1, true);
        $doc = $list['items'][0] ?? null;

        if (!$doc) {
            throw new \RuntimeException('Документ не найден у провайдера');
        }

        $package = $doc['package'] ?? null; // ВАЖНО: нужен package, не id
        $sid = $doc['contacts'][0]['sid'] ?? null; // первый подписант, при необходимости выбери другой

        Log::info('PODPISLON: RESENDSMART data', [
            'rid' => $rid, 'package' => $package, 'sid' => $sid,
            'has_contacts' => !empty($doc['contacts']),
        ]);

        // 2) пробуем официальный ресенд
        $res = $this->resend($package, $sid);

        // Если ресенд прошёл — возвращаем как есть
        if (($res['ok'] ?? null) === true || empty($res['error'])) {
            Log::info('PODPISLON: RESENDSMART ok', ['rid' => $rid, 'res' => $res]);
            return $res;
        }

        // 3) Фолбэк — возвращаем ссылки
        $links = $this->getSigningLinks($contract);
        Log::warning('PODPISLON: RESENDSMART fallback to links', ['rid' => $rid, 'links' => $links]);

        return [
            'ok' => false,
            'error' => 'fallback_links',
            'signing_links' => $links,
        ];
    }


//    public function resend(Contract $contract, Request $request, SignatureProvider $provider)
//    {
//        \Log::info('[ContractsController@resend] start', [
//            'contract_id'     => $contract->id,
//            'provider'        => $contract->provider,
//            'provider_doc_id' => $contract->provider_doc_id,
//        ]);
//
//        try {
//            /** @var \App\Services\Signatures\Providers\PodpislonProvider $pod */
//            $pod = app(\App\Services\Signatures\Providers\PodpislonProvider::class);
//            $res = $pod->resendForContract($contract);
//
//            \Log::info('[ContractsController@resend] provider OK', [
//                'contract_id' => $contract->id,
//                'res'         => $res,
//            ]);
//
//            ContractEvent::create([
//                'contract_id'  => $contract->id,
//                'type'         => 'resend',
//                'payload_json' => json_encode($res, JSON_UNESCAPED_UNICODE),
//            ]);
//
//            return response()->json(['message' => 'Ссылка повторно отправлена', 'status' => 'resent']);
//        } catch (\Throwable $e) {
//            \Log::error('[ContractsController@resend] provider FAIL', [
//                'contract_id' => $contract->id,
//                'error'       => $e->getMessage(),
//                'trace'       => $e->getTraceAsString(),
//            ]);
//
//            ContractEvent::create([
//                'contract_id'  => $contract->id,
//                'type'         => 'resend_failed',
//                'payload_json' => json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE),
//            ]);
//
//            return response()->json(['message'=>$e->getMessage()], 422);
//        }
//    }


//    private function sendMultipart(string $url, string $filePath, array $contact)
//    {
//        $req = Http::withHeaders($this->headers())
//            ->retry(2, 500)
//            ->asMultipart()
//            // ИСПОЛЬЗУЕМ ИМЯ ПОЛЯ "file" (без []), т.к. у некоторых их конфигураций это критично
//            ->attach('file', fopen($filePath, 'r'), basename($filePath));
//
//        if ($this->httpDebug) {
//            $req = $req->withOptions([
//                'debug'    => true,
//                'on_stats' => fn($s) => Log::debug('PODPISLON: add-document multipart stats', [
//                    'time_ms' => (int)($s->getTransferTime() * 1000),
//                    'url'     => (string) $s->getEffectiveUri(),
//                ]),
//            ]);
//        }
//
//        $payload = [
//            ['name'=>'name',      'contents'=>$contact['name']],
//            ['name'=>'last_name', 'contents'=>$contact['last_name']],
//            ['name'=>'phone',     'contents'=>$contact['phone']],
//            ['name'=>'agreement', 'contents'=>'Y'],
//        ];
//
//        // Важно: метод PUT
//        return $req->put($url, $payload);
//    }
//
//    private function sendJsonBase64(string $url, string $filePath, Contract $contract, array $contact)
//    {
//        $b64   = base64_encode(file_get_contents($filePath));
//        $fname = basename($filePath);
//
//        $json = [
//            'name'      => $contact['name'],
//            'last_name' => $contact['last_name'],
//            'phone'     => $contact['phone'],
//            'agreement' => 'Y',
//            // По схеме: массивы file и fileName
//            'file'      => [$b64],
//            'fileName'  => [$fname],
//        ];
//
//        $req = Http::withHeaders($this->headers() + ['Content-Type'=>'application/json'])
//            ->retry(2, 500);
//
//        if ($this->httpDebug) {
//            $req = $req->withOptions([
//                'debug'    => true,
//                'on_stats' => fn($s) => Log::debug('PODPISLON: add-document json stats', [
//                    'time_ms' => (int)($s->getTransferTime() * 1000),
//                    'url'     => (string) $s->getEffectiveUri(),
//                ]),
//            ]);
//        }
//
//        Log::info('PODPISLON: add-document (json) payload', [
//            'file_b64_len' => strlen($b64),
//            'file_name'    => $fname,
//        ]);
//
//        // Важно: метод PUT и application/json
//        return $req->put($url, $json);
//    }


}


