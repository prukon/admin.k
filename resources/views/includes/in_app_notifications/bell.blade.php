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
    <style>
        #inAppNotificationBell > .nav-link {
            position: relative;
        }
        .in-app-bell-count {
            position: absolute;
            top: 2px;
            right: -4px;
            font-size: 0.55rem;
            line-height: 1;
            min-width: 1rem;
            padding: 0.18em 0.38em;
            font-weight: 700;
            transform: none;
        }
        .in-app-bell-menu {
            min-width: 420px;
            width: 460px;
            max-width: min(460px, calc(100vw - 1rem));
            overflow: hidden;
        }
        .in-app-bell-item { white-space: normal; padding: 0.7rem 1rem; border-left: 3px solid transparent; }
        .in-app-bell-item.unread { background: #fff8ee; border-left-color: #f3a12b; }
        .in-app-bell-item .bell-title { font-weight: 600; color: #212529; }
        .in-app-bell-item .bell-meta {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.35rem 0.5rem;
            margin: 0.15rem 0 0.2rem;
        }
        .in-app-bell-menu .ian-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.12rem 0.55rem;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 650;
            letter-spacing: 0.01em;
            line-height: 1.4;
            color: inherit;
        }
        .in-app-bell-menu .ian-badge--update { color: #0d5f7c; background: #e7f6fb; }
        .in-app-bell-menu .ian-badge--important { color: #fff; background: #8e1c13; }
        .in-app-bell-item:hover { background: #f4f6f8; }
        .in-app-bell-item.unread:hover { background: #fff3e0; }
    </style>
@endif
