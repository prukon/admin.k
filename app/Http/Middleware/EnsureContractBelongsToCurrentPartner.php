<?php

namespace App\Http\Middleware;

use App\Models\Contract;
use Closure;
use Illuminate\Http\Request;

class EnsureContractBelongsToCurrentPartner
{
    public function handle(Request $request, Closure $next)
    {
        $partner = app('current_partner');
        abort_unless($partner, 403, 'Партнёр не выбран.');

        $contract = $request->route('contract');

        // На всякий случай: если биндинг не сработал и пришёл id — попробуем найти.
        if (is_numeric($contract)) {
            $contract = Contract::find($contract);
        }

        abort_unless($contract instanceof Contract, 404, 'Договор не найден.');
        abort_unless((int)$contract->school_id === (int)$partner->id, 403, 'Нет доступа к договору этого партнёра.');

        return $next($request);
    }
}

