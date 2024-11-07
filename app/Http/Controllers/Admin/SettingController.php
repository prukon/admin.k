<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Filters\TeamFilter;
use App\Http\Requests\Team\FilterRequest;
use App\Models\Log;
use App\Models\MenuItem;
use App\Models\Setting;
use App\Models\SocialItem;
use App\Models\Team;
use App\Models\TeamPrice;
use App\Models\UserPrice;
use App\Models\User;
use App\Models\Weekday;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
        $menuItems = MenuItem::all();
        $socialItems = SocialItem::all(); // Получаем все записи социальных сетей из базы данных
        $setting = Setting::where('name', 'textForUsers')->first();
        $textForUsers = $setting ? $setting->text : null;


        return view("admin/setting", compact(
            "textForUsers",
            "menuItems",
            "socialItems"
        ));
    }

//    AJAX Активность регистрации
    public function registrationActivity(Request $request)
    {
        $isRegistrationActivity = $request->query('isRegistrationActivity');
        $authorId = auth()->id(); // Авторизованный пользователь

        DB::transaction(function () use ($isRegistrationActivity, $authorId) {


//        if ($isRegistrationActivity) {
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
//        }
            if ($isRegistrationActivity == 1) {
                $isRegistrationActivityValue = "Вкл.";
            } else {
                $isRegistrationActivityValue = "Выкл.";
            }

            // Логирование изменения пароля
            Log::create([
                'type' => 1,
                'action' => 70,
                'author_id' => $authorId,
                'description' => ("Включение регистрации в сервисе: " . $isRegistrationActivityValue),
                'created_at' => now(),
            ]);

        });
        return response()->json([
            'success' => true,
            'isRegistrationActivity' => $isRegistrationActivity,

        ]);
    }

//    AJAX Текст сообщения для юзеров
    public function textForUsers(Request $request)
    {
        // Получаем данные из тела запроса
        $data = json_decode($request->getContent(), true);
        $textForUsers = $data['textForUsers'] ?? null;
        $authorId = auth()->id(); // Авторизованный пользователь

        DB::transaction(function () use ($textForUsers, $authorId) {

            Setting::updateOrCreate(
                [
                    'name' => "textForUsers",
                ],
                [
                    'text' => $textForUsers
                ]
            );

            // Логирование изменения пароля
            Log::create([
                'type' => 1,
                'action' => 70,
                'author_id' => $authorId,
                'description' => ("Изменение текста уведомления: " . $textForUsers),
                'created_at' => now(),
            ]);
        });
        return response()->json([
            'success' => true,
            'textForUsers' => $textForUsers,
        ]);
    }

    //Журнал логов
    public function logsAllData(Request $request)
    {
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

                    50 => 'Платежи',

                    60 => 'Расписание',

                    70 => 'Изменение настроек'

                ];
                return $typeLabels[$log->action] ?? 'Неизвестный тип';
            })
            ->make(true);
    }

    //сохрание меню в шапке
//    public function saveMenuItems(Request $request)
//    {
//        $errors = [];
//        $validatedData = [];
//        $authorId = auth()->id(); // Авторизованный пользователь
//
//
//        foreach ($request->input('menu_items', []) as $key => $data) {
//            // Создаем валидатор для каждого элемента меню
//            $validator = \Validator::make($data, [
//                'name' => ['required', 'max:20', 'regex:/^[\pL\pN\s]+$/u'], // Буквы, цифры и пробелы
//                'link' => ['nullable', 'regex:/^(\/[\S]*|https?:\/\/[^\s]+)$/'],
//            ], [
//                'name.required' => 'Заполните название.',
//                'name.max' => 'Название не может быть длиннее 20 символов.',
//                'name.regex' => 'Название не может содержать спецсимволы.',
//                'link.regex' => 'Введите корректный URL.',
//            ]);
//
//            if ($validator->fails()) {
//                foreach ($validator->errors()->messages() as $field => $messages) {
//                    $errors["menu_items[$key][$field]"] = $messages;
//                }
//            } else {
//                // Приведение target_blank к числовому значению
//                $data['target_blank'] = !empty($data['target_blank']) ? 1 : 0;
//                $validatedData[$key] = $data;
//            }
//        }
//
//        if (!empty($errors)) {
//            return response()->json(['success' => false, 'errors' => $errors], 422);
//        }
//
//        DB::transaction(function () use ($validatedData, $authorId, $request) {
//
//
//            foreach ($validatedData as $key => $data) {
//                if (is_numeric($key)) {
//                    $menuItem = MenuItem::find($key);
//                    if ($menuItem) {
//                        $menuItem->update([
//                            'name' => $data['name'],
//                            'link' => $data['link'] ?: '',
//                            'target_blank' => $data['target_blank'],
//                        ]);
//                    }
//                } else {
//                    MenuItem::create([
//                        'name' => $data['name'],
//                        'link' => $data['link'] ?: '',
//                        'target_blank' => $data['target_blank'],
//                    ]);
//                }
//            }
//
//            if ($request->has('deleted_items')) {
//                MenuItem::whereIn('id', $request->input('deleted_items'))->delete();
//            }
//
//
//            Log::create([
//                'type' => 1,
//                'action' => 70,
//                'author_id' => $authorId,
//                'description' => ("Изменены пункты главного мен: " . $request),
//                'created_at' => now(),
//            ]);
//        });
//        return response()->json(['success' => true]);
//    }



