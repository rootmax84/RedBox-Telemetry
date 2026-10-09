/* global $, jQuery, Choices, NoSleep, localization, redDialog, Cookies,
   initSlider, updCharts, resizeSplitter, markActiveSess, markCurrSess,
   initTableSorting, initMapLeaflet, extractValidSegmentsWithIndices,
   copyToClipboard, serverError, updatePlot, startPlotUpdates,
   stopPlotUpdates, handleSliderInit, alarm, rebuildMapFromRawPath,
   mapUpdRange, APP_CONFIG */

// =============================================================
// 1. Page bootstrap (was: first inline <script> in <body>)
// =============================================================
$(document).ready(function () {
    $(".copyright").html(`&copy; 2019-${(new Date).getFullYear()} RedBox Automotive · ${APP_CONFIG.version}`);

    if (!document.getElementById('plot_data')) {
        return;
    }

    let plotData = $('#plot_data');
    let lastValue = plotData.val() || [];

    let debounceTimer;
    function handleChange() {
        const newValue = plotDataChoices.getValue(true) || [];
        if (JSON.stringify(newValue) !== JSON.stringify(lastValue)) {
            lastValue = newValue;
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                updCharts();
            }, 1500);
        }
    }

    const observer = new MutationObserver((mutations) => {
        if (!lastValue.length && $('#placeholder')[0] != undefined) {
            updCharts();
        }
    });

    const targetNode = $('#right-container')[0];

    if (targetNode) {
        observer.observe(targetNode, {childList: true, subtree: true});
    }

    plotDataChoices = new Choices('#plot_data', {
        removeItemButton: true,
        placeholder: true,
        shouldSort: false,
        itemSelectText: null,
        maxItemCount: 10,
        maxItemText: (maxItemCount) => {
            return `${localization.key['overdata']} ${maxItemCount}`;
        },
        noResultsText: localization.key['vars.nores'] || 'Oops, nothing found!',
        placeholderValue: localization.key['vars.placeholder'] || 'Choose data...',
        classNames: {
            containerInner: ['choices__inner', 'choices__inner__plot'],
        },
    });

    seshidtagChoices = new Choices('#seshidtag', {
        itemSelectText: null,
        shouldSort: false,
        noResultsText: localization.key['vars.nores'] || 'Oops, nothing found!',
    });

    const selprofileChoices = new Choices('#selprofile', {
        searchEnabled: false,
        itemSelectText: null,
        shouldSort: false,
    });

    const selyearChoices = new Choices('#selyear', {
        searchEnabled: false,
        itemSelectText: null,
        shouldSort: false,
    });

    const selmonthChoices = new Choices('#selmonth', {
        searchEnabled: false,
        itemSelectText: null,
        shouldSort: false,
    });

    plotData.on('change', handleChange);

    // Sessions filters
    $('#selprofile, #selyear, #selmonth').on('change', function () {
        updateSessionList();
    });

    // Session select
    $('#seshidtag').on('change', function () {
        $('#wait_layout').show();
        $('#sessionForm').submit();
    });

    updCharts();
    if (window.history.replaceState) window.history.replaceState(null, null, window.location.href);
    resizeSplitter();
    markActiveSess();
    markCurrSess();

    // Filters init
    window.lastFilters = {
        profile: $('#selprofile').val(),
        year: $('#selyear').val(),
        month: $('#selmonth').val()
    };
});

// =============================================================
// 2. Slider init (was: inline <script> before .slider-container)
// =============================================================
let jsTimeMap = [];
if (APP_CONFIG.itime !== null && APP_CONFIG.itime !== '') {
    jsTimeMap = String(APP_CONFIG.itime)
        .split(',')
        .map(Number)
        .filter(n => !isNaN(n))
        .reverse();
}
if (APP_CONFIG.hasSession && !APP_CONFIG.isAdmin && jsTimeMap.length &&
    typeof initSlider === 'function') {
    initSlider(jsTimeMap, jsTimeMap[0], jsTimeMap.at(-1));
}
window.jsTimeMap = jsTimeMap;

