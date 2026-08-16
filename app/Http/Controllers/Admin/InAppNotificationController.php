<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminBaseController;
use App\Models\InAppNotification;
use App\Services\InAppNotifications\InAppNotificationInbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class InAppNotificationController extends AdminBaseController
{
    public function index(Request $request, InAppNotificationInbox $inbox): View
    {
        $user = $this->currentUser();
        $perPage = (int) config('in_app_notifications.index_per_page', 20);
        $highlightId = (int) $request->query('n', 0);

        if ($highlightId > 0) {
            $visible = $inbox->findVisible($user, $highlightId);
            if ($visible !== null) {
                $inbox->markRead($user, $visible);
            } else {
                $highlightId = 0;
            }
        }

        return view('admin.in_app_notifications.index', [
            'notifications' => $inbox->paginate($user, $perPage),
            'unreadCount' => $inbox->unreadCount($user),
            'canCompose' => $this->isSuperAdmin($user),
            'highlightId' => $highlightId,
        ]);
    }

    public function bell(InAppNotificationInbox $inbox): JsonResponse
    {
        $user = $this->currentUser();
        $limit = (int) config('in_app_notifications.dropdown_limit', 3);

        return response()->json([
            'unread_count' => $inbox->unreadCount($user),
            'items' => $inbox->latestItems($user, $limit),
        ]);
    }

    public function markRead(
        Request $request,
        InAppNotification $notification,
        InAppNotificationInbox $inbox,
    ): JsonResponse|RedirectResponse {
        $user = $this->currentUser();
        $visible = $inbox->findVisible($user, (int) $notification->id);
        if ($visible === null) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $inbox->markRead($user, $visible);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'unread_count' => $inbox->unreadCount($user),
                'items' => $inbox->latestItems($user, (int) config('in_app_notifications.dropdown_limit', 3)),
            ]);
        }

        return redirect()
            ->route('inAppNotifications.index')
            ->with('status', 'Уведомление отмечено как прочитанное.');
    }

    public function markAllRead(Request $request, InAppNotificationInbox $inbox): JsonResponse|RedirectResponse
    {
        $user = $this->currentUser();
        $inbox->markAllRead($user);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'unread_count' => 0,
                'items' => $inbox->latestItems($user, (int) config('in_app_notifications.dropdown_limit', 3)),
            ]);
        }

        return redirect()
            ->route('inAppNotifications.index')
            ->with('status', 'Все уведомления отмечены как прочитанные.');
    }

    public function open(InAppNotification $notification, InAppNotificationInbox $inbox): RedirectResponse
    {
        $user = $this->currentUser();
        $visible = $inbox->findVisible($user, (int) $notification->id);
        if ($visible === null) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $inbox->markRead($user, $visible);

        return redirect()->route('inAppNotifications.index', ['n' => $visible->id]);
    }
}
