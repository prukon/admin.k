@extends('layouts.admin2')

@section('title','Статус платежа')

@section('content')
    <div class="container py-4">
        <div class="alert alert-info">
            {{ $message ?? 'Платёж обрабатывается.' }}
        </div>
        <a href="/partner-wallet" class="btn btn-primary">Вернуться в кошелёк</a>
    </div>
@endsection
