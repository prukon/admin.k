@extends('layouts.admin2')

@section('title','Создать договор')

@section('content')
    <div class="container py-3">
        <h1 class="h4 mb-3">Создать договор (загрузка PDF)</h1>

        <form method="post" action="{{ url('/contracts') }}" enctype="multipart/form-data" class="card p-3">
            @csrf

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Школа (ID)</label>
                    <input type="number" name="school_id" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Ученик (user_id)</label>
                    <input type="number" name="user_id" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Группа (group_id, опц.)</label>
                    <input type="number" name="group_id" class="form-control">
                </div>
                <div class="col-12">
                    <label class="form-label">PDF-файл договора</label>
                    <input type="file" name="pdf" class="form-control" accept="application/pdf" required>
                    @error('pdf')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="mt-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Сохранить</button>
                <a href="{{ url('/contracts') }}" class="btn btn-outline-secondary">Отмена</a>
            </div>
        </form>
    </div>
@endsection
