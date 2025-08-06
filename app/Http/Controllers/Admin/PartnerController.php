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


//use App\Models\Log;
use App\Models\MyLog;
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
        // Логируем входящие данные
        Log::info('Admin\PartnerController@store called', [
            'user_id' => $request->user()->id ?? null,
            'input'   => $request->all(),
        ]);

        try {
            // Создаём партнёра
            $partner = Partner::create($request->validated());

            // Логируем успешное создание
            Log::info('Partner created successfully', [
                'partner_id' => $partner->id,
                'attributes'=> $partner->toArray(),
            ]);

            return response()->json([
                'message' => 'Партнёр успешно создан',
                'partner' => $partner,
            ], 201);

        } catch (\Throwable $e) {
            // Логируем ошибку с полным стектрейсом
            Log::error('Error in PartnerController@store', [
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            // Возвращаем понятный ответ клиенту
            return response()->json([
                'message' => 'Не удалось создать партнёра. Смотрите логи для подробностей.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function edit(Partner $partner)
    {
        return response()->json($partner);
    }

    public function update(UpdatePartnerRequest $request, Partner $partner)
    {
        $partner->update($request->validated());
        return response()->json(['message' => 'Партнёр успешно обновлён', 'partner' => $partner], 200);
    }

    public function destroy(Partner $partner)
    {
        $partner->delete();
        return response()->json(['message' => 'Партнёр удалён'], 200);
    }

    public function log(FilterRequest $request)
    {
        $partnerId = app('current_partner')->id;

        $logs = MyLog::with('author')
            ->where('type', 3) // Team логи
            ->where('partner_id', $partnerId)        // ИЗМЕНЕНИЕ #2: добавляем фильтр по partner_id

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
                    31 => 'Создание группы',
                    32 => 'Изменение группы',
                    33 => 'Удаление группы',
                ];
                return $typeLabels[$log->action] ?? 'Неизвестный тип';
            })
            ->make(true);
    }

}
