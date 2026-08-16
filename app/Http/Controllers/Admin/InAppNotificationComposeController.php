<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\InAppNotificationComposeRolesRequest;
use App\Http\Requests\Admin\StoreInAppNotificationRequest;
use App\Models\InAppNotification;
use App\Models\Partner;
use App\Services\InAppNotifications\InAppNotificationAudience;
use App\Services\InAppNotifications\InAppNotificationDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class InAppNotificationComposeController extends AdminBaseController
{
    public function create(InAppNotificationAudience $audience): View
    {
        $this->assertSuperAdminComposer();

        $partners = Partner::query()
            ->orderBy('title')
            ->get(['id', 'title', 'is_enabled']);

        $roles = $audience->availableRoles([], true);

        return view('admin.in_app_notifications.compose', [
            'partners' => $partners,
            'roles' => $roles,
            'categories' => config('in_app_notifications.categories'),
            'ttlPresets' => config('in_app_notifications.ttl_presets'),
            'defaults' => [
                'category' => InAppNotification::CATEGORY_NORMAL,
                'ttl_preset' => InAppNotification::TTL_7D,
                'all_partners' => false,
            ],
        ]);
    }

    public function roles(
        InAppNotificationComposeRolesRequest $request,
        InAppNotificationAudience $audience,
    ): JsonResponse {
        $this->assertSuperAdminComposer();

        $payload = $request->validatedPayload();
        $roles = $audience->availableRoles($payload['partner_ids'], $payload['all_partners']);

        return response()->json([
            'roles' => $roles->map(static fn ($role) => [
                'id' => (int) $role->id,
                'name' => (string) $role->name,
                'label' => (string) ($role->label ?: $role->name),
                'is_sistem' => (bool) $role->is_sistem,
            ])->values()->all(),
        ]);
    }

    public function store(
        StoreInAppNotificationRequest $request,
        InAppNotificationDispatcher $dispatcher,
    ): RedirectResponse {
        $this->assertSuperAdminComposer();

        try {
            $dispatcher->dispatchManual($request->validatedPayload(), $this->currentUser());
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['role_ids' => $e->getMessage()]);
        }

        return redirect()
            ->route('inAppNotifications.index')
            ->with('status', 'Уведомление поставлено в очередь на рассылку.');
    }

    private function assertSuperAdminComposer(): void
    {
        if (! $this->isSuperAdmin()) {
            abort(Response::HTTP_FORBIDDEN);
        }
    }
}
