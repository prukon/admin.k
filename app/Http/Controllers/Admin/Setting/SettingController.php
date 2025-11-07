<?php

namespace App\Http\Controllers\Admin\Setting;

use App\Http\Controllers\Controller;
use App\Http\Filters\TeamFilter;
use App\Http\Requests\Team\FilterRequest;

//use App\Models\Log;
use App\Models\MyLog;
use App\Models\MenuItem;
use App\Models\Partner;
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
use App\Support\BuildsLogTable;

class SettingController extends Controller
{
    use BuildsLogTable;

    //ВКЛАДКА НАСТРОЙКИ
    //Страница Настройки
    public function showSettings()
    {
        $partnerId = app('current_partner')->id;

        $setting = Setting::where('name', 'textForUsers')
            ->where('partner_id', $partnerId)
            ->first();
        $textForUsers = $setting ? $setting->text : null;


        // 2) Берём из базы запись для этого партнёра
        $setting = Setting::where('name', 'registrationActivity')
            ->where('partner_id', $partnerId)
            ->first();

        // 3) Флаг: если записи нет — считаем, что выключено
        $isRegistrationActive = $setting
            ? (bool)$setting->status
            : false;

//статус 2Fa для админов
        $force2faAdmins = Setting::getBool('force_2fa_admins', false, null);


        return view('admin.setting.index',
            ['activeTab' => 'setting'],
            compact(
                "textForUsers",
                'partnerId',
                'isRegistrationActive',
                'force2faAdmins'
            )
        );
    }

