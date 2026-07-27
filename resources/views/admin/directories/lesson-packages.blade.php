@extends('layouts.admin2')

@php
    $activeTab = 'packages';
@endphp

@section('content')
    <div class="main-content text-start">
        <h4 class="pt-3 pb-3 text-start">Справочники</h4>

        @include('admin.directories._section_tabs')

        @include('admin.lessonPackages.tabs.packages')
    </div>
@endsection
