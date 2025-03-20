{{--    @extends('layouts/main2')--}}
@extends('layouts.admin2')

@section('content')

    <div class="col-md-12 main-content users-list">
        <h4 class="pt-3 text-start">Пользователи</h4>
        <hr>
        <div class="buttons">
            <div class="row gy-2 index-user-wrap">
                <div id="search-container" class="col-12 col-md-6">
                    <input id="search-input" class="mr-2 search-input ps-3 width-170" type="text" placeholder="Имя">
                    <select id="search-select" class="mr-2 search-select width-170">
                        <option value="">Группа</option>
                        <option value="none">Без группы</option>
                        @foreach($allTeams as $team)
                            <option value="{{ $team->id }}">{{ $team->title }}</option>
                        @endforeach
                    </select>
                    <button id="search-button" class="btn btn-primary">Найти</button>
                </div>

                <div class="col-12 col-md-6">

                    <button id="new-user" type="button" class="btn btn-primary mr-2 new-user width-170"
                            data-bs-toggle="modal"
                            data-bs-target="#createUserModal">
                        Новый пользователь
                    </button>


                    <button id="field-modal" type="button" class="btn btn-primary"
                            data-bs-toggle="modal"
                            data-bs-target="#fieldModal">Настройки</button>


                    <div class="wrap-icon btn" data-bs-toggle="modal" data-bs-target="#historyModal">
                        <i class="fa-solid fa-clock-rotate-left logs "></i>
                    </div>

                    <!-- Модальное окно создания юзера -->
                @include('includes.modal.createUser')

                <!-- Модальное окно редактирования юзера -->
                @include('includes.modal.editUser')

                <!-- Модальное окно редактирования доп полей -->
                @include('includes.modal.fieldModal')

                <!-- Модальное окно логов -->
                @include('includes.logModal')

                </div>
            </div>
        </div>

        <hr>
        @php
            $counter = 1;
        @endphp

        <div class="wrap-user-list text-start">
            @foreach($allUsers as $user)
                <div class="user">

                    <a href="javascript:void(0);" class="edit-user-link" data-id="{{ $user->id }}"
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
    </div>
    <script>
        clickToSearch()

        $(document).ready(function () {
            showLogModal("{{ route('logs.data.user') }}"); // Здесь можно динамически передать route
        })
    </script>
@endsection