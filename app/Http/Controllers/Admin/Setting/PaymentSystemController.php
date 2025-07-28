<?php

namespace App\Http\Controllers\Admin\Setting;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\PaymentSystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;  // ← вот эта строка


class PaymentSystemController extends Controller
{
    /**
     * Список всех платёжных систем текущего партнёра
     */
    public function index()
    {
        $partnerId = app('current_partner')->id;

        // Все платёжные системы текущего партнёра
        $paymentSystems = PaymentSystem::where('partner_id', $partnerId)->get();

        // Для выпадающего списка партнёров (если он вам нужен в интерфейсе)
        $partners = Partner::all();

        // Текущий пользователь (если в шаблоне используется)
        $curUser = Auth::user();

        // Выборки по имени для удобства
        $robokassa = $paymentSystems->firstWhere('name', 'robokassa');
        $tbank     = $paymentSystems->firstWhere('name', 'tbank');

        return view('admin.setting.index', [
            'activeTab'      => 'paymentSystem',
            'paymentSystems' => $paymentSystems,
            'partners'       => $partners,      // ← передаём
            'curUser'        => $curUser,       // ← передаём, если нужно
            'robokassa'      => $robokassa,
            'tbank'          => $tbank,
        ]);
    }
    /**
     * Сохраняет (создаёт или обновляет) настройки платёжной системы
     */
    public function store(Request $request)
    {
        // 1) Валидация входных данных
        $validated = $request->validate([
            'name'               => 'required|string',
            'merchant_login'     => 'nullable|string',
            'password1'          => 'nullable|string',
            'password2'          => 'nullable|string',
            'test_mode'          => 'nullable|boolean',
            'tbank_account_id'   => 'nullable|string',
            'tbank_key'          => 'nullable|string',
        ]);

        $partnerId = app('current_partner')->id;

        // 2) Ищем или создаём запись для этого партнёра + системы по имени
        $paymentSystem = PaymentSystem::firstOrNew([
            'partner_id' => $partnerId,
            'name'       => $validated['name'],
        ]);

        // 3) Берём старые настройки (если есть)
        $settings = $paymentSystem->settings ?: [];

        // 4) Заполняем нужные поля в зависимости от name
        switch ($validated['name']) {
            case 'robokassa':
                $settings['merchant_login'] = $validated['merchant_login'] ?? null;
                $settings['password1']      = $validated['password1'] ?? null;
                $settings['password2']      = $validated['password2'] ?? null;
                $settings['test_mode']      = !empty($validated['test_mode']);
                break;

            case 'tbank':
                $settings['tbank_account_id'] = $validated['tbank_account_id'] ?? null;
                $settings['tbank_key']        = $validated['tbank_key'] ?? null;
                break;

            // при необходимости добавьте другие системы
        }

        // 5) Сохраняем массив настроек (mutator его зашифрует) и флаг test_mode
        $paymentSystem->settings  = $settings;
        $paymentSystem->test_mode = !empty($validated['test_mode']);

        // 6) Сохраняем модель
        $paymentSystem->save();

        return response()->json([
            'status'  => 'success',
            'message' => "Настройки [{$validated['name']}] успешно сохранены для партнёра #{$partnerId}",
        ]);
    }

    /**
     * Возвращает текущие настройки платёжной системы по имени
     */
    public function show(Request $request, string $name)
    {
        $partnerId = app('current_partner')->id;

        $paymentSystem = PaymentSystem::where([
            ['partner_id', '=', $partnerId],
            ['name', '=', $name],
        ])->first();

        if (!$paymentSystem) {
            return response()->json(['status' => 'not_found'], 404);
        }

        return response()->json([
            'status'    => 'success',
            'data'      => $paymentSystem->settings,    // автоматически расшифруется через accessor
            'test_mode' => $paymentSystem->test_mode,
        ]);
    }

    /**
     * Удаляет платёжную систему (только если она принадлежит текущему партнёру)
     */
    public function destroy(int $id)
    {
        $partnerId = app('current_partner')->id;

        $paymentSystem = PaymentSystem::findOrFail($id);

        if ($paymentSystem->partner_id !== $partnerId) {
            return response()->json(['message' => 'Доступ запрещён'], 403);
        }

        $paymentSystem->delete();

        return response()->json(['success' => true]);
    }
}
