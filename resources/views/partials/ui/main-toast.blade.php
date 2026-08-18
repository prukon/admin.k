{{-- Общая Bootstrap-всплывайка админки (как в заявках / ценах). --}}
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1090;">
    <div id="kidsMainToast" class="toast align-items-center text-white bg-success border-0" role="alert"
         aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body" id="kidsMainToastBody"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                    aria-label="Закрыть"></button>
        </div>
    </div>
</div>
<script>
    window.showToast = function (message, type) {
        var toastEl = document.getElementById('kidsMainToast');
        var toastBodyEl = document.getElementById('kidsMainToastBody');
        if (!toastEl || !toastBodyEl) {
            return;
        }

        toastEl.classList.remove('bg-success', 'bg-danger', 'bg-info', 'bg-warning', 'text-dark');
        var closeBtn = toastEl.querySelector('.btn-close');
        if (type === 'error') {
            toastEl.classList.add('bg-danger');
            if (closeBtn) closeBtn.classList.add('btn-close-white');
        } else if (type === 'warning') {
            toastEl.classList.add('bg-warning', 'text-dark');
            if (closeBtn) closeBtn.classList.remove('btn-close-white');
        } else if (type === 'info') {
            toastEl.classList.add('bg-info');
            if (closeBtn) closeBtn.classList.add('btn-close-white');
        } else {
            toastEl.classList.add('bg-success');
            if (closeBtn) closeBtn.classList.add('btn-close-white');
        }

        toastBodyEl.textContent = message || '';

        if (!window.bootstrap || !bootstrap.Toast) {
            return;
        }

        bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 2500 }).show();
    };
</script>
