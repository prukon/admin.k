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

        DB::transaction(function () use ($data, $authorId, &$partner) {
            // Создаём нового партнёра
            $partner = Partner::create($data);

            // Собираем значения для лога
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
                $val = $partner->{$key} ?? '—';
                if ($key === 'is_enabled') {
                    $val = $val ? 'Да' : 'Нет';
                }
                $lines[] = "{$label}: {$val}";
            }

            // Запись лога создания
            MyLog::create([
                'type'        => 80, // ваш код типа лога
                'action'      => 81, // ваш код действия «создание партнёра»
                'author_id'   => $authorId,
                'partner_id'  => $partner->id,
                'description' => "Создан новый партнёр:\n" . implode("\n", $lines),
                'created_at'  => now(),
            ]);
        });

        return response()->json([
            'message' => 'Партнёр успешно создан',
            'partner' => $partner,
        ], 201);
    }

    public function edit(Partner $partner)
    {
        return response()->json($partner);
    }

    public function update(UpdatePartnerRequest $request, Partner $partner)
    {
        $authorId = auth()->id();
        $data     = $request->validated();

        DB::transaction(function () use ($data, $authorId, $partner) {
            // Собираем старые значения
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

            // Обновляем партнёра
            $partner->update($data);

            // Собираем новые значения
            $new = $partner->only([
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

            // Названия полей
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

            $oldLines = [];
            $newLines = [];
            foreach ($fields as $key => $label) {
                $oldVal = $old[$key] ?? '—';
                $newVal = $new[$key] ?? '—';
                if ($key === 'is_enabled') {
                    $oldVal = $oldVal ? 'Да' : 'Нет';
                    $newVal = $newVal ? 'Да' : 'Нет';
                }
                // Добавляем только изменившиеся поля
                if ((string)$oldVal !== (string)$newVal) {
                    $oldLines[] = "{$label}: {$oldVal}";
                    $newLines[] = "{$label}: {$newVal}";
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
