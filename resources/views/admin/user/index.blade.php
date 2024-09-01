@extends('layouts/main2')
@extends('layouts.admin2')

@section('content')

    <div class="col-md-9 main-content">

        <h4 class="pt-3">Пользователи</h4>
        @php
            $counter = 1;
        @endphp

        @foreach($allUsers as $user)
            <div>
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
{{--    </div>--}}
{{--    </div>--}}
@endsection