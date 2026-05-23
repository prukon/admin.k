@extends('layouts.admin2')

@section('title', 'Шаблон: ' . $template->title)

@section('content')
    <div class="main-content text-start">
        <h4 class="pt-3">Шаблон: {{ $template->title }}</h4>
        <hr>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <form method="post" action="{{ route('contract-templates.update', $template) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Название</label>
                    <input type="text" name="title" class="form-control" value="{{ old('title', $template->title) }}" required>
                    @error('title')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">Текущая версия DOCX</label>
                    <div class="d-flex align-items-center gap-2">
                        @if($template->currentVersion)
                            <span class="badge bg-info text-dark">v{{ $template->currentVersion->version }}</span>
                            <a href="{{ route('contract-templates.download-docx', $template) }}" class="btn btn-sm btn-outline-secondary">Скачать DOCX</a>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label">Загрузить новую версию DOCX (необязательно)</label>
                    <input type="file" name="docx" class="form-control" accept=".docx">
                    <div class="form-text">При загрузке нового файла будет создана новая версия; существующие договоры останутся на старой версии.</div>
                    @error('docx')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_archived" value="1" id="is_archived"
                               @checked(old('is_archived', $template->is_archived))>
                        <label class="form-check-label" for="is_archived">В архиве (нельзя выбрать при создании договора)</label>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label">Тема письма клиенту</label>
                    <input type="text" name="email_subject" class="form-control"
                           value="{{ old('email_subject', $template->currentVersion?->email_subject) }}" maxlength="255">
                    @error('email_subject')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label class="form-label">Текст письма (HTML)</label>
                    <textarea name="email_body_html" class="form-control" rows="8">{{ old('email_body_html', $template->currentVersion?->email_body_html) }}</textarea>
                    <div class="form-text">
                        Подстановки:
                        <code>&#123;&#123;documents_url&#125;&#125;</code>,
                        <code>&#123;&#123;student_name&#125;&#125;</code>,
                        <code>&#123;&#123;contract_id&#125;&#125;</code>
                    </div>
                    @error('email_body_html')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
            </div>

            @include('contract-templates.partials.fields-editor', [
                'fields' => old('fields') ? array_values(old('fields')) : ($fields ?? []),
                'prefillSources' => $prefillSources,
            ])

            <div class="mt-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Сохранить</button>
                <a href="{{ route('contract-templates.index') }}" class="btn btn-outline-secondary">К списку</a>
            </div>
        </form>
    </div>
@endsection
