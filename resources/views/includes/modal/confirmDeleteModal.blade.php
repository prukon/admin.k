

<script>
// showConfirmDeleteModal(
// "Удаление пользователя",
// "Вы уверены, что хотите удалить пользователя?",
// function() {
//
// });
</script>



<!-- confirmDeleteModal.blade -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmDeleteModalLabel">Заголовок</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                Вы уревены?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Да</button>
            </div>
        </div>
    </div>
</div>

<script>



    function showConfirmDeleteModal(headerText, messageText, confirmCallback) {
        const modalEl   = document.getElementById('confirmDeleteModal');
        const modalInst = bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: 'static', keyboard: false });

        // всегда держим модалку в <body>
        document.body.appendChild(modalEl);

        // текст
        const titleEl = document.getElementById('confirmDeleteModalLabel');
        const bodyEl  = modalEl.querySelector('.modal-body');
        if (titleEl) titleEl.textContent = headerText;
        if (bodyEl)  bodyEl.textContent  = messageText;

        // снять старые обработчики клика — клон или off+one
        const oldBtn = document.getElementById('confirmDeleteBtn');
        const newBtn = oldBtn.cloneNode(true);
        oldBtn.parentNode.replaceChild(newBtn, oldBtn);

        newBtn.addEventListener('click', function () {
            try { if (typeof confirmCallback === 'function') confirmCallback(); }
            finally {
                // закрываем ЭТУ модалку — очередь вернёт предыдущую
                const inst = bootstrap.Modal.getInstance(modalEl);
                if (inst) inst.hide();
            }
        });

        // показываем ТОЛЬКО через очередь (спрячет нижнюю модалку и вернёт её потом)
        showModalQueued('confirmDeleteModal', { backdrop: 'static', keyboard: false });
    }



    function showConfirmDeleteModal3(headerText, messageText, confirmCallback) {
        const modalEl   = document.getElementById('confirmDeleteModal');
        const titleEl   = document.getElementById('confirmDeleteModalLabel');
        const bodyEl    = modalEl.querySelector('.modal-body');
        const oldBtn    = document.getElementById('confirmDeleteBtn');

        // текст
        if (titleEl) titleEl.textContent = headerText;
        if (bodyEl)  bodyEl.textContent  = messageText;

        // сброс обработчика кнопки
        const btn = oldBtn.cloneNode(true);
        oldBtn.parentNode.replaceChild(btn, oldBtn);

        // Показ — через очередь, чтобы нижняя модалка скрывалась корректно
        document.body.appendChild(modalEl);
        showModalQueued('confirmDeleteModal', { backdrop: 'static', keyboard: false });

        // На "Да": СНАЧАЛА прячем confirm, и только ПОСЛЕ hidden вызываем callback
        btn.addEventListener('click', function () {
            const inst = bootstrap.Modal.getOrCreateInstance(modalEl);
            modalEl.addEventListener('hidden.bs.modal', function handler() {
                modalEl.removeEventListener('hidden.bs.modal', handler);
                if (typeof confirmCallback === 'function') confirmCallback();
            }, { once: true });
            inst.hide();
        });
    }
</script>
