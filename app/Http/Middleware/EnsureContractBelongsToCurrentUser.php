<?php

namespace App\Http\Middleware;

use App\Models\Contract;
use App\Models\User;
use App\Services\Users\FamilyStudentContextService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureContractBelongsToCurrentUser
{
    public function handle(Request $request, Closure $next)
    {
        $contract = $request->route('contract');

        if (is_numeric($contract)) {
            $contract = Contract::find($contract);
        }

        abort_unless($contract instanceof Contract, 404, 'Договор не найден.');

        $actor = Auth::user();
        abort_unless($actor instanceof User, 404, 'Договор не найден.');
        abort_unless(
            app(FamilyStudentContextService::class)->canAccessContract($actor, $contract),
            404,
            'Договор не найден.'
        );

        return $next($request);
    }
}
