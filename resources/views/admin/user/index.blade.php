@extends('layouts/main2')
@extends('layouts.admin2')

@section('content')

    <div class="col-md-9 main-content users-list">


        <h4 class="pt-3 ">Пользователи</h4>

        <hr>


        <div class="buttons d-flex flex-column flex-md-row align-items-start align-items-md-center mb-3">
            <div id="search-container" class="d-flex flex-row align-items-center w-100 w-md-auto mb-2 mb-md-0" style="flex: 1;">
                <input id="search-input" class="form-control me-2" style="height: 38px;" type="text" placeholder="Имя" style="flex: 1; height: 38px;">
                <select id="search-select" class="form-control me-2" style="height: 38px;" style="flex: 1; height: 38px;">
                    <option value="">Группа</option>
                    <option value="none">Без группы</option>
                    @foreach($allTeams as $team)
                        <option value="{{ $team->id }}">{{ $team->title }}</option>
                    @endforeach
                </select>
                <button id="search-button" class="btn btn-primary" style="flex: 1; height: 38px; width: 100%;">Найти</button>
            </div>

            <div class="d-flex flex-row align-items-center mt-2 mt-md-0 w-100 w-md-auto" style="flex: 1;">
                <button type="button" class="btn btn-primary me-2  flex-fill" style="flex: 1; height: 38px; padding-top: 0; padding-bottom: 0;" onclick="window.location.href='{{ route('admin.user.create') }}'">Добавить пользователя</button>
                <button type="button" class="btn btn-primary flex-fill" id="logs" data-bs-toggle="modal" data-bs-target="#historyModal" style="flex: 1; height: 38px; width: 100%;">История изменений</button>
            </div>
        </div>




        <hr>
        @php
            $counter = 1;
        @endphp

        @foreach($allUsers as $user)
            <div class="user">
                <a href="{{ route('admin.user.edit', $user->id) }}"
                   style="{{ $user->is_enabled == 0 ? 'color: red;' : '' }}">
                    {{ $counter }}. {{$user->name}}
                </a>
            </div>
            @php
                $counter++;
            @endphp
        @endforeach


        <div class="mt-3">
            {{ $allUsers->withQueryString()->links() }}
        </div>

    </div>

    <script>
        clickToSearch()
    </script>

    <!-- Модальное окно логов -->
    @include('includes.logModal')
    <!-- Модальное окно логов -->
    <script>
        $(document).ready(function() {
            showLogModal("{{ route('logs.data.user') }}"); // Здесь можно динамически передать route
        })
    </script>
@endsection