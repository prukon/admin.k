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


//use App\Models\Log;
//use Illuminate\Support\Facades\Log;


//Контроллер для админа

class PartnerController extends Controller
{

    public function __construct(TeamService $service)
    {
        $this->service = $service;
    }

    public function index(FilterRequest $request)
    {

        $data = $request->validated();
        $filter = app()->make(TeamFilter::class, ['queryParams'=> array_filter($data)]);
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
        $data     = $request->validated();

        // На всякий: если ceo не передан — нормализуем пустую структуру
        if (!isset($data['ceo']) || !is_array($data['ceo'])) {
            $data['ceo'] = [
                'last_name'   => '',
                'first_name'  => '',
                'middle_name' => '',
                'phone'       => '',
            ];
        } else {
            // защитимся от отсутствующих ключей
            $data['ceo'] = array_merge([
                'last_name'   => '',
                'first_name'  => '',
                'middle_name' => '',
                'phone'       => '',
            ], $data['ceo']);
        }

        // Создадим переменную, чтобы вернуть созданного партнёра после транзакции
        $partner = null;

        DB::transaction(function () use ($data, $authorId, &$partner) {
            // Создаём партнёра
            $partner = Partner::create($data);

            // Список полей для лога (с учётом новых и переименований)
            $fields = [
                'business_type'       => 'Тип бизнеса',
                'title'               => 'Наименование',
                'tax_id'              => 'ИНН',
                'kpp'                 => 'КПП',
                'registration_number' => 'ОГРН (ОГРНИП)',

                'sms_name'            => 'Название для SMS/выписок',
                'city'                => 'Город',
                'zip'                 => 'Индекс',
                'address'             => 'Адрес',

                'phone'               => 'Телефон',
                'email'               => 'E-mail',
                'website'             => 'Сайт',
                'bank_name'           => 'Банк',
                'bank_bik'            => 'БИК',
                'bank_account'        => 'Расчётный счёт',
                'order_by'            => 'Сортировка',
                'is_enabled'          => 'Активность',
            ];

            $lines = [];
            foreach ($fields as $key => $label) {
                $val = $partner->{$key} ?? '—';
                if ($key === 'is_enabled') {
                    $val = $val ? 'Да' : 'Нет';
                }
                $lines[] = "{$label}: {$val}";
            }

            // Добьём блоком CEO
            $ceo = $partner->ceo ?? [];
            $lines[] = "Фамилия руководителя: " . ($ceo['last_name']   ?? '—');
            $lines[] = "Имя руководителя: "     . ($ceo['first_name']  ?? '—');
            $lines[] = "Отчество руководителя: ". ($ceo['middle_name'] ?? '—');
            $lines[] = "Телефон руководителя: " . ($ceo['phone']       ?? '—');

            // Запись лога создания
            MyLog::create([
                'type'        => 80, // ваш код типа лога
                'action'      => 81, // ваш код действия «создание партнёра»
                'author_id'   => $authorId,
                'partner_id'  => $partner->id,
                'description' => "Создан новый партнёр:\n" . implode("\n", $lines),
                'created_at'  => now(),
            ]);

            // Laravel-лог: что создали (для отладки)
            \Log::info('[Partner.store] created', [
                'partner_id' => $partner->id,
                'payload'    => $partner->only(array_keys($fields)) + ['ceo' => $partner->ceo],
            ]);
        });

        return response()->json([
            'message' => 'Партнёр успешно создан',
            'partner' => $partner,
        ], 201);
    }

    public function edit(Partner $partner)
    {
        // ceo уже приведён к массиву благодаря $casts;
        // нормализуем поля на случай null в отдельных ключах
        $ceo = $partner->ceo ?: [];
        if (!is_array($ceo)) {
            $ceo = json_decode($ceo ?? '[]', true) ?: [];
        }
        $ceo = [
            'last_name'   => $ceo['last_name']   ?? '',
            'first_name'  => $ceo['first_name']  ?? '',
            'middle_name' => $ceo['middle_name'] ?? '',
            'phone'       => $ceo['phone']       ?? '',
        ];

        $payload = [
            'id'                  => $partner->id,
            'business_type'       => $partner->business_type,
            'title'               => $partner->title,
            'tax_id'              => $partner->tax_id,
            'kpp'                 => $partner->kpp,
            'registration_number' => $partner->registration_number,

            'sms_name'            => $partner->sms_name,
            'city'                => $partner->city,
            'zip'                 => $partner->zip,
            'address'             => $partner->address,

            'phone'               => $partner->phone,
            'email'               => $partner->email,
            'website'             => $partner->website,

            'bank_name'           => $partner->bank_name,
            'bank_bik'            => $partner->bank_bik,
            'bank_account'        => $partner->bank_account,

            'order_by'            => $partner->order_by,
            'is_enabled'          => (bool) $partner->is_enabled,

            'ceo'                 => $ceo,
        ];

        // ЛОГ: что отдаём на фронт
        Log::info('[Partner.edit] payload', ['partner_id' => $partner->id, 'payload' => $payload]);

        return response()->json($payload);
    }

