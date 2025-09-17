@extends('layouts.admin2')
@section('content')
    <div class="main-content text-start">
        <h4 class="pt-3 ">Пользователи</h4>
        <hr>
        <div class="buttons">
            <div class="row gy-2 index-user-wrap">
                <div id="search-container" class="col-12 col-md-6">
                    <input id="search-input" class="mr-2 search-input ps-3 width-170" type="text" placeholder="Имя">
                    <select id="search-select" class="mr-2 ml-1 search-select width-170">
                        <option value="">Группа</option>
                        <option value="none">Без группы</option>
                        @foreach($allTeams as $team)
                            <option value="{{ $team->id }}">{{ $team->title }}</option>
                        @endforeach
                    </select>
                    <button id="search-button" class="btn btn-primary">Найти</button>
                </div>
                <div class="col-12 col-md-6 text-start">
                    <button id="new-user" type="button" class="btn btn-primary mr-2 new-user width-170"
                            data-bs-toggle="modal"
                            data-bs-target="#createUserModal">
                        Новый пользователь
                    </button>
                    <button id="field-modal" type="button" class="btn btn-primary mr-2"
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

        <div class="wrap-user-list">
            @foreach($allUsers as $user)
                <div class="user">

                    <a href="javascript:void(0);" class="edit-user-link" data-id="{{ $user->id }}"
                       style="{{ $user->is_enabled == 0 ? 'color: red;' : '' }}">
                        {{--{{ $counter }}. {{$user->name}}--}}
                        {{ $counter }}. {{ $user?->full_name ?: 'Без имени' }}

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

@endsection

@section('scripts')
    <script>
        function clickToSearch() {

            function searchUserName() {
                document.getElementById('search-button').addEventListener('click', function () {
                    var query = document.getElementById('search-input').value;
                    // Формируем новый URL
                    var newUrl = new URL(window.location.href);
                    if (query) {
                        // Если в инпуте есть текст, устанавливаем GET-параметр
                        newUrl.searchParams.set('name', query);
                    } else {
                        // Если инпут пустой, удаляем GET-параметр
                        newUrl.searchParams.delete('name');
                    }
                    // Обновляем URL без перезагрузки страницы
                    window.history.pushState(null, '', newUrl);
                    // Перезагружаем страницу с новым URL


                    var selectedOption = document.getElementById('search-select').value;
                    // Формируем новый URL
                    var newUrl = new URL(window.location.href);
                    if (selectedOption) {
                        // Если выбрана опция, устанавливаем GET-параметр
                        newUrl.searchParams.set('team_id', selectedOption);
                    } else {
                        // Если не выбрана опция (значение пустое), удаляем GET-параметр
                        newUrl.searchParams.delete('team_id');
                    }
                    // Обновляем URL без перезагрузки страницы
                    window.history.pushState(null, '', newUrl);


                    window.location.reload();
                });
            }

            // Функция для установки значения инпута при загрузке страницы
            function setInputFromURL() {
                var urlParams = new URLSearchParams(window.location.search);
                var nameQuery = urlParams.get('name');
                if (nameQuery) {
                    document.getElementById('search-input').value = nameQuery;
                }
            }

            // Функция для установки значения селекта при загрузке страницы
            function setSelectFromURL() {
                var urlParams = new URLSearchParams(window.location.search);
                var teamId = urlParams.get('team_id');
                if (teamId) {
                    document.getElementById('search-select').value = teamId;
                }
            }

            // Вызываем функции после загрузки страницы
            window.onload = function () {
                searchUserName();
                setInputFromURL();
                setSelectFromURL();
            };

        }

        clickToSearch();

        $(document).ready(function () {
            showLogModal("{{ route('logs.data.user') }}"); // Здесь можно динамически передать route
        })
    </script>
@endsection