@extends('layouts.admin2')

@section('title', 'Шаблоны договоров')

@php
    $shouldOpenEditModal = !empty($editTemplate);
    $shouldOpenCreateModal = !$shouldOpenEditModal && (request()->boolean('create') || ($errors->any() && !request()->filled('edit')));
@endphp

@section('content')
    <div class="main-content text-start">
        <h4 class="pt-3 pb-3 text-start">Документы</h4>

        <div class="">
            @include('contracts._contracts_section_tabs', ['activeTab' => $activeTab ?? 'templates'])

            <div class="tab-content mt-2">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button type="button"
                            class="btn btn-primary"
                            data-bs-toggle="modal"
                            data-bs-target="#createContractTemplateModal">
                        Добавить шаблон
                    </button>
                </div>

                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Название</th>
                            <th>Версия</th>
                            <th>Полей</th>
                            <th>Статус</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($templates as $t)
                            <tr>
                                <td>{{ $t->id }}</td>
                                <td>{{ $t->title }}</td>
                                <td>{{ $t->currentVersion?->version ?? '—' }}</td>
                                <td>{{ is_array($t->currentVersion?->fields_schema) ? count($t->currentVersion->fields_schema) : 0 }}</td>
                                <td>
                                    @if($t->is_archived)
                                        <span class="badge bg-secondary">В архиве</span>
                                    @elseif($t->isUsable())
                                        <span class="badge bg-success">Активен</span>
                                    @else
                                        <span class="badge bg-warning text-dark">Нет версии</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('contract-templates.index', ['edit' => $t->id]) }}"
                                       class="btn btn-sm btn-outline-primary">Изменить</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-muted text-center py-4">Шаблонов пока нет</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $templates->links() }}
            </div>
        </div>
    </div>

    @include('contract-templates.partials.create-modal')
    @include('contract-templates.partials.edit-modal')
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const shouldOpenCreateModal = @json($shouldOpenCreateModal);
            const shouldOpenEditModal = @json($shouldOpenEditModal);

            const createModalEl = document.getElementById('createContractTemplateModal');
            const editModalEl = document.getElementById('editContractTemplateModal');

            if (shouldOpenEditModal && editModalEl) {
                bootstrap.Modal.getOrCreateInstance(editModalEl).show();
            } else if (shouldOpenCreateModal && createModalEl) {
                bootstrap.Modal.getOrCreateInstance(createModalEl).show();
            }

            createModalEl?.addEventListener('hidden.bs.modal', function () {
                if (@json($errors->any() && !request()->filled('edit'))) {
                    return;
                }

                const form = document.getElementById('contractTemplateCreateForm');
                if (!form) {
                    return;
                }

                form.reset();
                form.querySelectorAll('.is-invalid').forEach(function (el) {
                    el.classList.remove('is-invalid');
                });
            });

            editModalEl?.addEventListener('hidden.bs.modal', function () {
                if (window.location.search.includes('edit=')) {
                    const url = new URL(window.location.href);
                    url.searchParams.delete('edit');
                    window.history.replaceState({}, '', url.pathname + (url.search ? url.search : ''));
                }
            });
        });
    </script>
@endpush
