@extends('layouts.landingPage')

@section('title', 'Публичная оферта — kidscrm.online')
@section('meta_description', 'Публичная оферта сервиса kidscrm.online для пользователей платформы.')

@section('content')
    <div class="main-content text-start">   
        @include('agreements.public-offerta')
    </div>

@endsection
 