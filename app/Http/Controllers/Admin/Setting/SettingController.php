<?php

namespace App\Http\Controllers\Admin\Setting;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Team\FilterRequest;
use App\Models\MyLog;
use App\Models\MenuItem;
use App\Models\PartnerSocialLink;
use App\Models\Setting;
use App\Models\SocialNetwork;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use App\Support\BuildsLogTable;
use App\Services\PartnerContext;

class SettingController extends AdminBaseController
{
    use BuildsLogTable;

    private const URL_REGEX = '/^(\/[\\S]*|https?:\\/\\/[^\s]+)$/';
    private const MENU_ITEM_NAME_REGEX = '/^[\pL\pN\s]+$/u';

    /**
     * Приводим ключи ошибок в формат name="" инпутов (с квадратными скобками),
     * чтобы фронт мог подсветить поля без сложной логики.
     *
     * Пример: menu_items.123.name -> menu_items[123][name]
     */
    private function bracketizeValidationErrors(array $errors): array
    {
        $out = [];
        foreach ($errors as $key => $messages) {
            $parts = explode('.', (string)$key);
            if (count($parts) >= 3) {
                $root = array_shift($parts);
                $bracket = $root;
                foreach ($parts as $p) {
                    $bracket .= '[' . $p . ']';
                }
                $out[$bracket] = $messages;
            } else {
                $out[$key] = $messages;
            }
        }
        return $out;
    }

    public function __construct(PartnerContext $partnerContext)
    {
        parent::__construct($partnerContext);
    }

