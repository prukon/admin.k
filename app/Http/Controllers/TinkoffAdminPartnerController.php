<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tinkoff\SmPatchRequest;
use App\Http\Requests\Tinkoff\SmRegisterRequest;
use App\Models\Partner;
use App\Models\Setting;
use App\Models\TinkoffCommissionRule;
use App\Models\TinkoffPayment;
use App\Models\TinkoffPayout;
use App\Services\Tinkoff\SmRegisterClient;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TinkoffAdminPartnerController extends Controller
{
    /**
     * Сводка T‑Bank по партнёру (автовыплаты, недавние платежи).
     * sm-register перенесён в справочник «Юр. лица».
     */
    public function show($id)
    {
        $sessionPartnerId = session('current_partner');
        $effectiveId = $sessionPartnerId ?: $id;

        if ($sessionPartnerId && $sessionPartnerId !== (int) $id) {
            Log::info('[admin][show] session current_partner=' . $sessionPartnerId . ' (url id=' . $id . ') — показываю партнёра из сессии');
        }

        $partner = Partner::findOrFail($effectiveId);

        $waiting = TinkoffPayout::where('partner_id', $partner->id)
            ->whereIn('status', ['INITIATED', 'CREDIT_CHECKING'])
            ->count();

        $latestPayments = TinkoffPayment::where('partner_id', $partner->id)
            ->latest()->limit(20)->get();

        $autoPayoutSummary = TinkoffCommissionRule::autoPayoutSummaryForPartner((int) $partner->id);
        $scheduledIntervalMinutes = Setting::getTinkoffPayoutScheduledIntervalMinutes();

        $autoPayoutStats = TinkoffPayout::query()
            ->where('partner_id', $partner->id)
            ->where('source', 'auto')
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('count(*) as cnt, max(created_at) as last_at')
            ->first();
        $autoPayoutCount30 = (int) ($autoPayoutStats->cnt ?? 0);
        $autoPayoutLastAt = isset($autoPayoutStats->last_at) && $autoPayoutStats->last_at
            ? Carbon::parse($autoPayoutStats->last_at) : null;

        return view('tinkoff.partners.show', compact(
            'partner',
            'waiting',
            'latestPayments',
            'autoPayoutSummary',
            'scheduledIntervalMinutes',
            'autoPayoutCount30',
            'autoPayoutLastAt'
        ));
    }

    /** @deprecated Используйте admin/legal-entities/{id}/sm-register */
    public function smRegister($id, SmRegisterRequest $request, SmRegisterClient $sm)
    {
        return $this->deprecatedPartnerSmRegisterResponse($request);
    }

    /** @deprecated Используйте admin/legal-entities/{id}/sm-patch */
    public function smPatch($id, SmPatchRequest $request, SmRegisterClient $sm)
    {
        return $this->deprecatedPartnerSmRegisterResponse($request);
    }

    /** @deprecated Используйте admin/legal-entities/{id}/sm-refresh */
    public function smRefresh($id, Request $request, SmRegisterClient $sm)
    {
        return $this->deprecatedPartnerSmRegisterResponse($request);
    }

    /** @deprecated Используйте admin/legal-entities/{id}/sm-pull */
    public function smPull($id, Request $request, SmRegisterClient $sm)
    {
        return $this->deprecatedPartnerSmRegisterResponse($request);
    }

    private function deprecatedPartnerSmRegisterResponse(Request $request)
    {
        $message = 'Регистрация T‑Bank перенесена в справочник «Юр. лица».';

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['ok' => false, 'error' => $message], 410);
        }

        return redirect()
            ->route('admin.legal-entities.index')
            ->with('warning', $message);
    }
}
