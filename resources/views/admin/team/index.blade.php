@extends('layouts/main2')
@extends('layouts.admin2')

@section('content')

    <div class="col-md-9 main-content teams-list">

        <h4 class="pt-3">Группы</h4>

        <hr>
        <div class="buttons d-flex flex-row align-items-center mb-3">
        <a href="{{ route('admin.team.create') }}" class="btn btn-primary">Добавить группу</a>
            <button type="button" class="btn btn-primary ml-3" id="logs" data-bs-toggle="modal" data-bs-target="#historyModal">История изменений</button>

        </div>
        <hr>

        @php
            $counter = 1;
        @endphp

        @foreach($allTeams as $team)
            <div class="team">
                <a href="{{ route('admin.team.edit', $team->id) }}"
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

    <!-- Модальное окно логов -->
    @include('includes.logModal')
    <!-- Модальное окно логов -->
    <script>
        $(document).ready(function() {
            showLogModal("{{ route('logs.data.team') }}");
        })
    </script>
@endsection