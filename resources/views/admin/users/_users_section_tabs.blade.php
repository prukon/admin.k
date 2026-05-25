<ul class="nav nav-tabs" id="usersSectionTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ ($activeTab ?? 'users') === 'users' ? 'active' : '' }}"
           href="{{ route('admin.user1') }}"
           role="tab">Все пользователи</a>
    </li>
    @can('trainers.view')
        <li class="nav-item" role="presentation">
            <a class="nav-link {{ ($activeTab ?? '') === 'trainers' ? 'active' : '' }}"
               href="{{ route('admin.trainers.index') }}"
               role="tab">Тренеры</a>
        </li>
    @endcan
</ul>