//    public function saveMenuItems(Request $request)
//    {
//        $errors = [];
//        $validatedData = [];
//        $authorId = auth()->id(); // Авторизованный пользователь
//        $changedItems = []; // Массив для хранения изменённых пунктов меню
//
//        foreach ($request->input('menu_items', []) as $key => $data) {
//            // Создаем валидатор для каждого элемента меню
//            $validator = \Validator::make($data, [
//                'name' => ['required', 'max:20', 'regex:/^[\pL\pN\s]+$/u'], // Буквы, цифры и пробелы
//                'link' => ['nullable', 'regex:/^(\/[\S]*|https?:\/\/[^\s]+)$/'],
//            ], [
//                'name.required' => 'Заполните название.',
//                'name.max' => 'Название не может быть длиннее 20 символов.',
//                'name.regex' => 'Название не может содержать спецсимволы.',
//                'link.regex' => 'Введите корректный URL.',
//            ]);
//
//            if ($validator->fails()) {
//                foreach ($validator->errors()->messages() as $field => $messages) {
//                    $errors["menu_items[$key][$field]"] = $messages;
//                }
//            } else {
//                // Приведение target_blank к числовому значению
//                $data['target_blank'] = !empty($data['target_blank']) ? 1 : 0;
//                $validatedData[$key] = $data;
//            }
//        }
//
//        if (!empty($errors)) {
//            return response()->json(['success' => false, 'errors' => $errors], 422);
//        }
//
//        DB::transaction(function () use ($validatedData, $authorId, $request, &$changedItems) {
//
//            foreach ($validatedData as $key => $data) {
//                if (is_numeric($key)) {
//                    $menuItem = MenuItem::find($key);
//                    if ($menuItem) {
//                        // Сохраняем старые и новые данные для логирования
//                        $oldData = $menuItem->toArray();
//                        $menuItem->update([
//                            'name' => $data['name'],
//                            'link' => $data['link'] ?: '',
//                            'target_blank' => $data['target_blank'],
//                        ]);
//
//                        // Добавляем изменённый элемент в массив изменений
//                        $changedItems[] = [
//                            'id' => $menuItem->id,
//                            'old' => $oldData,
//                            'new' => $data
//                        ];
//                    }
//                } else {
//                    $newItem = MenuItem::create([
//                        'name' => $data['name'],
//                        'link' => $data['link'] ?: '',
//                        'target_blank' => $data['target_blank'],
//                    ]);
//
//                    // Добавляем новый элемент в массив изменений
//                    $changedItems[] = [
//                        'id' => $newItem->id,
//                        'old' => null,
//                        'new' => $data
//                    ];
//                }
//            }
//
//            if ($request->has('deleted_items')) {
//                MenuItem::whereIn('id', $request->input('deleted_items'))->delete();
//
//                // Логируем удалённые элементы
//                foreach ($request->input('deleted_items') as $deletedId) {
//                    $changedItems[] = [
//                        'id' => $deletedId,
//                        'old' => 'deleted',
//                        'new' => null
//                    ];
//                }
//            }
//
//            Log::create([
//                'type' => 1,
//                'action' => 70,
//                'author_id' => $authorId,
//                'description' => "Изменены пункты меню: " . json_encode($changedItems, JSON_UNESCAPED_UNICODE),
//                'created_at' => now(),
//            ]);
//        });
//
//        return response()->json(['success' => true]);
//    }


