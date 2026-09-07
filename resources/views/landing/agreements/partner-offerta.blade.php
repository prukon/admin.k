@extends('layouts.landingPage')

@section('title', 'Партнёрская оферта — kidscrm.online')
@section('meta_description', 'Партнёрская оферта сервиса kidscrm.online для школ и секций.')
@section('meta_robots', 'noindex,nofollow')

@section('content')
    <div class="main-content text-start">
        @include('agreements.partner-offerta')
    </div>
@endsection
