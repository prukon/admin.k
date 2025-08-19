{{--@extends('layouts.app') --}}{{-- или твой admin layout --}}
@extends('layouts.admin2')



@section('content')
    <div class="container">
        <h1 class="mb-4">Заявки с лендинга</h1>

        <table class="table table-bordered">
            <thead>
            <tr>
                <th>#</th>
                <th>Имя</th>
                <th>Телефон</th>
                <th>Email</th>
                <th>Сообщение</th>
                <th>Дата</th>
            </tr>
            </thead>
            <tbody>
            @forelse($submissions as $submission)
                <tr>
                    <td>{{ $submission->id }}</td>
                    <td>{{ $submission->name }}</td>
                    <td>{{ $submission->phone }}</td>
                    <td>{{ $submission->email ?? '—' }}</td>
                    <td>{{ Str::limit($submission->message, 50) }}</td>
                    <td>{{ $submission->created_at->format('d.m.Y H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="6">Заявок пока нет.</td></tr>
            @endforelse
            </tbody>
        </table>

        {{ $submissions->links() }}
    </div>
@endsection
