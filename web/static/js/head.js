/* global $, jQuery, localization, toggle_dark, lang */

// --------------------------------------------------------------
// Localization + page-level init (was: first inline <script>)
// --------------------------------------------------------------
const l10n_time  = HEAD_CONFIG.l10nTime || '';
const l10n_saved = localStorage.getItem('l10n_time');

$(document).ready(function () {

    localization = new Localization();
    if (l10n_saved !== l10n_time) {
        localization.clearCache();
        localization.loadTranslations();
        localStorage.setItem('l10n_time', l10n_time);
    }
    fetch(`/translations?lang=${lang}`);

    const visitortimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    fetch("/timezone?time=" + visitortimezone);

    $("#theme-switch").click(function () {
        toggle_dark();
    });

    let btn = $('#top-btn');
    $(window).scroll(function () {
        if ($(window).scrollTop() > 1000) {
            btn.addClass('show');
        } else {
            btn.removeClass('show');
        }
    });

    btn.on('click', function (e) {
        e.preventDefault();
        $('html, body').animate({scrollTop: 0}, 500);
    });

    $(".navbar-brand").click(() => {
        $("#wait_layout").show();
    });

    $("#wait_layout").hide();

    if (HEAD_CONFIG.authPoll) auth();
});

// --------------------------------------------------------------
// CSRF token helpers (was: second inline <script>)
// --------------------------------------------------------------
function addCsrfTokenToForms() {
    const tokenMeta = document.querySelector('meta[name="csrf-token"]');
    if (!tokenMeta) return;
    const token = tokenMeta.getAttribute('content');

    document.querySelectorAll('form').forEach(form => {
        let input = form.querySelector('input[name="csrf_token"]');
        if (input) {
            input.value = token;
        } else {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'csrf_token';
            input.value = token;
            form.appendChild(input);
        }
    });
}

// JSON-запросы не могут передать csrf_token через $_POST (PHP не парсит
// application/json в $_POST). Поэтому шлём кастомный заголовок,
// который читает auth_user.php из $_SERVER['HTTP_X_CSRF_TOKEN'].
(function patchFetchForCsrf() {
    if (window.__csrfFetchPatched) return;
    window.__csrfFetchPatched = true;

    const _fetch = window.fetch.bind(window);

    window.fetch = function (input, init) {
        init = init || {};
        const method = (init.method || 'GET').toUpperCase();

        if (['POST', 'PUT', 'DELETE', 'PATCH'].includes(method)) {
            const tokenMeta = document.querySelector('meta[name="csrf-token"]');
            const token = tokenMeta ? tokenMeta.content : '';

            if (token) {
                if (init.headers instanceof Headers) {
                    if (!init.headers.has('X-CSRF-Token')) {
                        init.headers.set('X-CSRF-Token', token);
                    }
                } else {
                    init.headers = Object.assign(
                        { 'X-CSRF-Token': token },
                        init.headers || {}
                    );
                }
            }
        }

        return _fetch(input, init);
    };
})();

if (HEAD_CONFIG.torqueUser) {
    const checkCSRFToken = function () {
        const tokenMeta  = document.querySelector('meta[name="csrf-token"]');
        const expiryMeta = document.querySelector('meta[name="csrf-token-expiry"]');
        const currentTime = Math.floor(Date.now() / 1000);

        if (tokenMeta && expiryMeta) {
            const expiryTime = parseInt(expiryMeta.content);

            if (currentTime > expiryTime + 60) {
                fetch('/auth', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'update-csrf-token'})
                })
                .then(response => {
                    if (response.status === 401) {
                        // сессия мертва — уходим на логин
                        location.href = '/logout';
                        return null;
                    }
                    return response.json();
                })
                .then(data => {
                    if (data === null) return;
                    tokenMeta.content = data.token;
                    expiryMeta.content = data.expiry;
                    addCsrfTokenToForms();
                    console.log('CSRF token updated');
                })
                .catch(error => console.error('Error updating CSRF token:', error));
            }
        }
    };
    setInterval(checkCSRFToken, 60000);
}

document.addEventListener('DOMContentLoaded', addCsrfTokenToForms);

// --------------------------------------------------------------
// auth() — heartbeat, polls every 5s
// --------------------------------------------------------------
function auth() {
    fetch("/auth", {method: "HEAD"})
        .then(resp => {
            switch (resp.status) {
                case 200:
                    $("#offline_layout").hide();
                    if (!$('#redDialogOverLay').length) {
                        document.documentElement.style.overflow = '';
                    }
                    break;
                case 401:
                    location.href = '/logout';
                    break;
                case 307:
                    location.href = '/maintenance';
                    break;
                default:
                    throw new Error('offline');
            }
        })
        .catch(() => {
            $("#offline_layout").show();
            document.documentElement.style.overflow = 'hidden';
        })
        .finally(() => setTimeout(auth, 5000));
}

// --------------------------------------------------------------
// Username in document.title
// --------------------------------------------------------------
const username = HEAD_CONFIG.username || '';
if (username.trim() !== '') {
    document.title += ` - ${username}`;
}
