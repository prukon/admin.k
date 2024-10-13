<!-- Подключение CSS Bootstrap -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Подключение JavaScript Bootstrap и Popper -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>

<!-- Подключение Font Awesome для иконок -->
{{--<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"--}}
{{--      integrity="sha384-k6RqeWeci5ZR/Lv4MR0sA0FfDOMTn36ApK4H6tEGOgoiR8VtCgOoz6RDV+Z0zMOn" crossorigin="anonymous">--}}

<!-- Модальное окно настройки социальных сетей -->


<div class="modal fade mt-3 socialMenuModal" id="socialMenuModal" tabindex="-1" aria-labelledby="socialMenuModalLabel" aria-hidden="true">
    <div class="modal-dialog d-flex justify-content-center">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="socialMenuModalLabel">Настройка социальных сетей</h5>
                 <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table" id="socialTable">
                        <thead>
                        <tr>
                            <th>Иконка</th>
                            <th>Название</th>
                            <th>Ссылка</th> 
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($socialItems as $item)
                            <tr data-id="{{ $item->id }}">
                                <td>
                                    @switch($item->name)
                                        @case('vk.com')
                                            <i class="fa-brands fa-vk"></i>
                                            @break
                                        @case('YouTube.com')
                                            <i class="fa-brands fa-youtube"></i>
                                            @break
                                        @case('RuTube.ru')
                                            <i class="fa-brands fa-rutube"></i>
                                            @break
                                        @case('facebook.com')
                                            <i class="fa-brands fa-facebook"></i>
                                            @break
                                        @case('Instagram.com')
                                            <i class="fa-brands fa-instagram"></i>
                                            @break
                                        @case('Twitter.com')
                                            <i class="fa-brands fa-twitter"></i>
                                            @break
                                        @case('LinkedIn.com')
                                            <i class="fa-brands fa-linkedin"></i>
                                            @break
                                        @case('Telegram.org')
                                            <i class="fa-brands fa-telegram"></i>
                                            @break
                                        @case('Pinterest.com')
                                            <i class="fa-brands fa-pinterest"></i>
                                            @break
                                        @case('TikTok.com')
                                            <i class="fa-brands fa-tiktok"></i>
                                            @break
                                        @case('Reddit.com')
                                            <i class="fa-brands fa-reddit"></i>
                                            @break
                                        @case('Snapchat.com')
                                            <i class="fa-brands fa-snapchat"></i>
                                            @break
                                        @case('WhatsApp.com')
                                            <i class="fa-brands fa-whatsapp"></i>
                                            @break
                                        @case('Discord.com')
                                            <i class="fa-brands fa-discord"></i>
                                            @break
                                        @case('Tumblr.com')
                                            <i class="fa-brands fa-tumblr"></i>
                                            @break
                                        @case('Dribbble.com')
                                            <i class="fa-brands fa-dribbble"></i>
                                            @break
                                        @case('GitHub.com')
                                            <i class="fa-brands fa-github"></i>
                                            @break
                                        @case('Vimeo.com')
                                            <i class="fa-brands fa-vimeo"></i>
                                            @break
                                        @case('Slack.com')
                                            <i class="fa-brands fa-slack"></i>
                                            @break
                                        @case('Dropbox.com')
                                            <i class="fa-brands fa-dropbox"></i>
                                            @break
                                        @default
                                            <i class="fa fa-globe"></i>
                                    @endswitch
                                </td>
                                <td>
                                    <input type="text" name="social_items[{{ $item->id }}][name]" class="form-control"
                                           value="{{ $item->name }}" readonly>
                                    <div class="text-danger error-message"></div>
                                </td>
                                <td>
                                    <input type="text" name="social_items[{{ $item->id }}][link]" class="form-control"
                                           value="{{ $item->link }}">
                                    <div class="text-danger error-message"></div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" id="saveSocialMenu">Сохранить</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var token = '{{ csrf_token() }}';

        document.getElementById('saveSocialMenu').addEventListener('click', function () {
            const formData = new FormData();

            document.querySelectorAll('.error-message').forEach((el) => el.textContent = '');

            document.querySelectorAll('#socialTable tbody tr').forEach((row) => {
                const id = row.getAttribute('data-id');
                const nameInput = row.querySelector(`input[name*="[name]"]`);
                const linkInput = row.querySelector(`input[name*="[link]"]`);

                if (nameInput && linkInput) {
                    const baseKey = `social_items[${id}]`;
                    formData.append(`${baseKey}[name]`, nameInput.value);
                    formData.append(`${baseKey}[link]`, linkInput.value);
                }
            });

            fetch('{{ route('settings.saveSocialItems') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': token,
                },
                body: formData,
            })
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(data => {
                            throw new Error(JSON.stringify(data.errors));
                        });
                    }
                    return response.json();
                })
                .then(response => {
                    if (response.success) {
                        alert('Социальные сети успешно сохранены.');
                        location.reload();
                    }
                })
                .catch(error => {
                    const errors = JSON.parse(error.message);
                    Object.keys(errors).forEach((key) => {
                        const inputWithError = document.querySelector(`input[name="${key}"]`);
                        if (inputWithError) {
                            const errorContainer = inputWithError.nextElementSibling;
                            errorContainer.textContent = errors[key][0];
                        }
                    });
                });
        });
    });
</script>



<style>
{{--    /* Применение автоматической ширины для модального окна */--}}
    #socialMenuModal .modal-dialog {
        /*display: inline-block; !* Позволяет модалке занимать только необходимую ширину *!*/
        /*width: auto;*/
    }

    /* Задание ширины для столбцов таблицы */
    #socialTable th:nth-child(1),
    #socialTable td:nth-child(1) {
        /*width: 70px;*/
        /*text-align: center; !* Горизонтальное центрирование *!*/
        /*vertical-align: middle; !* Вертикальное центрирование *!*/
    }

    #socialTable th:nth-child(2),
    #socialTable td:nth-child(2) {
        /*width: 150px;*/
    }

    #socialTable th:nth-child(3),
    #socialTable td:nth-child(3) {
        /*width: 300px;*/
    }
</style>