    //ВКЛАДКА НАСТРОЙКИ
    //Страница Настройки
    public function showSettings()
    {
        $partner = $this->requirePartner();
        $partnerId = $partner->id;

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

        // Соцсети партнёра для модалки настроек:
        // - показываем только глобально включённые соцсети
        // - создаём недостающие строки для партнёра (не в middleware, а только на странице настроек)
        $socialNetworks = SocialNetwork::query()
            ->where('is_enabled', 1)
            ->orderBy('sort')
            ->get(['id', 'sort']);

        // Оптимизация: пачечная инициализация недостающих связей
        $networkIds = $socialNetworks->pluck('id')->all();
        if (!empty($networkIds)) {
            $existingNetworkIds = PartnerSocialLink::query()
                ->where('partner_id', $partnerId)
                ->whereIn('social_network_id', $networkIds)
                ->pluck('social_network_id')
                ->all();

            $existingSet = array_flip($existingNetworkIds);
            $now = now();
            $toInsert = [];
            foreach ($socialNetworks as $sn) {
                if (isset($existingSet[$sn->id])) continue;
                $toInsert[] = [
                    'partner_id' => $partnerId,
                    'social_network_id' => $sn->id,
                    'url' => null,
                    'is_enabled' => 1,
                    'sort' => (int)($sn->sort ?? 0),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if (!empty($toInsert)) {
                PartnerSocialLink::query()->insert($toInsert);
            }
        }

        $socialSettingsItems = PartnerSocialLink::query()
            ->where('partner_id', $partnerId)
            ->whereHas('socialNetwork', fn($q) => $q->where('is_enabled', 1))
            ->with(['socialNetwork:id,code,title,domain,icon,sort,is_enabled'])
            ->orderBy('sort')
            ->get();


        return view('admin.setting.index',
            ['activeTab' => 'setting'],
            compact(
                "textForUsers",
                'partnerId',
                'isRegistrationActive',
                'force2faAdmins',
                'socialSettingsItems'
            )
        );
    }

    //AJAX Активность регистрации
    public function registrationActivity(Request $request)
    {
        $partner = $this->requirePartner();

        $isRegistrationActivity = $request->input('isRegistrationActivity');
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
        $partner = $this->requirePartner();

        try {
            $validated = $request->validate([
                'textForUsers' => ['nullable', 'string'],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $this->bracketizeValidationErrors($e->errors())], 422);
        }

        // Laravel корректно читает и JSON body, и form-data через input()
        $textForUsers = $validated['textForUsers'] ?? null;
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
        $partner = $this->requirePartner();
        $authorId = auth()->id();

        try {
            $validated = $request->validate([
                'menu_items' => ['nullable', 'array'],
                'menu_items.*.name' => ['required', 'max:20', 'regex:' . self::MENU_ITEM_NAME_REGEX],
                'menu_items.*.link' => ['nullable', 'regex:' . self::URL_REGEX],
                'menu_items.*.target_blank' => ['nullable', 'boolean'],
                'deleted_items' => ['nullable', 'array'],
                'deleted_items.*' => ['integer'],
            ], [
                'menu_items.*.name.required' => 'Заполните название.',
                'menu_items.*.name.max' => 'Название не может быть длиннее 20 символов.',
                'menu_items.*.name.regex' => 'Название не может содержать спецсимволы.',
                'menu_items.*.link.regex' => 'Введите корректный URL.',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $this->bracketizeValidationErrors($e->errors())], 422);
        }

        $validatedData = $validated['menu_items'] ?? [];

        // Строго: если пришли числовые ключи (id) — это UPDATE существующих записей.
        // Если какой-то id не найден у текущего партнёра (в т.ч. "чужой") — 404 и НЕ создаём новую запись.
        $updateIds = [];
        foreach ($validatedData as $key => $_) {
            if (is_numeric($key)) {
                $updateIds[] = (int)$key;
            }
        }
        $updateIds = array_values(array_unique($updateIds));

        $existingById = collect();
        if (!empty($updateIds)) {
            $existingById = MenuItem::query()
                ->where('partner_id', $partner->id)
                ->whereIn('id', $updateIds)
                ->get()
                ->keyBy('id');

            if ($existingById->count() !== count($updateIds)) {
                return response()->json(['success' => false, 'message' => 'Not found'], 404);
            }
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
                $data['target_blank'] = !empty($data['target_blank']) ? 1 : 0;
                if (is_numeric($key)) {
                    // строго: до транзакции уже проверили, что id существует у партнёра
                    $menuItem = MenuItem::where('partner_id', $partner->id)->find((int)$key);

                    $oldItems[] = "\"{$menuItem->name}, {$menuItem->link}"
                        . ($menuItem->target_blank ? ", открывать в новой вкладке" : "")
                        . "\"";

                    $menuItem->update([
                        'name' => $data['name'],
                        'link' => $data['link'] ?: '',
                        'target_blank' => $data['target_blank'],
                    ]);

                    $newItems[] = "\"{$data['name']}, {$data['link']}"
                        . ($data['target_blank'] ? ", открывать в новой вкладке" : "")
                        . "\"";
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
                $toDelete = $validated['deleted_items'] ?? $request->input('deleted_items');
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
        $partner = $this->requirePartner();
        $authorId = auth()->id();

        try {
            $validated = $request->validate([
                'partner_social_links' => ['nullable', 'array'],
                'partner_social_links.*.url' => ['nullable', 'regex:' . self::URL_REGEX],
                'partner_social_links.*.is_enabled' => ['nullable', 'boolean'],
                'partner_social_links.*.sort' => ['nullable', 'integer', 'min:0', 'max:65535'],
            ], [
                'partner_social_links.*.url.regex' => 'Введите корректный URL.',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $this->bracketizeValidationErrors($e->errors())], 422);
        }

        $validatedData = [];
        foreach (($validated['partner_social_links'] ?? []) as $key => $data) {
            if (!is_numeric($key)) continue;
            $data['is_enabled'] = !empty($data['is_enabled']) ? 1 : 0;
            $data['sort'] = isset($data['sort']) ? (int)$data['sort'] : 0;
            $validatedData[(int)$key] = $data;
        }

        $ids = array_keys($validatedData);
        $linksById = PartnerSocialLink::query()
            ->where('partner_id', $partner->id)
            ->whereIn('id', $ids)
            ->with('socialNetwork:id,title')
            ->get()
            ->keyBy('id');

        if ($linksById->count() !== count($ids)) {
            // строго: чужое/несуществующее — 404 (в JSON, чтобы фронт не падал на response.json()).
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        DB::transaction(function () use ($authorId, $validatedData, $partner) {
            $oldItems = [];
            $newItems = [];

            // Перечитаем модели в рамках транзакции, но без N+1
            $ids = array_keys($validatedData);
            $linksById = PartnerSocialLink::query()
                ->where('partner_id', $partner->id)
                ->whereIn('id', $ids)
                ->with('socialNetwork:id,title')
                ->get()
                ->keyBy('id');

            foreach ($validatedData as $id => $data) {
                $link = $linksById->get($id);
                $label = $link?->socialNetwork?->title ?? ('#' . ($link?->social_network_id ?? $id));

                $oldItems[] = "\"{$label}\": url=\"{$link->url}\", enabled=" . ($link->is_enabled ? '1' : '0') . ", sort={$link->sort}";

                $link->update([
                    'url' => $data['url'] ?? null,
                    'is_enabled' => (int)$data['is_enabled'],
                    'sort' => (int)$data['sort'],
                ]);

                $newItems[] = "\"{$label}\": url=\"{$link->url}\", enabled=" . ($link->is_enabled ? '1' : '0') . ", sort={$link->sort}";
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

    //Журнал логов (все типы) текущего партнёра
    public function logsData(FilterRequest $request)
    {
        return $this->buildLogDataTable(null);
    }

//    Смена 2 Fa для админов


    public function toggleForce2faAdmins(Request $request)
    {
        $user = $request->user();

        // принимаем 0/1, true/false, "on" и т.д.
        $active = filter_var($request->input('force2faAdmins'), FILTER_VALIDATE_BOOLEAN);

        try {
            // Глобальная запись partner_id = NULL
            $ok = Setting::setBool('force_2fa_admins', (bool)$active, null);
            if (!$ok) {
                return response()->json(['success' => false, 'message' => 'DB error'], 500);
            }

            $value = Setting::getBool('force_2fa_admins', false, null);
            Log::info('toggleForce2faAdmins: saved', [
                'user_id' => $user?->id,
                'active' => $value,
            ]);

            return response()->json(['success' => true, 'value' => $value]);
        } catch (\Throwable $e) {
            Log::error('toggleForce2faAdmins: FAILED', [
                'error' => $e->getMessage(),
                'class' => get_class($e),
            ]);
            return response()->json(['success' => false, 'message' => 'DB error: ' . $e->getMessage()], 500);
        }
    }


}

 