    public function update(UpdatePartnerRequest $request, Partner $partner)
    {
        $authorId = auth()->id();
        $data     = $request->validated();

        DB::transaction(function () use ($data, $authorId, $partner) {

            $old = $partner->only([
                'business_type','title','tax_id','kpp','registration_number',
                'address','phone','email','website',
                'bank_name','bank_bik','bank_account',
                'order_by','is_enabled',
                'sms_name','city','zip','ceo',
            ]);

            $partner->update($data);

            $new = $partner->only([
                'business_type','title','tax_id','kpp','registration_number',
                'address','phone','email','website',
                'bank_name','bank_bik','bank_account',
                'order_by','is_enabled',
                'sms_name','city','zip','ceo',
            ]);

            $fields = [
                'business_type'       => 'Тип бизнеса',
                'title'               => 'Наименование',
                'tax_id'              => 'ИНН',
                'kpp'                 => 'КПП',
                'registration_number' => 'ОГРН (ОГРНИП)',
                'address'             => 'Адрес',
                'phone'               => 'Телефон',
                'email'               => 'E-mail',
                'website'             => 'Сайт',
                'bank_name'           => 'Банк',
                'bank_bik'            => 'БИК',
                'bank_account'        => 'Расчётный счёт',
                'order_by'            => 'Сортировка',
                'is_enabled'          => 'Активность',
                'sms_name'            => 'Название для SMS/выписок',
                'city'                => 'Город',
                'zip'                 => 'Индекс',
            ];

            $oldLines = [];
            $newLines = [];

            foreach ($fields as $key => $label) {
                $oldVal = $old[$key] ?? '—';
                $newVal = $new[$key] ?? '—';

                if ($key === 'is_enabled') {
                    $oldVal = $oldVal ? 'Да' : 'Нет';
                    $newVal = $newVal ? 'Да' : 'Нет';
                }

                if ((string) $oldVal !== (string) $newVal) {
                    $oldLines[] = "{$label}: {$oldVal}";
                    $newLines[] = "{$label}: {$newVal}";
                }
            }

            // CEО: сравниваем по ключам
            $oldCeo = is_array($old['ceo'] ?? null) ? ($old['ceo'] ?? []) : (json_decode($old['ceo'] ?? '[]', true) ?: []);
            $newCeo = is_array($new['ceo'] ?? null) ? ($new['ceo'] ?? []) : (json_decode($new['ceo'] ?? '[]', true) ?: []);

            $ceoFields = [
                'last_name'   => 'Фамилия руководителя',
                'first_name'  => 'Имя руководителя',
                'middle_name' => 'Отчество руководителя',
                'phone'       => 'Телефон руководителя',
            ];

            foreach ($ceoFields as $ckey => $clabel) {
                $o = $oldCeo[$ckey] ?? '—';
                $n = $newCeo[$ckey] ?? '—';
                if ((string) $o !== (string) $n) {
                    $oldLines[] = "{$clabel}: {$o}";
                    $newLines[] = "{$clabel}: {$n}";
                }
            }

            if (!empty($oldLines)) {
                $description = "Изменённые данные:\n"
                    . "Старые значения:\n" . implode("\n", $oldLines) . "\n"
                    . "Новые значения:\n" . implode("\n", $newLines);

                MyLog::create([
                    'type'        => 80,
                    'action'      => 82,
                    'author_id'   => $authorId,
                    'partner_id'  => $partner->id,
                    'description' => $description,
                    'created_at'  => now(),
                ]);
            }

            // ЛОГ: фиксация успешного обновления и нового среза ключевых полей
            Log::info('[Partner.update] updated', [
                'partner_id' => $partner->id,
                'changed_fields' => array_values($fields),
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
                'business_type'       => 'Тип бизнеса',
                'title'               => 'Наименование',
                'tax_id'              => 'ИНН',
                'kpp'                 => 'КПП',
                'registration_number' => 'ОГРН (ОГРНИП)',
                'address'             => 'Почтовый адрес',
                'phone'               => 'Телефон',
                'email'               => 'E-mail',
                'website'             => 'Сайт',
                'bank_name'           => 'Банк',
                'bank_bik'            => 'БИК',
                'bank_account'        => 'Расчетный счет',
                'order_by'            => 'Сортировка',
                'is_enabled'          => 'Активность',
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
                'type'        => 80,      // ваш код типа лога
                'action'      => 83,      // ваш код действия «удаление партнёра»
                'author_id'   => $authorId,
                'partner_id'  => $partner->id,
                'description' => "Удалён партнёр:\n" . implode("\n", $lines),
                'created_at'  => now(),
            ]);
        });

        return response()->json([
            'message' => 'Партнёр удалён',
        ], 200);
    }

    public function log(FilterRequest $request)
    {
        $partnerId = app('current_partner')->id;

        $logs = MyLog::with('author')
            ->where('type', 80) // Team партнеров
//            ->where('partner_id', $partnerId)        // ИЗМЕНЕНИЕ #2: добавляем фильтр по partner_id

            ->select('my_logs.*');
        return DataTables::of($logs)
            ->addColumn('author', function ($log) {
                return $log->author ? $log->author->name : 'Неизвестно';
            })
            ->editColumn('created_at', function ($log) {
                return $log->created_at->format('d.m.Y / H:i:s');
            })
            ->editColumn('action', function ($log) {
                // Логика для преобразования типа
                $typeLabels = [

                    81 => 'Создание партнера суперадмином',
                    82 => 'Изменение партнера суперадмином',
                    83 => 'Удаление партнера',


                ];
                return $typeLabels[$log->action] ?? 'Неизвестный тип';
            })
            ->make(true);
    }

}
