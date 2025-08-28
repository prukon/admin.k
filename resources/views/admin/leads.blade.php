@extends('layouts.admin2')

@section('content')
    <div class="main-content text-start">

        <h4 class="pt-3">Заявки с лендинга</h4>
        <hr>

        <div class="container">
            <table class="table table-bordered">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Имя</th>
                    <th>Телефон</th>
                    <th>Email</th>
                    <th>Сайт</th>
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
                        <td>
                            @if($submission->website)
                                <a href="{{ $submission->website }}" target="_blank" rel="noopener">
                                    {{ Str::limit($submission->website, 30) }}
                                </a>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ Str::limit($submission->message, 50) }}</td>
                        <td>{{ $submission->created_at->format('d.m.Y H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7">Заявок пока нет.</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $submissions->links() }}
        </div>
    </div>
@endsection