//    public function saveMenuItems(Request $request)
//    {
//        $errors = [];
//        $validatedData = [];
//        $authorId = auth()->id(); // Авторизованный пользователь
//        $changedItems = []; // Массив для хранения изменённых пунктов меню
//
//        foreach ($request->input('menu_items', []) as $key => $data) {
//            // Создаем валидатор для каждого элемента меню
//            $validator = \Validator::make($data, [
//                'name' => ['required', 'max:20', 'regex:/^[\pL\pN\s]+$/u'], // Буквы, цифры и пробелы
//                'link' => ['nullable', 'regex:/^(\/[\S]*|https?:\/\/[^\s]+)$/'],
//            ], [
//                'name.required' => 'Заполните название.',
//                'name.max' => 'Название не может быть длиннее 20 символов.',
//                'name.regex' => 'Название не может содержать спецсимволы.',
//                'link.regex' => 'Введите корректный URL.',
//            ]);
//
//            if ($validator->fails()) {
//                foreach ($validator->errors()->messages() as $field => $messages) {
//                    $errors["menu_items[$key][$field]"] = $messages;
//                }
//            } else {
//                // Приведение target_blank к числовому значению
//                $data['target_blank'] = !empty($data['target_blank']) ? 1 : 0;
//                $validatedData[$key] = $data;
//            }
//        }
//
//        if (!empty($errors)) {
//            return response()->json(['success' => false, 'errors' => $errors], 422);
//        }
//
//        DB::transaction(function () use ($validatedData, $authorId, $request, &$changedItems) {
//
//            foreach ($validatedData as $key => $data) {
//                if (is_numeric($key)) {
//                    $menuItem = MenuItem::find($key);
//                    if ($menuItem) {
//                        // Сохраняем старые и новые данные для логирования в читаемом формате
//                        $oldDescription = "{$menuItem->name}, {$menuItem->link}, " . ($menuItem->target_blank ? "Открывать в новой вкладке" : "Открывать в той же вкладке");
//                        $newDescription = "{$data['name']}, {$data['link']}, " . ($data['target_blank'] ? "Открывать в новой вкладке" : "Открывать в той же вкладке");
//
//                        $changedItems[] = "\"$oldDescription\" на \"$newDescription\"";
//
//                        // Обновляем элемент
//                        $menuItem->update([
//                            'name' => $data['name'],
//                            'link' => $data['link'] ?: '',
//                            'target_blank' => $data['target_blank'],
//                        ]);
//                    }
//                } else {
//                    $newItem = MenuItem::create([
//                        'name' => $data['name'],
//                        'link' => $data['link'] ?: '',
//                        'target_blank' => $data['target_blank'],
//                    ]);
//
//                    $newDescription = "{$data['name']}, {$data['link']}, " . ($data['target_blank'] ? "Открывать в новой вкладке" : "Открывать в той же вкладке");
//                    $changedItems[] = "Добавлен новый пункт меню: \"$newDescription\"";
//                }
//            }
//
//            if ($request->has('deleted_items')) {
//                MenuItem::whereIn('id', $request->input('deleted_items'))->delete();
//
//                foreach ($request->input('deleted_items') as $deletedId) {
//                    $changedItems[] = "Удалён пункт меню с ID: $deletedId";
//                }
//            }
//
//            Log::create([
//                'type' => 1,
//                'action' => 70,
//                'author_id' => $authorId,
//                'description' => "Изменены пункты меню:\n" . implode("\n", $changedItems),
//                'created_at' => now(),
//            ]);
//        });
//
//        return response()->json(['success' => true]);
//    }


    public function saveMenuItems(Request $request)
    {
        $errors = [];
        $validatedData = [];
        $authorId = auth()->id(); // Авторизованный пользователь
        $oldItems = []; // Старые данные пунктов меню
        $newItems = []; // Новые данные пунктов меню

        foreach ($request->input('menu_items', []) as $key => $data) {
            // Создаем валидатор для каждого элемента меню
            $validator = \Validator::make($data, [
                'name' => ['required', 'max:20', 'regex:/^[\pL\pN\s]+$/u'], // Буквы, цифры и пробелы
                'link' => ['nullable', 'regex:/^(\/[\S]*|https?:\/\/[^\s]+)$/'],
            ], [
                'name.required' => 'Заполните название.',
                'name.max' => 'Название не может быть длиннее 20 символов.',
                'name.regex' => 'Название не может содержать спецсимволы.',
                'link.regex' => 'Введите корректный URL.',
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->messages() as $field => $messages) {
                    $errors["menu_items[$key][$field]"] = $messages;
                }
            } else {
                // Приведение target_blank к числовому значению
                $data['target_blank'] = !empty($data['target_blank']) ? 1 : 0;
                $validatedData[$key] = $data;
            }
        }

        if (!empty($errors)) {
            return response()->json(['success' => false, 'errors' => $errors], 422);
        }

        DB::transaction(function () use ($validatedData, $authorId, $request, &$oldItems, &$newItems) {

            foreach ($validatedData as $key => $data) {
                if (is_numeric($key)) {
                    $menuItem = MenuItem::find($key);
                    if ($menuItem) {
                        // Формируем старое значение с учетом target_blank
                        $oldTargetBlank = $menuItem->target_blank ? ", Открывать в новой вкладке" : "";
                        $oldItems[] = "\"{$menuItem->name}, {$menuItem->link}{$oldTargetBlank}\"";

                        // Обновляем элемент и формируем новое значение
                        $menuItem->update([
                            'name' => $data['name'],
                            'link' => $data['link'] ?: '',
                            'target_blank' => $data['target_blank'],
                        ]);

                        $newTargetBlank = $data['target_blank'] ? ", Открывать в новой вкладке" : "";
                        $newItems[] = "\"{$data['name']}, {$data['link']}{$newTargetBlank}\"";
                    }
                } else {
                    $newItem = MenuItem::create([
                        'name' => $data['name'],
                        'link' => $data['link'] ?: '',
                        'target_blank' => $data['target_blank'],
                    ]);

                    // Для новых элементов добавляем только новое значение
                    $newTargetBlank = $data['target_blank'] ? ", Открывать в новой вкладке" : "";
                    $newItems[] = "\"{$data['name']}, {$data['link']}{$newTargetBlank}\"";
                }
            }

            if ($request->has('deleted_items')) {
                MenuItem::whereIn('id', $request->input('deleted_items'))->delete();
                foreach ($request->input('deleted_items') as $deletedId) {
                    $oldItems[] = "Удалён пункт меню с ID: $deletedId";
                }
            }

            // Формируем читаемый лог
            $description = "Изменены пункты меню:\n" . implode("\n", $oldItems) . "\nна:\n" . implode("\n", $newItems);

            Log::create([
                'type' => 1,
                'action' => 70,
                'author_id' => $authorId,
                'description' => $description,
                'created_at' => now(),
            ]);
        });

        return response()->json(['success' => true]);
    }





