@extends('layouts.admin2')

@section('title', 'Договор #' . $contract->id)

@section('content')
    <div class="main-content text-start">
        <div class="container-fluid px-0 px-md-2 py-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h4 class="mb-0">Договор #{{ $contract->id }}</h4>
                <a href="{{ route('account.documents.index') }}" class="btn btn-outline-secondary btn-sm">К списку</a>
            </div>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($contract->templateVersion?->template)
                <p class="text-muted">
                    Шаблон: <strong>{{ $contract->templateVersion->template->title }}</strong>
                    (версия {{ $contract->templateVersion->version }})
                </p>
            @endif

            @if($contract->canClientFill())
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header">Заполните данные для договора</div>
                    <div class="card-body">
                        <form method="post" action="{{ route('account.documents.generate', $contract) }}">
                            @csrf
                            <div class="row g-3">
                                @foreach($fields as $field)
                                    @php
                                        $key = $field['key'];
                                        $label = $field['label'] ?? $key;
                                        $required = !empty($field['required']);
                                        $value = old('fields.' . $key, $prefill[$key] ?? '');
                                    @endphp
                                    <div class="col-md-6">
                                        <label class="form-label">
                                            {{ $label }}
                                            @if($required)<span class="text-danger">*</span>@endif
                                        </label>
                                        <input type="text"
                                               name="fields[{{ $key }}]"
                                               class="form-control @error('fields.' . $key) is-invalid @enderror"
                                               value="{{ $value }}"
                                               {{ $required ? 'required' : '' }}
                                               maxlength="2000">
                                        @error('fields.' . $key)
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                @endforeach
                            </div>

                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary">Сформировать договор</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            @if($contract->canClientSign())
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header">Договор сформирован</div>
                    <div class="card-body">
                        <p class="mb-3">
                            <a class="btn btn-outline-primary btn-sm"
                               href="{{ route('account.documents.downloadOriginal', $contract) }}"
                               target="_blank" rel="noopener">
                                Скачать PDF
                            </a>
                        </p>

                        <form method="post" action="{{ route('account.documents.sign', $contract) }}">
                            @csrf
                            <h6 class="mb-3">Подписание (SMS через Подпислон)</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Фамилия <span class="text-danger">*</span></label>
                                    <input type="text" name="signer_lastname" class="form-control @error('signer_lastname') is-invalid @enderror"
                                           value="{{ old('signer_lastname', $signerDefaults['lastname']) }}" required maxlength="100">
                                    @error('signer_lastname')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Имя <span class="text-danger">*</span></label>
                                    <input type="text" name="signer_firstname" class="form-control @error('signer_firstname') is-invalid @enderror"
                                           value="{{ old('signer_firstname', $signerDefaults['firstname']) }}" required maxlength="100">
                                    @error('signer_firstname')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Отчество</label>
                                    <input type="text" name="signer_middlename" class="form-control @error('signer_middlename') is-invalid @enderror"
                                           value="{{ old('signer_middlename', $signerDefaults['middlename']) }}" maxlength="100">
                                    @error('signer_middlename')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Телефон для SMS <span class="text-danger">*</span></label>
                                    <input type="text" name="signer_phone" id="signer_phone" class="form-control @error('signer_phone') is-invalid @enderror"
                                           value="{{ old('signer_phone', $signerDefaults['phone']) }}" required>
                                    @error('signer_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="mt-4">
                                <button type="submit" class="btn btn-success">Подписать договор (отправить SMS)</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            @if(in_array($contract->status, [\App\Models\Contract::STATUS_SENT, \App\Models\Contract::STATUS_OPENED], true))
                <div class="alert alert-info">
                    SMS отправлена. Откройте ссылку из сообщения и завершите подписание в сервисе Подпислон.
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.inputmask/5.0.9/jquery.inputmask.min.js" referrerpolicy="no-referrer"></script>
    <script>
        $(function () {
            if ($('#signer_phone').length && $.fn.inputmask) {
                $('#signer_phone').inputmask({
                    mask: '+7 (999) 999-99-99',
                    showMaskOnHover: false,
                    autoUnmask: true,
                    removeMaskOnSubmit: true,
                });
            }
        });
    </script>
@endpush
