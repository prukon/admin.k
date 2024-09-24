<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Filters\TeamFilter;
use App\Http\Requests\Team\FilterRequest;
use App\Models\Setting;
use App\Models\Team;
use App\Models\TeamPrice;
use App\Models\UserPrice;
use App\Models\User;
use App\Models\Weekday;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
//use Illuminate\Support\Facades\Log;
use App\Models\Log;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class SettingPricesController extends Controller
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

    public function index(FilterRequest $request)
    {
        $allTeams = Team::all();
        $teamPrices = collect(); // Пустая коллекция по умолчанию
        $logs = Log::with('author')->orderBy('created_at', 'desc')->get();


        if (isset($_GET['current-month'])) {

            function mb_ucfirst($string, $encoding = 'UTF-8') {
                return mb_strtoupper(mb_substr($string, 0, 1, $encoding), $encoding) .
                    mb_substr($string, 1, null, $encoding);
            }

            Carbon::setLocale('ru');
            $currentDate = mb_ucfirst(Carbon::now()->translatedFormat('F Y'));
//            dump($currentDate);

            $setting = Setting::firstOrCreate([], ['date' => $currentDate]);
            if ($setting) {
                // Изменяем поле date на новое значение
                $setting->date = $currentDate; // Здесь можно использовать $month или любую другую логику для определения нового значения
                // Сохраняем изменения в базе данных
                $setting->save();
            }


//         Устанавливаем локаль на русском
        if ($currentDate) {
            $teamPrices = TeamPrice::where('month', $currentDate)->get();
        }
    } else {

            $currentDate = Setting::where('id', 1)->first();
            $currentDate = $currentDate->date;

            if ($currentDate) {
            $teamPrices = TeamPrice::where('month', $currentDate)->get();
        }
    }






        return view("admin/settingPrices", compact(
            "allTeams",
            'currentDate',
            'teamPrices',
            'logs'
        ));
    }

    // AJAX ПОДРОБНО. Получение списка пользователей
    public function getTeamPrice(Request $request)
    {
        $selectedDate = $request->query('selectedDate');
        $teamId = $request->query('teamId');
        $usersTeam = User::where('team_id', $teamId)
            ->where('is_enabled', true)
            ->orderBy('name', 'asc')
            ->get();

        $usersPrice = [];
            foreach ($usersTeam as $user) {
            $userPrice = UserPrice::firstOrCreate(
                [
                    'month' => $selectedDate,
                    'user_id' => $user->id
                ],
                [
                    'price' => 0
                ]
            );
                $userPrice->name = $user->name;

                $usersPrice[] = $userPrice;
        }



//        foreach ($usersTeam as $user) {
//            $userPrice = UserPrice::where('month', $selectedDate)
//                ->where('user_id', $user->id)
//                ->first();
//            if ($userPrice) {
//                $usersPrice[] = $userPrice;
//            }
//        }

        if ($usersTeam) {
            return response()->json([
                'success' => true,
                'usersTeam' => $usersTeam,
                'usersPrice' => $usersPrice,
            ]);
        } else {
            return response()->json(['success' => false]);
        }
    }

    // AJAX SELECT DATE. Обработчик изменения даты
    public function updateDate(Request $request)
    {
        $allTeams = Team::all();

        $month = ucfirst($request->query('month')); // Преобразуем первую букву месяца в заглавную
        if ($month) {
            $setting = Setting::firstOrCreate([], ['date' => $month]);
            if ($setting) {
                // Изменяем поле date на новое значение
                $setting->date = $month; // Здесь можно использовать $month или любую другую логику для определения нового значения
                // Сохраняем изменения в базе данных
                $setting->save();
                foreach ($allTeams as $team) {
                    TeamPrice::firstOrCreate(
                        [
                            'team_id' => $team->id,
                            'month' => $month
                        ],
                        [
                            'price' => 0 // можно задать дефолтное значение для price, если нужно
                        ]
                    );
                }
                return response()->json([
                    'success' => true,
                    'month' => $month,
                ]);
            } else {
                return response()->json([
                    'success' => false]);
            }
        }
    }

    //AJAX Кнопка ОК. Установка цен группе и юзерам.
    public function setTeamPrice(Request $request)
    {
        $teamPrice = $request->query('teamPrice');
        $teamId = $request->query('teamId');
        $selectedDate = $request->query('selectedDate');
        $usersTeam = User::where('team_id', $teamId)->get();
        $authorId = auth()->id(); // Авторизованный пользователь
        $teamTitle = Team::where('id', $teamId)->first()->title;


        TeamPrice::updateOrCreate(
            [
                'team_id' => $teamId,
                'month' => $selectedDate,
            ],
            [
                'price' => $teamPrice
            ]
        );

        Log::create([
            'type' => 3, // Лог для обновления цены команды
            'author_id' => $authorId,
            'description' => "Обновлена цена : {$teamPrice} руб. Группа: {$teamTitle}. ID: {$teamId}. Дата: {$selectedDate}.",
            'created_at' => now(),
        ]);


        // Обновляем цены для пользователей
        $users = User::where('team_id', $teamId)->get(); // Предполагается, что пользователи связаны с командами

        foreach ($users as $user) {
            $userPrice = UserPrice::where('user_id', $user['id'])
                ->where('month', $selectedDate)
                ->where('is_paid', false)
                ->first();

            if ($userPrice) {
                $userPrice->update([
                    'price' => $teamPrice
                ]);
            } else {
                UserPrice::create([
                    'user_id' => $user['id'],
                    'month' => $selectedDate,
                    'price' => $teamPrice,
                    'is_paid' => false
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'teamPrice' => $teamPrice,
            'selectedDate' => $selectedDate,
            'teamId' => $teamId,
        ]);
    }

    //AJAX ПРИМЕНИТЬ слева.Установка цен всем группам
    public function setPriceAllTeams(Request $request)
    {
        $selectedDate = $request->query('selectedDate');
        $teamsData = json_decode($request->query('teamsData'), true);
        $authorId = auth()->id(); // Авторизованный пользователь

        // Перебираем массив и обновляем цены команд
        foreach ($teamsData as $teamData) {
            // Обновляем цены для групп
            $team = Team::where('title', $teamData['name'])->first();
            if ($team) {
                // Обновляем или создаем запись в таблице team_prices
                TeamPrice::updateOrCreate(
                    [
                        'team_id' => $team->id,
                        'month' => $selectedDate
                    ],
                    [
                        'price' => $teamData['price']
                    ]
                );

                // Логируем успешное обновление цены для команды
                Log::create([
                    'type' => 1, // Лог для обновления цены команды
                    'author_id' => $authorId,
                    'description' => "Обновлена цена: {$teamData['price']} руб. Команда: {$team->title}. ID: {$team->id}. Дата: {$selectedDate}.",
                    'created_at' => now(),
                ]);
            }

            // Обновляем цены для пользователей
            $users = User::where('team_id', $team->id)->get(); // Предполагается, что пользователи связаны с командами

            foreach ($users as $user) {
                $userPrice = UserPrice::where('user_id', $user['id'])
                    ->where('month', $selectedDate)
                    ->where('is_paid', false)
                    ->first();

                if ($userPrice) {
                    $userPrice->update([
                        'price' => $teamData['price']
                    ]);

                    // Логируем успешное обновление цены для пользователя
//                    Log::create([
//                        'type' => 2, // Лог для обновления цены пользователя
//                        'author_id' => $authorId,
//                        'descr    iption' => "Обновлена цена для пользователя {$user['name']} (ID: {$user['id']}) на дату {$selectedDate}.",
//                        'created_at' => now(),
//                    ]);
                } else {
                    UserPrice::create([
                        'user_id' => $user['id'],
                        'month' => $selectedDate,
                        'price' => $teamData['price'],
                        'is_paid' => false
                    ]);

                    // Логируем создание новой записи цены для пользователя
//                    Log::create([
//                        'type' => 2, // Лог для создания цены пользователя
//                        'author_id' => $authorId,
//                        'description' => "Создана новая запись цены для пользователя {$user['name']} (ID: {$user['id']}) на дату {$selectedDate}.",
//                        'created_at' => now(),
//                    ]);
                }
            }
        }

        return response()->json([
            'success' => true,
        ]);
    }

    //AJAX ПРИМЕНИТЬ справа.Установка цен всем ученикам
    public function setPriceAllUsers(Request $request)
        {

            $selectedDate = $request->query('selectedDate');
            $usersPrice = json_decode($request->query('usersPrice'), true);
            $authorId = auth()->id(); // Авторизованный пользователь


            foreach ($usersPrice as $priceData) {

                $userPriceRecord = UserPrice::where('user_id', $priceData['user_id'])
                    ->where('month', $selectedDate)
                    ->where('is_paid', 0)
                    ->first();

                if ($userPriceRecord) {
//                    Log::info('Применить справа: ', ['id' => $priceData['id'], 'price' => $priceData['price']]);
                    $userPriceRecord->update([
                        'price' => $priceData['price']
                    ]);

                    // Логируем успешное обновление цены для команды
                    Log::create([
                        'type' => 2, // Лог для обновления цены команды
                        'author_id' => $authorId,
                        'description' => "Обновлена цена : {$priceData['price']} руб. Имя: {$priceData['name']}. ID: {$priceData['user_id']}. Дата: {$selectedDate}.",
                        'created_at' => now(),
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'usersPrice' => $usersPrice,
                'selectedDate' => $selectedDate
            ]);
        }

    // Метод для обработки DataTables запросов
    public function getLogsData()
    {
        $logs = Log::with('author')->select('logs.*');

        return DataTables::of($logs)
            ->addColumn('author', function ($log) {
                return $log->author ? $log->author->name : 'Неизвестно';
            })
            ->editColumn('created_at', function ($log) {
                return $log->created_at->format('d.m.Y / H:i:s');
            })
            ->editColumn('type', function ($log) {
                // Логика для преобразования типа
                $typeLabels = [
                    1 => 'Изменение цен во всех группах',
                    2 => 'Индивидуальное изменение цен',
                    3 => 'Изменение цен в одной группе',
                ];
                return $typeLabels[$log->type] ?? 'Неизвестный тип';
            })
            ->make(true);
    }
}