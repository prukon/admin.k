<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\SettingPricesInvoiceEmailRequest;
use App\Services\PartnerContext;
use App\Services\SettingPrices\SettingPricesInvoiceEmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SettingPricesInvoiceEmailController extends AdminBaseController
{
    public function __construct(
        PartnerContext $partnerContext,
        private readonly SettingPricesInvoiceEmailService $invoiceEmail,
    ) {
        parent::__construct($partnerContext);
    }

    public function preview(SettingPricesInvoiceEmailRequest $request): JsonResponse|RedirectResponse
    {
        $row = $this->invoiceEmail->findCharge(
            $this->requirePartnerId(),
            (int) $request->validated('user_id'),
            (int) $request->validated('team_id'),
            (string) $request->validated('new_month'),
        );

        if ($row === null) {
            return $this->respond($request, [
                'message' => 'Начисление не найдено.',
            ], 404);
        }

        return $this->respond($request, $this->invoiceEmail->preview($row));
    }

    public function send(SettingPricesInvoiceEmailRequest $request): JsonResponse|RedirectResponse
    {
        $row = $this->invoiceEmail->findCharge(
            $this->requirePartnerId(),
            (int) $request->validated('user_id'),
            (int) $request->validated('team_id'),
            (string) $request->validated('new_month'),
        );

        if ($row === null) {
            return $this->respond($request, [
                'message' => 'Начисление не найдено.',
            ], 404);
        }

        return $this->respond($request, $this->invoiceEmail->send($row));
    }

    /**
     * AJAX и Accept: application/json → JSON. Обычная HTML-форма → редирект назад на вкладку.
     *
     * @param  array<string, mixed>  $payload
     */
    private function respond(Request $request, array $payload, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json($payload, $status);
        }

        if ($status === 404) {
            return redirect()->back()->withErrors([
                'user_id' => (string) ($payload['message'] ?? 'Начисление не найдено.'),
            ]);
        }

        $errors = $payload['errors'] ?? [];
        if (is_array($errors) && $errors !== []) {
            return redirect()->back()->withErrors($errors);
        }

        $message = trim((string) ($payload['message'] ?? ''));
        $redirect = redirect()->back();

        return $message !== '' ? $redirect->with('status', $message) : $redirect;
    }
}
