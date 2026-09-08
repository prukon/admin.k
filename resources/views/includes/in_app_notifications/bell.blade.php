{{-- Колокольчик in-app уведомлений. --}}
@php
    $bell = $inAppNotificationBell ?? null;
@endphp
@if(!empty($bell) && is_array($bell))
    @php
        $unreadCount = (int) ($bell['unread_count'] ?? 0);
        $bellItems = is_array($bell['items'] ?? null) ? $bell['items'] : [];
    @endphp
    <li class="nav-item dropdown d-flex align-items-center me-2"
        id="inAppNotificationBell"
        data-bell-url="{{ route('inAppNotifications.bell') }}"
        data-read-url-template="{{ route('inAppNotifications.read', ['notification' => '__ID__']) }}"
        data-read-all-url="{{ route('inAppNotifications.readAll') }}"
        data-index-url="{{ route('inAppNotifications.index') }}"
        data-current-partner-id="{{ (int) ($bell['current_partner_id'] ?? 0) }}"
        data-is-superadmin="{{ !empty($bell['is_superadmin']) ? '1' : '0' }}"
        data-csrf="{{ csrf_token() }}">
        <a class="nav-link position-relative px-2" href="#"
           id="inAppNotificationBellToggle"
           data-bs-toggle="dropdown"
           data-bs-display="static"
           aria-expanded="false"
           aria-label="Уведомления">
            <i class="fas fa-bell"></i>
            <span class="badge rounded-pill bg-danger js-in-app-bell-count in-app-bell-count"
                  style="{{ $unreadCount > 0 ? '' : 'display:none;' }}">{{ $unreadCount }}</span>
        </a>
        <div class="dropdown-menu dropdown-menu-end shadow-sm p-0 in-app-bell-menu" aria-labelledby="inAppNotificationBellToggle">
            <div class="px-3 py-2 border-bottom d-flex justify-content-between align-items-center">
                <strong>Уведомления</strong>
            </div>
            <div class="js-in-app-bell-list">
                @forelse($bellItems as $item)
                    @include('includes.in_app_notifications.bell_item', ['item' => $item])
                @empty
                    <div class="px-3 py-3 text-muted small js-in-app-bell-empty">Нет уведомлений</div>
                @endforelse
            </div>
            <div class="border-top">
                <a class="dropdown-item text-center py-2" href="{{ route('inAppNotifications.index') }}">Показать все</a>
            </div>
        </div>
    </li>
@endif
