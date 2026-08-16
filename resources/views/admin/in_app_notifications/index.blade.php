@extends('layouts.admin2')

@php
    $highlightId = (int) ($highlightId ?? 0);
    $cssPath = public_path('css/in-app-notifications.css');
@endphp

@section('content')
    @vite(['resources/css/admin-list-toolbar.css'])
    <link rel="stylesheet" href="{{ asset('css/in-app-notifications.css') }}?v={{ @filemtime($cssPath) ?: time() }}">

    <div class="main-content text-start ian-page">
        <div class="card payments-report-surface border-0 shadow-sm mb-3 mt-2">
            <div class="card-body px-3 py-3">
                <div class="payments-report-toolbar d-flex flex-nowrap align-items-center justify-content-between gap-2 gap-md-3 min-w-0">
                    <div class="d-flex align-items-center gap-2 min-w-0 flex-shrink-1">
                        <h1 class="h5 mb-0 fw-semibold text-body payments-report-title text-truncate">Уведомления</h1>
                        <span class="ian-unread-pill {{ $unreadCount > 0 ? '' : 'is-zero' }}">
                            {{ $unreadCount > 0 ? $unreadCount.' непрочит.' : 'Все прочитаны' }}
                        </span>
                    </div>
                    <div class="d-flex align-items-center gap-2 payments-report-toolbar-actions flex-shrink-0">
                        @if($unreadCount > 0)
                            <form method="POST" action="{{ route('inAppNotifications.readAll') }}" class="mb-0 d-inline-flex">
                                @csrf
                                <button type="submit" class="payments-report-toolbar-action d-inline-flex align-items-center gap-2">
                                    <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                                        <i class="fas fa-check-double payments-report-toolbar-icon"></i>
                                    </span>
                                    <span class="payments-report-toolbar-label d-none d-sm-inline">Прочитать все</span>
                                </button>
                            </form>
                        @endif
                        @if(!empty($canCompose))
                            <a href="{{ route('inAppNotifications.compose') }}"
                               class="payments-report-toolbar-action d-inline-flex align-items-center gap-2 text-decoration-none">
                                <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                                    <i class="fas fa-plus payments-report-toolbar-icon"></i>
                                </span>
                                <span class="payments-report-toolbar-label d-none d-sm-inline">Создать</span>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if($notifications->isEmpty())
            <div class="ian-empty">
                <div class="ian-empty-icon" aria-hidden="true">
                    <i class="fas fa-bell"></i>
                </div>
                <div class="ian-empty-title">Пока тихо</div>
                <div>Новых уведомлений нет. Когда появится сообщение, оно окажется здесь и в колокольчике.</div>
            </div>
        @else
            <div class="ian-feed">
                @foreach($notifications as $item)
                    @php
                        $itemId = (int) ($item['id'] ?? 0);
                        $isRead = !empty($item['is_read']);
                        $category = (string) ($item['category'] ?? 'normal');
                    @endphp
                    <article id="in-app-notification-{{ $itemId }}"
                             class="ian-card {{ $isRead ? 'is-read' : 'is-unread' }}{{ $highlightId === $itemId ? ' is-focused' : '' }}">
                        <div class="ian-card-inner">
                            <div class="ian-card-head">
                                <h2 class="ian-card-title">{{ $item['title'] }}</h2>
                            </div>
                            <div class="ian-card-meta">
                                @if($category !== 'normal')
                                    <span class="ian-badge ian-badge--{{ $category }}">{{ $item['category_label'] }}</span>
                                    <span class="ian-dot" aria-hidden="true"></span>
                                @endif
                                <time>{{ $item['created_at_human'] }}</time>
                                @if(!$isRead)
                                    <span class="ian-dot" aria-hidden="true"></span>
                                    <span class="ian-unread-label">непрочитано</span>
                                @endif
                            </div>
                            <div class="ian-card-body">{!! $item['body'] !!}</div>
                            @if(!$isRead)
                                <div class="ian-card-actions">
                                    <form method="POST" action="{{ route('inAppNotifications.read', ['notification' => $itemId]) }}">
                                        @csrf
                                        <button type="submit" class="ian-mark-read">Отметить прочитанным</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="mt-3">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
@endsection

@if($highlightId > 0)
    @push('scripts')
        <script>
            (function () {
                var el = document.getElementById('in-app-notification-{{ $highlightId }}');
                if (!el) {
                    return;
                }
                window.requestAnimationFrame(function () {
                    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                });
            })();
        </script>
    @endpush
@endif