// =============================================================
// 3. Live Data toggle (was: inline <script> in Live DATA block)
// =============================================================
function logToggle() {
    if ($("#log").is(":hidden")) {
        $("#log").show();
        $("#log_toggle").html('↑');
    } else {
        $("#log").hide();
        $("#log_toggle").html('↓');
        msg_def.innerHTML = localization.key['import.label'];
        msg_ok.innerHTML = "";
        msg_err.innerHTML = "";
        document.getElementById('logFile').value = "";
        up_btn.hide();
        log_list.innerHTML = "";
    }
}

const noSleep = new NoSleep();
let streamBtn_svg = null;
let stream = false;
let src = null;

function dataToggleCore() {
    updatePlot(function () {
        if ($("#data").is(":hidden")) {
            handleSliderInit();
            $("#data").show();
            $("#data_toggle").html(localization.key['collapse']);
            const streamUrl = "/sessions/stream" +
                (APP_CONFIG.streamLock > 0
                    ? '?id=' + encodeURIComponent(APP_CONFIG.sessionId)
                    : '');
            src = new EventSource(streamUrl);
            src.onmessage = e => { $("#stream").html(e.data); };
            alarm.muted = false;
            noSleep.enable();
            stream = true;
            startPlotUpdates();
        } else {
            $("#data").hide();
            $("#data_toggle").html(localization.key['expand']);
            src.close();
            alarm.muted = true;
            noSleep.disable();
            stream = false;
            stopPlotUpdates();
            if (rawPath.length) {
                rebuildMapFromRawPath();
                if (window.MapData && window.MapData.flatCoords.length > 0) {
                    mapUpdRange(0, window.MapData.flatCoords.length - 1);
                }
            }
        }
        if (streamBtn_svg !== null) streamBtn_svg.style.color = stream ? '#008000' : 'inherit';
    });
}

let lastCallTime = 0;

function dataToggle() {
    const now = Date.now();
    if (now - lastCallTime < 2000) {
        return;
    }
    lastCallTime = now;
    dataToggleCore();
}