    //AJAX Активность регистрации
    public function registrationActivity(Request $request)
    {
        $partner = app('current_partner');

        $isRegistrationActivity = $request->query('isRegistrationActivity');
        $authorId = auth()->id(); // Авторизованный пользователь

        DB::transaction(function () use ($isRegistrationActivity, $authorId, $partner) {

            $isRegistrationActivity = filter_var($isRegistrationActivity, FILTER_VALIDATE_BOOLEAN);
            // Обновляем или создаем запись в таблице team_prices
            Setting::updateOrCreate(
                [
                    'name' => "registrationActivity",
                    'partner_id' => "$partner->id",
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
                'partner_id' => $partner->id,
                'target_type' => 'App\Models\Setting',
                'target_id' => $partner->id,
                'target_label' => $partner->title,
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
        $partner = app('current_partner');

        // Получаем данные из тела запроса
        $data = json_decode($request->getContent(), true);
        $textForUsers = $data['textForUsers'] ?? null;
        $authorId = auth()->id(); // Авторизованный пользователь

        DB::transaction(function () use ($textForUsers, $authorId, $partner) {

            Setting::updateOrCreate(
                [
                    'name' => "textForUsers",
                    'partner_id' => "$partner->id",
                ],
                [
                    'text' => $textForUsers
                ]
            );

            MyLog::create([
                'type' => 1,
                'action' => 70,
                'author_id' => $authorId,
                'partner_id' => $partner->id,

                'target_type' => 'App\Models\Setting',
                'target_id' => $partner->id,
                'target_label' => $partner->title,

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
        $partner = app('current_partner');
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
                'name.max' => 'Название не может быть длиннее 20 символов.',
                'name.regex' => 'Название не может содержать спецсимволы.',
                'link.regex' => 'Введите корректный URL.',
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->messages() as $field => $messages) {
                    $errors["menu_items[$key][$field]"] = $messages;
                }

            } else {
                $data['target_blank'] = !empty($data['target_blank']) ? 1 : 0;
                $validatedData[$key] = $data;
            }
        }

        if (!empty($errors)) {
            return response()->json(['success' => false, 'errors' => $errors], 422);
        }

        // ИНИЦИАЛИЗАЦИЯ массивов для логирования изменений
        $oldItems = [];
        $newItems = [];

        DB::transaction(function () use (
            $validatedData,
            $authorId,
            $request,
            $partner,
            &$oldItems,
            &$newItems
        ) {

            foreach ($validatedData as $key => $data) {
                if (is_numeric($key)) {
                    // ИЗМЕНЕНО: ищем только свои записи
                    $menuItem = MenuItem::where('partner_id', $partner->id)->find($key);

                    if ($menuItem) {
                        $oldItems[] = "\"{$menuItem->name}, {$menuItem->link}"
                            . ($menuItem->target_blank ? ", открывать в новой вкладке" : "")
                            . "\"";

                        $menuItem->update([
                            'name' => $data['name'],
                            'link' => $data['link'] ?: '',
                            'target_blank' => $data['target_blank'],
                            // partner_id оставляем прежним
                        ]);

                        $newItems[] = "\"{$data['name']}, {$data['link']}"
                            . ($data['target_blank'] ? ", открывать в новой вкладке" : "")
                            . "\"";
                    } else {
                        // ИЗМЕНЕНО: при попытке обновить чужую или несуществующую — создаём новую

                        $new = MenuItem::create([
                            'name' => $data['name'],
                            'link' => $data['link'] ?: '',
                            'target_blank' => $data['target_blank'],
                            'partner_id' => $partner->id,
                        ]);

                        $newItems[] = "\"{$data['name']}, {$data['link']}"
                            . ($data['target_blank'] ? ", открывать в новой вкладке" : "")
                            . "\"";
                    }
                } else {
                    // ИЗМЕНЕНО: обычное создание для новых ключей
                    $created = MenuItem::create([
                        'name' => $data['name'],
                        'link' => $data['link'] ?: '',
                        'target_blank' => $data['target_blank'],
                        'partner_id' => $partner->id,
                    ]);

                    $newItems[] = "\"{$data['name']}, {$data['link']}"
                        . ($data['target_blank'] ? ", открывать в новой вкладке" : "")
                        . "\"";
                }
            }

            if ($request->has('deleted_items')) {
                $toDelete = $request->input('deleted_items');
                // ИЗМЕНЕНО: удаляем только свои
                MenuItem::where('partner_id', $partner->id)
                    ->whereIn('id', $toDelete)
                    ->delete();

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

            MyLog::create([
                'type' => 1,
                'action' => 70,
                'author_id' => $authorId,

                'target_type' => 'App\Models\Setting',
                'target_id' => $partner->id,
                'target_label' => $partner->title,

                'description' => $description,
                'created_at' => now(),
                'partner_id' => $partner->id,
            ]);
        });

        return response()->json(['success' => true]);
    }

    //Сохранение соц. меню в шапке
    public function saveSocialItems(Request $request)
    {
        $partner = app('current_partner');
        $authorId = auth()->id();
        $errors = [];
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

        DB::transaction(function () use ($authorId, $validatedData, $partner) {
            $oldItems = [];
            $newItems = [];

            foreach ($validatedData as $data) {
                // пытаемся найти существующую запись партнёра по названию соцсети
                $item = SocialItem::where('partner_id', $partner->id)
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
                        'partner_id' => $partner->id,
                        'name' => $data['name'],
                        'link' => $data['link'] ?: '',
                    ]);
                }

                // логируем старое и новое
                $oldItems[] = "\"{$item->name}\", \"{$oldLink}\"";
                $newItems[] = "\"{$item->name}\", \"{$item->link}\"";
            }

            // формируем читаемое описание изменений
            $description = "Изменены социальные элементы для партнёра #{$partner->id}:\n"
                . implode("\n", $oldItems)
                . "\nна:\n"
                . implode("\n", $newItems);

            MyLog::create([
                'type' => 1,
                'action' => 70,
                'author_id' => $authorId,
                'partner_id' => $partner->id,

                'target_type' => 'App\Models\Setting',
                'target_id' => $partner->id,
                'target_label' => $partner->title,

                'description' => $description,
                'created_at' => now(),
            ]);
        });

        return response()->json(['success' => true]);
    }

    //Журнал логов
    public function logsAllData(FilterRequest $request)
    {
        return $this->buildLogDataTable(null);
    }

//    Смена 2 Fa для админов


    public function toggleForce2faAdmins(Request $request)
    {
        $user = $request->user();

        Log::info('toggleForce2faAdmins: request IN', [
            'user_id' => $user?->id,
            'role_id' => $user?->role_id,
            'payload' => $request->all(),
            'headers' => [
                'X-Requested-With' => $request->header('X-Requested-With'),
                'Content-Type' => $request->header('Content-Type'),
            ],
        ]);

        if ((int)$user->role_id !== 1) {
            Log::warning('toggleForce2faAdmins: forbidden (not superadmin)', [
                'user_id' => $user?->id,
                'role_id' => $user?->role_id,
            ]);
            return response()->json(['success' => false, 'message' => 'Доступ только для суперадмина'], 403);
        }

        // принимаем 0/1, true/false, "on" и т.д.
        $active = filter_var($request->input('force2faAdmins'), FILTER_VALIDATE_BOOLEAN);
        Log::info('toggleForce2faAdmins: parsed value', ['active' => $active, 'raw' => $request->input('force2faAdmins')]);

        try {
            // Пишем НАПРЯМУЮ в таблицу settings (без модели), глобальная запись partner_id = NULL
            $ok = DB::table('settings')->updateOrInsert(
                ['name' => 'force_2fa_admins', 'partner_id' => null],
                [
                    'status' => $active ? 1 : 0,
                    'updated_at' => now(),
                    'created_at' => now(),
                    'text' => DB::raw('COALESCE(text, "Обязательная 2FA для роли 10 (админ). 0/1.")'),
                ]
            );

            Log::info('toggleForce2faAdmins: DB updateOrInsert result', ['ok' => $ok]);

            // перечитаем для контроля
            $row = DB::table('settings')->whereNull('partner_id')->where('name', 'force_2fa_admins')->first();
            Log::info('toggleForce2faAdmins: row after save', [
                'exists' => (bool)$row,
                'status' => $row->status ?? null,
                'id' => $row->id ?? null,
            ]);

            return response()->json(['success' => true, 'value' => (bool)($row->status ?? 0)]);
        } catch (\Throwable $e) {
            Log::error('toggleForce2faAdmins: FAILED', [
                'error' => $e->getMessage(),
                'class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['success' => false, 'message' => 'DB error: ' . $e->getMessage()], 500);
        }
    }


}

 