<?php

namespace App\Http\Controllers\Admin\Setting;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\PaymentSystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class PaymentSystemController extends Controller
{
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
        $tbank = $paymentSystems->firstWhere('name', 'tbank');

        return view('admin.setting.index', [
            'activeTab' => 'paymentSystem',
            'paymentSystems' => $paymentSystems,
            'partners' => $partners,      // ← передаём
            'curUser' => $curUser,       // ← передаём, если нужно
            'robokassa' => $robokassa,
            'tbank' => $tbank,
        ]);
    }

    public function store2(Request $request)
    {
        \Log::debug('PaymentSystemController@store ENTER', [
            'route' => 'payment-systems.store',
            'user' => auth()->id(),
            'input' => $request->all(),
        ]);

        // Валидация
        $data = $request->validate([
            'name' => 'required|string',
            'merchant_login' => 'required|string',
            'password1' => 'required|string',
            'password2' => 'required|string',
            'test_mode' => 'required|boolean',
        ]);
        \Log::debug('Validated data', $data);

        $partnerId = app('current_partner')->id ?? null;
        \Log::debug('Current partner', ['partnerId' => $partnerId]);

        // Собираем настройки
        $settings = [
            'name' => $data['name'],
            'merchant_login' => $data['merchant_login'],
            'password1' => $data['password1'],
            'password2' => $data['password2'],
        ];

        \Log::debug('Prepared settings payload', $settings);

        // Ищем или создаём систему
        $paymentSystem = \App\Models\PaymentSystem::firstOrNew([
            'partner_id' => $partnerId,
            'name' => $data['name'],
        ]);

        \Log::debug('Loaded PaymentSystem model', [
            'exists' => $paymentSystem->exists,
            'attrs' => $paymentSystem->toArray(),
        ]);

        // Присваиваем значения
        $paymentSystem->settings = $settings;
        $paymentSystem->test_mode = $data['test_mode'];

        \Log::debug('Before save getDirty', [
            'dirty' => $paymentSystem->getDirty(),
        ]);

        // Лог SQL запросов
        \DB::listen(function ($q) {
            \Log::debug('SQL', [
                'sql' => $q->sql,
                'bindings' => $q->bindings,
            ]);
        });

        try {
            $ok = $paymentSystem->save();

            \Log::debug('After save()', [
                'ok' => $ok,
                'dirty_now' => $paymentSystem->getDirty(),
            ]);
        } catch (\Throwable $e) {
            \Log::error('Save threw exception', [
                'msg' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        // Проверяем, что реально в БД
        $freshRow = \DB::table('payment_systems')->where('id', $paymentSystem->id)->first();
        \Log::debug('Fresh row from DB', ['row' => $freshRow]);

        // Если settings остался пустым — жёсткий UPDATE
        if (empty($freshRow->settings)) {
            \Log::warning('Fallback UPDATE(settings) because model save did not persist');

            $json = json_encode($settings, JSON_UNESCAPED_UNICODE);
            $enc = \Illuminate\Support\Facades\Crypt::encryptString($json);

            $affected = \DB::table('payment_systems')
                ->where('id', $paymentSystem->id)
                ->update([
                    'settings' => $enc,
                    'test_mode' => $data['test_mode'],
                    'updated_at' => now(),
                ]);

            \Log::warning('Fallback result', [
                'affected' => $affected,
                'row' => \DB::table('payment_systems')->where('id', $paymentSystem->id)->first(),
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function store(Request $request)
    {
        \Log::debug('*** REAL PaymentSystemController::store REACHED ***', [
            'file'  => __FILE__,
            'class' => __CLASS__,
        ]);

        \Log::debug('HIT store BEFORE validate', [
            'route'   => \Route::currentRouteName(),
            'user_id' => optional(\Auth::user())->id,
            'all'     => $request->all(),
            'ctype'   => $request->headers->get('content-type'),
            'method'  => $request->method(),
            'csrf_ok' => $request->has('_token'),
        ]);

        $validator = \Validator::make($request->all(), [
            'name'             => 'required|string',
            'merchant_login'   => 'nullable|string',
            'password1'        => 'nullable|string',
            'password2'        => 'nullable|string',
            'test_mode'        => 'nullable',
            'tbank_account_id' => 'nullable|string',
            'tbank_key'        => 'nullable|string',
        ]);

        if ($validator->fails()) {
            \Log::warning('payment-systems.store VALIDATION FAILED', $validator->errors()->toArray());
            return back()->withErrors($validator)->withInput();
        }

        \Log::debug('HIT store AFTER validate');

        $validated = $validator->validated();
        $partnerId = app('current_partner')->id;

        \Log::debug('store payment settings', [
            'partnerId' => $partnerId,
            'payload'   => $validated,
        ]);

        $paymentSystem = PaymentSystem::firstOrNew([
            'partner_id' => $partnerId,
            'name'       => $validated['name'],
        ]);

        // берём старые настройки
        $settings = $paymentSystem->settings ?? [];

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
        }

        $paymentSystem->settings  = $settings;
        $paymentSystem->test_mode = !empty($validated['test_mode']);
        $paymentSystem->save();

        \Log::debug('DB write check', [
            'db'  => \DB::getDatabaseName(),
            'id'  => $paymentSystem->id,
            'row' => $paymentSystem->toArray(),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => "Настройки [{$validated['name']}] успешно сохранены для партнёра #{$partnerId}",
        ]);
    }

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
            'status' => 'success',
            'data' => $paymentSystem->settings,    // автоматически расшифруется через accessor
            'test_mode' => $paymentSystem->test_mode,
        ]);
    }

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

    public function getIsConnectedAttribute()
    {
        $s = $this->settings; // уже безопасный массив
        return !empty($s['merchant_login']) && !empty($s['password1']);
    }
}