// =============================================================
// 4. Log upload dialog (was: inline <script> at bottom)
// =============================================================
function uploadLogDialog() {
    let reload_sw = false;
    const messageHtml = `<div class="drop-card" id="log">
         <div style="display:flex; justify-content:center; margin-bottom:10px;">
             <span class="label label-default" id="log-msg-def">${localization.key['import.label']}</span>
             <span class="label label-success" id="log-msg-ok"></span>
             <span class="label label-danger" id="log-msg-err"></span>
         </div>
         <div style="display:flex; justify-content:center;">
             <form method="POST" style="display:contents" enctype="multipart/form-data" onsubmit="return false">
                 <input class="btn btn-default" style="border-radius:5px" type="file" multiple name="file[]" id="logFile" accept=".txt,.csv">
                 <input class="btn btn-default upload-log-btn" id="log-upload-btn" type="submit" value="">
             </form>
         </div>
         <ul id="log-list"></ul>
    </div>`;

    redDialog.make({
        title: localization.key['dialog.result'],
        message: messageHtml,
        btnClassSuccessText: "OK",
        btnClassFail: "hidden",
        onResolve: () => {
            if (reload_sw) {
                window.location.href = '/';
            }
        }
    });

    function appendLogListItem(text) {
        const li = document.createElement('li');
        li.style.fontFamily = 'monospace';
        li.textContent = text;
        log_list.appendChild(li);
    }

    function appendLogSeparator() {
        const hr = document.createElement('hr');
        log_list.appendChild(hr);
    }

    document.getElementById('redDialogWrap').style.width = 'auto';

    const dropArea = document.getElementById('log');
    const fl = document.getElementById('logFile');
    const msg_def = document.getElementById('log-msg-def');
    const msg_ok = document.getElementById('log-msg-ok');
    const msg_err = document.getElementById('log-msg-err');
    const up_btn = $('#log-upload-btn');
    const log_list = document.getElementById('log-list');

    window.processedFiles = [];

    dropArea.addEventListener('drop', drop);
    dropArea.addEventListener('dragover', dragover);
    dropArea.addEventListener('dragleave', dragleave);

    function drop(event) {
        event.preventDefault();
        dropArea.style.border = '';
        fl.files = event.dataTransfer.files;
        checkLog();
    }

    function dragover(event) {
        event.preventDefault();
        dropArea.style.borderColor = '#0eff00';
    }

    function dragleave(event) {
        event.preventDefault();
        dropArea.style.borderColor = '';
    }

    function readFileStart(file) {
        return new Promise((resolve, reject) => {
            const slice = file.slice(0, 2048);
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result);
            reader.onerror = reject;
            reader.readAsText(slice);
        });
    }

    function detectTypeAndDate(startContent, fileName) {
        let logType = null;
        let logDate = null;

        function localizeMonth(str) {
            const months = {
                'янв': 'jan', 'фев': 'feb', 'мар': 'mar', 'апр': 'apr',
                'май': 'may', 'мая': 'may',
                'июн': 'jun', 'июл': 'jul', 'авг': 'aug',
                'сен': 'sep', 'окт': 'oct', 'ноя': 'nov', 'дек': 'dec',
                'ene': 'jan', 'feb': 'feb', 'mar': 'mar', 'abr': 'apr',
                'may': 'may', 'jun': 'jun', 'jul': 'jul', 'ago': 'aug',
                'sep': 'sep', 'oct': 'oct', 'nov': 'nov', 'dic': 'dec',
                'mär': 'mar', 'mrz': 'mar', 'mai': 'may', 'okt': 'oct', 'dez': 'dec'
            };
            let result = str.toLowerCase();
            for (let ru in months) {
                result = result.replace(new RegExp(ru + '\\.?', 'g'), months[ru]);
            }
            return result;
        }

        if (startContent.startsWith("TIME ECT")) {
            logType = 'redlog';
        } else if (startContent.includes("Device Time") || startContent.includes("GPS Time")) {
            logType = 'torque';
        } else {
            return { logType: null, logDate: null };
        }

        try {
            const lines = startContent.split("\n");
            if (logType === 'redlog') {
                if (lines.length >= 2) {
                    const timestamp = parseInt(lines[1].split(" ")[0]);
                    logDate = new Date(timestamp);
                    if (isNaN(logDate) || logDate.getFullYear() < 2000) throw new Error('');
                }
            } else {
                let headerLine = null;
                for (let line of lines) {
                    line = line.trim();
                    if (line.startsWith("Device Time") || line.startsWith("GPS Time")) {
                        headerLine = line;
                        break;
                    }
                }
                if (!headerLine) throw new Error('Header not found');
                const headers = headerLine.split(",").map(h => h.trim().toLowerCase());
                const deviceTimeIdx = headers.indexOf('device time');
                const gpsTimeIdx = headers.indexOf('gps time');

                let firstDataLine = null;
                for (let line of lines) {
                    line = line.trim();
                    if (line === '' || line.startsWith("GPS Time") || line.startsWith("Device Time")) continue;
                    firstDataLine = line;
                    break;
                }
                if (!firstDataLine) throw new Error('No data line');
                const cols = firstDataLine.split(",");

                let dateStr = null;
                if (deviceTimeIdx !== -1 && cols[deviceTimeIdx] && cols[deviceTimeIdx].trim() !== '-' && cols[deviceTimeIdx].trim() !== '') {
                    dateStr = cols[deviceTimeIdx].trim();
                } else if (gpsTimeIdx !== -1 && cols[gpsTimeIdx] && cols[gpsTimeIdx].trim() !== '-' && cols[gpsTimeIdx].trim() !== '') {
                    dateStr = cols[gpsTimeIdx].trim();
                }
                if (!dateStr) throw new Error('No date value');

                dateStr = localizeMonth(dateStr);
                logDate = new Date(dateStr);
                if (isNaN(logDate)) {
                    const parts = dateStr.split(' ');
                    if (parts.length >= 2) {
                        const dateParts = parts[0].split('-');
                        const timeParts = parts[1].split(':');
                        if (dateParts.length === 3 && timeParts.length === 3) {
                            const day = parseInt(dateParts[0]);
                            const monthIndex = ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'].indexOf(dateParts[1].toLowerCase());
                            const year = parseInt(dateParts[2]);
                            const hours = parseInt(timeParts[0]);
                            const minutes = parseInt(timeParts[1]);
                            const secondsParts = timeParts[2].split('.');
                            const seconds = parseInt(secondsParts[0]);
                            const ms = secondsParts.length > 1 ? parseInt(secondsParts[1]) : 0;
                            if (!isNaN(day) && monthIndex !== -1 && !isNaN(year)) {
                                logDate = new Date(year, monthIndex, day, hours, minutes, seconds, ms);
                            }
                        }
                    }
                    if (isNaN(logDate)) throw new Error('Invalid date');
                }
            }
        } catch (e) {
            logDate = null;
        }

        return { logType, logDate };
    }

    async function checkLog() {
        msg_def.innerHTML = "";
        msg_err.innerHTML = "";
        msg_ok.innerHTML = "";
        log_list.innerHTML = "";
        const log_data = document.getElementById('logFile');
        let size = 0;
        window.processedFiles = [];

        if (!log_data.files.length) {
            $("#log-list").css({"display": "none"});
            msg_def.innerHTML = localization.key['import.label'];
            up_btn.hide();
            return;
        } else {
            $("#log-list").css({"display": "grid"});
        }

        msg_def.innerHTML = localization.key['import.read'];

        for (let i = 0; i < log_data.files.length; i++) {
            size += log_data.files[i].size;
        }
        if (log_data.files.length > 10) {
            msg_def.innerHTML = "";
            msg_err.innerHTML = localization.key['import.warn.count'];
            up_btn.hide();
            return;
        }
        if (size > 52428800) {
            msg_def.innerHTML = "";
            msg_err.innerHTML = localization.key['import.warn.size'];
            up_btn.hide();
            return;
        }

        const filePromises = Array.from(log_data.files).map(async (file) => {
            try {
                const startContent = await readFileStart(file);
                const { logType, logDate } = detectTypeAndDate(startContent, file.name);
                if (!logType || !logDate) {
                    msg_def.innerHTML = "";
                    msg_err.innerHTML = localization.key['import.broken.label'];
                    msg_ok.innerHTML = "";
                    up_btn.hide();
                    appendLogListItem(` ${file.name} ${localization.key['import.broken.el']}`);
                    return;
                }

                const dateDMY = `${logDate.getFullYear()}-${(logDate.getMonth() + 1)}-${logDate.getDate()}`;
                const dateTime = Cookies.get('timeformat') === '12'
                    ? logDate.toLocaleString('en-US', { hour: 'numeric', minute: 'numeric', hour12: true })
                    : `${logDate.getHours()}:${('0' + logDate.getMinutes()).slice(-2)}`;
                const dateStr = `${localization.key['import.date']} ${dateDMY} ${dateTime})`;

                window.processedFiles.push({
                    name: file.name,
                    file: file,
                    type: logType
                });

                const typeLabel = logType === 'redlog' ? ' [RedManage]' : ' [Torque]';
                appendLogListItem(` ${file.name} ${dateStr} ${typeLabel}`);
            } catch (e) {
                msg_def.innerHTML = "";
                msg_err.innerHTML = localization.key['import.broken.label'];
                msg_ok.innerHTML = "";
                up_btn.hide();
                appendLogListItem(` ${file.name} ${localization.key['import.broken.el']}`);
            }
        });

        await Promise.all(filePromises);

        if (window.processedFiles.length === log_data.files.length) {
            msg_def.innerHTML = "";
            msg_ok.innerHTML = localization.key['import.ready'];
            up_btn.show();
        }
    }

    async function submitLog(event) {
        event.preventDefault();
        const logFile = document.getElementById('logFile');

        up_btn.hide();
        msg_err.innerHTML = "";
        msg_def.innerHTML = "";
        msg_ok.classList.add("wait");
        msg_ok.innerHTML = localization.key['import.upload'];
        logFile.setAttribute("disabled", "");
        logFile.setAttribute("disabled", "");

        appendLogSeparator();

        const groups = {};
        for (const item of window.processedFiles) {
            if (!groups[item.type]) groups[item.type] = [];
            groups[item.type].push(item.file);
        }

        const endpoints = {
            redlog: '/import/redlog',
            torque: '/import/torque'
        };

        let hasErrors = false;
        const summaryMessages = [];

        // ────────────────────────────────────────────────────────
        // Обработка одного NDJSON-потока от import_*.php
        // ────────────────────────────────────────────────────────
        async function consumeStream(type, response) {
            const reader  = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;

                buffer += decoder.decode(value, { stream: true });

                let lines = buffer.split('\n');
                buffer = lines.pop();   // неполная строка — оставляем

                for (const line of lines) {
                    const trimmed = line.trim();
                    if (!trimmed) continue;
                    let evt;
                    try {
                        evt = JSON.parse(trimmed);
                    } catch (e) {
                        console.warn('Bad NDJSON line:', trimmed);
                        continue;
                    }
                    handleEvent(type, evt);
                }
            }

            // Остаток без завершающего \n
            if (buffer.trim()) {
                try {
                    handleEvent(type, JSON.parse(buffer.trim()));
                } catch (e) { /* ignore */ }
            }
        }

        function handleEvent(type, evt) {
            const typeLabel = type === 'redlog' ? '[RedManage]' : '[Torque]';

            switch (evt.type) {
                case 'error':
                    hasErrors = true;
                    msg_err.innerHTML = evt.message || 'Error';
                    break;

                case 'file_start':
                    appendLogListItem(` ${evt.file} — ${localization.key['import.end']}`);
                    break;

                case 'file_done':
                    if (evt.status === 'ok') {
                        const sessionsStr = evt.sessions ? ` (${evt.sessions})` : '';
                        appendLogListItem(` ✓ ${evt.file}${sessionsStr} ${typeLabel}`);
                    } else {
                        hasErrors = true;
                        appendLogListItem(` ✗ ${evt.file} — ${evt.message || 'error'}`);
                    }
                    break;

                case 'summary':
                    if (evt.message) summaryMessages.push(evt.message);
                    if (evt.files_failed && evt.files_failed > 0) {
                        hasErrors = true;
                    }
                    break;
            }
        }

        // ────────────────────────────────────────────────────────
        // Запуск обоих потоков параллельно
        // ────────────────────────────────────────────────────────
        const streamPromises = Object.keys(groups).map(async (type) => {
            const formData = new FormData();
            groups[type].forEach(file => {
                formData.append('file[]', file, file.name);
            });

            try {
                const response = await fetch(endpoints[type], {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Stream': '1' }
                });

                const contentType = response.headers.get('content-type') || '';

                // Streaming-путь
                if (contentType.includes('application/x-ndjson')
                    && response.body
                    && typeof response.body.getReader === 'function'
                ) {
                    await consumeStream(type, response);
                    return;
                }

                // Fallback: сервер не поддержал streaming (старый nginx, PHP-ошибка до header())
                // или ответ не OK — читаем как текст.
                const text = await response.text();
                if (response.ok) {
                    summaryMessages.push(text);
                    appendLogListItem(` ${type}: ${text}`);
                } else {
                    hasErrors = true;
                    appendLogListItem(` ✗ ${type}: ${text || ('HTTP ' + response.status)}`);
                }
            } catch (error) {
                hasErrors = true;
                appendLogListItem(` ✗ ${type}: ${error.message}`);
            }
        });

        await Promise.all(streamPromises);

        if (hasErrors) {
            msg_ok.innerHTML = '';
            // msg_err уже заполнен через handleEvent('error') или fallback
            if (!msg_err.innerHTML.trim()) {
                msg_err.innerHTML = localization.key['import.broken.label'];
            }
        } else {
            msg_ok.innerHTML = summaryMessages.join('<br>') || 'OK';
            reload_sw = true;
        }

        msg_ok.classList.remove("wait");
        logFile.removeAttribute("disabled");
    }

    // ── вешаем обработчики ──
    const form = document.querySelector('#redDialogWrap form');
    if (form) {
        form.addEventListener('submit', submitLog);
    }

    const fileInput = document.getElementById('logFile');
    if (fileInput) {
        fileInput.addEventListener('change', checkLog);
    }
}

