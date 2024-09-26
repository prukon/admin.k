@extends('layouts/main2')
@extends('layouts.admin2')

@section('content')

    <div class="col-md-9 main-content users-list">


        <h4 class="pt-3 ">Пользователи</h4>

        <hr>
        <div class="buttons d-flex flex-row align-items-center mb-3">

            <div id="search-container" class="d-flex flex-row align-items-center">
                <input id="search-input" class="form-control mr-3" type="text" placeholder="Имя" style="height: 38px;">
                <select id="search-select" class="form-control mr-3" style="height: 38px;">
                    <option value="">Группа</option>
                    <option value="none">Без группы</option>
                    @foreach($allTeams as $team)
                        <option value="{{ $team->id }}">{{ $team->title }}</option>
                    @endforeach
                </select>
                <button id="search-button" class="btn btn-primary" style="height: 38px;">Найти</button>
            </div>

            <div class="ml-3 mr-3" style="width: 1px; height: 20px; background-color: #000;"></div>


            <a href="{{ route('admin.user.create') }}" class="btn btn-primary ">Добавить пользователя</a>

            <button type="button" class="btn btn-primary ml-3" id="logs" data-bs-toggle="modal" data-bs-target="#historyModal">История изменений</button>

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