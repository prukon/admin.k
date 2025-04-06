<?php

namespace App\Http\Controllers\Admin\Setting;


use App\Http\Controllers\Controller;
use App\Models\PaymentSystem;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class PaymentSystemController extends Controller
{

    public function index()
    {
        $paymentSystems = PaymentSystem::with('partner')->get();
        $partners = Partner::all();
        $curUser = Auth::user();
        $partnerId = $curUser->partner_id;
        $paymentSystems = PaymentSystem::where('partner_id', $partnerId)->get();
        $robokassa = $paymentSystems->firstWhere('name', 'robokassa');
        $tbank = $paymentSystems->firstWhere('name', 'tbank');

        return view('admin.setting.index',
            ['activeTab' => 'paymentSystem'],
            compact(
                "paymentSystems",
                'partners',
                'curUser',
                'robokassa',
                'tbank'
            )
        );
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        // Валидация
        $validated = $request->validate([
            'name' => 'required|string', // Например: "robokassa", "tbank", "yookassa"

            // Всё, что относится к настройкам, валидируем «опционально»
            // потому что у каждой системы может быть свой набор полей
            'merchant_login' => 'nullable|string',
            'password1' => 'nullable|string',
            'password2' => 'nullable|string',
            'test_mode' => 'nullable|boolean',


            // TBank, например
            'tbank_account_id' => 'nullable|string',
            'tbank_key' => 'nullable|string',
        ]);

        // Ищем запись payment_systems: partner_id + name
        $paymentSystem = PaymentSystem::firstOrNew([
            'partner_id' => $user->partner_id,
            'name' => $validated['name'],
        ]);

        // Берём старое значение settings (массив), если есть
        $settings = $paymentSystem->settings ?: [];

        // Наполняем/перезаписываем поля внутри settings — всё, что нужно этой ПС
        // Например, если name = "robokassa", сохраняем все поля, относящиеся к Робокассе:
        if ($validated['name'] === 'robokassa') {
            $settings['merchant_login'] = $validated['merchant_login'] ?? null;
            $settings['password1'] = $validated['password1'] ?? null;
            $settings['password2'] = $validated['password2'] ?? null;
            $settings['test_mode'] = !empty($validated['test_mode']); // bool
        }

        // Аналогично для TBank:
        if ($validated['name'] === 'tbank') {
            $settings['tbank_account_id'] = $validated['tbank_account_id'] ?? null;
            $settings['tbank_key'] = $validated['tbank_key'] ?? null;
        }

        // Можно просто в любом случае писать что пришло:
        // $settings = array_merge($settings, $request->only([...]));
        // но пример выше чуть нагляднее.

        // Записываем settings в модель (оно тут же будет зашифровано mutator-ом)
        $paymentSystem->settings = $settings;


        // Отдельно сохраняем test_mode
        $paymentSystem->test_mode = !empty($validated['test_mode']);


        // Сохраняем
        $paymentSystem->save();

        // Возвращаем ответ (например, JSON)
        return response()->json([
            'status' => 'success',
            'message' => "Настройки для [{$validated['name']}] сохранены",
        ]);
    }

    public function show(Request $request, $name)
    {
        $user = Auth::user();

        $request->merge([
            'test_mode' => filter_var($request->input('test_mode'), FILTER_VALIDATE_BOOLEAN),
        ]);

        $paymentSystem = PaymentSystem::where('partner_id', $user->id)
            ->where('name', $name)
            ->first();

        if (!$paymentSystem) {
            return response()->json(['status' => 'not_found'], 404);
        }

        return response()->json([
            'status' => 'success',
            // settings уже будет расшифрован, т.к. срабатывает getSettingsAttribute
            'data' => $paymentSystem->settings,
            'test_mode' => $paymentSystem->test_mode,    // отдельный флаг

        ]);
    }

    public function destroy(PaymentSystem $paymentSystem)
    {
        // Безопасность: только владелец может удалить
        if ($paymentSystem->partner_id !== auth()->user()->partner_id) {
            return response()->json(['message' => 'Доступ запрещён'], 403);
        }

        $paymentSystem->delete();

        return response()->json(['success' => true]);
    }

}