// =============================================================
// 5. Session actions (was: inline <script> at bottom)
// =============================================================
function delSession() {
    $("#wait_layout").hide();
    const sessionId   = APP_CONFIG.sessionId   || '';
    const sessionDate = APP_CONFIG.sessionDate || '';
    if (!sessionId.length) return;

    const formatTime = (timestamp) => {
        const date = new Date(timestamp);
        return Cookies.get('timeformat') == '12'
            ? date.toLocaleTimeString('en-US')
            : date.toLocaleTimeString('ru-RU');
    };

    let messageText = `${localization.key['dialog.del.session']} (${sessionDate})`;
    if (cutStart !== null && cutEnd !== null) {
        const startTime = formatTime(cutStart);
        const endTime   = formatTime(cutEnd);
        messageText += ` <strong>${localization.key['dialog.del.range']}</strong> ${startTime} - ${endTime}`;
    }
    messageText += "?";

    const dialogOpt = {
        title: localization.key['dialog.confirm'],
        btnClassSuccessText: localization.key['btn.yes'],
        btnClassFailText:    localization.key['btn.no'],
        btnClassFail: "btn btn-info btn-sm",
        message: messageText,
        onResolve: function () {
            const fd = new FormData();
            fd.append('deletesession', sessionId);
            if (cutStart !== null && cutEnd !== null) {
                fd.append('cutstart', cutStart);
                fd.append('cutend',   cutEnd);
            }

            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            if (csrfMeta) fd.append('csrf_token', csrfMeta.content);

            $("#wait_layout").show();

            fetch('/sessions/delete-one', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            })
            .then(r => {
                const ct = r.headers.get('content-type') || '';
                if (ct.includes('application/json')) {
                    return r.json();
                }
                // Не должно случаться при корректном del_session.php,
                // но подстрахуемся на случай старого сервера/прокси.
                return r.text().then(t => ({ _text: t }));
            })
            .then(data => {
                if (data._text !== undefined) {
                    location.reload();
                    return;
                }

                if (data.reload)   { location.href = '/logout'; return; }
                if (data.redirect) { location.href = data.redirect; return; }

                if (data.status === 'accepted' && data.task_id) {
                    $("#wait_layout").hide();
                    pollHeavyTask(data.task_id, {
                        taskType: 'delete_sessions',
                        onDone: () => location.reload(),
                    });
                    return;
                }

                $("#wait_layout").hide();
                serverError(data.error || 'Unknown response');
            })
            .catch(err => {
                $("#wait_layout").hide();
                serverError(err.message);
            });
        },
        onReject: function () { return; }
    };

    redDialog.make(dialogOpt);
}

