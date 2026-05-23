@extends('layouts.admin2')

@section('title', 'Новый шаблон договора')

@section('content')
    <div class="main-content text-start">
        <h4 class="pt-3">Новый шаблон договора</h4>
        <hr>

        <p class="text-muted">
            Загрузите DOCX с плейсхолдерами вида <code>&#123;&#123;fio_parent&#125;&#125;</code>.
            После сохранения проверьте подписи полей и текст email-уведомления.
        </p>

        <form method="post" action="{{ route('contract-templates.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Название</label>
                    <input type="text" name="title" class="form-control" value="{{ old('title') }}" required maxlength="255">
                    @error('title')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">Файл DOCX</label>
                    <input type="file" name="docx" class="form-control" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required>
                    @error('docx')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label class="form-label">Тема письма клиенту (необязательно)</label>
                    <input type="text" name="email_subject" class="form-control" value="{{ old('email_subject') }}" maxlength="255"
                           placeholder="Договор: требуется заполнение и подписание">
                    @error('email_subject')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label class="form-label">Текст письма (HTML, необязательно)</label>
                    <textarea name="email_body_html" class="form-control" rows="6"
                              placeholder="&lt;p&gt;Здравствуйте! Перейдите в раздел «Мои документы»: &#123;&#123;documents_url&#125;&#125;&lt;/p&gt;">{{ old('email_body_html') }}</textarea>
                    <div class="form-text">
                        Для <strong>письма</strong> (не для DOCX): подстановки
                        <code>&#123;&#123;documents_url&#125;&#125;</code>,
                        <code>&#123;&#123;student_name&#125;&#125;</code>,
                        <code>&#123;&#123;contract_id&#125;&#125;</code>.
                        В файле DOCX используйте свои поля вида <code>&#123;&#123;fio_parent&#125;&#125;</code> — они появятся в форме клиента.
                    </div>
                    @error('email_body_html')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="mt-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Сохранить шаблон</button>
                <a href="{{ route('contract-templates.index') }}" class="btn btn-outline-secondary">Отмена</a>
            </div>
        </form>
    </div>
@endsection
