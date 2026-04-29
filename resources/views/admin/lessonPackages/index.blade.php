@extends('layouts.admin2')

@section('content')
    <div class="main-content">
        <h4 class="pt-3 pb-3 text-start">Абонементы</h4>

        <ul class="nav nav-tabs" role="tablist">
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
            @else
                @include('admin.lessonPackages.tabs.packages')
            @endif
        </div>
    </div>
@endsection

