<?php

namespace App\Http\Controllers\Admin\Setting;

use App\Http\Controllers\AdminBaseController;
use App\Models\PaymentSystem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use App\Services\PartnerContext;


class PaymentSystemController extends AdminBaseController
{
    public function __construct(PartnerContext $partnerContext)
    {
        parent::__construct($partnerContext);
    }

    public function index()
    {
        $partnerId = $this->requirePartnerId();

        $paymentSystems = PaymentSystem::where('partner_id', $partnerId)->get();

        $curUser = Auth::user();

        $robokassa = $paymentSystems->firstWhere('name', 'robokassa');
        $tbank = PaymentSystem::globalTbank();

        return view('admin.setting.index', [
            'activeTab' => 'paymentSystem',
            'paymentSystems' => $paymentSystems,
            'curUser' => $curUser,
            'robokassa' => $robokassa,
            'tbank' => $tbank,
        ]);
    }

    public function store(Request $request)
    {
        Log::debug('*** REAL PaymentSystemController::store REACHED ***', [
            'file'  => __FILE__,
            'class' => __CLASS__,
        ]);

        Log::debug('HIT store BEFORE validate', [
            'route'   => Route::currentRouteName(),
            'user_id' => optional(Auth::user())->id,
            'all'     => $request->all(),
            'ctype'   => $request->headers->get('content-type'),
            'method'  => $request->method(),
            'csrf_ok' => $request->has('_token'),
        ]);

        $validator = Validator::make($request->all(), [
            'name'             => 'required|string',
            'merchant_login'   => 'nullable|string',
            'password1'        => 'nullable|string',
            'password2'        => 'nullable|string',
            'password3'        => 'nullable|string',
            'test_mode'        => 'nullable',
            'is_enabled'       => 'nullable',
            // T-Bank (eacq)
            'terminal_key'     => 'nullable|string',
            'token_password'   => 'nullable|string',
            // T-Bank (e2c payouts)
            'e2c_terminal_key'   => 'nullable|string',
            'e2c_token_password' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            Log::warning('payment-systems.store VALIDATION FAILED', $validator->errors()->toArray());
            return back()->withErrors($validator)->withInput();
        }

        Log::debug('HIT store AFTER validate');

        $validated = $validator->validated();
        $this->authorizePaymentSystemMethod($validated['name']);

        Log::debug('store payment settings', [
            'name'    => $validated['name'],
            'payload' => $validated,
        ]);

        if ($validated['name'] === 'tbank') {
            $paymentSystem = PaymentSystem::firstOrNew([
                'partner_id' => null,
                'name'       => 'tbank',
            ]);
        } else {
            $partnerId = $this->requirePartnerId();
            $paymentSystem = PaymentSystem::firstOrNew([
                'partner_id' => $partnerId,
                'name'       => $validated['name'],
            ]);
        }

        $settings = $paymentSystem->settings ?? [];

        switch ($validated['name']) {
            case 'robokassa':
                $settings['merchant_login'] = $validated['merchant_login'] ?? null;
                $settings['password1']      = $validated['password1'] ?? null;
                $settings['password2']      = $validated['password2'] ?? null;
                $settings['password3']      = $validated['password3'] ?? ($settings['password3'] ?? null);
                $settings['test_mode']      = !empty($validated['test_mode']);
                break;

            case 'tbank':
                $settings['terminal_key']   = $validated['terminal_key'] ?? null;
                $settings['token_password'] = $validated['token_password'] ?? null;
                $settings['e2c_terminal_key']   = $validated['e2c_terminal_key'] ?? null;
                $settings['e2c_token_password'] = $validated['e2c_token_password'] ?? null;
                break;
        }

        $paymentSystem->settings  = $settings;
        $paymentSystem->test_mode = !empty($validated['test_mode']);
        if ($validated['name'] === 'tbank') {
            $paymentSystem->is_enabled = $request->boolean('is_enabled', true);
        }
        $paymentSystem->save();

        Log::debug('DB write check', [
            'db'  => DB::getDatabaseName(),
            'id'  => $paymentSystem->id,
            'row' => $paymentSystem->toArray(),
        ]);

        $message = $validated['name'] === 'tbank'
            ? "Настройки [{$validated['name']}] успешно сохранены (глобальный терминал платформы)"
            : "Настройки [{$validated['name']}] успешно сохранены для партнёра #{$paymentSystem->partner_id}";

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'status'  => 'success',
                'message' => $message,
            ]);
        }

        return redirect()
            ->route('admin.setting.paymentSystem')
            ->with('status', $message);
    }

    public function show(Request $request, string $name)
    {
        $this->authorizePaymentSystemMethod($name);

        if ($name === 'tbank') {
            $paymentSystem = PaymentSystem::globalTbank();
        } else {
            $partnerId = $this->requirePartnerId();
            $paymentSystem = PaymentSystem::where([
                ['partner_id', '=', $partnerId],
                ['name', '=', $name],
            ])->first();
        }

        if (!$paymentSystem) {
            return response()->json(['status' => 'not_found'], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $paymentSystem->settings,
            'test_mode' => $paymentSystem->test_mode,
            'is_enabled' => $paymentSystem->is_enabled,
        ]);
    }

    public function destroy(int $id)
    {
        $paymentSystem = PaymentSystem::findOrFail($id);

        if ($paymentSystem->name === 'tbank') {
            if ($paymentSystem->partner_id !== null) {
                return response()->json(['message' => 'Доступ запрещён'], 403);
            }

            $user = Auth::user();
            if (! $user instanceof User || ! $user->hasRole('superadmin')) {
                return response()->json([
                    'message' => 'Удаление глобального терминала T‑Bank доступно только superadmin',
                ], 403);
            }
        } else {
            $partnerId = $this->requirePartnerId();
            if ($paymentSystem->partner_id !== $partnerId) {
                return response()->json(['message' => 'Доступ запрещён'], 403);
            }
        }

        $this->authorizePaymentSystemMethod($paymentSystem->name);

        $paymentSystem->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Настройка конкретной ПС в админке разрешена только при соответствующем праве на способ оплаты.
     */
    private function authorizePaymentSystemMethod(string $name): void
    {
        if ($name === 'robokassa') {
            $this->authorize('payment.method.robokassa');

            return;
        }

        if ($name === 'tbank') {
            $user = Auth::user();
            if (!$user->can('payment.method.tbankCard') && !$user->can('payment.method.tbankSBP')) {
                abort(403);
            }

            return;
        }
    }
}