//    Сохранение соц. меню в шапке
    public function saveSocialItems(Request $request)
    {
        $errors = [];
        $validatedData = [];

        foreach ($request->input('social_items', []) as $key => $data) {
//            $validator = \Validator::make($data, [
//                'link' => ['nullable', 'url'], // Валидация только для URL
//            ], [
//                'link.url' => 'Введите корректный URL.',
//            ]);

            $validator = \Validator::make($data, [
                'link' => ['nullable', 'regex:/^(\/[\S]*|https?:\/\/[^\s]+)$/'],
            ], [
                'link.regex' => 'Введите корректный URL.',
            ]);


            if ($validator->fails()) {
                foreach ($validator->errors()->messages() as $field => $messages) {
                    $errors["social_items[$key][$field]"] = $messages;
                }
            } else {
                $validatedData[$key] = $data;
            }
        }

        if (!empty($errors)) {
            return response()->json(['success' => false, 'errors' => $errors], 422);
        }

        foreach ($validatedData as $key => $data) {
            $socialItem = SocialItem::find($key);
            if ($socialItem) {
                $socialItem->update([
                    'name' => $data['name'], // Поле `name` сохраняется без валидации
                    'link' => $data['link'] ?: '',
                ]);
            }
        }

        return response()->json(['success' => true]);
    }

}