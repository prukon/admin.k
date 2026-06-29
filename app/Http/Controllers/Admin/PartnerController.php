<?php


namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Partner\PartnerDataTableRequest;
use App\Http\Requests\Partner\StorePartnerRequest;
use App\Http\Requests\Partner\UpdatePartnerRequest;
use App\Http\Requests\Team\FilterRequest;


use App\Models\Partner;
use App\Models\Team;
use App\Models\User;
use App\Models\Weekday;
use App\Services\TeamService;
use App\Services\UserService;
use Carbon\Carbon;

//use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Enums\AuditEvent;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditLogger;
use App\Support\BuildsLogTable;
use App\Services\PartnerContext;

//Контроллер для админа

class PartnerController extends AdminBaseController
{
    use BuildsLogTable;

    protected TeamService $service;

    public function __construct(
        TeamService $service,
        PartnerContext $partnerContext,
        private readonly AuditLogger $auditLogger,
    )
    {
        parent::__construct($partnerContext);
        $this->service = $service;
    }

    public function index()
    {
        return view('admin.partners.index', [
            'activeTab' => 'partners',
        ]);
    }

    public function data(PartnerDataTableRequest $request)
    {
        $validated = $request->validated();

        $baseQuery = Partner::query();

        $titleSearch = trim((string) ($validated['title'] ?? ''));
        if ($titleSearch === '' && $request->filled('search.value')) {
            $titleSearch = trim((string) $request->input('search.value'));
        }

        if ($titleSearch !== '') {
            $like = '%' . $titleSearch . '%';
            $baseQuery->where(function ($q) use ($like, $titleSearch) {
                $q->where('title', 'like', $like)
                    ->orWhere('organization_name', 'like', $like)
                    ->orWhere('tax_id', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);

                if (ctype_digit($titleSearch)) {
                    $q->orWhere('id', (int) $titleSearch);
                }
            });
        }

        $status = $validated['status'] ?? null;
        if ($status === 'active') {
            $baseQuery->where('is_enabled', 1);
        } elseif ($status === 'inactive') {
            $baseQuery->where('is_enabled', 0);
        }

        $totalRecords = Partner::query()->count();
        $recordsFiltered = (clone $baseQuery)->count();

        $orderColumnIndex = $request->input('order.0.column');
        $orderDir         = $request->input('order.0.dir', 'asc') === 'desc' ? 'desc' : 'asc';
        $columnsDef       = $request->input('columns', []);
        $orderColumnName  = null;
        if ($orderColumnIndex !== null && isset($columnsDef[(int) $orderColumnIndex]['name'])) {
            $orderColumnName = $columnsDef[(int) $orderColumnIndex]['name'];
        }

        switch ($orderColumnName) {
            case 'order_by':
                $baseQuery->orderBy('order_by', $orderDir)
                    ->orderBy('title', 'asc');
                break;
            case 'title':
                $baseQuery->orderBy('title', $orderDir);
                break;
            case 'organization_name':
                $baseQuery->orderBy('organization_name', $orderDir)
                    ->orderBy('title', 'asc');
                break;
            case 'tax_id':
                $baseQuery->orderBy('tax_id', $orderDir)
                    ->orderBy('title', 'asc');
                break;
            case 'email':
                $baseQuery->orderBy('email', $orderDir)
                    ->orderBy('title', 'asc');
                break;
            case 'phone':
                $baseQuery->orderBy('phone', $orderDir)
                    ->orderBy('title', 'asc');
                break;
            case 'status_label':
                $baseQuery->orderBy('is_enabled', $orderDir)
                    ->orderBy('title', 'asc');
                break;
            case 'rownum':
            case 'actions':
            default:
                $baseQuery->orderBy('order_by', 'asc')
                    ->orderBy('title', 'asc');
                break;
        }

        $start  = $validated['start'] ?? 0;
        $length = $validated['length'] ?? 10;

        $partners = $baseQuery
            ->skip($start)
            ->take($length)
            ->get();

        $data = $partners->map(function (Partner $partner) {
            return [
                'id'                 => $partner->id,
                'order_by'           => $partner->order_by,
                'title'              => $partner->title,
                'organization_name'  => $partner->organization_name ?? '',
                'tax_id'             => $partner->tax_id ?? '',
                'email'              => $partner->email ?? '',
                'phone'              => $partner->phone ?? '',
                'status_label'       => $partner->is_enabled ? 'Активен' : 'Неактивен',
                'is_enabled'         => (int) $partner->is_enabled,
            ];
        })->toArray();

        return response()->json([
            'draw'            => (int) ($validated['draw'] ?? 0),
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    public function store(StorePartnerRequest $request)
    {
        $partnerId = $this->requirePartnerId();
        $authorId = auth()->id();
        $data = $request->validated();

        // гарантируем наличие camelCase-ключей
        $data['ceo'] = [
            'lastName' => $data['ceo']['lastName'] ?? '',
            'firstName' => $data['ceo']['firstName'] ?? '',
            'middleName' => $data['ceo']['middleName'] ?? '',
            'phone' => $data['ceo']['phone'] ?? '',
        ];

        $partner = null;

        DB::transaction(function () use ($data, $authorId, $partnerId, &$partner) {
            $partner = Partner::create($data);

            $fields = [
                'business_type' => 'Тип бизнеса',
                'title' => 'Наименование',
                'organization_name' => 'Наименование организации',
                'tax_id' => 'ИНН',
                'vat' => 'Ставка НДС (онлайн-чек)',
                'kpp' => 'КПП',
                'registration_number' => 'ОГРН (ОГРНИП)',
                'sms_name' => 'Название для SMS/выписок',
                'city' => 'Город',
                'zip' => 'Индекс',
                'address' => 'Адрес',
                'phone' => 'Телефон',
                'email' => 'E-mail',
                'website' => 'Сайт',
                'bank_name' => 'Банк',
                'bank_bik' => 'БИК',
                'bank_account' => 'Расчётный счёт',
                'order_by' => 'Сортировка',
                'is_enabled' => 'Активность',
            ];

            $lines = [];
            foreach ($fields as $key => $label) {
                $val = $partner->{$key} ?? '—';
                if ($key === 'is_enabled') $val = $val ? 'Да' : 'Нет';
                if ($key === 'vat') {
                    $val = self::vatLabel($val === '—' || $val === null || $val === '' ? null : (int) $val);
                }
                $lines[] = "{$label}: {$val}";
            }

            $ceo = $partner->ceo ?? [];
            $lines[] = "Фамилия руководителя: " . ($ceo['lastName'] ?? '—');
            $lines[] = "Имя руководителя: " . ($ceo['firstName'] ?? '—');
            $lines[] = "Отчество руководителя: " . ($ceo['middleName'] ?? '—');
            $lines[] = "Телефон руководителя: " . ($ceo['phone'] ?? '—');

            $this->auditLogger->record(
                AuditEvent::PartnerCreated,
                AuditContext::make("Создан новый партнёр:\n" . implode("\n", $lines))
                    ->withTarget($partner, $partner->title)
                    ->withAuthorId($authorId)
                    ->withPartnerId($partnerId)
                    ->withCreatedAt(now())
            );
        });

        return response()->json([
            'message' => 'Партнёр успешно создан',
            'partner' => $partner,
        ], 201);
    }

    public function edit(Partner $partner)
    {
        // что реально в БД (на случай смешанных версий)
        $raw = DB::table('partners')->where('id', $partner->id)->value('ceo');

        // cast модели (array|null)
        $cast = $partner->ceo;

        // нормализация к camelCase (поддержка legacy snake_case)
        $src = is_array($cast) ? $cast : (json_decode($raw ?? '[]', true) ?: []);
        $ceo = [
            'lastName' => $src['lastName'] ?? $src['last_name'] ?? '',
            'firstName' => $src['firstName'] ?? $src['first_name'] ?? '',
            'middleName' => $src['middleName'] ?? $src['middle_name'] ?? '',
            'phone' => $src['phone'] ?? '',
        ];

        $payload = [
            'id' => $partner->id,
            'business_type' => $partner->business_type,
            'title' => $partner->title,
            'organization_name' => $partner->organization_name,
            'tax_id' => $partner->tax_id,
            'vat' => $partner->vat,
            'kpp' => $partner->kpp,
            'registration_number' => $partner->registration_number,
            'sms_name' => $partner->sms_name,
            'city' => $partner->city,
            'zip' => $partner->zip,
            'address' => $partner->address,
            'phone' => $partner->phone,
            'email' => $partner->email,
            'website' => $partner->website,
            'bank_name' => $partner->bank_name,
            'bank_bik' => $partner->bank_bik,
            'bank_account' => $partner->bank_account,
            'order_by' => $partner->order_by,
            'is_enabled' => (bool)$partner->is_enabled,
            'ceo' => $ceo,
        ];

        // Log::info('[Partner.edit] payload', [
        //     'partner_id' => $partner->id,
        //     'raw_ceo' => $raw,
        //     'cast_ceo' => $cast,
        //     'payload' => $payload
        // ]);

        return response()->json($payload);
    }

    public function update(UpdatePartnerRequest $request, Partner $partner)
    {
        $partnerId = $this->requirePartnerId();
        $authorId = auth()->id();
        $data = $request->validated();

        // гарантия camelCase-ключей в ceo
        $data['ceo'] = [
            'lastName' => $data['ceo']['lastName'] ?? '',
            'firstName' => $data['ceo']['firstName'] ?? '',
            'middleName' => $data['ceo']['middleName'] ?? '',
            'phone' => $data['ceo']['phone'] ?? '',
        ];

        DB::transaction(function () use ($data, $authorId, $partnerId, $partner) {

            $old = $partner->only([
                'business_type', 'title', 'organization_name', 'tax_id', 'vat', 'kpp', 'registration_number',
                'sms_name', 'city', 'zip', 'address',
                'phone', 'email', 'website',
                'bank_name', 'bank_bik', 'bank_account',
                'order_by', 'is_enabled', 'ceo',
            ]);

            $partner->update($data);

            $new = $partner->only([
                'business_type', 'title', 'organization_name', 'tax_id', 'vat', 'kpp', 'registration_number',
                'sms_name', 'city', 'zip', 'address',
                'phone', 'email', 'website',
                'bank_name', 'bank_bik', 'bank_account',
                'order_by', 'is_enabled', 'ceo',
            ]);

            $fields = [
                'business_type' => 'Тип бизнеса',
                'title' => 'Наименование',
                'organization_name' => 'Наименование организации',
                'tax_id' => 'ИНН',
                'vat' => 'Ставка НДС (онлайн-чек)',
                'kpp' => 'КПП',
                'registration_number' => 'ОГРН (ОГРНИП)',
                'sms_name' => 'Название для SMS/выписок',
                'city' => 'Город',
                'zip' => 'Индекс',
                'address' => 'Адрес',
                'phone' => 'Телефон',
                'email' => 'E-mail',
                'website' => 'Сайт',
                'bank_name' => 'Банк',
                'bank_bik' => 'БИК',
                'bank_account' => 'Расчётный счёт',
                'order_by' => 'Сортировка',
                'is_enabled' => 'Активность',
            ];

            $oldLines = [];
            $newLines = [];

            foreach ($fields as $key => $label) {
                $ov = $old[$key] ?? '—';
                $nv = $new[$key] ?? '—';
                if ($key === 'is_enabled') {
                    $ov = $ov ? 'Да' : 'Нет';
                    $nv = $nv ? 'Да' : 'Нет';
                }
                if ($key === 'vat') {
                    $ov = self::vatLabel($ov === null || $ov === '' ? null : (int) $ov);
                    $nv = self::vatLabel($nv === null || $nv === '' ? null : (int) $nv);
                }
                if ((string)$ov !== (string)$nv) {
                    $oldLines[] = "{$label}: {$ov}";
                    $newLines[] = "{$label}: {$nv}";
                }
            }

            // сравнение CEO (camelCase, понимаем legacy snake_case в old)
            $oldCeoSrc = is_array($old['ceo'] ?? null) ? $old['ceo'] : (json_decode($old['ceo'] ?? '[]', true) ?: []);
            $newCeoSrc = is_array($new['ceo'] ?? null) ? $new['ceo'] : (json_decode($new['ceo'] ?? '[]', true) ?: []);

            $oldCeo = [
                'lastName' => $oldCeoSrc['lastName'] ?? $oldCeoSrc['last_name'] ?? '',
                'firstName' => $oldCeoSrc['firstName'] ?? $oldCeoSrc['first_name'] ?? '',
                'middleName' => $oldCeoSrc['middleName'] ?? $oldCeoSrc['middle_name'] ?? '',
                'phone' => $oldCeoSrc['phone'] ?? '',
            ];
            $newCeo = [
                'lastName' => $newCeoSrc['lastName'] ?? $newCeoSrc['last_name'] ?? '',
                'firstName' => $newCeoSrc['firstName'] ?? $newCeoSrc['first_name'] ?? '',
                'middleName' => $newCeoSrc['middleName'] ?? $newCeoSrc['middle_name'] ?? '',
                'phone' => $newCeoSrc['phone'] ?? '',
            ];

            $ceoLabels = [
                'lastName' => 'Фамилия руководителя',
                'firstName' => 'Имя руководителя',
                'middleName' => 'Отчество руководителя',
                'phone' => 'Телефон руководителя',
            ];

            foreach ($ceoLabels as $k => $label) {
                if ((string)($oldCeo[$k] ?? '') !== (string)($newCeo[$k] ?? '')) {
                    $oldLines[] = "{$label}: " . ($oldCeo[$k] ?? '—');
                    $newLines[] = "{$label}: " . ($newCeo[$k] ?? '—');
                }
            }


            if ($oldLines) {
                // собираем пары "старое → новое"
                $changes = [];
                foreach ($oldLines as $i => $oldLine) {
                    $label = explode(':', $oldLine, 2)[0] ?? '';
                    $oldVal = trim(explode(':', $oldLine, 2)[1] ?? '—');
                    $newVal = trim(explode(':', $newLines[$i] ?? '', 2)[1] ?? '—');
                    $changes[] = "{$label}: {$oldVal} → {$newVal}";
                }

                // переносы строк после каждой пары
                $description = implode(";\n", $changes) . "\n";

            //Изменение партнера
                $this->auditLogger->record(
                    AuditEvent::PartnerUpdatedBySuperadmin,
                    AuditContext::make($description)
                        ->withTarget($partner, $partner->title)
                        ->withAuthorId($authorId)
                        ->withPartnerId($partnerId)
                        ->withCreatedAt(now())
                );
            }

            Log::info('[Partner.update] updated', [
                'partner_id' => $partner->id,
                'after' => $new,
            ]);
        });

        return response()->json([
            'message' => 'Партнёр успешно обновлён',
            'partner' => $partner,
        ], 200);
    }

    public function destroy(Partner $partner)
    {
        $partnerId = $this->requirePartnerId();
        $authorId = auth()->id();

        // Собираем данные партнёра перед удалением
        $old = $partner->only([
            'business_type',
            'title',
            'organization_name',
            'tax_id',
            'vat',
            'kpp',
            'registration_number',
            'address',
            'phone',
            'email',
            'website',
            'bank_name',
            'bank_bik',
            'bank_account',
            'order_by',
            'is_enabled',
        ]);

        DB::transaction(function () use ($partner, $old, $authorId, $partnerId) {
            // Удаляем партнёра
            $partner->delete();

            // Формируем читаемую строку старых значений
            $fields = [
                'business_type' => 'Тип бизнеса',
                'title' => 'Наименование',
                'organization_name' => 'Наименование организации',
                'tax_id' => 'ИНН',
                'vat' => 'Ставка НДС (онлайн-чек)',
                'kpp' => 'КПП',
                'registration_number' => 'ОГРН (ОГРНИП)',
                'address' => 'Почтовый адрес',
                'phone' => 'Телефон',
                'email' => 'E-mail',
                'website' => 'Сайт',
                'bank_name' => 'Банк',
                'bank_bik' => 'БИК',
                'bank_account' => 'Расчетный счет',
                'order_by' => 'Сортировка',
                'is_enabled' => 'Активность',
            ];

            $lines = [];
            foreach ($fields as $key => $label) {
                $val = $old[$key] ?? '—';
                if ($key === 'is_enabled') {
                    $val = $val ? 'Да' : 'Нет';
                }
                if ($key === 'vat') {
                    $val = self::vatLabel($val === '—' || $val === null || $val === '' ? null : (int) $val);
                }
                $lines[] = "{$label}: {$val}";
            }

            // Запись лога удаления
            $this->auditLogger->record(
                AuditEvent::PartnerDeleted,
                AuditContext::make("Удалён партнёр:\n" . implode("\n", $lines))
                    ->withTarget($partner, $partner->title)
                    ->withAuthorId($authorId)
                    ->withPartnerId($partnerId)
                    ->withCreatedAt(now())
            );
        });

        return response()->json([
            'message' => 'Партнёр удалён',
        ], 200);
    }

    public function log(FilterRequest $request)
    {
        return $this->buildLogDataTable('partner');
    }

    private static function vatLabel(?int $value): string
    {
        return \App\Enums\CloudKassirVatRate::labelFor($value);
    }
}
