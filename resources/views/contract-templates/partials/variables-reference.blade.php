@php
    use App\Services\Contracts\ContractTemplateVariablePresets;

    $compact = $compact ?? false;
    $collapseAll = $collapseAll ?? false;
    $accordionId = $accordionId ?? 'contractTemplateVariablesAccordion';
    $groups = ContractTemplateVariablePresets::groupLabels();
@endphp

<div class="contract-template-variables-reference {{ $compact ? 'contract-template-variables-reference--compact' : '' }}">
    @unless($compact)
        <p class="text-muted">
            Вставьте переменные в Word в формате <code>&#123;&#123;имя_переменной&#125;&#125;</code>.
            Рекомендуемые ключи ниже — с русскими подписями для формы родителя и привязкой к CRM, где указано.
        </p>
    @endunless

    <div class="accordion" id="{{ $accordionId }}">
        @foreach($groups as $groupKey => $groupLabel)
            @php
                $items = ContractTemplateVariablePresets::recommendedForGroup($groupKey);
                $panelId = $accordionId . '-' . $groupKey;
            @endphp
            @if($items !== [])
                @php
                    $isExpanded = !$collapseAll && $loop->first;
                @endphp
                <div class="accordion-item">
                    <h2 class="accordion-header" id="{{ $panelId }}-heading">
                        <button class="accordion-button {{ $isExpanded ? '' : 'collapsed' }}"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#{{ $panelId }}"
                                aria-expanded="{{ $isExpanded ? 'true' : 'false' }}"
                                aria-controls="{{ $panelId }}">
                            {{ $groupLabel }}
                            <span class="badge bg-secondary ms-2">{{ count($items) }}</span>
                        </button>
                    </h2>
                    <div id="{{ $panelId }}"
                         class="accordion-collapse collapse {{ $isExpanded ? 'show' : '' }}"
                         aria-labelledby="{{ $panelId }}-heading"
                         data-bs-parent="#{{ $accordionId }}">
                        <div class="accordion-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0 align-middle">
                                    <thead>
                                    <tr>
                                        <th>Переменная для Word</th>
                                        <th>Подпись для родителя</th>
                                        @unless($compact)
                                            <th>Описание</th>
                                        @endunless
                                        <th class="text-end" style="width: 6rem;">Копировать</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($items as $item)
                                        <tr>
                                            <td>
                                                <code class="user-select-all">&#123;&#123;{{ $item['key'] }}&#125;&#125;</code>
                                            </td>
                                            <td>{{ $item['label'] }}</td>
                                            @unless($compact)
                                                <td class="text-muted small">{{ $item['description'] }}</td>
                                            @endunless
                                            <td class="text-end">
                                                <button type="button"
                                                        class="btn btn-sm btn-outline-secondary contract-template-copy-variable"
                                                        data-copy="{{ ContractTemplateVariablePresets::placeholderToken($item['key']) }}"
                                                        title="Скопировать переменную">
                                                    <i class="fas fa-copy" aria-hidden="true"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @endforeach

        <div class="accordion-item">
            <h2 class="accordion-header" id="{{ $accordionId }}-custom-heading">
                <button class="accordion-button collapsed"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#{{ $accordionId }}-custom"
                        aria-expanded="false"
                        aria-controls="{{ $accordionId }}-custom">
                    Свои переменные
                </button>
            </h2>
            <div id="{{ $accordionId }}-custom"
                 class="accordion-collapse collapse"
                 aria-labelledby="{{ $accordionId }}-custom-heading"
                 data-bs-parent="#{{ $accordionId }}">
                <div class="accordion-body">
                    <p class="mb-2">Можно использовать любые ключи, которых нет в списке выше, например <code>&#123;&#123;lessons_per_month&#125;&#125;</code>.</p>
                    <ul class="small text-muted mb-0">
                        <li>Только латиница, цифры и подчёркивание; первый символ — буква.</li>
                        <li>Пишите переменную в Word одним фрагментом: <code>&#123;&#123;my_field&#125;&#125;</code>.</li>
                        <li>После загрузки DOCX настройте подпись для родителя в таблице полей шаблона.</li>
                        <li>Служебные ключи (<code>contract_id</code>, <code>documents_url</code>, <code>contract_date</code> и др.) подставляются автоматически и в форму родителя не выводятся.</li>
                        <li>В тексте письма дополнительно: <code>&#123;&#123;child_full_name&#125;&#125;</code>, <code>&#123;&#123;partner_name&#125;&#125;</code>, <code>&#123;&#123;fill_deadline&#125;&#125;</code> — см. подсказку над полем письма.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('click', function (event) {
                const btn = event.target.closest('.contract-template-copy-variable');
                if (!btn) {
                    return;
                }

                const text = btn.getAttribute('data-copy') || '';
                if (!text) {
                    return;
                }

                const done = function () {
                    const original = btn.innerHTML;
                    btn.innerHTML = '<i class="fas fa-check" aria-hidden="true"></i>';
                    setTimeout(function () {
                        btn.innerHTML = original;
                    }, 1200);
                };

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(done).catch(function () {
                        window.prompt('Скопируйте переменную:', text);
                    });
                } else {
                    window.prompt('Скопируйте переменную:', text);
                }
            });
        </script>
    @endpush
@endonce
