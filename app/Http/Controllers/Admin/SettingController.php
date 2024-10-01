<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Filters\TeamFilter;
use App\Http\Requests\Team\FilterRequest;
use App\Models\Log;
use App\Models\MenuItem;
use App\Models\Setting;
use App\Models\Team;
use App\Models\TeamPrice;
use App\Models\UserPrice;
use App\Models\User;
use App\Models\Weekday;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Yajra\DataTables\DataTables;



class SettingController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('admin');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */

    public function index()
    {
        $setting = Setting::where('name', 'textForUsers')->first();
        $textForUsers = $setting ? $setting->text : null;

        return view("admin/setting", compact(
            "textForUsers"
        ));
    }
//    AJAX Активность регистрации
    public function registrationActivity(Request $request) {
        $isRegistrationActivity = $request->query('isRegistrationActivity');

        // Обновляем цены для групп
        if ($isRegistrationActivity) {
            $isRegistrationActivity = filter_var($isRegistrationActivity, FILTER_VALIDATE_BOOLEAN);
            // Обновляем или создаем запись в таблице team_prices
            Setting::updateOrCreate(
                [
                    'name' => "registrationActivity",
                ],
                [
                    'status' => $isRegistrationActivity
                ]
            );
        }
        return response()->json([
            'success' => true,
            'isRegistrationActivity' => $isRegistrationActivity,

        ]);
    }
//    AJAX Текст сообщения для юзеров
    public function textForUsers(Request $request) {
        $textForUsers = $request->query('textForUsers');
     if($textForUsers) {
         Setting::updateOrCreate(
             [
                 'name' => "textForUsers",
             ],
             [
                 'text' => $textForUsers
             ]
         );
     }

        return response()->json([
            'success' => true,
            'textForUsers' => $textForUsers,
        ]);
    }

    //Журнал логов
    public function logsAllData(Request $request) {
        $logs = Log::with('author')
//            ->where('type', 1) // Добавляем условие для фильтрации по type
            ->select('logs.*');

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

                    11 => 'Изм. цен во всех группах (Применить слева)', //Применить слева
                    12 => 'Инд. изм. цен (Применить справа)', //Применить справа
                    13 => 'Изм. цен в одной группе  (ок)', //Кнопка "ок"

                    21 => 'Создание пользователя',
                    22 => 'Обновление учетной записи в пользователях',
                    23 => 'Обновление учетной записи',
                    24 => 'Удаление пользователя в пользователях',
                    25 => 'Изменение пароля (админ)',
                    26 => 'Изменение пароля',
                    27 => 'Изменение аватара (админ)',
                    28 => 'Изменение аватара',

                    31 => 'Создание группы',
                    32 => 'Изменение группы',
                    33 => 'Удаление группы',



                    40 => 'Авторизация',


                ];
                return $typeLabels[$log->action] ?? 'Неизвестный тип';
            })
            ->make(true);
    }


    public function saveMenuItems(Request $request)
    {
        $menuItems = $request->input('menu_items');

        // Очистка существующих пунктов меню
        MenuItem::truncate();

        // Сохранение новых пунктов меню
        foreach ($menuItems as $item) {
            MenuItem::create([
                'name' => $item['name'],
                'link' => $item['link'],
                'target_blank' => isset($item['target_blank']) ? true : false,
            ]);
        }

        return response()->json(['success' => true]);
    }


}