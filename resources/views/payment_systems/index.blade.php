@extends('layouts.admin2')

@section('content')
    <div class="container">
        <h1>Платёжные системы</h1>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <a href="{{ route('payment-systems.create') }}" class="btn btn-primary">Добавить систему</a>

        <table class="table mt-3">
            <thead>
            <tr>
                <th>ID</th>
                <th>Партнёр</th>
                <th>Название</th>
                <th>Merchant Login</th>
                <th>Test Mode</th>
                <th>Действия</th>
            </tr>
            </thead>
            <tbody>
            @foreach($paymentSystems as $system)
                <tr>
                    <td>{{ $system->id }}</td>
                    <td>
                        @if($system->partner)
                            {{ $system->partner->name }}
                            {{-- Или другое поле, в зависимости от модели Partner --}}
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ $system->name }}</td>
                    <td>{{ $system->merchant_login }}</td>
                    <td>{{ $system->test_mode ? 'Да' : 'Нет' }}</td>
                    <td>
                        <a href="{{ route('payment-systems.edit', $system) }}" class="btn btn-sm btn-warning">
                            Редактировать
                        </a>
                        <form action="{{ route('payment-systems.destroy', $system) }}" method="POST" style="display:inline-block">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-danger" onclick="return confirm('Точно удалить?')">
                                Удалить
                            </button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endsection
