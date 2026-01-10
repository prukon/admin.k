<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentIntent;
use Illuminate\Http\Request;

class PaymentIntentController extends Controller
{
    public function index(Request $request)
    {
        $q = PaymentIntent::query()
            ->with(['user', 'partner'])
            ->orderByDesc('id');

        if ($request->filled('inv_id') && ctype_digit((string) $request->query('inv_id'))) {
            $q->where('id', (int) $request->query('inv_id'));
        }

        if ($request->filled('status')) {
            $q->where('status', (string) $request->query('status'));
        }

        if ($request->filled('provider')) {
            $q->where('provider', (string) $request->query('provider'));
        }

        if ($request->filled('partner_id') && ctype_digit((string) $request->query('partner_id'))) {
            $q->where('partner_id', (int) $request->query('partner_id'));
        }

        if ($request->filled('user_id') && ctype_digit((string) $request->query('user_id'))) {
            $q->where('user_id', (int) $request->query('user_id'));
        }

        if ($request->filled('created_from')) {
            $q->whereDate('created_at', '>=', (string) $request->query('created_from'));
        }
        if ($request->filled('created_to')) {
            $q->whereDate('created_at', '<=', (string) $request->query('created_to'));
        }

        if ($request->filled('paid_from')) {
            $q->whereDate('paid_at', '>=', (string) $request->query('paid_from'));
        }
        if ($request->filled('paid_to')) {
            $q->whereDate('paid_at', '<=', (string) $request->query('paid_to'));
        }

        $intents = $q->paginate(50)->appends($request->query());

        return view('admin.payment_intents.index', [
            'intents' => $intents,
            'filters' => $request->query(),
        ]);
    }
}


