@php
    $item = is_array($item ?? null) ? $item : [];
    $id = (int) ($item['id'] ?? 0);
    $isRead = !empty($item['is_read']);
    $pageUrl = (string) ($item['page_url'] ?? route('inAppNotifications.index', ['n' => $id]));
    $title = (string) ($item['title'] ?? '');
    $preview = (string) ($item['body_preview'] ?? '');
    $category = (string) ($item['category'] ?? 'normal');
    $categoryLabel = (string) ($item['category_label'] ?? '');
    $createdHuman = (string) ($item['created_at_human'] ?? '');
    $showCategoryBadge = in_array($category, ['update', 'important'], true);
@endphp
@if($id > 0)
    <a class="dropdown-item in-app-bell-item {{ $isRead ? '' : 'unread' }}"
       href="{{ $pageUrl }}"
       data-notification-id="{{ $id }}">
        <div class="bell-title">{{ $title }}</div>
        <div class="small text-muted bell-meta">
            @if($showCategoryBadge)
                <span class="ian-badge ian-badge--{{ $category }}">{{ $categoryLabel }}</span>
            @endif
            <time>{{ $createdHuman }}</time>
        </div>
        <div class="small">{{ $preview }}</div>
    </a>
@endif
