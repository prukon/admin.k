<?php

namespace App\Http\Controllers;

use App\Models\Partner;
use App\Models\TinkoffPayout;
use App\Models\TinkoffPayment;
use App\Services\Tinkoff\SmRegisterClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TinkoffAdminPartnerController extends Controller
{
    /**
     * Страница партнёра.
     * Теперь, если в сессии выбран current_partner — показываем его,
     * игнорируя $id из URL. Иначе используем $id.
     */
    public function show($id)
    {
        $sessionPartnerId = session('current_partner');
        $effectiveId = $sessionPartnerId ?: $id;

        if ($sessionPartnerId && $sessionPartnerId !== (int)$id) {
            Log::info('[admin][show] session current_partner='.$sessionPartnerId.' (url id='.$id.') — показываю партнёра из сессии');
        }

        $partner = Partner::findOrFail($effectiveId);

        $waiting = TinkoffPayout::where('partner_id', $partner->id)
            ->whereIn('status', ['INITIATED', 'CREDIT_CHECKING'])
            ->count();

        $latestPayments = TinkoffPayment::where('partner_id', $partner->id)
            ->latest()->limit(20)->get();

        return view('tinkoff.partners.show', compact('partner', 'waiting', 'latestPayments'));
    }

    public function smRegister($id, Request $request, SmRegisterClient $sm)
    {
        $partner = Partner::findOrFail($id);

        $validated = $request->validate([
            'business_type'        => 'required|string|in:individual_entrepreneur,company,physical_person,non_commercial_organization',
            'title'                => 'required|string|max:255',
            'email'                => 'required|email',
            'tax_id'               => 'required|string|max:20',     // ИНН
            'registration_number'  => 'required|string|max:20',     // ОГРН/ОГРНИП
            'address'              => 'required|string|max:255',    // свободная строка улицы — мы почистим
            'city'                 => 'required|string|max:100',
            'zip'                  => 'required|string|max:20',

            'bank_name'            => 'required|string|max:255',
            'bank_bik'             => 'required|string|max:20',
            'bank_account'         => 'required|string|max:32',
            'sm_details_template'  => 'required|string|max:500',

            'phone'                => 'nullable|string|max:32',
            'website'              => 'nullable|url|max:255',       // сайт теперь из partners.website
            'kpp'                  => 'nullable|string|max:12',
        ]);

        // ---- helpers ----
        $normalizePhone = function (?string $raw): ?string {
            if (!$raw) return null;
            $d = preg_replace('/\D+/', '', $raw);
            if (!$d) return null;
            if (strlen($d) === 11 && ($d[0] === '7' || $d[0] === '8')) $d = '7'.substr($d,1);
            elseif (strlen($d) === 10) $d = '7'.$d;
            return '+'.$d;
        };
        $makeDescriptor = function (string $src): string {
            $map = ['А'=>'A','Б'=>'B','В'=>'V','Г'=>'G','Д'=>'D','Е'=>'E','Ё'=>'E','Ж'=>'ZH','З'=>'Z','И'=>'I','Й'=>'Y','К'=>'K','Л'=>'L','М'=>'M','Н'=>'N','О'=>'O','П'=>'P','Р'=>'R','С'=>'S','Т'=>'T','У'=>'U','Ф'=>'F','Х'=>'H','Ц'=>'C','Ч'=>'CH','Ш'=>'SH','Щ'=>'SCH','Ы'=>'Y','Э'=>'E','Ю'=>'YU','Я'=>'YA','Ь'=>'','Ъ'=>''];
            $map += array_change_key_case($map, CASE_LOWER);
            $s = strtoupper(strtr($src, $map));
            $s = preg_replace('/[^A-Z0-9 ._-]+/', '', $s) ?? '';
            $s = trim(preg_replace('/\s+/', ' ', $s) ?? '');
            if ($s === '') $s = 'KRUZHOK';
            if (strlen($s) > 14) $s = substr($s, 0, 14);
            return $s;
        };
        $extractCeo = function (string $title): array {
            $t = trim(preg_replace('/^ИП\s+/ui', '', $title));
            $parts = preg_split('/\s+/u', $t, -1, PREG_SPLIT_NO_EMPTY);
            $last  = $parts[0] ?? 'Иванов';
            $first = $parts[1] ?? 'Иван';
            $middle= $parts[2] ?? null;
            return [$first, $last, $middle];
        };
        $sanitizeStreet = function (string $raw, string $city): string {
            // убираем упоминания города из строки улицы
            $s = preg_replace('/\b(г\.?|город)\b[\s\.]*санкт[\s\-]*петербург\b/iu', '', $raw);
            $s = preg_replace('/\bсанкт[\s\-]*петербург\b/iu', '', $s);
            $s = preg_replace('/\b(спб|с\-пб)\b/iu', '', $s);

            // нормализация типа улицы и служебных сокращений
            $s = preg_replace('/\b(пр[\.\-]?\s*т|просп\.?|пр\-т)\b/iu', 'проспект', $s);
            $s = preg_replace('/корп\.?\s*\/\s*ст\.?/iu', 'к.', $s);
            $s = preg_replace('/корп\.?/iu', 'к.', $s);
            $s = preg_replace('/стр\.?/iu', 'стр.', $s);
            $s = preg_replace('/кв\.?\s*\/\s*оф\.?/iu', 'оф.', $s);
            $s = preg_replace('/кв\.?/iu', 'кв.', $s);
            $s = preg_replace('/оф\.?/iu', 'оф.', $s);

            // допустимые символы + нормализация пробелов/запятых
            $s = preg_replace('/[^0-9A-Za-zА-Яа-яЁё\s\.,]/u', '', $s);
            $s = preg_replace('/\s*,\s*/u', ', ', $s);
            $s = preg_replace('/\s+/u', ' ', $s);
            $s = trim($s, " ,");

            if ($s === '') $s = trim(preg_replace('/'.$city.'/iu', '', $raw));
            return $s;
        };

        // ---- подготовка данных ----
        $phone   = $normalizePhone($validated['phone'] ?? ($partner->phone ?? null));

        // billingDescriptor: из БД; если пусто — сгенерим из title и сохраним
        $bdFromDb = $partner->sms_name ?: null;
        $bd       = $bdFromDb ?: $makeDescriptor($validated['title']);

        $city    = preg_match('/^(\s*spb|\s*спб)$/iu', $validated['city']) ? 'Санкт-Петербург' : $validated['city'];
        $street  = $sanitizeStreet($validated['address'], $city);

        // KPP: для ИП/ФЛ/НКО — нули
        $kpp = $validated['kpp'] ?? $partner->kpp ?? null;
        if ($validated['business_type'] !== 'company') $kpp = '000000000';
        if (!$kpp) $kpp = '000000000';

        // сайт — из БД/конфига
        $siteUrl = $validated['website'] ?? $partner->website ?? config('app.url');

        // ОГРН/ОГРНИП — Integer
        $ogrnDigits = preg_replace('/\D+/', '', (string)$validated['registration_number']);
        $ogrn = $ogrnDigits !== '' ? (int)$ogrnDigits : null;

        // CEO: из БД или строим из title/phone
        $existingCeo = is_array($partner->ceo) ? $partner->ceo : null;
        if ($existingCeo && !empty($existingCeo['firstName']) && !empty($existingCeo['lastName'])) {
            $ceoFirst  = $existingCeo['firstName'];
            $ceoLast   = $existingCeo['lastName'];
            $ceoMiddle = $existingCeo['middleName'] ?? null;
            $ceoPhone  = $normalizePhone($existingCeo['phone'] ?? ($validated['phone'] ?? $partner->phone ?? null)) ?: '+70000000000';
        } else {
            [$ceoFirst, $ceoLast, $ceoMiddle] = $extractCeo($validated['title']);
            $ceoPhone = $normalizePhone($validated['phone'] ?? ($partner->phone ?? null)) ?: '+70000000000';
        }

        // ---- payload ----
        $payload = [
            'billingDescriptor' => $bd,
            'fullName'          => $validated['title'],
            'name'              => $validated['title'],
            'inn'               => (string)$validated['tax_id'],
            'kpp'               => (string)$kpp,
            'ogrn'              => $ogrn,
            'addresses' => [[
                'type'    => 'legal',
                'zip'     => (string)$validated['zip'],
                'country' => 'RUS',
                'city'    => $city,
                'street'  => $street,
            ]],
            'phones' => $phone ? [[
                'type'        => 'common',
                'phone'       => $phone,
                'description' => 'Контакт',
            ]] : [],
            'email'   => $validated['email'],
            'siteUrl' => $siteUrl,
            'bankAccount' => [
                'account'  => (string)$validated['bank_account'],
                'bankName' => $validated['bank_name'],
                'bik'      => (string)$validated['bank_bik'],
                'details'  => $validated['sm_details_template'],
            ],
            'ceo' => [
                'firstName'  => $ceoFirst,
                'lastName'   => $ceoLast,
                'middleName' => $ceoMiddle,
                'phone'      => $ceoPhone,
                'country'    => 'RUS',
            ],
        ];

        // чистка null/пустых
        $clean = function ($v) use (&$clean) {
            if (is_array($v)) {
                $o = [];
                foreach ($v as $k => $x) {
                    $cx = $clean($x);
                    if ($cx !== null && $cx !== '') $o[$k] = $cx;
                }
                return $o;
            }
            return $v;
        };
        $payload = $clean($payload);

        try {
            Log::channel('tinkoff')->info('[sm-register][payload] '.json_encode($payload, JSON_UNESCAPED_UNICODE));
            $response = $sm->register($payload);

            $shopCode = data_get($response, 'shopCode') ?? data_get($response, 'code') ?? data_get($response, 'id');
            $status   = data_get($response, 'status') ?? 'REGISTERED';

            // если в БД не было sms_name — сохраним сгенер
            $smsToSave = $partner->sms_name ?: $bd;

            $partner->fill([
                'tinkoff_partner_id'            => $shopCode,
                'sm_register_status'            => $status,
                'bank_name'                     => $validated['bank_name'],
                'bank_bik'                      => $validated['bank_bik'],
                'bank_account'                  => $validated['bank_account'],
                'sm_details_template'           => $validated['sm_details_template'],
                'bank_details_version'          => (int)($partner->bank_details_version ?? 0) + 1,
                'bank_details_last_updated_at'  => now(),
                'city'                          => $city,
                'zip'                           => (string)$validated['zip'],
                'phone'                         => $phone ?: $partner->phone,
                'website'                       => $siteUrl,
                'kpp'                           => $kpp,
                'sms_name'                      => $smsToSave,
                'ceo'                           => [
                    'firstName'  => $ceoFirst,
                    'lastName'   => $ceoLast,
                    'middleName' => $ceoMiddle,
                    'phone'      => $ceoPhone,
                ],
            ])->save();

            if ($request->ajax()) {
                return response()->json(['ok'=>true,'shopCode'=>$shopCode,'status'=>$status,'raw'=>$response]);
            }
            return back()->with('ok', "Партнёр зарегистрирован (shopCode: {$shopCode})");

        } catch (\Throwable $e) {
            Log::channel('tinkoff')->error('[sm-register][register] '.$e->getMessage());
            $msg = 'Ошибка регистрации: '.$e->getMessage();
            return $request->ajax()
                ? response()->json(['ok'=>false,'error'=>$msg], 422)
                : back()->withErrors(['sm'=>$msg]);
        }
    }

    public function smPatch($id, Request $request, SmRegisterClient $sm)
    {
        $partner = Partner::findOrFail($id);
        if (!$partner->tinkoff_partner_id) {
            return $request->ajax()
                ? response()->json(['ok'=>false,'error'=>'Сначала зарегистрируйте партнёра (нет PartnerId)'], 422)
                : back()->withErrors(['sm'=>'Сначала зарегистрируйте партнёра (нет PartnerId)']);
        }

        // те же поля, что и в регистрации
        $validated = $request->validate([
            'business_type'        => 'required|string|in:individual_entrepreneur,company,physical_person,non_commercial_organization',
            'title'                => 'required|string|max:255',
            'email'                => 'required|email',
            'tax_id'               => 'required|string|max:20',     // ИНН
            'registration_number'  => 'required|string|max:20',     // ОГРН/ОГРНИП
            'address'              => 'required|string|max:255',
            'city'                 => 'required|string|max:100',
            'zip'                  => 'required|string|max:20',

            'bank_name'            => 'required|string|max:255',
            'bank_bik'             => 'required|string|max:20',
            'bank_account'         => 'required|string|max:32',
            'sm_details_template'  => 'required|string|max:500',

            'phone'                => 'nullable|string|max:32',
            'website'              => 'nullable|url|max:255',
            'kpp'                  => 'nullable|string|max:12',
        ]);

        // helpers (короткие версии)
        $normalizePhone = fn (?string $raw) =>
        (!$raw || !($d=preg_replace('/\D+/','',$raw))) ? null :
            ('+'.(strlen($d)===11 && ($d[0]==='7'||$d[0]==='8') ? '7'.substr($d,1) : (strlen($d)===10 ? '7'.$d : $d)));

    $sanitizeStreet = function (string $raw, string $city): string {
        $s = preg_replace('/\b(г\.?|город)\b[\s\.]*санкт[\s\-]*петербург\b/iu', '', $raw);
        $s = preg_replace('/\bсанкт[\s\-]*петербург\b/iu', '', $s);
        $s = preg_replace('/\b(спб|с\-пб)\b/iu', '', $s);
        $s = preg_replace('/\b(пр[\.\-]?\s*т|просп\.?|пр\-т)\b/iu', 'проспект', $s);
        $s = preg_replace('/корп\.?\s*\/\s*ст\.?/iu', 'к.', $s);
        $s = preg_replace('/корп\.?/iu', 'к.', $s);
        $s = preg_replace('/стр\.?/iu', 'стр.', $s);
        $s = preg_replace('/кв\.?\s*\/\s*оф\.?/iu', 'оф.', $s);
        $s = preg_replace('/кв\.?/iu', 'кв.', $s);
        $s = preg_replace('/оф\.?/iu', 'оф.', $s);
        $s = preg_replace('/[^0-9A-Za-zА-Яа-яЁё\s\.,]/u', '', $s);
        $s = preg_replace('/\s*,\s*/u', ', ', $s);
        $s = preg_replace('/\s+/u', ' ', $s);
        $s = trim($s, " ,");
        if ($s === '') $s = trim(preg_replace('/'.$city.'/iu', '', $raw));
        return $s;
    };

    // подготовка данных (как в регистрации)
    $phone   = $normalizePhone($validated['phone'] ?? $partner->phone);
    $city    = preg_match('/^(\s*spb|\s*спб)$/iu', $validated['city']) ? 'Санкт-Петербург' : $validated['city'];
    $street  = $sanitizeStreet($validated['address'], $city);

    $kpp = $validated['business_type'] === 'company'
        ? ($validated['kpp'] ?: $partner->kpp ?: '000000000')
        : '000000000';

    $ogrnDigits = preg_replace('/\D+/', '', (string)$validated['registration_number']);
    $ogrn = $ogrnDigits !== '' ? (int)$ogrnDigits : null;

    $siteUrl = $validated['website'] ?? $partner->website ?? config('app.url');

    // формируем широкий PATCH-пейлоад (банк + адрес + базовые поля)
    $payload = [
        // billingDescriptor НЕ меняем из формы — источник истины в БД/регистрации
        'fullName' => $validated['title'],
        'name'     => $validated['title'],
        'inn'      => (string)$validated['tax_id'],
        'kpp'      => (string)$kpp,
        'ogrn'     => $ogrn,

        'addresses' => [[
            'type'    => 'legal',
            'zip'     => (string)$validated['zip'],
            'country' => 'RUS',
            'city'    => $city,
            'street'  => $street,
        ]],

        'phones' => $phone ? [[
            'type'        => 'common',
            'phone'       => $phone,
            'description' => 'Контакт',
        ]] : [],

        'email'   => $validated['email'],
        'siteUrl' => $siteUrl,

        'bankAccount' => [
            'account'  => (string)$validated['bank_account'],
            'bankName' => $validated['bank_name'],
            'bik'      => (string)$validated['bank_bik'],
            'details'  => $validated['sm_details_template'],
        ],
    ];

    // чистим null/пустые
    $clean = function ($v) use (&$clean) {
        if (is_array($v)) {
            $o = [];
            foreach ($v as $k => $x) {
                $cx = $clean($x);
                if ($cx !== null && $cx !== '') $o[$k] = $cx;
            }
            return $o;
        }
        return $v;
    };
    $payload = $clean($payload);

    try {
        Log::info('[admin][smPatch] partner_id='.$partner->id.' shopCode='.$partner->tinkoff_partner_id);
        Log::info('[admin][smPatch] outgoing payload='.json_encode($payload, JSON_UNESCAPED_UNICODE));

        $response = $sm->patch($partner->tinkoff_partner_id, $payload);

        // локально обновляем всё, что есть в форме
        $partner->fill([
            'title'                        => $validated['title'],
            'tax_id'                       => $validated['tax_id'],
            'registration_number'          => $validated['registration_number'],
            'kpp'                          => $kpp,

            'address'                      => $validated['address'],
            'city'                         => $city,
            'zip'                          => (string)$validated['zip'],

            'email'                        => $validated['email'],
            'phone'                        => $phone ?: $partner->phone,
            'website'                      => $siteUrl,

            'bank_name'                    => $validated['bank_name'],
            'bank_bik'                     => $validated['bank_bik'],
            'bank_account'                 => $validated['bank_account'],
            'sm_details_template'          => $validated['sm_details_template'],
            'bank_details_version'         => (int)($partner->bank_details_version ?? 0) + 1,
            'bank_details_last_updated_at' => now(),
        ])->save();

        return $request->ajax()
            ? response()->json(['ok'=>true,'raw'=>$response])
            : back()->with('ok','Данные партнёра обновлены в sm-register');
    } catch (\Throwable $e) {
        Log::channel('tinkoff')->error('[sm-register][patch] '.$e->getMessage());
        $msg = 'Ошибка PATCH: '.$e->getMessage();
        return $request->ajax()
            ? response()->json(['ok'=>false,'error'=>$msg], 422)
            : back()->withErrors(['sm'=>$msg]);
    }
}

    public function smRefresh($id, Request $r, SmRegisterClient $sm)
    {
        $partner = Partner::findOrFail($id);
        if (!$partner->tinkoff_partner_id) {
            return $r->ajax()
                ? response()->json(['ok' => false, 'error' => 'Нет PartnerId'], 422)
                : back()->withErrors(['sm' => 'Нет PartnerId']);
        }

        try {
            $res = $sm->getStatus($partner->tinkoff_partner_id);
            $partner->sm_register_status = data_get($res, 'status') ?? $partner->sm_register_status;
            $partner->save();

            if ($r->ajax()) return response()->json(['ok' => true, 'status' => $partner->sm_register_status, 'raw' => $res]);
            return back()->with('ok', 'Статус обновлён: ' . $partner->sm_register_status);
        } catch (\Throwable $e) {
            Log::channel('tinkoff')->error('[sm-register][status] ' . $e->getMessage());
            $msg = 'Ошибка запроса статуса: ' . $e->getMessage();
            if ($r->ajax()) return response()->json(['ok' => false, 'error' => $msg], 422);
            return back()->withErrors(['sm' => $msg]);
        }
    }

    // TinkoffAdminPartnerController.php

// app/Http/Controllers/TinkoffAdminPartnerController.php

// app/Http/Controllers/TinkoffAdminPartnerController.php

    public function smPull($id, \Illuminate\Http\Request $r, \App\Services\Tinkoff\SmRegisterClient $sm)
    {
        $partner = \App\Models\Partner::findOrFail($id);
        if (!$partner->tinkoff_partner_id) {
            return $r->ajax()
                ? response()->json(['ok' => false, 'error' => 'Нет PartnerId'], 422)
                : back()->withErrors(['sm' => 'Нет PartnerId']);
        }

        try {
            // 1) Тянем данные у банка
            $remote = $sm->getStatus($partner->tinkoff_partner_id);

            // ЛОГ 1: сырой ответ
            \Log::channel('tinkoff')->info(
                '[admin][smPull][remote_raw] shopCode='.$partner->tinkoff_partner_id.' body='.json_encode($remote, JSON_UNESCAPED_UNICODE)
            );

            // Доп. лог ключей на верхнем уровне и в bankAccount
            \Log::channel('tinkoff')->info(
                '[admin][smPull][remote_keys] top=' . implode(',', array_keys($remote ?: [])) .
                ' bankAccount_keys=' . implode(',', array_keys((array) data_get($remote, 'bankAccount', []))) .
                ' addresses_present=' . (data_get($remote, 'addresses') ? 'yes' : 'no')
            );

            // 2) Распаковываем
            $addr    = data_get($remote, 'addresses.0', []);
            $bank    = data_get($remote, 'bankAccount', []);
            $phones  = data_get($remote, 'phones', []);
            $phone   = data_get($phones, '0.phone');
            $details = (string) data_get($bank, 'details', ''); // <-- то самое поле

            // sms_name берём из БД (источник истины), если пусто — попробуем из ответа (если когда-нибудь начнут присылать)
            $smsName = $partner->sms_name ?: (string) data_get($remote, 'billingDescriptor');

            // 3) Что собираемся писать (включили sm_details_template)
            $toWrite = [
                'title'               => (string) data_get($remote, 'fullName', $partner->title),
                'tax_id'              => (string) data_get($remote, 'inn', $partner->tax_id),
                'kpp'                 => (string) data_get($remote, 'kpp', $partner->kpp),
                'registration_number' => (string) data_get($remote, 'ogrn', $partner->registration_number),

                'email'   => (string) data_get($remote, 'email', $partner->email),
                'website' => (string) data_get($remote, 'siteUrl', $partner->website),
                'phone'   => $phone ?: $partner->phone,

                // адресов в ответе у тебя сейчас нет — оставляем локальные, чтобы не затирать пустотой
                'city'    => (string) data_get($addr, 'city', $partner->city),
                'zip'     => (string) data_get($addr, 'zip', $partner->zip),
                'address' => (string) data_get($addr, 'street', $partner->address),

                'bank_name'          => (string) data_get($bank, 'bankName', $partner->bank_name),
                'bank_bik'           => (string) data_get($bank, 'bik', $partner->bank_bik),
                'bank_account'       => (string) data_get($bank, 'account', $partner->bank_account),
                'sm_details_template'=> $details !== '' ? $details : ($partner->sm_details_template ?? null), // <-- добавлено

                'sm_register_status' => (string) data_get($remote, 'status', $partner->sm_register_status),
                'sms_name'           => $smsName ?: $partner->sms_name,
            ];

            // ЛОГ 2: что планируем писать + отдельно значение details
            \Log::channel('tinkoff')->info('[admin][smPull][bank.details] "'.$details.'"');
            \Log::channel('tinkoff')->info('[admin][smPull][to_write] '.json_encode($toWrite, JSON_UNESCAPED_UNICODE));

            // 4) Дифф
            $before = $partner->only(array_keys($toWrite));

            // Заполняем и сохраняем
            $partner->fill($toWrite);

            $dirty = $partner->getDirty();
            if (!empty($dirty)) {
                $partner->bank_details_version = (int) ($partner->bank_details_version ?? 0) + 1;
                $partner->bank_details_last_updated_at = now();
            }
            $partner->save();

            // 5) ЛОГ 3: что реально изменилось
            $after   = $partner->fresh()->only(array_keys($toWrite));
            $changed = [];
            foreach ($after as $k => $v) {
                if (($before[$k] ?? null) !== $v) {
                    $changed[$k] = ['from' => $before[$k] ?? null, 'to' => $v];
                }
            }
            \Log::channel('tinkoff')->info('[admin][smPull][changed] '.json_encode($changed, JSON_UNESCAPED_UNICODE));

            if ($r->ajax()) {
                return response()->json(['ok' => true, 'changed' => $changed, 'remote' => $remote]);
            }
            return back()->with('ok', 'Реквизиты подтянуты из sm-register');

        } catch (\Throwable $e) {
            \Log::channel('tinkoff')->error('[sm-register][pull] '.$e->getMessage());
            $msg = 'Ошибка pull: '.$e->getMessage();
            return $r->ajax()
                ? response()->json(['ok' => false, 'error' => $msg], 422)
                : back()->withErrors(['sm' => $msg]);
        }
    }

}