function exportSession(type) {
    $("#wait_layout").hide();
    const sessionId = APP_CONFIG.sessionId || '';
    const sessionDate = APP_CONFIG.sessionDate || '';

    const formatTime = (timestamp) => {
        const date = new Date(timestamp);
        if (Cookies.get('timeformat') === '12') {
            return date.toLocaleTimeString('en-US');
        } else {
            return date.toLocaleTimeString('ru-RU');
        }
    };

    let messageText = `${localization.key['dialog.export']} ${type} (${sessionDate})`;
    if (cutStart !== null && cutEnd !== null) {
        const startTime = formatTime(cutStart);
        const endTime = formatTime(cutEnd);
        messageText += ` <strong>${localization.key['dialog.del.range']}</strong> ${startTime} - ${endTime}`;
    }
    messageText += "?";

    let dialogOpt = {
        title: localization.key['dialog.confirm'],
        btnClassSuccessText: localization.key['btn.yes'],
        btnClassFailText: localization.key['btn.no'],
        btnClassFail: "btn btn-info btn-sm",
        message: messageText,
        onResolve: function () {
            let url = `/sessions/export?sid=${sessionId}&filetype=${type.toLowerCase()}`;
            if (cutStart !== null && cutEnd !== null) {
                url += `&cutstart=${cutStart}&cutend=${cutEnd}`;
            }
            location.href = url;
        }
    };
    redDialog.make(dialogOpt);
}

