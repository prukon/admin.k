@extends('layouts.admin2')
@section('content')
    {{--<div class="col-md-12 main-content users-list text-start">--}}
    <div class="main-content text-start">

        <h4 class="pt-3">Группы</h4>
        <hr>
        <div class="buttons d-flex flex-row align-items-center mb-3">
            <button id="new-team" type="button" class="btn btn-primary mr-2 new-team width-170"
                    data-bs-toggle="modal" data-bs-target="#createTeamModal">
                Добавить группу
            </button>
            <button type="button" class="btn btn-primary width-170" id="logs" data-bs-toggle="modal"
                    data-bs-target="#historyModal">История изменений
            </button>
        </div>
        <hr>
        @php
            $counter = 1;
        @endphp

        @foreach($allTeams as $team)
            <div class="team">
                {{--                <a href="{{ route('admin.team.edit', $team->id) }}"--}}
                {{--                   style="{{ $team->is_enabled == 0 ? 'color: red;' : '' }}">--}}
                {{--                    {{ $counter }}. {{$team->title}}--}}
                {{--                </a>--}}
                <a href="javascript:void(0);" class="edit-team-link" data-id="{{ $team->id }}"
                   style="{{ $team->is_enabled == 0 ? 'color: red;' : '' }}">
                    {{ $counter }}. {{$team->title}}
                </a>
            </div>
            @php
                $counter++;
            @endphp
        @endforeach

        <div class="mt-3">
            {{ $allTeams->withQueryString()->links() }}
        </div>
    </div>
    <!-- Модальное окно создания юзера -->
    @include('includes.modal.createTeam')

    <!-- Модальное окно редактирования юзера -->
    @include('includes.modal.editTeam')

    <!-- Модальное окно логов -->
    @include('includes.logModal')

@endsection

@section('scripts')
    <!-- Модальное окно логов -->
    <script>
        $(document).ready(function () {
            showLogModal("{{ route('logs.data.team') }}");
        })
    </script>
@endsection