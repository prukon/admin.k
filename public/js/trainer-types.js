(function () {
    const cfg = window.__trainerTypesConfig;
    if (!cfg || !cfg.listUrl) {
        return;
    }

    const modalEl = document.getElementById('trainerTypesModal');
    if (!modalEl) {
        return;
    }

    const listWrap = document.getElementById('trainer-types-list-wrap');
    const listBody = document.getElementById('trainer-types-list-body');
    const form = document.getElementById('trainer-type-form');
    const formError = modalEl.querySelector('.js-form-error');
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const canManage = !!cfg.canManage;

    function showAlert(message) {
        if (!formError) return;
        if (!message) {
            formError.classList.add('d-none');
            formError.textContent = '';
            return;
        }
        formError.textContent = message;
        formError.classList.remove('d-none');
    }

    function clearFieldErrors() {
        form.querySelectorAll('.invalid-feedback[data-error-for]').forEach((el) => {
            el.textContent = '';
        });
        form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
    }

    function showFieldErrors(errors) {
        if (!errors || typeof errors !== 'object') return;
        Object.keys(errors).forEach((key) => {
            const messages = errors[key];
            const text = Array.isArray(messages) ? messages[0] : String(messages);
            const box = form.querySelector('[data-error-for="' + key + '"]');
            if (box) {
                box.textContent = text;
            }
            const input = form.querySelector('[name="' + key + '"]');
            if (input) {
                input.classList.add('is-invalid');
            }
        });
    }

    function formatMoney(value) {
        const n = Number(value);
        if (!Number.isFinite(n)) {
            return String(value ?? '0.00');
        }
        return n.toFixed(2).replace('.', ',');
    }

    function showList() {
        form.classList.add('d-none');
        listWrap.classList.remove('d-none');
        showAlert('');
        clearFieldErrors();
    }

    function showForm() {
        listWrap.classList.add('d-none');
        form.classList.remove('d-none');
        showAlert('');
        clearFieldErrors();
    }

    function fillForm(type) {
        form.querySelector('[name="id"]').value = type?.id || '';
        form.querySelector('[name="name"]').value = type?.name || '';
        form.querySelector('[name="rate_per_training"]').value = type?.rate_per_training ?? '0.00';
        form.querySelector('[name="base_premium"]').value = type?.base_premium ?? '0.00';
        form.querySelector('[name="sort_order"]').value = String(type?.sort_order ?? 10);
        const enabled = document.getElementById('trainer-type-enabled');
        if (enabled) {
            enabled.value = String(type?.is_enabled ?? 1);
            enabled.disabled = !!type?.is_system;
        }
        const nameInput = document.getElementById('trainer-type-name');
        if (nameInput) {
            nameInput.readOnly = false;
        }
        const delBtn = document.getElementById('trainer-type-delete-btn');
        if (delBtn) {
            const canDelete = canManage && type?.id && type.can_delete;
            delBtn.classList.toggle('d-none', !canDelete);
        }
        const saveBtn = document.getElementById('trainer-type-save-btn');
        if (saveBtn) {
            saveBtn.classList.toggle('d-none', !canManage);
            saveBtn.disabled = !canManage;
        }
        ['name', 'rate_per_training', 'base_premium', 'sort_order', 'is_enabled'].forEach((name) => {
            const el = form.querySelector('[name="' + name + '"]');
            if (el && name !== 'is_enabled') {
                el.readOnly = !canManage;
            }
        });
    }

    async function parseJson(res) {
        try {
            return await res.json();
        } catch (e) {
            return {};
        }
    }

    async function loadList(reason) {
        reason = reason || 'open';
        const res = await fetch(cfg.listUrl, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        });
        const data = await parseJson(res);
        if (!res.ok) {
            showAlert(data.message || 'Не удалось загрузить типы тренеров.');
            return [];
        }
        const types = Array.isArray(data.types) ? data.types : [];
        renderList(types);
        if (typeof window.__onTrainerTypesChanged === 'function') {
            window.__onTrainerTypesChanged(types, reason);
        }
        return types;
    }

    function renderList(types) {
        if (!listBody) return;
        listBody.innerHTML = '';
        if (!types.length) {
            const tr = document.createElement('tr');
            tr.innerHTML = '<td colspan="4" class="text-muted small">Типы не найдены</td>';
            listBody.appendChild(tr);
            return;
        }
        types.forEach((type) => {
            const tr = document.createElement('tr');
            const badge = type.is_system ? ' <span class="badge bg-secondary">системный</span>' : '';
            const action = canManage
                ? '<button type="button" class="btn btn-sm btn-outline-primary js-trainer-type-edit">Изменить</button>'
                : '<button type="button" class="btn btn-sm btn-outline-secondary js-trainer-type-edit">Открыть</button>';
            tr.innerHTML =
                '<td>' + escapeHtml(type.name || '') + badge + '</td>' +
                '<td class="text-end">' + escapeHtml(formatMoney(type.rate_per_training)) + '</td>' +
                '<td class="text-end">' + escapeHtml(formatMoney(type.base_premium)) + '</td>' +
                '<td class="text-end">' + action + '</td>';
            tr.querySelector('.js-trainer-type-edit')?.addEventListener('click', () => {
                fillForm(type);
                showForm();
            });
            listBody.appendChild(tr);
        });
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formPayload() {
        return {
            name: form.querySelector('[name="name"]').value,
            rate_per_training: form.querySelector('[name="rate_per_training"]').value,
            base_premium: form.querySelector('[name="base_premium"]').value,
            sort_order: form.querySelector('[name="sort_order"]').value,
            is_enabled: form.querySelector('[name="is_enabled"]')?.value ?? '1',
        };
    }

    async function saveType() {
        if (!canManage) return;
        clearFieldErrors();
        showAlert('');
        const id = form.querySelector('[name="id"]').value;
        const url = id ? cfg.updateUrlTemplate.replace('__ID__', encodeURIComponent(id)) : cfg.storeUrl;
        const method = id ? 'PUT' : 'POST';
        const res = await fetch(url, {
            method,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
            },
            body: JSON.stringify(formPayload()),
        });
        const data = await parseJson(res);
        if (res.status === 422) {
            showAlert(data.message || 'Ошибка сохранения');
            showFieldErrors(data.errors || {});
            return;
        }
        if (!res.ok) {
            showAlert(data.message || 'Ошибка сохранения');
            return;
        }
        await loadList('saved');
        showList();
        if (typeof showSuccessModal === 'function') {
            showSuccessModal('Типы тренеров', data.message || 'Сохранено', 0);
        }
    }

    async function deleteType() {
        if (!canManage) return;
        const id = form.querySelector('[name="id"]').value;
        if (!id) return;
        if (!window.confirm('Удалить этот тип тренера?')) {
            return;
        }
        clearFieldErrors();
        showAlert('');
        const res = await fetch(cfg.destroyUrlTemplate.replace('__ID__', encodeURIComponent(id)), {
            method: 'DELETE',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token,
            },
        });
        const data = await parseJson(res);
        if (res.status === 422) {
            showAlert(data.message || 'Нельзя удалить тип');
            showFieldErrors(data.errors || {});
            return;
        }
        if (!res.ok) {
            showAlert(data.message || 'Ошибка удаления');
            return;
        }
        await loadList('saved');
        showList();
        if (typeof showSuccessModal === 'function') {
            showSuccessModal('Типы тренеров', data.message || 'Тип удалён', 0);
        }
    }

    document.getElementById('trainer-types-add-btn')?.addEventListener('click', () => {
        fillForm({
            id: '',
            name: '',
            rate_per_training: '0.00',
            base_premium: '0.00',
            sort_order: 10,
            is_enabled: 1,
            is_system: 0,
            can_delete: false,
        });
        showForm();
    });
    document.getElementById('trainer-type-form-back')?.addEventListener('click', showList);
    document.getElementById('trainer-type-save-btn')?.addEventListener('click', () => {
        saveType().catch(() => showAlert('Ошибка сохранения'));
    });
    document.getElementById('trainer-type-delete-btn')?.addEventListener('click', () => {
        deleteType().catch(() => showAlert('Ошибка удаления'));
    });

    modalEl.addEventListener('show.bs.modal', () => {
        showList();
        loadList('open').catch(() => showAlert('Не удалось загрузить типы тренеров.'));
    });
})();