function mergeSessions() {
    location.href = "/sessions/merge?mergesession=" + encodeURIComponent(APP_CONFIG.sessionId || '');
}

function shareSession() {
    const uid = APP_CONFIG.uid;
    const id = APP_CONFIG.sessionId;
    $(".fetch-data").css("display", "block");
    $(".share-img").css("pointer-events", "none");

    fetch('/sign', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ uid, id })
    })
    .then(response => response.json())
    .then(result => {
        if (result.signature) {
            const sig = result.signature;
            const url = `${window.location.origin}/share?uid=${encodeURIComponent(uid)}&id=${encodeURIComponent(id)}&sig=${sig}`;
            if (navigator.share) {
                return navigator.share({
                    text: `${new Date(Number(id)).toLocaleString()}`,
                    url: url,
                }).catch((err) => {
                    if (err.name !== 'AbortError') throw err;
                }).finally(() => {
                    $(".fetch-data").css("display", "none");
                    $(".share-img").css("pointer-events", "auto");
                });
            } else {
                $(".fetch-data").css("display", "none");
                $(".share-img").css("pointer-events", "auto");
                let dialogOpt = {
                    title: localization.key['dialog.confirm'],
                    message: localization.key['share.dialog.text'],
                    btnClassSuccessText: localization.key['btn.yes'],
                    btnClassFailText: localization.key['btn.no'],
                    btnClassFail: "btn btn-info btn-sm",
                    onResolve: function () {
                        copyToClipboard(url);
                    }
                };
                redDialog.make(dialogOpt);
            }
        } else {
            $(".fetch-data").css("display", "none");
            serverError(result.error);
        }
    })
    .catch(err => {
        $(".fetch-data").css("display", "none");
        serverError(err);
    });
}

