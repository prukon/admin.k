/**
 * Общая модалка комментария для ручной отметки оплаты (вкладки «Установка цен»).
 * Зависимости: Bootstrap 5, #manualUserPricePaidModal в DOM, опционально showModalQueued (admin2).
 */
(function (window) {
    'use strict';

    function showManualPaidCommentModal(title, hint, onConfirm) {
        const modalEl = document.getElementById('manualUserPricePaidModal');
        if (!modalEl) {
            console.error('manualUserPricePaidModal not found');
            return;
        }

        const titleEl = document.getElementById('manualUserPricePaidModalLabel');
        const hintEl = document.getElementById('manualUserPricePaidModalHint');
        const ta = document.getElementById('manualUserPricePaidComment');
        const errEl = document.getElementById('manualUserPricePaidCommentError');
        const confirmBtn = document.getElementById('manualUserPricePaidConfirmBtn');

        if (titleEl) titleEl.textContent = title || 'Комментарий';
        if (hintEl) hintEl.textContent = hint || '';
        if (ta) {
            ta.value = '';
            ta.classList.remove('is-invalid');
        }
        if (errEl) {
            errEl.style.display = 'none';
            errEl.textContent = '';
        }

        const newBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newBtn, confirmBtn);

        newBtn.addEventListener('click', function () {
            const comment = (ta && ta.value) ? ta.value.trim() : '';
            if (comment.length < 3) {
                if (ta) ta.classList.add('is-invalid');
                if (errEl) {
                    errEl.style.display = 'block';
                    errEl.textContent = 'Введите комментарий не короче 3 символов.';
                }
                return;
            }
            if (ta) ta.classList.remove('is-invalid');
            if (errEl) errEl.style.display = 'none';

            try {
                if (typeof onConfirm === 'function') {
                    onConfirm(comment);
                }
            } finally {
                const inst = bootstrap.Modal.getInstance(modalEl);
                if (inst) inst.hide();
            }
        });

        if (typeof window.showModalQueued === 'function') {
            window.showModalQueued('manualUserPricePaidModal', {backdrop: 'static', keyboard: false});
        } else {
            bootstrap.Modal.getOrCreateInstance(modalEl, {backdrop: 'static', keyboard: false}).show();
        }
    }

    window.showManualPaidCommentModal = showManualPaidCommentModal;
})(window);
