<?php

namespace App\Http\Controllers\Admin\Setting;

use App\Http\Controllers\Controller;
use App\Http\Filters\TeamFilter;
use App\Http\Requests\Team\FilterRequest;
//use App\Models\Log;
use App\Models\MyLog;
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
use App\Models\Role;
use App\Models\Permission;
use App\Models\Log;

class SettingController extends Controller
{

    //ВКЛАДКА НАСТРОЙКИ
    //Страница Настройки
    public function showSettings()
    {
        $setting = Setting::where('name', 'textForUsers')->first();
        $textForUsers = $setting ? $setting->text : null;
        return view('admin.setting.index',
            ['activeTab' => 'setting'],
            compact(
                "textForUsers")
        );
    }

    //AJAX Активность регистрации
    public function registrationActivity(Request $request)
    {
        $partnerId = app('current_partner')->id;
        $isRegistrationActivity = $request->query('isRegistrationActivity');
        $authorId = auth()->id(); // Авторизованный пользователь

        DB::transaction(function () use ($isRegistrationActivity, $authorId, $partnerId) {

            $isRegistrationActivity = filter_var($isRegistrationActivity, FILTER_VALIDATE_BOOLEAN);
            // Обновляем или создаем запись в таблице team_prices
            Setting::updateOrCreate(
                [
                    'name' => "registrationActivity",
                    'partner_id' => "$partnerId",
                ],
                [
                    'status' => $isRegistrationActivity
                ]
            );

            if ($isRegistrationActivity == 1) {
                $isRegistrationActivityValue = "Вкл.";
            } else {
                $isRegistrationActivityValue = "Выкл.";
            }

            // Логирование изменения пароля
            MyLog::create([
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

    //AJAX Текст сообщения для юзеров
    public function textForUsers(Request $request)
    {
        $partnerId = app('current_partner')->id;
        // Получаем данные из тела запроса
        $data = json_decode($request->getContent(), true);
        $textForUsers = $data['textForUsers'] ?? null;
        $authorId = auth()->id(); // Авторизованный пользователь

        DB::transaction(function () use ($textForUsers, $authorId, $partnerId) {

            Setting::updateOrCreate(
                [
                    'name' => "textForUsers",
                    'partner_id' => "$partnerId",
                ],
                [
                    'text' => $textForUsers
                ]
            );

            MyLog::create([
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

    //Сохранение меню в шапке
    public function saveMenuItems(Request $request)
    {
        $partnerId = app('current_partner')->id;
          $errors = [];
        $validatedData = [];
        $authorId = auth()->id();

        // Валидация входящих пунктов
        foreach ($request->input('menu_items', []) as $key => $data) {
            $validator = \Validator::make($data, [
                'name' => ['required', 'max:20', 'regex:/^[\pL\pN\s]+$/u'],
                'link' => ['nullable', 'regex:/^(\/[\S]*|https?:\/\/[^\s]+)$/'],
            ], [
                'name.required' => 'Заполните название.',
                'name.max'      => 'Название не может быть длиннее 20 символов.',
                'name.regex'    => 'Название не может содержать спецсимволы.',
                'link.regex'    => 'Введите корректный URL.',
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->messages() as $field => $messages) {
                    $errors["menu_items[$key][$field]"] = $messages;
                }

            } else {
                $data['target_blank'] = !empty($data['target_blank']) ? 1 : 0;
                $validatedData[$key]  = $data;
                \Log::info("Validated menu_items[$key]", ['data' => $data]);
            }
        }

        if (!empty($errors)) {
            \Log::error('saveMenuItems validation errors', ['errors' => $errors]);
            return response()->json(['success' => false, 'errors' => $errors], 422);
        }

        // ИНИЦИАЛИЗАЦИЯ массивов для логирования изменений
        $oldItems = [];
        $newItems = [];

        DB::transaction(function () use (
            $validatedData,
            $authorId,
            $request,
            $partnerId,
            &$oldItems,
            &$newItems
        ) {
            \Log::info('saveMenuItems transaction started', ['count' => count($validatedData)]);

            foreach ($validatedData as $key => $data) {
                if (is_numeric($key)) {
                    // ИЗМЕНЕНО: ищем только свои записи
                    $menuItem = MenuItem::where('partner_id', $partnerId)->find($key);

                    if ($menuItem) {
                        \Log::info('Updating own MenuItem', [
                            'id'   => $key,
                            'old'  => $menuItem->toArray(),
                            'new'  => $data,
                        ]);

                        $oldItems[] = "\"{$menuItem->name}, {$menuItem->link}"
                            . ($menuItem->target_blank ? ", открывать в новой вкладке" : "")
                            . "\"";

                        $menuItem->update([
                            'name'         => $data['name'],
                            'link'         => $data['link'] ?: '',
                            'target_blank' => $data['target_blank'],
                            // partner_id оставляем прежним
                        ]);

                        $newItems[] = "\"{$data['name']}, {$data['link']}"
                            . ($data['target_blank'] ? ", открывать в новой вкладке" : "")
                            . "\"";
                    } else {
                        // ИЗМЕНЕНО: при попытке обновить чужую или несуществующую — создаём новую
                        \Log::warning("MenuItem id {$key} not found for partner {$partnerId}, creating new instead");

                        $new = MenuItem::create([
                            'name'         => $data['name'],
                            'link'         => $data['link'] ?: '',
                            'target_blank' => $data['target_blank'],
                            'partner_id'   => $partnerId,
                        ]);

                        $newItems[] = "\"{$data['name']}, {$data['link']}"
                            . ($data['target_blank'] ? ", открывать в новой вкладке" : "")
                            . "\"";
                    }
                } else {
                    // ИЗМЕНЕНО: обычное создание для новых ключей
                    \Log::info('Creating new MenuItem', ['data' => $data, 'partnerId' => $partnerId]);

                    $created = MenuItem::create([
                        'name'         => $data['name'],
                        'link'         => $data['link'] ?: '',
                        'target_blank' => $data['target_blank'],
                        'partner_id'   => $partnerId,
                    ]);

                    $newItems[] = "\"{$data['name']}, {$data['link']}"
                        . ($data['target_blank'] ? ", открывать в новой вкладке" : "")
                        . "\"";
                }
            }

            if ($request->has('deleted_items')) {
                $toDelete = $request->input('deleted_items');
                // ИЗМЕНЕНО: удаляем только свои
                MenuItem::where('partner_id', $partnerId)
                    ->whereIn('id', $toDelete)
                    ->delete();

                \Log::info('Deleted own MenuItems', ['ids' => $toDelete, 'partnerId' => $partnerId]);

                foreach ($toDelete as $id) {
                    $oldItems[] = "Удалён пункт меню с ID: {$id}";
                }
            }

            // Безопасное приведение к массивам
            $oldArr = is_array($oldItems) ? $oldItems : [];
            $newArr = is_array($newItems) ? $newItems : [];

            $description = "Изменены пункты меню:\n"
                . implode("\n", $oldArr)
                . "\nна:\n"
                . implode("\n", $newArr);

            \Log::info('Creating MyLog entry', [
                'description' => $description,
                'authorId'    => $authorId,
                'partnerId'   => $partnerId,
            ]);

            MyLog::create([
                'type'        => 1,
                'action'      => 70,
                'author_id'   => $authorId,
                'description' => $description,
                'created_at'  => now(),
                'partner_id'  => $partnerId,
            ]);
        });

        \Log::info('saveMenuItems completed successfully', ['partnerId' => $partnerId]);

        return response()->json(['success' => true]);
    }

    //Сохранение соц. меню в шапке
    public function saveSocialItems(Request $request)
    {
        $partnerId     = app('current_partner')->id;
        $authorId      = auth()->id();
        $errors        = [];
        $validatedData = [];

        // Валидация каждого social_item
        foreach ($request->input('social_items', []) as $key => $data) {
            $validator = \Validator::make($data, [
                'link' => ['nullable', 'regex:/^(\/[\S]*|https?:\/\/[^\s]+)$/'],
                'name' => ['required', 'string'],
            ], [
                'link.regex' => 'Введите корректный URL.',
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->messages() as $field => $messages) {
                    $errors["social_items[$key][$field]"] = $messages;
                }
            } else {
                $validatedData[] = $data;
            }
        }

        if (!empty($errors)) {
            return response()->json(['success' => false, 'errors' => $errors], 422);
        }

        DB::transaction(function () use ($partnerId, $authorId, $validatedData) {
            $oldItems = [];
            $newItems = [];

            foreach ($validatedData as $data) {
                // пытаемся найти существующую запись партнёра по названию соцсети
                $item = SocialItem::where('partner_id', $partnerId)
                    ->where('name', $data['name'])
                    ->first();

                // старое значение (если есть)
                $oldLink = $item ? $item->link : '';

                if ($item) {
                    // обновляем ссылку
                    $item->update([
                        'link' => $data['link'] ?: '',
                    ]);
                } else {
                    // создаём новую запись для этого партнёра
                    $item = SocialItem::create([
                        'partner_id' => $partnerId,
                        'name'       => $data['name'],
                        'link'       => $data['link'] ?: '',
                    ]);
                }

                // логируем старое и новое
                $oldItems[] = "\"{$item->name}\", \"{$oldLink}\"";
                $newItems[] = "\"{$item->name}\", \"{$item->link}\"";
            }

            // формируем читаемое описание изменений
            $description = "Изменены социальные элементы для партнёра #{$partnerId}:\n"
                . implode("\n", $oldItems)
                . "\nна:\n"
                . implode("\n", $newItems);

            MyLog::create([
                'type'       => 1,
                'action'     => 70,
                'author_id'  => $authorId,
                'description'=> $description,
                'created_at' => now(),
            ]);
        });

        return response()->json(['success' => true]);
    }

    //Журнал логов
    public function logsAllData(Request $request)
    {
        $partnerId = app('current_partner')->id;
        $logs = MyLog::with('author')
//            ->where('type', 1) // Добавляем условие для фильтрации по type
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

                    21 => 'Создание пользователя',
                    22 => 'Обновление учетной записи в пользователях',
                    23 => 'Обновление учетной записи',
                    24 => 'Удаление пользователя в пользователях',
                    25 => 'Изменение пароля (админ)',
                    26 => 'Изменение пароля',
                    27 => 'Изменение аватара (админ)',
                    28 => 'Изменение аватара',
                    29 => 'Удаление аватара',

                    210 => 'Изменение доп полей пользователя',


                    31 => 'Создание группы',
                    32 => 'Изменение группы',
                    33 => 'Удаление группы',

                    40 => 'Авторизация',

                    50 => 'Платежи',

                    60 => 'Расписание',

                    70 => 'Изменение настроек',

                    710 => 'Создание роли',
                    720 => 'Изменение роли',
                    730 => 'Удаление роли',

                    80 => 'Изменение партнера',

                    90 => 'Создание статуса расписания',
                    91 => 'Изменение статуса расписания',
                    92 => 'Удаление статуса расписания',


                ];
                return $typeLabels[$log->action] ?? 'Неизвестный тип (setting)';
            })
            ->make(true);
    }
}