<?php


namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Filters\TeamFilter;
use App\Http\Requests\Team\FilterRequest;

//use App\Http\Requests\Partner\UpdateRequest;
//use App\Http\Requests\Team\StoreRequest;
use App\Http\Requests\Partner\StorePartnerRequest;
use App\Http\Requests\Partner\UpdatePartnerRequest;


use App\Models\Partner;
use App\Models\Team;
use App\Models\User;
use App\Models\Weekday;
use App\Servises\TeamService;
use App\Servises\UserService;
use Carbon\Carbon;
use function Illuminate\Http\Client\dump;

//use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Yajra\DataTables\DataTables;


use Illuminate\Support\Facades\Log;
use App\Models\MyLog;

use App\Support\BuildsLogTable;

//use App\Models\Log;
//use Illuminate\Support\Facades\Log;


//Контроллер для админа

class PartnerController extends Controller
{
    use BuildsLogTable;

    public function __construct(TeamService $service)
    {
        $this->service = $service;
    }

    public function index(FilterRequest $request)
    {

        $data = $request->validated();
        $filter = app()->make(TeamFilter::class, ['queryParams' => array_filter($data)]);
        $partnerId = app('current_partner')->id;


        $allPartners = Partner::filter($filter)
            ->orderBy('order_by', 'asc') // сортировка по полю order_by по возрастанию
            ->paginate(10);


        return view("admin/partner", compact('allPartners'
        ));
    }

    public function store(StorePartnerRequest $request)
    {
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

        DB::transaction(function () use ($data, $authorId, &$partner) {
            $partner = Partner::create($data);

            $fields = [
                'business_type' => 'Тип бизнеса',
                'title' => 'Наименование',
                'organization_name' => 'Наименование организации',
                'tax_id' => 'ИНН',
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
                $lines[] = "{$label}: {$val}";
            }

            $ceo = $partner->ceo ?? [];
            $lines[] = "Фамилия руководителя: " . ($ceo['lastName'] ?? '—');
            $lines[] = "Имя руководителя: " . ($ceo['firstName'] ?? '—');
            $lines[] = "Отчество руководителя: " . ($ceo['middleName'] ?? '—');
            $lines[] = "Телефон руководителя: " . ($ceo['phone'] ?? '—');

            MyLog::create([
                'type' => 80,
                'action' => 81,
                'target_type' => 'App\Models\Partner',
                'target_id' => $partner->id,
                'target_label' => $partner->title,
                'description' => "Создан новый партнёр:\n" . implode("\n", $lines),
                'created_at' => now(),
            ]);
        });

        return response()->json([
            'message' => 'Партнёр успешно создан',
            'partner' => $partner,
        ], 201);
    }

    public function edit(Partner $partner)
    {
        // что реально в БД (на случай смешанных версий)
        $raw = \DB::table('partners')->where('id', $partner->id)->value('ceo');

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

        \Log::info('[Partner.edit] payload', [
            'partner_id' => $partner->id,
            'raw_ceo' => $raw,
            'cast_ceo' => $cast,
            'payload' => $payload
        ]);

        return response()->json($payload);
    }

    public function update(UpdatePartnerRequest $request, Partner $partner)
    {
        $authorId = auth()->id();
        $data = $request->validated();

        // гарантия camelCase-ключей в ceo
        $data['ceo'] = [
            'lastName' => $data['ceo']['lastName'] ?? '',
            'firstName' => $data['ceo']['firstName'] ?? '',
            'middleName' => $data['ceo']['middleName'] ?? '',
            'phone' => $data['ceo']['phone'] ?? '',
        ];

        \DB::transaction(function () use ($data, $authorId, $partner) {

            $old = $partner->only([
                'business_type', 'title', 'organization_name', 'tax_id', 'kpp', 'registration_number',
                'sms_name', 'city', 'zip', 'address',
                'phone', 'email', 'website',
                'bank_name', 'bank_bik', 'bank_account',
                'order_by', 'is_enabled', 'ceo',
            ]);

            $partner->update($data);

            $new = $partner->only([
                'business_type', 'title', 'organization_name', 'tax_id', 'kpp', 'registration_number',
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
                MyLog::create([
                    'type' => 80,
                    'action' => 82,
                    'target_type' => 'App\Models\Partner',
                    'target_id' => $partner->id,
                    'target_label' => $partner->title,
                    'description' => $description,
                    'created_at' => now(),
                ]);
            }

            \Log::info('[Partner.update] updated', [
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
        $authorId = auth()->id();

        // Собираем данные партнёра перед удалением
        $old = $partner->only([
            'business_type',
            'title',
            'organization_name',
            'tax_id',
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

        DB::transaction(function () use ($partner, $old, $authorId) {
            // Удаляем партнёра
            $partner->delete();

            // Формируем читаемую строку старых значений
            $fields = [
                'business_type' => 'Тип бизнеса',
                'title' => 'Наименование',
            'organization_name' => 'Наименование организации',
                'tax_id' => 'ИНН',
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
                $lines[] = "{$label}: {$val}";
            }

            // Запись лога удаления
            MyLog::create([
                'type' => 80,      // ваш код типа лога
                'action' => 83,      // ваш код действия «удаление партнёра»
                'target_type' => 'App\Models\Partner',
                'target_id' => $partner->id,
                'target_label' => $partner->title,
                'description' => "Удалён партнёр:\n" . implode("\n", $lines),
                'created_at' => now(),
            ]);
        });

        return response()->json([
            'message' => 'Партнёр удалён',
        ], 200);
    }

    public function log(FilterRequest $request)
    {
        return $this->buildLogDataTable(80);
    }

}
