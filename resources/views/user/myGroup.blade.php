@extends('layouts.admin2')

@section('title', 'Моя группа')

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <div class="container py-4">
        <h1 class="h4 text-center mb-4">Моя группа</h1>

        <div id="group-visual"
             class="position-relative border rounded-4 p-3 p-md-4"
             style="min-height: 520px; background: #fafafa;">

            {{-- SVG для линий --}}
            <svg id="link-layer" class="position-absolute top-0 start-0 w-100 h-100" style="pointer-events:none;"></svg>

            {{-- Центр: текущий юзер (сверху по центру) --}}
            <div id="current-user"
                 class="position-absolute d-flex flex-column align-items-center"
                 style="top: 12px; left: 50%; transform: translateX(-50%);">
                <div class="avatar avatar-lg mb-2">
                    <img src="" alt="you" class="avatar-img" />
                </div>
                <div class="small fw-semibold text-center name"></div>
            </div>

            {{-- Контейнер для участников снизу --}}
            <div id="peers-grid"
                 class="position-absolute start-0 end-0"
                 style="bottom: 12px;">
                <div class="d-flex flex-wrap justify-content-center gap-3 gap-md-4 px-2" id="peers-wrap">
                    {{-- peers рендерятся через JS --}}
                </div>
            </div>

            {{-- Лоадер --}}
            <div id="loading" class="position-absolute top-50 start-50 translate-middle text-muted">
                Загружаем группу…
            </div>

            {{-- Ошибка --}}
            <div id="error" class="position-absolute top-50 start-50 translate-middle text-danger d-none"></div>
        </div>
    </div>

    {{-- Стили кружков --}}
    <style>
        .avatar {
            width: 80px; height: 80px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid #ffd45c;
            box-shadow: 0 6px 18px rgba(0,0,0,.08);
            background: #fff;
        }
        .avatar img, .avatar .avatar-img {
            width: 100%; height: 100%; object-fit: cover; display: block;
        }
        .avatar-lg { width: 110px; height: 110px; border-width: 4px; }
        .peer {
            width: 92px;
        }
        .peer .name {
            line-height: 1.15;
            max-width: 92px;
            word-break: break-word;
        }
        @media (max-width: 576px) {
            .avatar { width: 66px; height: 66px; }
            .avatar-lg { width: 90px; height: 90px; }
            .peer { width: 78px; }
            .peer .name { max-width: 78px; font-size: 12px; }
        }
    </style>

    {{-- jQuery (используем ajax как просил) --}}
    <script>
        (function(){
            const $loading = $('#loading');
            const $error   = $('#error');
            const $you     = $('#current-user');
            const $youImg  = $('#current-user img.avatar-img');
            const $youName = $('#current-user .name');
            const $wrap    = $('#peers-wrap');
            const $svg     = $('#link-layer');
            const $visual  = $('#group-visual');

            // CSRF для AJAX
            $.ajaxSetup({
                headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')}
            });

            // Получаем данные
            $.ajax({
                method: 'GET',
                url: '/my-group/data',
                dataType: 'json'
            }).done(function(resp){
                $loading.addClass('d-none');

                if (!resp.success) {
                    $error.removeClass('d-none').text(resp.message || 'Не удалось загрузить данные.');
                    return;
                }

                // Текущий
                $youImg.attr('src', resp.current.avatar);
                $youName.text(resp.current.name ?? 'Вы');

                // Остальные
                $wrap.empty();
                (resp.peers || []).forEach(function(u){
                    const $card = $(`
                    <div class="peer d-flex flex-column align-items-center">
                        <div class="avatar mb-2">
                            <img class="avatar-img" alt="">
                        </div>
                        <div class="small fw-semibold text-center name"></div>
                    </div>
                `);
                    $card.find('img').attr('src', u.avatar);
                    $card.find('.name').text(u.name);
                    // data-ид метки на всякий случай
                    $card.attr('data-user-id', u.id);
                    $wrap.append($card);
                });

                // Ждём, когда изображения загрузятся, чтобы корректно вычислить позиции
                waitImagesLoaded($visual[0]).then(drawAllLines);

                // Перерисовка при ресайзе/скролле
                $(window).on('resize', debounce(drawAllLines, 150));
                // если внутри контейнера что-то двинулось — небольшой таймаут
                setTimeout(drawAllLines, 50);
                setTimeout(drawAllLines, 300);
            }).fail(function(xhr){
                $loading.addClass('d-none');
                $error.removeClass('d-none').text('Ошибка загрузки: ' + (xhr.statusText || 'unknown'));
            });

            function drawAllLines(){
                // очищаем svg
                $svg.empty();

                const youCenter = centerOfEl($you[0], $visual[0]);
                if (!youCenter) return;

                // для каждого peer рисуем линию
                $('#peers-wrap .peer .avatar').each(function(){
                    const peerCenter = centerOfEl(this, $visual[0]);
                    if (!peerCenter) return;

                    const line = document.createElementNS('http://www.w3.org/2000/svg','line');
                    line.setAttribute('x1', youCenter.x);
                    line.setAttribute('y1', youCenter.y);
                    line.setAttribute('x2', peerCenter.x);
                    line.setAttribute('y2', peerCenter.y);
                    line.setAttribute('stroke', '#e2e2e2');
                    line.setAttribute('stroke-width', '2');
                    line.setAttribute('stroke-linecap', 'round');
                    $svg[0].appendChild(line);
                });
            }

            function centerOfEl(el, relativeTo){
                if (!el || !relativeTo) return null;
                const r = el.getBoundingClientRect();
                const rr = relativeTo.getBoundingClientRect();
                return {
                    x: (r.left + r.right)/2 - rr.left,
                    y: (r.top  + r.bottom)/2 - rr.top
                };
            }

            function waitImagesLoaded(container){
                const imgs = Array.from(container.querySelectorAll('img'));
                const need = imgs.filter(img => !img.complete);
                if (need.length === 0) return Promise.resolve();
                return new Promise(resolve => {
                    let left = need.length;
                    need.forEach(img => {
                        img.addEventListener('load', () => (--left === 0) && resolve(), {once:true});
                        img.addEventListener('error', () => (--left === 0) && resolve(), {once:true});
                    });
                });
            }

            function debounce(fn, ms){
                let t; return function(){ clearTimeout(t); t = setTimeout(fn, ms); };
            }
        })();
    </script>
@endsection
