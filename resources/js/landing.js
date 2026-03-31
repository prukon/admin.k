/**
 * Публичная шапка: мобильный drawer справа, аккордеон «Решения для»,
 * переход по якорям главной (как раньше через replace).
 */
(function () {
    var drawer = document.getElementById('publicNavDrawer');
    var toggles = document.querySelectorAll('.public-nav-drawer-toggle');
    var menuEl = document.getElementById('mainNav');

    var DRAWER_OPEN_CLASS = 'public-nav-drawer--open';
    var BODY_CLASS = 'public-nav-drawer-open';

    function isMobileNav() {
        return window.matchMedia('(max-width: 767.98px)').matches;
    }

    function openDrawer() {
        if (!drawer) {
            return;
        }
        drawer.classList.add(DRAWER_OPEN_CLASS);
        drawer.setAttribute('aria-hidden', 'false');
        document.body.classList.add(BODY_CLASS);
        toggles.forEach(function (btn) {
            btn.setAttribute('aria-expanded', 'true');
        });
        var closeBtn = drawer.querySelector('.public-nav-drawer__close');
        if (closeBtn) {
            closeBtn.focus();
        }
    }

    function closeDrawer(immediateCallback) {
        if (!drawer) {
            if (typeof immediateCallback === 'function') {
                immediateCallback();
            }
            return;
        }
        if (!drawer.classList.contains(DRAWER_OPEN_CLASS)) {
            if (typeof immediateCallback === 'function') {
                immediateCallback();
            }
            return;
        }

        var panel = drawer.querySelector('.public-nav-drawer__panel');
        var finished = false;

        function finish() {
            if (finished) {
                return;
            }
            finished = true;
            drawer.removeEventListener('transitionend', onEnd);
            if (typeof immediateCallback === 'function') {
                immediateCallback();
            } else if (toggles[0]) {
                toggles[0].focus();
            }
        }

        function onEnd(e) {
            if (panel && e.target !== panel) {
                return;
            }
            if (e.propertyName !== 'transform') {
                return;
            }
            finish();
        }

        drawer.addEventListener('transitionend', onEnd);
        drawer.classList.remove(DRAWER_OPEN_CLASS);
        drawer.setAttribute('aria-hidden', 'true');
        document.body.classList.remove(BODY_CLASS);
        toggles.forEach(function (btn) {
            btn.setAttribute('aria-expanded', 'false');
        });
        window.setTimeout(finish, 450);
    }

    toggles.forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!drawer) {
                return;
            }
            if (drawer.classList.contains(DRAWER_OPEN_CLASS)) {
                closeDrawer();
            } else {
                openDrawer();
            }
        });
    });

    document.querySelectorAll('[data-public-nav-close]').forEach(function (el) {
        el.addEventListener('click', function () {
            closeDrawer();
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && drawer && drawer.classList.contains(DRAWER_OPEN_CLASS)) {
            e.preventDefault();
            closeDrawer();
        }
    });

    var accBtn = document.getElementById('publicNavSolutionsBtn');
    var accPanel = document.getElementById('publicNavSolutionsPanel');
    if (accBtn && accPanel) {
        accBtn.addEventListener('click', function () {
            var open = accBtn.getAttribute('aria-expanded') === 'true';
            var next = !open;
            accBtn.setAttribute('aria-expanded', next ? 'true' : 'false');
            accPanel.setAttribute('aria-hidden', next ? 'false' : 'true');
            var wrap = accBtn.closest('.public-nav-drawer__accordion');
            if (wrap) {
                wrap.classList.toggle('is-open', next);
            }
        });
    }

    function bindHashNav(container) {
        if (!container) {
            return;
        }
        var sel = 'a.nav-link[href^="#"], a.public-nav-drawer__link[href^="#"], .navbar-nav .btn[href^="#"]';
        container.querySelectorAll(sel).forEach(function (el) {
            el.addEventListener('click', function (e) {
                var hash = this.getAttribute('href');
                if (!hash || hash === '#') {
                    return;
                }
                e.preventDefault();

                function navigate() {
                    window.location.replace(window.location.pathname + hash);
                }

                if (drawer && drawer.classList.contains(DRAWER_OPEN_CLASS)) {
                    closeDrawer(navigate);
                } else {
                    navigate();
                }
            });
        });
    }

    bindHashNav(menuEl);
    bindHashNav(drawer);

    window.addEventListener('resize', function () {
        if (!isMobileNav() && drawer && drawer.classList.contains(DRAWER_OPEN_CLASS)) {
            closeDrawer();
        }
    });
})();
