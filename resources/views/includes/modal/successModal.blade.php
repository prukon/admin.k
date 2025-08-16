
<div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="dataUpdatedModalLabel">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="dataUpdatedModalLabel">Данные успешно обновлены</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-success " id="success-message">
                    Данные были успешно обновлены.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="btn-success" data-bs-dismiss="modal">ОК</button>
            </div>
        </div>
    </div>
</div>

{{--Пример:--}}
{{--showSuccessModal("Редактирование пользователя", "Пользователь успешно обновлен.", 1);--}}


<script>


    // function showSuccessModal(headerText, messageText, confirmCallback) {
    //     // Подменяем заголовок и текст сообщения
    //     $('#successModal #dataUpdatedModalLabel').text(headerText);
    //     $('#successModal #success-message').text(messageText);
    //
    //     // Сначала убираем все старые обработчики, чтобы при повторных вызовах не накапливались
    //     $('#btn-success').off('click');
    //
    //     // Если confirmCallback == 1, то при нажатии "ОК" делаем перезагрузку страницы
    //     if (confirmCallback === 1) {
    //         $('#btn-success').on('click', function () {
    //             location.reload();
    //         });
    //     }
    //
    //     // Показываем модалку
    //     $('#successModal').modal('show');
    // }


    function showSuccessModal2(headerText, messageText, confirmCallback = 0) {
        const $success = $('#successModal');

        $('#dataUpdatedModalLabel').text(headerText);
        $('#success-message').text(messageText);

        $('#btn-success').off('click').one('click', function () {
            if (confirmCallback === 1) location.reload();
        });

        // гарантируем, что модалка в <body>
        $success.appendTo('body');

        // НЕ вызываем .modal('hide') / .show() сами!
        showModalQueued('successModal', { backdrop: 'static', keyboard: false });
    }




</script>

<script>
    // не показывать successModal повторно, если он уже открыт
    function showSuccessModal(headerText, messageText, confirmCallback = 0) {
        const el = document.getElementById('successModal');
        if (!el) return;

        // если уже открыт — выходим
        if (el.classList.contains('show')) return;

        // тексты
        document.getElementById('dataUpdatedModalLabel').textContent = headerText;
        document.getElementById('success-message').textContent = messageText;

        // кнопка "ОК"
        const btn = document.getElementById('btn-success');
        btn.onclick = null;
        btn.addEventListener('click', () => { if (confirmCallback === 1) location.reload(); }, { once:true });

        // перенос в body
        document.body.appendChild(el);

        // Показать — ТОЛЬКО после того как все прочие модалки спрячутся
        closeAnyOpenModal().then(() => {
            const inst = bootstrap.Modal.getOrCreateInstance(el, { backdrop:'static', keyboard:false, focus:false });
            // отключаем фокус-трап у success (главный «антифриз»)
            el.addEventListener('shown.bs.modal', function handler(e){
                el.removeEventListener('shown.bs.modal', handler);
                const i = bootstrap.Modal.getInstance(el);
                if (i && i._focustrap) i._focustrap.deactivate();
            });
            inst.show();
        });
    }

    // утилита: если что-то открыто — спрятать и дождаться hidden, иначе сразу resolve
    function closeAnyOpenModal() {
        return new Promise(resolve => {
            const opened = document.querySelector('.modal.show');
            if (!opened) return resolve();
            const inst = bootstrap.Modal.getInstance(opened) || bootstrap.Modal.getOrCreateInstance(opened);
            opened.addEventListener('hidden.bs.modal', function handler(){
                opened.removeEventListener('hidden.bs.modal', handler);
                resolve();
            }, { once:true });
            inst.hide();
        });
    }
</script>
