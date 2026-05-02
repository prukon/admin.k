@extends('layouts.admin2')

@section('content')
    <div class="main-content">
        <h4 class="pt-3 pb-3 text-start">Расписание школы</h4>

        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link {{ ($activeTab ?? '') === 'school-schedule' ? 'active' : '' }}"
                   href="{{ route('admin.lesson-packages.school-schedule') }}"
                   role="tab">Расписание школы</a>
            </li>
            @can('scheduleSlots.table')
                <li class="nav-item" role="presentation">
                    <a class="nav-link {{ ($activeTab ?? '') === 'team-schedule-slots' ? 'active' : '' }}"
                       href="{{ route('admin.lesson-packages.team-schedule-slots') }}"
                       role="tab">Таблица занятий</a>
                </li>
            @endcan
            <li class="nav-item" role="presentation">
                <a class="nav-link {{ ($activeTab ?? '') === 'occurrence-statuses' ? 'active' : '' }}"
                   href="{{ route('admin.lesson-packages.occurrence-statuses.index') }}"
                   role="tab">Статусы занятий</a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link {{ ($activeTab ?? 'packages') === 'packages' ? 'active' : '' }}"
                   href="{{ route('admin.lesson-packages.index') }}"
                   role="tab">Абонементы</a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link {{ ($activeTab ?? '') === 'assignments' ? 'active' : '' }}"
                   href="{{ route('admin.lesson-packages.assignments') }}"
                   role="tab">Назначение абонементов</a>
            </li>
   
       
        </ul>

        <div class="tab-content">
            @if (($activeTab ?? 'packages') === 'assignments')
                @include('admin.lessonPackages.tabs.assignments')
            @elseif (($activeTab ?? '') === 'occurrence-statuses')
                @include('admin.lessonPackages.tabs.occurrenceStatuses')
            @elseif (($activeTab ?? '') === 'school-schedule')
                @include('admin.lessonPackages.tabs.schoolSchedule')
            @elseif (($activeTab ?? '') === 'team-schedule-slots')
                @include('admin.lessonPackages.tabs.teamScheduleSlotsTable')
            @else
                @include('admin.lessonPackages.tabs.packages')
            @endif
        </div>
    </div>
@endsection

