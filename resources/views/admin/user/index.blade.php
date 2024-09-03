@extends('layouts/main2')
@extends('layouts.admin2')

@section('content')

    <div class="col-md-9 main-content users-list">

        <h4 class="pt-3 ">Пользователи</h4>

        <div id="search-container" class="d-flex align-items-center mb-3">
            <input id="search-input" class="find-input mr-3" type="text" placeholder="Имя">
            <select id="search-select" class="find-select mr-3">
                <option value="">Группа</option>
                @foreach($allTeams as $team)
                    <option value="{{ $team->id }}">{{ $team->title }}</option>
                @endforeach
            </select>
            <button id="search-button" class="btn btn-primary">Найти</button>
        </div>




        @php
            $counter = 1;
        @endphp

        @foreach($allUsers as $user)
            <div class="user">
                <a href="{{ route('admin.user.edit', $user->id) }}">
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

        <div>
            <a href="{{ route('admin.user.create') }}" class="btn btn-primary mb-3 mt-3">Добавить пользователя</a>
        </div>


    </div>

    <script>
        clickToSearch()
    </script>
    {{--    </div>--}}
    {{--    </div>--}}
@endsection