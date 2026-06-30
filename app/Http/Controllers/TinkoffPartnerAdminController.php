<?php

namespace App\Http\Controllers;

use App\Models\Partner;
use App\Services\Tinkoff\SmRegisterClient;
use Illuminate\Http\Request;

class TinkoffPartnerAdminController extends Controller
{
    private const DEPRECATED_MESSAGE = 'Регистрация и обновление реквизитов T‑Bank выполняются в справочнике «Юр. лица».';

    public function show($id)
    {
        $partner = Partner::findOrFail($id);
        return view('tinkoff.partners.show', compact('partner'));
    }

    public function smRegister($id, Request $r, SmRegisterClient $sm)
    {
        return $this->deprecatedResponse($r);
    }

    public function smPatch($id, Request $r, SmRegisterClient $sm)
    {
        return $this->deprecatedResponse($r);
    }

    public function smRefresh($id)
    {
        return $this->deprecatedResponse(request());
    }

    private function deprecatedResponse(Request $request)
    {
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'ok' => false,
                'message' => self::DEPRECATED_MESSAGE,
            ], 410);
        }

        return redirect()
            ->route('admin.legal-entities.index')
            ->with('warning', self::DEPRECATED_MESSAGE);
    }
}
