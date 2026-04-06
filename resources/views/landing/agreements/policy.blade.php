@extends('layouts.landingPage')

@section('title', 'Политика конфиденциальности — kidscrm.online')
@section('meta_description', 'Политика обработки персональных данных сервиса kidscrm.online.')

@section('content')
    <div class="main-content text-start">   
        @include('agreements.policy')
    </div>

@endsection
 