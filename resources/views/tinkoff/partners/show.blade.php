@extends('layouts.admin2')

@section('content')
    <div class="container py-3">

        <div class="main-content text-start">
            <h4 class="pt-3"><Партнёр></Партнёр>: {{ $partner->title }}</h4>

            @if ($errors->any())
                <div class="alert alert-danger">
                    @foreach ($errors->all() as $e) <div>{{ $e }}</div> @endforeach
                </div>
            @endif
            @if (session('ok'))
                <div class="alert alert-success">{{ session('ok') }}</div>
            @endif

            <div class="card mb-3">
                <div class="card-body">
                    <div><b>PartnerId (shopCode):</b> {{ $partner->tinkoff_partner_id ?? '—' }}</div>
                    <div><b>Статус sm-register:</b> {{ $partner->sm_register_status ?? 'Не зарегистрирован' }}</div>
                    <div><b>Версия реквизитов:</b> {{ $partner->bank_details_version ?? 0 }}</div>
                    <div><b>Обновлены:</b> {{ $partner->bank_details_last_updated_at ?? '—' }}</div>
                </div>
            </div>

            @if (!$partner->tinkoff_partner_id)
                {{-- ФОРМА РЕГИСТРАЦИИ --}}
                <div class="card">
                    <div class="card-header">Регистрация в sm-register</div>
                    <div class="card-body">
                        <form action="{{ route('tinkoff.partners.smRegister', $partner->id) }}" method="POST" id="sm-register-form">
                            @csrf

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Юр. форма</label>
                                    <select name="business_type" class="form-select" id="business_type" required>
                                        @php $bt = $partner->business_type; @endphp
                                        <option value="individual_entrepreneur" @selected($bt==='individual_entrepreneur')>ИП</option>
                                        <option value="company" @selected($bt==='company')>ЮЛ</option>
                                        <option value="physical_person" @selected($bt==='physical_person')>ФЛ</option>
                                        <option value="non_commercial_organization" @selected($bt==='non_commercial_organization')>НКО</option>
                                    </select>
                                    <div class="form-text">API: служебно (влияет на обязательность <code>kpp*</code>).</div>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Наименование</label>
                                    <input name="title" id="title" class="form-control" required value="{{ $partner->title }}">
                                    <div class="form-text">API: <code>fullName*</code> (а также <code>name*</code>).</div>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">E-mail</label>
                                    <input name="email" class="form-control" type="email" required value="{{ $partner->email }}">
                                    <div class="form-text">API: <code>email*</code></div>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">ИНН</label>
                                    <input name="tax_id" class="form-control" required value="{{ $partner->tax_id }}">
                                    <div class="form-text">API: <code>inn*</code></div>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">ОГРН/ОГРНИП</label>
                                    <input name="registration_number" class="form-control" required value="{{ $partner->registration_number }}">
                                    <div class="form-text">API: <code>ogrn*</code> (Integer)</div>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">КПП</label>
                                    <input name="kpp" id="kpp" class="form-control" value="{{ $partner->kpp }}">
                                    <div class="form-text">API: <code>kpp*</code>. Для ИП/ФЛ/НКО — <code>000000000</code>.</div>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Телефон контакта</label>
                                    <input name="phone" id="phone" class="form-control" type="tel" value="{{ $partner->phone }}">
                                    <div class="form-text">API: <code>phones[0].phone</code> и <code>ceo.phone*</code>.</div>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Сайт</label>
                                    <input name="website" id="website" class="form-control" type="url"
                                           value="{{ $partner->website ?? '' }}" placeholder="{{ config('app.url') }}">
                                    <div class="form-text">API: <code>siteUrl*</code></div>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Название для SMS/выписок</label>
                                    <input class="form-control" value="{{ $partner->sms_name ?? '' }}" readonly>
                                    <div class="form-text">API: <code>billingDescriptor*</code> — из БД.</div>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">Город</label>
                                    <input name="city" class="form-control" required value="{{ $partner->city }}">
                                    <div class="form-text">API: <code>addresses[0].city*</code></div>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">Индекс</label>
                                    <input name="zip" class="form-control" required pattern="\d{6}" maxlength="6" value="{{ $partner->zip }}">
                                    <div class="form-text">API: <code>addresses[0].zip*</code> (6 цифр)</div>
                                </div>

                                <div class="col-md-8">
                                    <label class="form-label">Улица, дом, офис</label>
                                    <input name="address" class="form-control" required value="{{ $partner->address }}">
                                    <div class="form-text">API: <code>addresses[0].street*</code></div>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Страна (адрес)</label>
                                    <input class="form-control" value="RUS" readonly>
                                    <div class="form-text">API: <code>addresses[0].country*</code></div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Тип адреса</label>
                                    <input class="form-control" value="legal" readonly>
                                    <div class="form-text">API: <code>addresses[0].type*</code></div>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Банк</label>
                                    <input name="bank_name" class="form-control" required value="{{ $partner->bank_name }}">
                                    <div class="form-text">API: <code>bankAccount.bankName*</code></div>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">БИК</label>
                                    <input name="bank_bik" class="form-control" required value="{{ $partner->bank_bik }}">
                                    <div class="form-text">API: <code>bankAccount.bik*</code></div>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Р/с</label>
                                    <input name="bank_account" class="form-control" required value="{{ $partner->bank_account }}">
                                    <div class="form-text">API: <code>bankAccount.account*</code></div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Назначение платежа (details)</label>
                                    <textarea name="sm_details_template" class="form-control" rows="2" required>{{ $partner->sm_details_template ?? 'Выплата по договору МР-08.25/УЕА_01 от 21.08.25, НДС не облагается' }}</textarea>
                                    <div class="form-text">API: <code>bankAccount.details*</code></div>
                                </div>

                                <div class="col-12">
                                    <div class="alert alert-secondary mb-0">
                                        <div class="row g-3">
                                            <div class="col-md-3">
                                                <label class="form-label mb-1">CEO: Фамилия (из БД)</label>
                                                <input class="form-control" value="{{ data_get($partner->ceo, 'lastName', '') }}" readonly>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label mb-1">CEO: Имя (из БД)</label>
                                                <input class="form-control" value="{{ data_get($partner->ceo, 'firstName', '') }}" readonly>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label mb-1">CEO: Отчество (из БД)</label>
                                                <input class="form-control" value="{{ data_get($partner->ceo, 'middleName', '') }}" readonly>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label mb-1">CEO: Телефон (из БД)</label>
                                                <input class="form-control" value="{{ data_get($partner->ceo, 'phone', '') }}" readonly>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3">
                                <button class="btn btn-primary">Зарегистрировать</button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Скрипты регистрации (оставил как есть, только аккуратно сгруппированы) --}}
                <script>
                    (function($){
                        $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

                        function toggleKpp(){
                            var bt = $('#business_type').val();
                            var $kpp = $('#kpp');
                            if(bt === 'company'){
                                $kpp.prop('disabled', false);
                            } else {
                                $kpp.val('000000000').prop('disabled', true);
                            }
                        }
                        $(document).on('change', '#business_type', toggleKpp);
                        toggleKpp();

                        $(document).on('submit', 'form[action="/admin/tinkoff/partners/{{ $partner->id }}/sm-register"]', function(e){
                            e.preventDefault();
                            var data = $(this).serialize();
                            $.ajax({
                                url: '/admin/tinkoff/partners/{{ $partner->id }}/sm-register',
                                method: 'POST',
                                data: data,
                                success: function(resp){ location.reload(); },
                                error: function(xhr){
                                    alert('Ошибка регистрации: ' + (xhr.responseJSON?.error ?? 'см. логи'));
                                }
                            });
                        });
                    })(jQuery);
                </script>

                <script>
                    (function($){
                        $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });
                        function makeDescriptor(src){
                            if(!src) return 'KRUZHOK';
                            var map = {"А":"A","Б":"B","В":"V","Г":"G","Д":"D","Е":"E","Ё":"E","Ж":"ZH","З":"Z","И":"I","Й":"Y","К":"K","Л":"L","М":"M","Н":"N","О":"O","П":"P","Р":"R","С":"S","Т":"T","У":"U","Ф":"F","Х":"H","Ц":"C","Ч":"CH","Ш":"SH","Щ":"SCH","Ы":"Y","Э":"E","Ю":"YU","Я":"YA","Ь":"","Ъ":""};
                            var s = src.replace(/./g, function(ch){
                                var up = ch.toUpperCase();
                                var tr = map[up];
                                return tr === undefined ? ch : (ch===up ? tr : tr.toLowerCase());
                            });
                            s = s.toUpperCase().replace(/[^A-Z0-9 ._-]+/g,'').replace(/\s+/g,' ').trim();
                            if(s==='') s='KRUZHOK';
                            if(s.length>14) s = s.slice(0,14);
                            return s;
                        }
                        function toggleKpp(){
                            var bt = $('#business_type').val();
                            var $kpp = $('#kpp');
                            if(bt === 'company'){
                                $kpp.prop('disabled', false);
                            } else {
                                $kpp.val('000000000').prop('disabled', true);
                            }
                        }
                        function initSmsName(){
                            var $sms = $('#sms_name');
                            if(($sms.val()||'').trim() === ''){
                                var title = $('#title').val() || '';
                                $sms.val(makeDescriptor(title));
                            }
                        }
                        $(document).on('change', '#business_type', toggleKpp);
                        toggleKpp();
                        initSmsName();
                        $(document).on('submit', 'form[action="/admin/tinkoff/partners/{{ $partner->id }}/sm-register"]', function(e){
                            e.preventDefault();
                            var data = $(this).serialize();
                            $.ajax({
                                url: '/admin/tinkoff/partners/{{ $partner->id }}/sm-register',
                                method: 'POST',
                                data: data,
                                success: function(resp){ location.reload(); },
                                error: function(xhr){
                                    alert('Ошибка регистрации: ' + (xhr.responseJSON?.error ?? 'см. логи'));
                                }
                            });
                        });
                    })(jQuery);
                </script>

                <script>
                    (function($){
                        $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });
                        function makeDescriptor(src){
                            if(!src) return 'KRUZHOK';
                            var map = {"А":"A","Б":"B","В":"V","Г":"G","Д":"D","Е":"E","Ё":"E","Ж":"ZH","З":"Z","И":"I","Й":"Y","К":"K","Л":"L","М":"M","Н":"N","О":"O","П":"P","Р":"R","С":"S","Т":"T","У":"U","Ф":"F","Х":"H","Ц":"C","Ч":"CH","Ш":"SH","Щ":"SCH","Ы":"Y","Э":"E","Ю":"YU","Я":"YA","Ь":"","Ъ":""};
                            var s = src.replace(/./g, function(ch){
                                var up = ch.toUpperCase();
                                var tr = map[up];
                                return tr === undefined ? ch : (ch===up ? tr : tr.toLowerCase());
                            });
                            s = s.toUpperCase().replace(/[^A-Z0-9 ._-]+/g,'').replace(/\s+/g,' ').trim();
                            if(s==='') s='KRUZHOK';
                            if(s.length>14) s = s.slice(0,14);
                            return s;
                        }
                        function toggleKpp(){
                            var bt = $('#business_type').val();
                            var $kpp = $('#kpp');
                            if(bt === 'company'){
                                $kpp.prop('disabled', false);
                            } else {
                                $kpp.val('000000000').prop('disabled', true);
                            }
                        }
                        function refreshComputed(){
                            var title = $('#title').val() || '';
                            $('#shortNamePreview').val(title);
                            var bd = makeDescriptor(title);
                            $('#billingDescriptor').val(bd);
                            $('#billing_descriptor_hidden').val(bd);
                        }
                        $(document).on('input change', '#title', refreshComputed);
                        $(document).on('change', '#business_type', toggleKpp);
                        toggleKpp();
                        refreshComputed();
                        $(document).on('submit', 'form[action="/admin/tinkoff/partners/{{ $partner->id }}/sm-register"]', function(e){
                            e.preventDefault();
                            var data = $(this).serialize();
                            $.ajax({
                                url: '/admin/tinkoff/partners/{{ $partner->id }}/sm-register',
                                method: 'POST',
                                data: data,
                                success: function(resp){ location.reload(); },
                                error: function(xhr){
                                    alert('Ошибка регистрации: ' + (xhr.responseJSON?.error ?? 'см. логи'));
                                }
                            });
                        });
                    })(jQuery);
                </script>
            @else
                {{-- ФОРМА PATCH РЕКВИЗИТОВ --}}
                {{-- ФОРМА ПОЛНОГО PATCH --}}
                <div class="card">
                    <div class="card-header">Обновить реквизиты в sm-register</div>
                    <div class="card-body">
                        <form action="{{ route('tinkoff.partners.smPatch', $partner->id) }}" method="POST" id="sm-patch-form">
                            @csrf

                            <div class="row g-3">
                                {{-- Юр. форма --}}
                                <div class="col-md-4">
                                    <label class="form-label">Юр. форма</label>
                                    <select name="business_type" class="form-select" id="business_type" required>
                                        @php $bt = $partner->business_type; @endphp
                                        <option value="individual_entrepreneur" @selected($bt==='individual_entrepreneur')>ИП</option>
                                        <option value="company" @selected($bt==='company')>ЮЛ</option>
                                        <option value="physical_person" @selected($bt==='physical_person')>ФЛ</option>
                                        <option value="non_commercial_organization" @selected($bt==='non_commercial_organization')>НКО</option>
                                    </select>
                                </div>

                                {{-- Наименование --}}
                                <div class="col-md-4">
                                    <label class="form-label">Наименование</label>
                                    <input name="title" id="title" class="form-control" required value="{{ $partner->title }}">
                                </div>

                                {{-- E-mail --}}
                                <div class="col-md-4">
                                    <label class="form-label">E-mail</label>
                                    <input name="email" class="form-control" type="email" required value="{{ $partner->email }}">
                                </div>

                                {{-- ИНН/ОГРН/КПП --}}
                                <div class="col-md-4">
                                    <label class="form-label">ИНН</label>
                                    <input name="tax_id" class="form-control" required value="{{ $partner->tax_id }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">ОГРН/ОГРНИП</label>
                                    <input name="registration_number" class="form-control" required value="{{ $partner->registration_number }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">КПП</label>
                                    <input name="kpp" id="kpp" class="form-control" value="{{ $partner->kpp }}">
                                    <div class="form-text">Для ИП/ФЛ/НКО отправится 000000000.</div>
                                </div>

                                {{-- Контакты / сайт --}}
                                <div class="col-md-4">
                                    <label class="form-label">Телефон контакта</label>
                                    <input name="phone" id="phone" class="form-control" type="tel" value="{{ $partner->phone }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Сайт</label>
                                    <input name="website" id="website" class="form-control" type="url"
                                           value="{{ $partner->website ?? '' }}" placeholder="{{ config('app.url') }}">
                                </div>

                                {{-- billingDescriptor (read-only) --}}
                                <div class="col-md-4">
                                    <label class="form-label">Название для SMS/выписок</label>
                                    <input class="form-control" value="{{ $partner->sms_name ?? '' }}" readonly>
                                    <div class="form-text">Меняется из БД (billingDescriptor), не отсюда.</div>
                                </div>

                                {{-- Адрес --}}
                                <div class="col-md-2">
                                    <label class="form-label">Город</label>
                                    <input name="city" class="form-control" required value="{{ $partner->city }}">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Индекс</label>
                                    <input name="zip" class="form-control" required pattern="\d{6}" maxlength="6" value="{{ $partner->zip }}">
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">Улица, дом, офис</label>
                                    <input name="address" class="form-control" required value="{{ $partner->address }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Страна (адрес)</label>
                                    <input class="form-control" value="RUS" readonly>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Тип адреса</label>
                                    <input class="form-control" value="legal" readonly>
                                </div>

                                {{-- Банк --}}
                                <div class="col-md-4">
                                    <label class="form-label">Банк</label>
                                    <input name="bank_name" class="form-control" required value="{{ $partner->bank_name }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">БИК</label>
                                    <input name="bank_bik" class="form-control" required value="{{ $partner->bank_bik }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Р/с</label>
                                    <input name="bank_account" class="form-control" required value="{{ $partner->bank_account }}">
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Назначение платежа (details)</label>
                                    <textarea name="sm_details_template" class="form-control" rows="2" required>{{ $partner->sm_details_template }}</textarea>
                                </div>

                                {{-- CEO (read-only) --}}
                                <div class="col-12">
                                    <div class="alert alert-secondary mb-0">
                                        <div class="row g-3">
                                            <div class="col-md-3">
                                                <label class="form-label mb-1">CEO: Фамилия</label>
                                                <input class="form-control" value="{{ data_get($partner->ceo, 'lastName', '') }}" readonly>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label mb-1">CEO: Имя</label>
                                                <input class="form-control" value="{{ data_get($partner->ceo, 'firstName', '') }}" readonly>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label mb-1">CEO: Отчество</label>
                                                <input class="form-control" value="{{ data_get($partner->ceo, 'middleName', '') }}" readonly>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label mb-1">CEO: Телефон</label>
                                                <input class="form-control" value="{{ data_get($partner->ceo, 'phone', '') }}" readonly>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3 d-flex gap-2">
                                <button class="btn btn-primary">Сохранить в sm-register</button>
                                <form action="{{ route('tinkoff.partners.smRefresh', $partner->id) }}" method="POST" class="d-inline">@csrf
                                    <button class="btn btn-outline-secondary" formaction="{{ route('tinkoff.partners.smRefresh', $partner->id) }}">Обновить статус</button>
                                </form>
                            </div>
                        </form>
                    </div>
                </div>

                @push('scripts')
                    <script>
                        (function($){
                            function toggleKpp(){
                                var bt = $('#business_type').val();
                                var $kpp = $('#kpp');
                                if(bt === 'company'){
                                    $kpp.prop('disabled', false);
                                } else {
                                    $kpp.val('000000000').prop('disabled', true);
                                }
                            }
                            $(document).on('change', '#business_type', toggleKpp);
                            toggleKpp();
                        })(jQuery);
                    </script>
                @endpush
            @endif
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h5 mb-0">Партнёр: {{ $partner->title }} (ID {{ $partner->id }})</h1>
            @if($partner->tinkoff_partner_id)
                <span class="badge text-bg-dark">shopCode: {{ $partner->tinkoff_partner_id }}</span>
            @else
                <span class="badge text-bg-warning">Не зарегистрирован в sm-register</span>
            @endif
        </div>

        <div class="row g-3">
            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="mb-2">Ожидают выплат: <strong>{{ $waiting }}</strong></div>
                        <div class="small text-muted">Статус sm-register: {{ $partner->sm_register_status ?? '—' }}</div>
                        <div class="small text-muted">Банк обновлён: {{ $partner->bank_details_last_updated_at?->format('d.m.Y H:i') ?? '—' }}</div>
                        <div class="small text-muted">Версия реквизитов: {{ $partner->bank_details_version ?? '—' }}</div>
                    </div>
                </div>

                <div class="card shadow-sm mt-3">
                    <div class="card-body">
                        <h6 class="mb-2">Назначение платежа (шаблон)</h6>
                        <pre class="small mb-0" style="max-height: 240px; overflow:auto;">{{ $partner->sm_details_template ?? 'Не задано' }}</pre>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h6 class="mb-2">Недавние платежи</h6>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead class="table-light">
                                <tr><th>ID</th><th>Order</th><th>Сумма</th><th>Статус</th><th>Deal</th><th></th></tr>
                                </thead>
                                <tbody>
                                @foreach($latestPayments as $p)
                                    @php
                                        $badge = ['NEW'=>'secondary','FORM'=>'info','CONFIRMED'=>'success','REJECTED'=>'danger','CANCELED'=>'warning'][$p->status] ?? 'secondary';
                                    @endphp
                                    <tr>
                                        <td>{{ $p->id }}</td>
                                        <td>{{ $p->order_id }}</td>
                                        <td>{{ number_format($p->amount/100,2,',',' ') }} ₽</td>
                                        <td><span class="badge text-bg-{{ $badge }}">{{ $p->status }}</span></td>
                                        <td>{{ $p->deal_id ?? '—' }}</td>
                                        <td class="text-end"><a class="btn btn-outline-primary btn-sm" href="/admin/tinkoff/payments/{{ $p->id }}">Открыть</a></td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection

