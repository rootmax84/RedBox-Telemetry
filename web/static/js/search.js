/* global $, jQuery, Choices, localization, xhrResponse, SEARCH_CONFIG */

const form = document.getElementById('searchForm');
const pidSelect = document.getElementById('pidSelect');
const operatorSelect = document.getElementById('operatorSelect');
const valueInput = document.getElementById('valueInput');
const resultsBody = document.getElementById('results-body');
const resultsTable = document.getElementById('results-table');
const noMore = document.getElementById('no-more');
const noResults = document.getElementById('no-results');

let lastSession = 0;      // keyset-курсор: последний показанный session
let hasMore = false;
let isLoading = false;
let isFirstPage = true;
let currentParams = null;

function formatSessionDate(timestampMs, lang) {
    const ts = Math.floor(timestampMs / 1000);
    const date = new Date(ts * 1000);
    const months = {
        'Jan': localization.key['month.jan'] || 'Jan',
        'Feb': localization.key['month.feb'] || 'Feb',
        'Mar': localization.key['month.mar'] || 'Mar',
        'Apr': localization.key['month.apr'] || 'Apr',
        'May': localization.key['month.may'] || 'May',
        'Jun': localization.key['month.jun'] || 'Jun',
        'Jul': localization.key['month.jul'] || 'Jul',
        'Aug': localization.key['month.aug'] || 'Aug',
        'Sep': localization.key['month.sep'] || 'Sep',
        'Oct': localization.key['month.oct'] || 'Oct',
        'Nov': localization.key['month.nov'] || 'Nov',
        'Dec': localization.key['month.dec'] || 'Dec'
    };
    const monthKey = date.toLocaleString('en', { month: 'short' });
    const month = months[monthKey] || monthKey;
    const timeFormat = document.cookie.replace(/(?:(?:^|.*;\s*)timeformat\s*\=\s*([^;]*).*$)|^.*$/, "$1") || '24';
    const day = String(date.getDate()).padStart(2, '0');
    const year = date.getFullYear();
    let hours = date.getHours();
    let minutes = String(date.getMinutes()).padStart(2, '0');
    let ampm = '';
    if (timeFormat === '12') {
        ampm = hours >= 12 ? 'pm' : 'am';
        hours = hours % 12 || 12;
    }
    const timeStr = timeFormat === '12' ? `${hours}:${minutes}${ampm}` : `${String(hours).padStart(2, '0')}:${minutes}`;
    return `${month} ${day}, ${year} ${timeStr}`;
}

function renderRows(data) {
    data.forEach(session => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${formatSessionDate(session.time, SEARCH_CONFIG.lang)}</td>
            <td>${session.timeend ? formatSessionDate(session.timeend, SEARCH_CONFIG.lang) : '-'}</td>
            <td>${session.sessionsize}</td>
            <td>${escapeHtml(session.profileName || '-')}</td>
            <td><a href="index.php?id=${encodeURIComponent(session.session)}">${SEARCH_CONFIG.favOpen}</a></td>
        `;
        resultsBody.appendChild(tr);
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function clearResults() {
    resultsBody.innerHTML = '';
    resultsTable.style.display = 'none';
    noMore.style.display = 'none';
    noResults.style.display = 'none';
    lastSession = 0;
    hasMore = false;
    isFirstPage = true;
}

function showError(msg) {
    xhrResponse(escapeHtml(msg));
}

function isPageScrollable() {
    return document.documentElement.scrollHeight > window.innerHeight;
}

function loadPage() {
    if (isLoading) return;
    if (!isFirstPage && !hasMore) return;

    isLoading = true;
    $('.fetch-data').css('display', 'block');

    const params = new FormData();
    params.append('pid', currentParams.pid);
    params.append('operator', currentParams.operator);
    params.append('value', currentParams.value);
    params.append('range', currentParams.range || 'day');
    params.append('last_session', lastSession);

    fetch('search_processor.php', {
        method: 'POST',
        body: params
    })
    .then(response => response.json())
    .then(data => {
        isLoading = false;
        $('.fetch-data').css('display', 'none');

        if (data.error) {
            showError(data.error);
            resultsTable.style.display = 'none';
            return;
        }

        // Пусто на первой странице — "no results"
        if (isFirstPage && (!data.data || data.data.length === 0)) {
            noResults.style.display = 'block';
            resultsTable.style.display = 'none';
            return;
        }

        if (isFirstPage) {
            resultsTable.style.display = 'table';
            isFirstPage = false;
        }

        if (data.data && data.data.length) {
            renderRows(data.data);
            // Обновляем keyset-курсор: последняя строка = самая старая показанная
            lastSession = data.data[data.data.length - 1].session;
        }

        hasMore = !!data.hasMore;

        if (!hasMore && lastSession) {
            noMore.style.display = 'block';
        } else {
            noMore.style.display = 'none';
        }

        // Если страница ещё не заполнила экран — догружаем
        if (hasMore && !isPageScrollable()) {
            loadPage();
        }
    })
    .catch(err => {
        isLoading = false;
        $('.fetch-data').css('display', 'none');
        showError(err.message);
    });
}

const rangeSelect = document.getElementById('rangeSelect');

form.addEventListener('submit', function (e) {
    e.preventDefault();

    const pid      = pidSelect.value;
    const operator = operatorSelect.value;
    const value    = valueInput.value.trim();
    const range    = rangeSelect ? rangeSelect.value : 'day';

    if (!pid) {
        showError(SEARCH_CONFIG.errorNoPid);
        return;
    }
    if (!value || isNaN(value)) {
        showError(SEARCH_CONFIG.errorInvalidValue);
        return;
    }

    currentParams = { pid, operator, value, range };

    clearResults();

    loadPage();
});

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting && !isLoading && hasMore) {
            loadPage();
        }
    });
}, { rootMargin: '0px 0px 100px 0px' });

const target = document.createElement('div');
target.id = 'scroll-trigger';
target.style.height = '1px';
document.getElementById('results-container').appendChild(target);
observer.observe(target);

document.addEventListener('DOMContentLoaded', function () {
    if (typeof Choices !== 'undefined') {
        document.querySelectorAll('.choices-select').forEach(el => new Choices(el, {
            searchEnabled: true,
            shouldSort: false,
            searchFloor: 2,
            itemSelectText: ''
        }));
    }
});
