/* global $, jQuery, Choices, localization, xhrResponse, SEARCH_CONFIG */

const form = document.getElementById('searchForm');
const pidSelect = document.getElementById('pidSelect');
const operatorSelect = document.getElementById('operatorSelect');
const valueInput = document.getElementById('valueInput');
const resultsBody = document.getElementById('results-body');
const resultsTable = document.getElementById('results-table');
const noMore = document.getElementById('no-more');
const noResults = document.getElementById('no-results');

let currentPage = 1;
let totalResults = 0;
let hasMore = false;
let isLoading = false;
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
    currentPage = 1;
    hasMore = false;
    totalResults = 0;
}

function showError(msg) {
    xhrResponse(escapeHtml(msg));
}

function isPageScrollable() {
    return document.documentElement.scrollHeight > window.innerHeight;
}

function loadPage(page) {
    if (isLoading) return;
    if (page > 1 && !hasMore) return;

    isLoading = true;
    $('.fetch-data').css('display', 'block');

    const params = new FormData();
    params.append('pid', currentParams.pid);
    params.append('operator', currentParams.operator);
    params.append('value', currentParams.value);
    params.append('page', page);

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

        if (page === 1 && data.total === 0) {
            noResults.style.display = 'block';
            resultsTable.style.display = 'none';
            return;
        }

        if (page === 1) {
            resultsTable.style.display = 'table';
        }

        if (data.data && data.data.length) {
            renderRows(data.data);
        }

        totalResults = data.total;
        hasMore = data.hasMore;

        if (!hasMore && totalResults > 0) {
            noMore.style.display = 'block';
        } else {
            noMore.style.display = 'none';
        }

        currentPage = page;

        if (hasMore && !isPageScrollable()) {
            loadPage(currentPage + 1);
        }
    })
    .catch(err => {
        isLoading = false;
        $('.fetch-data').css('display', 'none');
        showError(err.message);
    });
}

form.addEventListener('submit', function (e) {
    e.preventDefault();

    const pid = pidSelect.value;
    const operator = operatorSelect.value;
    const value = valueInput.value.trim();

    if (!pid) {
        showError(SEARCH_CONFIG.errorNoPid);
        return;
    }
    if (!value || isNaN(value)) {
        showError(SEARCH_CONFIG.errorInvalidValue);
        return;
    }

    currentParams = { pid, operator, value };

    clearResults();

    loadPage(1);
});

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting && !isLoading && hasMore) {
            loadPage(currentPage + 1);
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
            searchFloor: 2,
            itemSelectText: ''
        }));
    }
});