function addToFavorite() {
    const id = APP_CONFIG.sessionId;
    const isFavorite = $('.favorite').hasClass('favorite-en');
    const method = isFavorite ? 'DELETE' : 'POST';

    $(".fetch-data").css("display", "block");
    $(".favorite").css("pointer-events", "none");

    fetch('/sessions/favorite', {
        method: method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    })
    .then(response => {
        if (!response.ok) throw new Error('Network error');
        return response.json();
    })
    .then(result => {
        if (result.status === 'success') {
            if (result.action === 'added') {
                $('.favorite').addClass('favorite-en');
            } else if (result.action === 'deleted') {
                $('.favorite').removeClass('favorite-en');
            }
        }
    })
    .catch(err => {
        serverError(err);
    })
    .finally(() => {
        $(".fetch-data").css("display", "none");
        $(".favorite").css("pointer-events", "auto");
    });
}

function updateSessionList() {
    const currentSessionId = APP_CONFIG.sessionId || '';
    sid = currentSessionId;
    const year = $('#selyear').val() || '';
    const month = $('#selmonth').val() || '';
    const profile = $('#selprofile').val() === localization.key['profile.ns']
        ? 'Not Specified'
        : ($('#selprofile').val() || '');

    $('.fetch-data').css('display', 'block');

    $('#hiddenProfile').val(profile);
    $('#hiddenYear').val(year);
    $('#hiddenMonth').val(month);

    let url = `/sessions/filtered/?current_id=${encodeURIComponent(currentSessionId)}`;

    if (year && year !== 'ALL' && year !== '') {
        url += `&year=${encodeURIComponent(year)}`;
    }
    if (month && month !== 'ALL' && month !== '') {
        url += `&month=${encodeURIComponent(month)}`;
    }
    if (profile && profile !== 'ALL' && profile !== '') {
        url += `&profile=${encodeURIComponent(profile)}`;
    }

    fetch(url)
        .then(response => {
            if (!response.ok) throw new Error(`Network error: ${response.status}`);
            return response.json();
        })
        .then(data => {
            const allOptions = [];
            const sessionCount = data.sessions ? data.sessions.length : 0;

            const translate = localization.key['filter.found'];
            const placeholderText = sessionCount
                ? `${translate}: ${sessionCount}`
                : `${translate}: 0`;

            if (data.sessions && data.sessions.length > 0) {
                data.sessions.forEach(session => {
                    let optionText = session.date;

                    if (session.profile) {
                        optionText += ` (${localization.key['sel.profile'] || 'Profile'}: ${session.profile})`;
                    }

                    const showSessionLength = !!APP_CONFIG.showSessionLength;
                    if (showSessionLength && session.duration) {
                        optionText += ` (${localization.key['get.sess.length'] || 'Length'}: ${session.duration})`;
                    }

                    if (session.ip) {
                        optionText += ` (IP: ${session.ip})`;
                    }
                    if (session.active) {
                        optionText += ` ${session.active}`;
                    }
                    if (session.selected) {
                        optionText += ` ${localization.key['get.sess.curr'] || '(current)'}`;
                    }

                    allOptions.push({
                        value: session.id,
                        label: optionText,
                        selected: session.selected,
                    });
                });

                seshidtagChoices.destroy();

                const selectElement = document.getElementById('seshidtag');
                if (selectElement) {
                    selectElement.innerHTML = '';
                    allOptions.forEach(option => {
                        const opt = document.createElement('option');
                        opt.value = option.value;
                        opt.textContent = option.label;
                        if (option.selected) {
                            opt.selected = true;
                        }
                        selectElement.appendChild(opt);
                    });
                }

                seshidtagChoices = new Choices('#seshidtag', {
                    itemSelectText: null,
                    shouldSort: false,
                    noResultsText: localization.key['vars.nores'] || 'No sessions found',
                    placeholder: true,
                    placeholderValue: placeholderText,
                });

                window.seshidtagChoices = seshidtagChoices;
                markActiveSess();
                markCurrSess();
            } else {
                seshidtagChoices.destroy();

                const selectElement = document.getElementById('seshidtag');
                if (selectElement) {
                    selectElement.innerHTML = '';
                    const noSessionOption = document.createElement('option');
                    noSessionOption.value = '';
                    noSessionOption.textContent = localization.key['vars.nores'] || 'No sessions found';
                    noSessionOption.disabled = true;
                    selectElement.appendChild(noSessionOption);
                }

                seshidtagChoices = new Choices('#seshidtag', {
                    itemSelectText: null,
                    shouldSort: false,
                    noResultsText: localization.key['vars.nores'] || 'No sessions found',
                    placeholder: true,
                    placeholderValue: placeholderText,
                });

                window.seshidtagChoices = seshidtagChoices;
            }

            $('.fetch-data').css('display', 'none');
        })
        .catch(error => {
            $('.fetch-data').css('display', 'none');
            serverError(error);
        });
}

