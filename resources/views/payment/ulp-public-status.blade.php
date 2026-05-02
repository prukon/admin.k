@extends('layouts.app')

@section('title', $title ?? 'Оплата')

@section('content')
    <div class="container" style="max-width: 36rem;">
        <div class="card shadow-sm border-0 mt-3">
            <div class="card-body p-4">
                <h1 class="h5 mb-3">{{ $title ?? 'Оплата' }}</h1>
                <p class="text-muted mb-0">{{ $message ?? '' }}</p>
            </div>
        </div>
    </div>
@endsection
