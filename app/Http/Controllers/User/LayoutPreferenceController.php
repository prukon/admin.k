<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UpdateLayoutWideRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class LayoutPreferenceController extends Controller
{
    public function update(UpdateLayoutWideRequest $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $layoutWide = $request->boolean('layout_wide');

        $user->layout_wide = $layoutWide;
        $user->save();

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'layout_wide' => $layoutWide,
            ]);
        }

        return back()->with(
            'status',
            $layoutWide
                ? 'Кабинет развёрнут на всю ширину.'
                : 'Кабинет возвращён к обычной ширине.'
        );
    }
}
