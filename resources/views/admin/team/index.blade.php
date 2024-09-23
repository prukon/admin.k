@extends('layouts/main2')
@extends('layouts.admin2')

@section('content')

    <div class="col-md-9 main-content teams-list">

        <h4 class="pt-3">Группы</h4>

        <hr>
        <div>
            <a href="{{ route('admin.team.create') }}" class="btn btn-primary">Добавить группу</a>
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
    {{--    </div>--}}

@endsection