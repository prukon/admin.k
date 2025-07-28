document.addEventListener('DOMContentLoaded', () => {
    const contactForm = document.getElementById('contactForm');
    if (!contactForm) return;

    contactForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        const formData = new FormData(contactForm);
        const action = contactForm.action;
        const token = document.querySelector('input[name="_token"]').value;

        try {
            const response = await fetch(action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': token,
                }
            });

            if (!response.ok) throw new Error('Ошибка при отправке формы');

            const data = await response.json();

            // Закрываем модалку
            const orderModalEl = document.getElementById('createOrder');
            const orderModal = bootstrap.Modal.getInstance(orderModalEl) || new bootstrap.Modal(orderModalEl);
            orderModal.hide();

            // Показываем успех
            const successModalEl = document.getElementById('successModal');
            const successModal = new bootstrap.Modal(successModalEl);
            successModal.show();

            contactForm.reset();
        } catch (error) {
            console.error(error);

            const errorModalEl = document.getElementById('errorModal');
            const errorModal = new bootstrap.Modal(errorModalEl);
            errorModal.show();
        }
    });
});
