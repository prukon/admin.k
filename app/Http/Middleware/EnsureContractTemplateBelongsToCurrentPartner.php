<?php

namespace App\Http\Middleware;

use App\Models\ContractTemplate;
use Closure;
use Illuminate\Http\Request;

class EnsureContractTemplateBelongsToCurrentPartner
{
    public function handle(Request $request, Closure $next)
    {
        $partner = app('current_partner');
        abort_unless($partner, 403, 'Партнёр не выбран.');

        $template = $request->route('template');

        if (is_numeric($template)) {
            $template = ContractTemplate::find($template);
        }

        abort_unless($template instanceof ContractTemplate, 404, 'Шаблон не найден.');
        abort_unless((int) $template->partner_id === (int) $partner->id, 403, 'Нет доступа к шаблону этого партнёра.');

        return $next($request);
    }
}
