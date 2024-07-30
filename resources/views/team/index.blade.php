@extends('layouts/main')
@extends('layouts/header')
@extends('layouts/footer')
@extends('layouts/mainMenu')
@section('content')

    <div class="col-md-9 main-content">

        <h4 class="mt-3">Группы</h4>

        @foreach($allTeams as $team)
            <div><a href="{{ route('team.edit', $team->id) }}">   {{$team->id}}. {{$team->title }} </a>


                @foreach($weekdays as $weekday)
                    @foreach($team->weekdays as $teamWeekday)
                      @if ($weekday->id === $teamWeekday->id)
{{--                         {{ $weekday->title }}--}}
                        @endif
                    @endforeach
                @endforeach


            </div>
        @endforeach

        <div class="mt-3">
            {{ $allTeams->withQueryString()->links() }}
        </div>


        <div>
            <a href="{{ route('team.create') }}" class="btn btn-primary mb-3 mt-3">Добавить группу</a>
        </div>


    </div>
    </div>
    </div>
@endsection