// =============================================================
// 6. Map init (was: inline <script> at bottom of session block)
// =============================================================
window.rawPath = APP_CONFIG.imapdata
    ? JSON.parse('[' + APP_CONFIG.imapdata + ']')
    : [];

if (!rawPath.length) {
    $('#map-div').hide();
} else {
    const coordsOnly = rawPath.map(p => [p[0], p[1]]);
    let validSegmentsWithIndices = extractValidSegmentsWithIndices(coordsOnly, {
        minPoints: Math.trunc(rawPath.length * 0.05)
    });

    if (!validSegmentsWithIndices.length) {
        const fallbackPoints = rawPath
            .map((p, idx) => ({
                coord: [p[0], p[1]],
                index: idx
            }))
            .filter(item => item.coord[0] !== 0 || item.coord[1] !== 0);

        if (fallbackPoints.length) {
            validSegmentsWithIndices = [fallbackPoints];
        }
    }

    const segmentsCoords = validSegmentsWithIndices.map(seg => seg.map(p => p.coord));
    const flatCoords = segmentsCoords.flat();
    const flatIndices = validSegmentsWithIndices.flat().map(p => p.index);

    window.MapData = {
        segmentsCoords,
        segmentsIndices: validSegmentsWithIndices,
        flatCoords,
        flatIndices
    };

    window.MapData.rawPathLength = rawPath.length;

    const origHeading = {};
    rawPath.forEach((point, idx) => {
        if (point.length >= 3) {
            origHeading[idx] = point[2];
        }
    });
    window.MapData.origHeading = origHeading;

    const origToFlat = {};
    window.MapData.segmentsIndices.flat().forEach(({ index }) => {
        if (index >= 0 && !(index in origToFlat)) {
            origToFlat[index] = window.MapData.flatIndices.indexOf(index);
        }
    });
    window.MapData.origToFlat = origToFlat;

    window.chartRangeStart = 0;
    window.chartRangeEnd = 0;

    initMap = initMapLeaflet;
    jsCBinitMap = () => $(document).ready(initMap);
    jsCBinitMap();
}

// =============================================================
// 7. Admin: sort users table (was: inline <script> in admin block)
// =============================================================
if (APP_CONFIG.isAdmin && document.querySelector('.users-list')) {
    initTableSorting('.users-list');
}