<script>
    (function($){
        $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

        // перехватываем submit формы регистрации и шлём AJAX
        $(document).on('submit', 'form[action="/admin/tinkoff/partners/{{ $partner->id }}/sm-register"]', function(e){
            e.preventDefault();
            var $form = $(this);
            var url   = '/admin/tinkoff/partners/{{ $partner->id }}/sm-register';
            var data  = $form.serialize();

            $.ajax({
                url: url,
                method: 'POST',
                data: data,
                success: function(resp){ location.reload(); },
                error: function(xhr){
                    alert('Ошибка регистрации: ' + (xhr.responseJSON?.error ?? 'см. логи'));
                }
            });
        });
    })(jQuery);
</script>

<script>
    (function($){
        $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

        function normalizePhone(raw){
            if(!raw) return '';
            var d = (raw+'').replace(/\D+/g,'');
            if(!d) return '';
            if(d.length===11 && (d[0]==='7' || d[0]==='8')) d = '7'+d.slice(1);
            else if(d.length===10) d = '7'+d;
            return '+'+d;
        }

        function makeDescriptor(src){
            if(!src) return 'KRUZHOK';
            var map = {"А":"A","Б":"B","В":"V","Г":"G","Д":"D","Е":"E","Ё":"E","Ж":"ZH","З":"Z","И":"I","Й":"Y","К":"K","Л":"L","М":"M","Н":"N","О":"O","П":"P","Р":"R","С":"S","Т":"T","У":"U","Ф":"F","Х":"H","Ц":"C","Ч":"CH","Ш":"SH","Щ":"SCH","Ы":"Y","Э":"E","Ю":"YU","Я":"YA","Ь":"","Ъ":""};
            var s = src.replace(/./g, function(ch){
                var up = ch.toUpperCase();
                var tr = map[up];
                return tr === undefined ? ch : (ch===up ? tr : tr.toLowerCase());
            });
            s = s.toUpperCase().replace(/[^A-Z0-9 ._-]+/g,'').replace(/\s+/g,' ').trim();
            if(s==='') s='KRUZHOK';
            if(s.length>14) s = s.slice(0,14);
            return s;
        }

        function splitFIOFromTitle(title){
            var t = (title||'').replace(/^ИП\s+/i,'').trim();
            var parts = t.split(/\s+/);
            var last  = parts[0] || 'Иванов';
            var first = parts[1] || 'Иван';
            var middle= parts[2] || '';
            return {first:first, last:last, middle:middle};
        }

        function refreshComputed(){
            var title = $('#title').val() || '';
            $('#shortNamePreview').val(title);

            var bd = makeDescriptor(title);
            $('#billingDescriptor').val(bd);
            $('#billing_descriptor_hidden').val(bd);

            var fio = splitFIOFromTitle(title);
            var phone = normalizePhone($('#phone').val());
            var ceoStr = fio.last + ' ' + fio.first + (fio.middle?(' '+fio.middle):'') + ' | ' + (phone || '+70000000000') + ' | RUS';
            console.debug('CEO preview:', ceoStr);
        }

        function toggleKpp(){
            var bt = $('#business_type').val();
            var $kpp = $('#kpp');
            if(bt === 'company'){
                $kpp.prop('disabled', false);
            } else {
                $kpp.val('000000000').prop('disabled', true);
            }
        }

        $(document).on('input change', '#title,#phone', refreshComputed);
        $(document).on('change', '#business_type', toggleKpp);
        toggleKpp();
        refreshComputed();

        $(document).on('submit', 'form[action="/admin/tinkoff/partners/{{ $partner->id }}/sm-register"]', function(e){
            e.preventDefault();
            var $form = $(this);
            var url   = '/admin/tinkoff/partners/{{ $partner->id }}/sm-register';
            var data  = $form.serialize();

            $.ajax({
                url: url,
                method: 'POST',
                data: data,
                success: function(resp){ location.reload(); },
                error: function(xhr){
                    alert('Ошибка регистрации: ' + (xhr.responseJSON?.error ?? 'см. логи'));
                }
            });
        });

    })(jQuery);
</script>
