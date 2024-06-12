@extends('layouts/main')
@extends('layouts/header')

@extends('layouts/footer')
@extends('layouts/mainMenu')


@section('content')




    <div class="col-md-9 main-content">

        @foreach($allUsers as $user)
            <div>{{$user->id}}.{{$user->name}}</div>
        @endforeach


    </div>
    </div>
    </div>
@endsection