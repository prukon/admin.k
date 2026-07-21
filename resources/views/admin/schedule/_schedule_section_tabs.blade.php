<ul class="nav nav-tabs mb-3" id="scheduleSectionTabs" role="tablist">
    @can('schedule.view')
        <li class="nav-item" role="presentation">
            <a class="nav-link {{ ($activeTab ?? 'journal') === 'journal' ? 'active' : '' }}"
               href="{{ route('schedule.index', request()->only(['year', 'month', 'team'])) }}"
               role="tab">Журнал расписания</a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link {{ ($activeTab ?? '') === 'trainer-workload' ? 'active' : '' }}"
               href="{{ route('schedule.trainer-workload') }}"
               role="tab">Нагрузка тренеров</a>
        </li>
    @endcan
    @can('lessonOccurrenceStatuses.manage')
        <li class="nav-item" role="presentation">
            <a class="nav-link {{ ($activeTab ?? '') === 'occurrence-statuses' ? 'active' : '' }}"
               href="{{ route('schedule.occurrence-statuses') }}"
               role="tab">Статусы занятий</a>
        </li>
    @endcan
    @can('schedule.trainerSalary.view')
        <li class="nav-item" role="presentation">
            <a class="nav-link {{ ($activeTab ?? '') === 'trainer-salary' ? 'active' : '' }}"
               href="{{ route('schedule.trainer-salary') }}"
               role="tab">ЗП тренеров</a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link {{ in_array($activeTab ?? '', ['trainer-salary-sheets', 'trainer-salary-sheets-show'], true) ? 'active' : '' }}"
               href="{{ route('schedule.trainer-salary-sheets') }}"
               role="tab">Листы ЗП</a>
        </li>
    @endcan
</ul>
