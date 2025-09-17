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
//use App\Models\Log;
use App\Models\MyLog;

//use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use function Termwind\dd;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Str;


class SettingPricesController extends Controller
{

    public function formatedDate2($month)
    {
        // Массив соответствий русских и английских названий месяцев
        $months = [
            'Январь' => 'January',
            'Февраль' => 'February',
            'Март' => 'March',
            'Апрель' => 'April',
            'Май' => 'May',
            'Июнь' => 'June',
            'Июль' => 'July',
            'Август' => 'August',
            'Сентябрь' => 'September',
            'Октябрь' => 'October',
            'Ноябрь' => 'November',
            'Декабрь' => 'December',
        ];

        // Разделение строки на месяц и год
        $parts = explode(' ', $month);
        if (count($parts) === 2 && isset($months[$parts[0]])) {
            $month = $months[$parts[0]] . ' ' . $parts[1]; // Замена русского месяца на английский
        } else {
            return null; // Если формат не соответствует "Месяц Год", возвращаем null
        }

        // Преобразуем строку в объект DateTime
        try {
            $date = \DateTime::createFromFormat('F Y', $month); // F - имя месяца, Y - год
            if ($date) {
                return $date->format('Y-m-01'); // Всегда возвращаем первое число месяца
            }
            return null; // Возвращаем null, если не удалось преобразовать
        } catch (\Exception $e) {
            \Log::error('Ошибка преобразования даты: ' . $e->getMessage());
            return null;
        }
    }

    public function index(FilterRequest $request)
    {
        $partnerId = app('current_partner')->id;

        // 1) все команды текущего партнёра
        $allTeams = Team::where('partner_id', $partnerId)
            ->orderBy('order_by', 'asc')// сортировка по order_by по возрастанию
            ->whereNull('deleted_at')
            ->get();

        // 2) история изменений
        $logs = MyLog::with('author')
            ->where('partner_id', $partnerId)
            ->orderBy('created_at','desc')
            ->get();

        // 3) месяц из сессии или текущий
        Carbon::setLocale('ru');
        $monthString = session('prices_month',
            Str::ucfirst(Carbon::now()->translatedFormat('F Y'))
        );

        // 4) приводим в date‑формат для фильтрации
        $monthDate = $this->formatedDate($monthString);

        // 5) убеждаемся, что для всех команд есть TeamPrice
        foreach($allTeams as $team) {
            TeamPrice::firstOrCreate(
                ['team_id'=> $team->id, 'new_month'=> $monthDate],
                ['price'  => 0]
            );
        }

        // 6) загружаем цены именно за этот месяц
        $teamPrices = TeamPrice::with('team')
            ->where('new_month', $monthDate)
            ->whereHas('team', fn($q)=>
                $q->where('partner_id',$partnerId)
                    ->whereNull('deleted_at')
            )
            ->get();

        return view('admin.settingPrices', compact(
            'allTeams',
            'monthString',
            'teamPrices'
//            'logs'
        ));
    }

    // AJAX ПОДРОБНО. Получение списка пользователей
    public function getTeamPrice(Request $request)
    {
        // Получаем данные из тела запроса
        $data = json_decode($request->getContent(), true);
        $selectedDate = $data['selectedDate'] ?? null;
        $teamId = $data['teamId'] ?? null;

        $usersTeam = User::where('team_id', $teamId)
            ->where('is_enabled', true)
            ->orderBy('name', 'asc')
            ->get();

        $usersPrice = [];
        $selectedDate = $this->formatedDate($selectedDate);


        \Log::info('$usersTeam:', $usersTeam->toArray());

        foreach ($usersTeam as $user) {
            \Log::info('$user:', $user->toArray());
            $userPrice = UserPrice::firstOrCreate(
                [
                    'new_month' => $selectedDate,
                    'user_id' => $user->id
                ],
                [
                    'price' => 0
                ]
            );
            $userPrice->name = $user->name;
            $userPrice->refresh();
            $userPrice->load('user'); // Загружаем отношение для каждой модели
            $usersPrice[] = $userPrice;
        }

        // Преобразуем каждую модель UserPrice в массив
        \Log::info('$usersPrice:', array_map(function ($item) {
            return $item->toArray();
        }, $usersPrice));

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
        // 1) Валидация входного параметра
        $request->validate([
            'month' => 'required|string|max:255',
        ]);

        // 2) Сохраняем выбор месяца (например "Сентябрь 2024") в сессии
        $month = ucfirst($request->input('month'));
        session(['prices_month' => $month]);

        // 3) Преобразуем строку "Сентябрь 2024" в формат date для new_month
        $formatedMonth = $this->formatedDate($month);

        // 4) Получаем команды, принадлежащие текущему партнёру
        $partnerId = app('current_partner')->id;
        $teams = Team::where('partner_id', $partnerId)
            ->whereNull('deleted_at')
            ->get();

        // 5) Для каждой из этих команд создаём запись в team_prices, если её ещё нет
        foreach ($teams as $team) {
            TeamPrice::firstOrCreate(
                [
                    'team_id'   => $team->id,
                    'new_month' => $formatedMonth,
                ],
                [
                    'price' => 0,
                ]
            );
        }

        // 6) Отправляем ответ
        return response()->json([
            'success' => true,
            'month'   => $month,
        ]);
    }

    /**
     * Помогает преобразовать строку "Сентябрь 2024" в YYYY-MM-01
     */
    protected function formatedDate(string $monthString): string
    {
        // Ваши реализации: разбить на месяц и год, собрать дату первого числа
        // Например:
        $parts = explode(' ', $monthString);
        $ruMonths = [
            'январь'=>1, 'февраль'=>2, 'март'=>3, 'апрель'=>4,
            'май'=>5, 'июнь'=>6, 'июль'=>7, 'август'=>8,
            'сентябрь'=>9, 'октябрь'=>10, 'ноябрь'=>11, 'декабрь'=>12
        ];
        $month  = mb_strtolower($parts[0], 'UTF-8');
        $year   = $parts[1] ?? date('Y');
        $mNum   = $ruMonths[$month] ?? date('n');
        // Возвращаем первый день месяца в формате YYYY-MM-DD
        return sprintf('%04d-%02d-01', (int)$year, $mNum);
    }

    //AJAX Кнопка ОК. Установка цен группе и юзерам.
    public function setTeamPrice(Request $request)
    {
        $partnerId = app('current_partner')->id;
        // Получаем данные из тела запроса
        $data = json_decode($request->getContent(), true);
        $teamPrice = $data['teamPrice'] ?? null;
        $teamId = $data['teamId'] ?? null;
        $selectedDate = $data['selectedDate'] ?? null;


        $usersTeam = User::where('team_id', $teamId)->get();
        $authorId = auth()->id(); // Авторизованный пользователь
        $teamTitle = Team::where('id', $teamId)->first()->title;
        $selectedDateString = $selectedDate;
        $selectedDate = $this->formatedDate($selectedDate);


        DB::transaction(function () use ($teamId, $selectedDate, $teamPrice, $authorId, $teamTitle, $selectedDateString, $partnerId) {

            TeamPrice::updateOrCreate(
                [
                    'team_id' => $teamId,
                    'new_month' => $selectedDate,
                ],
                [
                    'price' => $teamPrice
                ]
            );

            MyLog::create([
                'type' => 1,
                'action' => 13, // Изменение цен в одной группе
                'author_id' => $authorId,
                'partner_id'  => $partnerId,
                'description' => "Обновлена цена : {$teamPrice} руб. Группа: {$teamTitle}. ID: {$teamId}. Дата: {$selectedDateString}.",
                'created_at' => now(),
            ]);


            // Обновляем цены для пользователей
//            $users = User::where('team_id', $teamId)->get(); // Предполагается, что пользователи связаны с командами

            $users = User::where('team_id', $teamId)
                ->where('is_enabled', 1)
                ->get();


            foreach ($users as $user) {
                $userPrice = UserPrice::where('user_id', $user['id'])
                    ->where('new_month', $selectedDate)
                    ->first();

                if ($userPrice) {
                    // Если запись существует и не оплачена, обновляем её
                    if (!$userPrice->is_paid) {
                        $userPrice->update([
                            'price' => $teamPrice
                        ]);
                    }
                } else {
                    // Если записи нет, создаем новую
                    UserPrice::create([
                        'user_id' => $user['id'],
                        'new_month' => $selectedDate,
                        'price' => $teamPrice,
                        'is_paid' => false
                    ]);
                }
            }
        });

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
        $partnerId = app('current_partner')->id;

        // Получаем данные из тела запроса
        $data = json_decode($request->getContent(), true);

        $selectedDate = $data['selectedDate'] ?? null;
        $teamsData = $data['teamsData'] ?? null;

        if (is_null($teamsData) || !is_array($teamsData)) {
            return response()->json(['error' => 'Invalid teams data1'], 400);
        }



        $selectedDateString = $selectedDate;
        $selectedDate = $this->formatedDate($selectedDate);

        if (is_null($teamsData) || !is_array($teamsData)) {
            return response()->json(['error' => 'Invalid teams data2'], 400);
        }


        $authorId = auth()->id(); // Авторизованный пользователь


        DB::transaction(function () use ($selectedDate, $authorId, $selectedDateString, $teamsData, $partnerId) {

            // Перебираем массив и обновляем цены команд
            foreach ($teamsData as $teamData) {
                // Обновляем цены для групп
//                $team = Team::where('title', $teamData['name'])->first();
                $teamId = $teamData['teamId'];
                if ($teamId) {
                    // Обновляем или создаем запись в таблице team_prices
                    TeamPrice::updateOrCreate(
                        [
                            'team_id' => $teamId,
                            'new_month' => $selectedDate
                        ],
                        [
                            'price' => $teamData['price']
                        ]
                    );

                    // Логируем успешное обновление цены для команды
                    MyLog::create([
                        'type' => 1,
                        'action' => 11, // Изменение цен во всех группах
                        'author_id' => $authorId,
                        'partner_id'  => $partnerId,
                        'description' => "Обновлена цена: {$teamData['price']} руб. Команда: {$teamId}. ID: {$teamId}. Дата: {$selectedDateString}.",
                        'created_at' => now(),
                    ]);
                }

                // Обновляем цены для пользователей
//                $users = User::where('team_id', $teamId)->get(); // Предполагается, что пользователи связаны с командами

                $users = User::where('team_id', $teamId)
                    ->where('is_enabled', 1)
                    ->get();




                foreach ($users as $user) {
                    $userPrice = UserPrice::where('user_id', $user['id'])
                        ->where('new_month', $selectedDate)
                        ->first();

                    if ($userPrice) {
                        // Если запись существует и не оплачена, обновляем её
                        if (!$userPrice->is_paid) {
                            $userPrice->update([
                                'price' => $teamData['price']
                            ]);
                        }
                    } else {
                        // Если записи нет, создаем новую
                        UserPrice::create([
                            'user_id' => $user['id'],
                            'new_month' => $selectedDate,
                            'price' => $teamData['price'],
                            'is_paid' => false
                        ]);
                    }
                }

            }
        });
        return response()->json([
            'success' => true,
        ]);
    }

    //AJAX ПРИМЕНИТЬ справа.Установка цен всем ученикам
    public function setPriceAllUsers(Request $request)
    {
        $partnerId = app('current_partner')->id;

        // Получаем JSON-содержимое запроса и декодируем его
        $data = json_decode($request->getContent(), true);

        // Проверяем, что данные переданы корректно
        $selectedDate = $data['selectedDate'] ?? null;
        $usersPrice = $data['usersPrice'] ?? null;

        // Проверка данных
        if (is_null($usersPrice) || !is_array($usersPrice)) {
            \Log::error('usersPrice не является массивом или пуст');
            return response()->json(['error' => 'Некорректные данные'], 400);
        }

        $authorId = auth()->id(); // Авторизованный пользователь
        $selectedDateString = $selectedDate;
        $selectedDate = $this->formatedDate($selectedDate); // Предполагаем, что эта функция существует для форматирования даты

        DB::transaction(function () use ($selectedDate, $authorId, $selectedDateString, $usersPrice, $partnerId) {
            foreach ($usersPrice as $priceData) {
                $userPriceRecord = UserPrice::where('user_id', $priceData['user_id'])
                    ->where('new_month', $selectedDate)
                    ->where('is_paid', 0)
                    ->first();

                if ($userPriceRecord) {
                    // Проверка, изменилось ли значение `price`
                    if ($userPriceRecord->price != $priceData['price']) {
                        $userPriceRecord->update([
                            'price' => $priceData['price']
                        ]);

                        // Получаем имя через отношение
//                        $userName = $priceData->user->name ?? 'Неизвестный пользователь'; // Защита от null
                        $userName = $priceData['user']['name'] ?? 'Неизвестный пользователь';


                        // Логируем успешное обновление цены для команды
                        MyLog::create([
                            'type' => 1,
                            'action' => 12, // Лог для обновления цены команды
                            'author_id' => $authorId,
                            'partner_id'  => $partnerId,
                            'description' => "Обновлена цена: {$priceData['price']} руб. Имя: {$userName}. ID: {$priceData['user_id']}. Дата: {$selectedDateString}.",
                            'created_at' => now(),
                        ]);
                    }
                }
            }
        });

//        return response()->json(['status' => 'Цены обновлены при необходимости'], 200);

                return response()->json([
            'success' => true,
            'usersPrice' => $usersPrice,
            'selectedDate' => $selectedDate
        ]);
    }



    // Метод для обработки DataTables запросов
    public function getLogsData()
    {
        $partnerId = app('current_partner')->id;

        $logs = MyLog::with('author')
            ->where('type', 1) // Добавляем условие для фильтрации по type
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
                    11 => 'Изм. цен во всех группах (Применить слева)', //Применить слева
                    12 => 'Инд. изм. цен (Применить справа)', //Применить справа
                    13 => 'Изм. цен в одной группе  (ок)', //Кнопка "ок"
                ];
                return $typeLabels[$log->action] ?? 'Неизвестный тип';
            })
            ->make(true);
    }
}