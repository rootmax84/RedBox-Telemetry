'use strict;'

const POLLING_INTERVAL = 10000;
const MCU_LOCAL_LENGTH = 404;   // bytes stored in `data`
const MCU_WIRE_LENGTH  = 406;   // bytes + '~' + timestamp
const MCU_TERMINATOR   = '~';
const TS_MIN_MS        = 1577836800000; // 2020-01-01

let eop_sensor = null;
let fp_sensor = null;
let map_limit = null;
let cfg_data = [];

/* =====================================================
 *  VALIDATION HELPERS
 * ===================================================== */

function isByte(v) {
    return Number.isInteger(v) && v >= 0 && v <= 255;
}

function isValidByteArray(arr, expectedLength) {
    if (!Array.isArray(arr) || arr.length !== expectedLength) return false;
    for (let i = 0; i < arr.length; i++) {
        if (!isByte(arr[i])) return false;
    }
    return true;
}

function isValidTimestamp(ts) {
    const n = Number(ts);
    if (!Number.isInteger(n)) return false;
    if (n < TS_MIN_MS) return false;
    if (n > Date.now() + 86400000) return false;
    return true;
}

/**
 * Validate the split payload that came from the server.
 * parts = result.split(',')
 */
function isValidServerPayload(parts) {
    if (!Array.isArray(parts) || parts.length !== MCU_WIRE_LENGTH) return false;
    if (parts[MCU_WIRE_LENGTH - 2] !== MCU_TERMINATOR) return false;

    for (let i = 0; i < MCU_LOCAL_LENGTH; i++) {
        const s = String(parts[i]).trim();
        if (!/^\d{1,3}$/.test(s)) return false;
        const n = Number(s);
        if (n < 0 || n > 255) return false;
    }

    const tsStr = String(parts[MCU_WIRE_LENGTH - 1]).trim();
    if (!/^\d{13}$/.test(tsStr)) return false;
    return isValidTimestamp(Number(tsStr));
}

/* =====================================================
 *  MAP RANGE / CHOICES
 * ===================================================== */

function mapRange(x, in_min, in_max, out_min, out_max) {
    const constrainedX = Math.min(Math.max(x, in_min), in_max);
    if (in_min === in_max) return out_min;
    return (constrainedX - in_min) * (out_max - out_min) / (in_max - in_min) + out_min;
}

function createChoices() {
    document.querySelectorAll('select').forEach(select => {
        if (select._choices) {
            select._choices.destroy();
            select._choices = null;
        }
    });

    document.querySelectorAll('select').forEach(select => {
        select._choices = new Choices(select, {
            itemSelectText: null,
            shouldSort: false,
            searchEnabled: false,
            classNames: {
                containerInner: ['choices__inner', 'choices__settings__text'],
            },
        });
    });
}

function initChoicesSystem() {
    const MAX_WAIT_TIME = 10000;
    let timeoutId;

    function cleanup() {
        if (timeoutId) clearTimeout(timeoutId);
    }

    function executeCreateChoices() {
        cleanup();
        createChoices();
    }

    if (localStorage.getItem(`${localization.cacheKey}-${localization.currentLang}`)) {
        executeCreateChoices();
        return;
    }

    timeoutId = setTimeout(() => { cleanup(); }, MAX_WAIT_TIME);

    const originalSetItem = localStorage.setItem;
    localStorage.setItem = function (key, value) {
        originalSetItem.apply(this, arguments);
        if (key === `${localization.cacheKey}-${localization.currentLang}`) {
            executeCreateChoices();
        }
    };

    window.addEventListener('storage', (e) => {
        if (e.key === `${localization.cacheKey}-${localization.currentLang}` && e.newValue) {
            executeCreateChoices();
        }
    });
}

/* =====================================================
 *  HELPERS
 * ===================================================== */

Object.defineProperty($.fn, 'value', {
    get: function () { return this.val(); },
    set: function (value) { return this.val(value); },
    configurable: true
});

Object.defineProperty($.fn, 'checked', {
    get: function () { return this.prop('checked'); },
    set: function (value) {
        const boolValue = !!(value && value !== "" && value !== "false");
        return this.prop('checked', boolValue);
    },
    configurable: true
});

Object.defineProperty($.fn, 'style', {
    get: function () {
        return {
            set pointerEvents(value) { $(this).css('pointer-events', value); },
            get pointerEvents() { return $(this).css('pointer-events'); }
        };
    }
});

async function checkSig() {
    const urlToCheck = window.location.href;
    const url = new URL(urlToCheck);
    const searchParams = url.searchParams;

    if (!searchParams.has('uid') || !searchParams.has('sig')) return;

    try {
        const response = await fetch(urlToCheck, {
            method: 'HEAD',
            cache: 'no-cache'
        });

        if (response.status === 404) {
            window.location.href = 'catch.php?c=noshare';
            throw new Error('Signature invalid');
        }
    } catch (error) {
        window.location.href = 'catch.php?c=noshare';
        throw error;
    }
}

/* =====================================================
 *  CONFIG IMPORT/EXPORT
 * ===================================================== */

function checkCfg() {
    const fileInput = document.getElementById('cfgFile');
    const file = fileInput.files[0];

    if (!file) return;

    const reader = new FileReader();
    reader.onload = function (e) {
        try {
            const decodedString = atob(e.target.result);
            const elements = decodedString.split(',');

            if (elements.length !== 405 || elements[404] !== MCU_TERMINATOR) {
                serverError(localization.key['import.broken.el']);
                $("#cfgFile").value = '';
                return;
            }

            const numbers = [];
            for (let i = 0; i < 404; i++) {
                const s = String(elements[i]).trim();
                if (!/^\d{1,3}$/.test(s)) {
                    serverError(localization.key['import.broken.el']);
                    $("#cfgFile").value = '';
                    return;
                }
                const n = Number(s);
                if (n < 0 || n > 255) {
                    serverError(localization.key['import.broken.el']);
                    $("#cfgFile").value = '';
                    return;
                }
                numbers.push(n);
            }

            cfg_data = numbers;

        } catch (error) {
            serverError(error.message);
            $("#cfgFile").value = '';
        }
    };

    reader.onerror = function () {
        serverError();
        $("#cfgFile").value = '';
    };

    reader.readAsText(file);
}

function cfgUpload() {
    if (!cfg_data.length) return;

    if (!isValidByteArray(cfg_data, MCU_LOCAL_LENGTH)) {
        serverError(localization.key['import.broken.el']);
        cfg_data = [];
        return;
    }

    $("#cfgFile").value = '';
    data = cfg_data;
    cfg_data = [];
    fillData();
    createChoices();
    saveData();
}

function cfgDownload() {
    if (!isValidByteArray(data, MCU_LOCAL_LENGTH)) {
        serverError();
        return;
    }

    const dataToDownload = [...data, MCU_TERMINATOR];
    if (dataToDownload.length !== 405 || dataToDownload[404] !== MCU_TERMINATOR) {
        serverError();
        return;
    }

    const dataString = btoa(dataToDownload.join(','));
    const fileName = `ra150_mcu_cfg_${Date.now()}.b64`;
    const blob = new Blob([dataString], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = fileName;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

/* =====================================================
 *  BINARY SERIALIZATION
 * ===================================================== */

function numberToBytesTyped(number, type = 'auto') {
    const sizes = {
        'uint8': 1, 'int8': 1,
        'uint16': 2, 'int16': 2,
        'uint32': 4, 'int32': 4,
        'float32': 4
    };

    if (type === 'auto') {
        type = Number.isInteger(number) ? 'int32' : 'float32';
    }

    const byteLength = sizes[type];
    if (!byteLength) throw new Error('Unsupported type: ' + type);

    // -------- float32 --------
    if (type === 'float32') {
        const n = Number(number);
        if (!Number.isFinite(n)) throw new Error('float32 not finite: ' + number);
        const fa = new Float32Array(1);
        fa[0] = n;
        return Array.from(new Uint8Array(fa.buffer));
    }

    // -------- int types --------
    const n = Math.round(Number(number));
    if (!Number.isFinite(n)) throw new Error(type + ' not finite: ' + number);

    const ranges = {
        'uint8':  [0, 255],
        'int8':   [-128, 127],
        'uint16': [0, 65535],
        'int16':  [-32768, 32767],
        'uint32': [0, 0xFFFFFFFF],
        'int32':  [-0x80000000, 0x7FFFFFFF]
    };
    const [min, max] = ranges[type];
    if (n < min || n > max) {
        throw new Error(`${type} out of range: ${n} (${min}..${max})`);
    }

    if (byteLength === 1) {
        return [n & 0xFF];
    }
    if (byteLength === 2) {
        return [n & 0xFF, (n >>> 8) & 0xFF];
    }
    if (byteLength === 4) {
        return [n & 0xFF, (n >>> 8) & 0xFF, (n >>> 16) & 0xFF, (n >>> 24) & 0xFF];
    }

    throw new Error('Unsupported type');
}

function setDataValue(data, address, value, type = 'auto') {
    if (!Array.isArray(data)) throw new Error('setDataValue: data is not an array');
    if (!Number.isInteger(address) || address < 0) {
        throw new Error('setDataValue: bad address ' + address);
    }

    const bytes = numberToBytesTyped(value, type);

    if (address + bytes.length > data.length) {
        throw new Error('setDataValue: out of bounds');
    }

    for (let i = 0; i < bytes.length; i++) {
        data[address + i] = bytes[i];
    }
}

/* =====================================================
 *  SAVE / FETCH
 * ===================================================== */

let saveInFlight = false;
let saveDesired  = false;

function saveData() {
    if (!isValidByteArray(data, MCU_LOCAL_LENGTH)) {
        xhrResponse('Invalid data array (length/range)');
        return;
    }

    const storedDataJson = localStorage.getItem("data");
    const storedData = storedDataJson ? JSON.parse(storedDataJson) : [];
    if (JSON.stringify(data) === JSON.stringify(storedData)) {
        return;
    }

    localStorage.setItem("data", JSON.stringify(data));
    stor_data = JSON.parse(JSON.stringify(data));

    saveDesired = true;
    if (saveInFlight) return;
    flushSave();
}

async function flushSave() {
    saveInFlight = true;

    try {
        while (saveDesired) {
            saveDesired = false;

            const snapshot   = [...data];
            const dataToSend = [...snapshot, MCU_TERMINATOR, Date.now()];

            if (!isValidServerPayload(dataToSend.map(String))) {
                xhrResponse('Invalid outgoing payload');
                continue;
            }

            const headers = { 'Content-Type': 'application/x-www-form-urlencoded' };
            if (typeof token !== 'undefined') headers['Authorization'] = token;

            try {
                const response = await fetch('remote.php', {
                    method: 'POST',
                    headers,
                    body: new URLSearchParams({
                        data: dataToSend.join(','),
                        lang: lang
                    })
                });

                if (response.status === 200) {
                    xhrResponse(localization.key['set.common.updated']);
                    await fetchData();
                } else if (response.status === 409) {
                    // Сервер отверг устаревший timestamp: у него более новые данные.
                    // Подтянем их и не будем перезаписывать.
                    await fetchData();
                    xhrResponse(localization.key['set.common.updated']);
                } else {
                    xhrResponse(localization.key['redlog.err']);
                }
            } catch (_) {
                xhrResponse(localization.key['redlog.err']);
            }
        }
    } finally {
        saveInFlight = false;
    }
}

async function fetchData() {
    try {
        const headers = { 'Content-Type': 'application/x-www-form-urlencoded' };
        if (typeof token !== 'undefined') headers['Authorization'] = token;

        const response = await fetch('remote.php', {
            method: 'POST',
            headers: headers,
            body: new URLSearchParams({ data: 'fetch', lang: lang })
        });

        if (response.status === 200) {
            const result = await response.text();
            const parts = result.split(',');

            if (!isValidServerPayload(parts)) {
                xhrResponse('Invalid server payload');
                return null;
            }

            // convert to numbers (except '~')
            const newData = parts.map(item => {
                const n = parseInt(item, 10);
                return isNaN(n) ? item : n;
            });

            const newDataForComparison = [...newData];
            newDataForComparison.splice(newDataForComparison.length - 2, 2);

            const currentDataForComparison = [...data];

            const arraysEqual = (arr1, arr2) => {
                if (arr1.length !== arr2.length) return false;
                for (let i = 0; i < arr1.length; i++) {
                    if (arr1[i] !== arr2[i]) return false;
                }
                return true;
            };

            const dataChanged = !arraysEqual(currentDataForComparison, newDataForComparison);

            data.length = 0;
            data.push(...newData);

            const date = new Date(data.at(-1));
            let date_res = date.toLocaleString(
                Cookies.get('timeformat') == '12' ? 'en-US' : 'ru-RU',
                {
                    year: 'numeric', month: '2-digit', day: '2-digit',
                    hour: '2-digit', minute: '2-digit', second: '2-digit',
                    hour12: Cookies.get('timeformat') == '12'
                }
            );
            date_res = date_res.replace(/-/g, '.').replace(', ', ' ');
            if (Cookies.get('timeformat') == '12') {
                date_res = date_res.replace(/\s?(AM|PM)/i, '$1');
            }

            $("#timestamp").html(
                `${localization.key['remote.last.change']} <br> ${date_res}`
            );

            data.splice(data.length - 2, 2);

            if (dataChanged) {
                xhrResponse(localization.key['remote.mcu.update.dialog']);
                localStorage.setItem("data", JSON.stringify(data));
                fillData();
                createChoices();
            }

            return newData;

        } else if (response.status === 403) {
            location.reload();
            return null;
        } else if (response.status === 204) {
            location.reload();
        } else {
            return null;
        }
    } catch (error) {
        return null;
    }
}

/* =====================================================
 *  INPUT UTILITIES
 * ===================================================== */

function iv(idx, intx, step) {
    let el = document.getElementById(idx);
    let value = intx == 1 ? parseInt(el.value) : parseFloat(el.value);
    value = isNaN(value) ? 0 : value;

    let min = parseFloat(el.getAttribute('min'));
    if (!isNaN(min)) {
        value < min ? value = min - step : '';
    }

    value += step;
    value = Math.round(value / step) * step;
    document.getElementById(idx).value = Math.round(value * 100) / 100;
}

function dv(idx, intx, step) {
    let el = document.getElementById(idx);
    let value = intx == 1 ? parseInt(el.value) : parseFloat(el.value);
    value = isNaN(value) ? 0 : value;

    let min = parseFloat(el.getAttribute('min'));
    if (!isNaN(min)) {
        value <= min ? value = min + step : '';
    } else {
        value < step ? value = step : '';
    }

    value -= step;
    value = Math.round(value / step) * step;
    document.getElementById(idx).value = Math.round(value * 100) / 100;
}

function eInputs(btnId) {
    const form = document.getElementById(btnId).closest('form');
    if (!form.checkValidity()) {
        form.reportValidity();
        return true;
    }
    return false;
}

function nolet(e) {
    let evt = e;
    if (evt.keyCode === 69) return false;
}

function vlim(input, lim) {
    let el = document.getElementById(input);
    let limit = lim;
    if (el.value > limit) el.value = limit;
    else if (el.value < 0) el.value = '0';
    return el.value;
}

function getFloat(b1, b2, b3, b4) {
    let data = [b1, b2, b3, b4];
    let buf = new ArrayBuffer(4);
    let view = new DataView(buf);
    data.forEach(function (b, i) { view.setUint8(i, b); });
    let num = view.getFloat32(0);
    num = Math.round(num * 100.0) / 100.0;
    return num;
}

function getInt(b1, b2) {
    let num = (b1 & 0xff) << 8 | (b2 & 0xff);
    return num;
}

function getUlong(b1, b2, b3, b4) {
    let num = (b1 & 0xff) << 24 | (b2 & 0xff) << 16 | (b3 & 0xff) << 8 | (b4 & 0xff);
    return num;
}

/* =====================================================
 *  BOOST
 * ===================================================== */
let map_custom;
let selected_map;

function limits_boost() {
    const mapType = $("#boost-map").prop("selectedIndex");
    const fields = $("#boost-target, #boost-start, #boost-threshold, #boost-g1-target, #boost-g2-target, #boost-g3-target, #boost-g4-target");

    fields.each(function () {
        if ($(this).val() < 0) $(this).val('0');
    });

    const maxValues = { 1: 0, 2: 150, 3: 200, 4: map_custom };
    const maxValue = maxValues[mapType];

    if (maxValue !== undefined) {
        fields.each(function () {
            if ($(this).val() > maxValue) $(this).val(maxValue.toString());
        });
    }
}

function filter() {
    const isEnabled = $("#boost-map").prop("selectedIndex") >= 2;
    $("#boost-map-filter").prop("disabled", !isEnabled);
    $("#filter-off").css("pointer-events", isEnabled ? "auto" : "none");
}

function checkInputs() {
    if (data[232] == 1) {
        $("#boost-target, #boost-duty").prop("disabled", true);
        $("#target-off, #duty-off").css("pointer-events", "none");
    } else {
        $("#boost-target, #boost-duty").prop("disabled", false);
        $("#target-off, #duty-off").css("pointer-events", "auto");
    }
    filter();
}

/* =====================================================
 *  PROTECTION
 * ===================================================== */
function limits_protection() {
    const input = $("#boost-limit");
    if (input.value < 0) input.value = '0';

    const limits = { 0: 0, 1: 150, 2: 200, 3: map_custom };

    if (input.value > limits[map_limit]) {
        input.value = limits[map_limit];
    }
}

/* =====================================================
 *  FAN
 * ===================================================== */
function limits_fan() {
    if ($("#fan-target").value < 0) $("#fan-target").value = '0';

    if (document.getElementById("fan-mode-sel").selectedIndex == "1") {
        if ($("#fan-target").value > 125) $("#fan-target").value = '125';
    } else if (document.getElementById("fan-mode-sel").selectedIndex == "2") {
        if ($("#fan-target").value > 105) $("#fan-target").value = '105';
    }
}

/* =====================================================
 *  LOGIC
 * ===================================================== */
function step_pg0a() {
    let inp = document.getElementById("pg0-a-var").selectedIndex;
    if (inp == "12" || inp == "13" || inp == "16" || inp == "19") return 0.01;
    return 1;
}

function step_pg0b() {
    let inp = document.getElementById("pg0-b-var").selectedIndex;
    if (inp == "12" || inp == "13" || inp == "16" || inp == "19") return 0.01;
    return 1;
}

function step_pg1a() {
    let inp = document.getElementById("pg1-a-var").selectedIndex;
    if (inp == "12" || inp == "13" || inp == "16" || inp == "19") return 0.01;
    return 1;
}

function limits_logic() {
    const paramConfig = {
        '1':  { max: 0, unit: '', step: 1, hidden: true },
        '2':  { max: 125, unit: '°C', step: 1 },
        '3':  { max: 125, unit: '°C', step: 1 },
        '4':  { max: 125, unit: '°C', step: 1 },
        '5':  { max: 125, unit: '°C', step: 1 },
        '6':  { max: 125, unit: '°C', step: 1 },
        '7':  { max: 125, unit: '°C', step: 1 },
        '8':  { max: 90, unit: '%', step: 1 },
        '9':  { max: 250, unit: 'km/h', step: 1 },
        '10': { max: 65534, unit: '', step: 1, hidden: true },
        '11': { max: 1, unit: '', step: 1, hidden: true },
        '12': { max: () => eop_sensor == 1 ? 7 : 10, unit: 'bar', step: 0.01 },
        '13': { max: () => fp_sensor == 1 ? 7 : 10, unit: 'bar', step: 0.01 },
        '14': { max: () => {
            switch (map_limit) {
                case 0: return 0;
                case 1: return 250;
                case 2: return 300;
                case 3: return map_custom;
                default: return 0;
            }
        }, unit: 'kPa', step: 1 },
        '15': { max: 10000, unit: '', step: 1, hidden: true },
        '16': { max: 20, unit: 'V', step: 0.01 },
        '17': { max: 1000, unit: '°C', step: 1 },
        '18': { max: 1, unit: '', step: 1, hidden: true },
        '19': { max: 25, unit: 'A/F', step: 0.01 },
        '20': { max: 65534, unit: 'sec', step: 1 },
        '21': { max: 1092, unit: 'h', step: 1 },
        '22': { max: 1, unit: '', step: 1, hidden: true },
        '23': { max: 1, unit: '', step: 1, hidden: true }
    };

    const fields = ['pg0-a', 'pg0-b', 'pg1-a', 'pg1-b'];

    fields.forEach(field => {
        const select = $(`#${field}-var`)[0];
        const selectedIndex = select.selectedIndex.toString();
        const config = paramConfig[selectedIndex];
        if (!config) return;

        const valueField = $(`#${field}-value`);
        const labelField = $(`#${field}-label`);

        const maxValue = typeof config.max === 'function' ? config.max() : config.max;

        if (valueField.val() < 0) valueField.val('0');
        if (valueField.val() > maxValue) valueField.val(maxValue.toString());

        valueField.attr('step', config.step.toString());

        if (config.hidden) {
            labelField.attr('hidden', '');
        } else {
            labelField.removeAttr('hidden');
            labelField.html(config.unit === '°C' ? ' &#8451' : ` ${config.unit}`);
        }
    });
}

function step_pg1b() {
    let inp = document.getElementById("pg1-b-var").selectedIndex;
    if (inp == "12" || inp == "13" || inp == "16" || inp == "19") return 0.01;
    return 1;
}

/* =====================================================
 *  INPUTS
 * ===================================================== */
let toHex = function (str) {
    let hex = Number(str).toString(16);
    if (hex.length < 2) hex = "0" + hex;
    return hex.toUpperCase();
};

function checkAuxCollisions() {
    const getValues = (prefix, count) =>
        Array.from({ length: count }, (_, i) => $(`#${prefix}${i}-sel`).value);

    const aux = getValues('aux', 5).filter(v => v !== '0');
    const ds = getValues('ds', 5);

    if (new Set(aux).size !== aux.length) {
        xhrResponse(localization.key['inputs.aux.collide']);
        return true;
    }
    if (aux.some(a => ds.includes(a))) {
        xhrResponse(localization.key['inputs.auxds.collide']);
        return true;
    }
    return false;
}

function checkDSCollisions() {
    const getValues = (prefix, count) =>
        Array.from({ length: count }, (_, i) => $(`#${prefix}${i}-sel`).value);

    const ds = getValues('ds', 5).filter(v => v !== '0');
    const aux = getValues('aux', 5);

    if (new Set(ds).size !== ds.length) {
        xhrResponse(localization.key['inputs.ds.collide']);
        return true;
    }
    if (ds.some(d => aux.includes(d))) {
        xhrResponse(localization.key['inputs.dsaux.collide']);
        return true;
    }
    return false;
}

function checkCollisions(prefix, count) {
    let colCount = 0;
    const values = [];
    for (let i = 0; i < count; i++) {
        const select = document.getElementById(`${prefix}${i}-sel`);
        values.push(select ? select.value : null);
    }

    for (let i = 0; i < count; i++) {
        const currentSelect = document.getElementById(`${prefix}${i}-sel`);
        if (!currentSelect) continue;

        const currentValue = values[i];
        const parentDiv = currentSelect.closest('.choices__inner.choices__settings__text');
        if (!parentDiv) continue;

        if (currentValue === '' || currentValue === '0' || currentValue === null) {
            parentDiv.classList.remove('collision');
            continue;
        }

        const hasCollision = values.some((value, index) =>
            index !== i && value === currentValue &&
            value !== '' && value !== '0' && value !== null);

        const otherPrefix = prefix === 'aux' ? 'ds' : 'aux';
        const hasOtherCollision = Array.from({ length: count }, (_, j) => {
            const otherSelect = document.getElementById(`${otherPrefix}${j}-sel`);
            if (!otherSelect) return false;
            const otherValue = otherSelect.value;
            return otherValue !== '' && otherValue !== '0' && otherValue === currentValue;
        }).some(Boolean);

        if (hasCollision || hasOtherCollision) {
            parentDiv.classList.add('collision');
            $("#aux-set-btn").prop("disabled", true);
            $("#ds-set-btn").prop("disabled", true);
        } else {
            parentDiv.classList.remove('collision');
            $("#aux-set-btn").prop("disabled", false);
            $("#ds-set-btn").prop("disabled", false);
        }
    }
}

function checkAllCollisions() {
    checkCollisions('aux', 5);
    checkCollisions('ds', 5);
}

function formatDSAddress(startIndex) {
    return Array.from({ length: 8 }, (_, i) => toHex(data[startIndex + i])).join(':');
}

/* =====================================================
 *  CALIBRATION
 * ===================================================== */
let a0_pullup, a1_pullup;

function pF(b1, b2) {
    return Math.round((5 - (getInt(b1, b2) * 0.0048828)) * 100.0) / 100.0;
}

function mF(b1, b2) {
    return Math.round((getInt(b1, b2) * 0.0048828) * 100.0) / 100.0;
}

function pT(b1, b2) {
    let num = Math.round((5 - (getInt(b1, b2) * 0.0048828)) * 4700 / (5 - (5 - (getInt(b1, b2) * 0.0048828))));
    return !isFinite(num) ? 0 : num;
}

function select_aux() {
    const auxIn = $("#aux-in").value;

    if (auxIn == "0" || auxIn == "1") {
        const baseIndex = auxIn * 30;
        const isPullup = (auxIn == "0" && a0_pullup == 1) || (auxIn == "1" && a1_pullup == 1);
        $("#nom").html(isPullup ? "Ohm" : "Volt");

        const processFunc = isPullup ? pT : pF;
        for (let i = 0; i < 10; i++) {
            const dataIndex = baseIndex + i * 2;
            $("#aux-v" + i).value = processFunc(data[dataIndex + 1], data[dataIndex]);
        }
    } else {
        $("#nom").html("Volt");
        const baseIndex = (auxIn == "2") ? 60 : (auxIn == "3") ? 90 : 120;
        for (let i = 0; i < 10; i++) {
            const dataIndex = baseIndex + i * 2;
            $("#aux-v" + i).value = pF(data[dataIndex + 1], data[dataIndex]);
        }
    }

    switch (auxIn) {
        case "0": for (let i = 0; i < 10; i++) $("#aux-t" + i).value = data[20 + i]; break;
        case "1": for (let i = 0; i < 10; i++) $("#aux-t" + i).value = data[50 + i]; break;
        case "2": for (let i = 0; i < 10; i++) $("#aux-t" + i).value = data[80 + i]; break;
        case "3": for (let i = 0; i < 10; i++) $("#aux-t" + i).value = data[110 + i]; break;
        case "4": for (let i = 0; i < 10; i++) $("#aux-t" + i).value = data[140 + i]; break;
    }
}

function limits_calibration() {
    switch ($("#aux-in").value) {
        case "0":
            if (a0_pullup != 1) return 5;
            return 99000;
        case "1":
            if (a1_pullup != 1) return 5;
            return 99000;
        default:
            return 5;
    }
}

function step_def() {
    $('[id^="aux-v"]').each(function () {
        $(this).attr("step", "0.01").attr("min", "0.01").attr("max", "5");
    });
}

function step_pullup() {
    $('[id^="aux-v"]').each(function () {
        $(this).attr("step", "1").attr("min", "1").attr("max", "99000");
    });
}

function step() {
    switch ($("#aux-in").value) {
        case "0":
            if (a0_pullup != 1) { step_def(); return 0.01; }
            step_pullup(); return 1;
        case "1":
            if (a1_pullup != 1) { step_def(); return 0.01; }
            step_pullup(); return 1;
        default:
            step_def();
            return 0.01;
    }
}

/* =====================================================
 *  OTHER
 * ===================================================== */
function checkPIM() {
    if ($("#pim-mode").val() > 2) {
        $("#pim-off").css("pointer-events", "none");
        $("#pim-out").prop("disabled", true);
    } else {
        $("#pim-off").css("pointer-events", "auto");
        $("#pim-out").prop("disabled", false);
    }
}

function fillData() {
// === BOOST
    $("#boost-target").value = getInt(data[227], data[226]) - 101;
    $("#boost-start").value = data[229] * 5;
    $("#boost-threshold").value = data[330] * 5;
    $("#boost-duty").value = data[228];
    $("#boost-dc-corr").value = data[295];
    $("#boost-rpm-start").value = data[245] * 50;
    $("#boost-rpm-end").value = data[294] * 50;
    $("#boost-rpm-duty").value = data[246];

    data[225] != 1 ? $("#boost-status").checked = "true" : $("#boost-status").checked = "";
    map_custom = (getInt(data[307], data[306])) - 100;
    $("#boost-map-filter").val(data[314]);

    switch (data[264]) {
        case 0: $("#boost-map").value = 0; break;
        case 1: $("#boost-map").value = 1; break;
        case 2: $("#boost-map").value = 2; break;
        case 3: $("#boost-map").value = 3; break;
    }
    selected_map = document.getElementById("boost-map").selectedIndex;
    $("#boost-freq").val(data[323] == 0 ? "0" : "1");

    $("#pid-kp").value = getFloat(data[216], data[215], data[214], data[213]);
    $("#pid-ki").value = getFloat(data[220], data[219], data[218], data[217]);
    $("#pid-kd").value = getFloat(data[224], data[223], data[222], data[221]);
    $("#pid-freq").value = data[212];
    $("#pid-deadband").value = data[329];

    switch (data[211]) {
        case 0: $("#pid-mode").value = 0; break;
        case 1: $("#pid-mode").value = 1; break;
    }

    data[232] == 1 ? $("#boost-gear-status").checked = "true" : $("#boost-gear-status").checked = "";

    $("#boost-g1-target").value = getInt(data[234], data[233]) - 101;
    $("#boost-g2-target").value = getInt(data[236], data[235]) - 101;
    $("#boost-g3-target").value = getInt(data[238], data[237]) - 101;
    $("#boost-g4-target").value = getInt(data[240], data[239]) - 101;

    $("#boost-g1-duty").value = data[241];
    $("#boost-g2-duty").value = data[242];
    $("#boost-g3-duty").value = data[243];
    $("#boost-g4-duty").value = data[244];

    limits_boost();
    checkInputs();

// === PROTECTION
    $("#max-ect").value = data[249] - 40;
    $("#max-eot").value = data[247] - 40;
    $("#max-egt").value = data[252] * 5;
    $("#max-iat").value = data[251] - 40;
    $("#max-atf").value = data[296] - 40;
    $("#max-aat").value = data[327] - 40;
    $("#max-ext").value = data[328] - 40;
    $("#min-ect").value = data[250] - 40;
    $("#min-eot").value = data[248] - 40;
    $("#min-atf").value = data[297] - 40;
    $("#boost-limit").value = getInt(data[231], data[230]) - 101;
    $("#afr").value = data[256] / 10;

    data[253] == 1 ? $("#low-eop").checked = "true" : $("#low-eop").checked = "";
    data[255] == 1 ? $("#low-fp").checked = "true" : $("#low-fp").checked = "";
    data[254] == 1 ? $("#knock").checked = "true" : $("#knock").checked = "";

    switch (data[264]) {
        case 0: map_limit = 0; break;
        case 1: map_limit = 1; break;
        case 2: map_limit = 2; break;
        case 3: map_limit = 3; break;
    }
    map_custom = (getInt(data[307], data[306])) - 100;

// === FAN
    switch (data[155]) {
        case 0: $("#fan-mode-sel").value = 0; break;
        case 1: $("#fan-mode-sel").value = 1; break;
    }
    data[208] == 1 ? $("#fan-ac").checked = "true" : $("#fan-ac").checked = "";
    data[207] == 1 ? $("#fan-engine").checked = "true" : $("#fan-engine").checked = "";
    data[293] == 1 ? $("#fan-test").checked = "true" : $("#fan-test").checked = "";
    $("#fan-target").value = data[203] - 40;

    switch (data[202]) {
        case 0: $("#fan-src-sel").value = 0; break;
        case 1: $("#fan-src-sel").value = 1; break;
        case 2: $("#fan-src-sel").value = 2; break;
        case 3: $("#fan-src-sel").value = 3; break;
        case 4: $("#fan-src-sel").value = 4; break;
        case 5: $("#fan-src-sel").value = 5; break;
        case 6: $("#fan-src-sel").value = 6; break;
    }

    data[205] == 1 ? $("#pwm-invert").checked = "true" : $("#pwm-invert").checked = "";
    $("#pwm-spd").value = data[209];
    $("#pwm-width").value = data[206];
    $("#pwm-min-dc").value = Math.round(100 - (data[299] * 100 / 255));
    switch (data[201]) {
        case 0: $("#pwm-freq").value = 0; break;
        case 1: $("#pwm-freq").value = 1; break;
        case 2: $("#pwm-freq").value = 2; break;
    }

    $("#sw-off-delay").value = data[204];
    $("#hyst").value = data[210];
    limits_fan();

// === LOGIC
    let value = null;
    switch (data[264]) {
        case 0: map_limit = 0; break;
        case 1: map_limit = 1; break;
        case 2: map_limit = 2; break;
        case 3: map_limit = 3; break;
    }
    map_custom = getInt(data[307], data[306]);

    eop_sensor = data[318];
    fp_sensor = data[319];

    data[267] == 1 ? $("#pg0-status").checked = "true" : $("#pg0-status").checked = "";
    data[321] == 1 ? $("#pg0-engine-run").checked = "true" : $("#pg0-engine-run").checked = "";
    data[312] == 1 ? $("#pg0-loop").checked = "true" : $("#pg0-loop").checked = "";
    $("#pg0-off-delay").value = data[278];
    $("#pg0-on-delay").value = data[279] / 25;
    $("#pg0-on-limit").value = data[310] / 25;

    switch (data[272]) {
        case 0: $("#pg0-a-operand").value = 0; break;
        case 1: $("#pg0-a-operand").value = 1; break;
    }
    switch (data[273]) {
        case 0: $("#pg0-b-operand").value = 0; break;
        case 1: $("#pg0-b-operand").value = 1; break;
    }
    switch (data[271]) {
        case 0: $("#pg0-func").value = 0; break;
        case 1: $("#pg0-func").value = 1; break;
        case 2: $("#pg0-func").value = 2; break;
    }

    value = parseInt(data[269], 10);
    if (!isNaN(value) && value >= 0 && value <= 22) {
        $("#pg0-a-var").value = value.toString();
    }
    value = parseInt(data[270], 10);
    if (!isNaN(value) && value >= 0 && value <= 22) {
        $("#pg0-b-var").value = value.toString();
    }

    let pg0_a_var = parseInt($("#pg0-a-var").value);
    let pg0_b_var = parseInt($("#pg0-b-var").value);
    let pg0_a_value = parseInt(getInt(data[275], data[274]));
    let pg0_b_value = parseInt(getInt(data[277], data[276]));

    if (pg0_a_var > 0 && pg0_a_var <= 6) pg0_a_value = pg0_a_value - 40;
    else if (pg0_a_var == 7) pg0_a_value = Math.round(mapRange(pg0_a_value, 0, 147, 0, 100));
    else if (pg0_a_var == 20) pg0_a_value = pg0_a_value / 60;
    else if (pg0_a_var == 11 || pg0_a_var == 12 || pg0_a_var == 15 || pg0_a_var == 18) pg0_a_value = parseFloat(pg0_a_value / 100);
    $("#pg0-a-value").value = pg0_a_value;

    if (pg0_b_var > 0 && pg0_b_var <= 6) pg0_b_value = pg0_b_value - 40;
    else if (pg0_b_var == 7) pg0_b_value = Math.round(mapRange(pg0_b_value, 0, 147, 0, 100));
    else if (pg0_b_var == 20) pg0_b_value = pg0_b_value / 60;
    else if (pg0_b_var == 11 || pg0_b_var == 12 || pg0_b_var == 15 || pg0_b_var == 18) pg0_b_value = parseFloat(pg0_b_value / 100);
    $("#pg0-b-value").value = pg0_b_value;

    data[268] == 1 ? $("#pg1-status").checked = "true" : $("#pg1-status").checked = "";
    data[322] == 1 ? $("#pg1-engine-run").checked = "true" : $("#pg1-engine-run").checked = "";
    data[313] == 1 ? $("#pg1-loop").checked = "true" : $("#pg1-loop").checked = "";
    $("#pg1-off-delay").value = data[289];
    $("#pg1-on-delay").value = data[290] / 25;
    $("#pg1-on-limit").value = data[311] / 25;

    switch (data[283]) {
        case 0: $("#pg1-a-operand").value = 0; break;
        case 1: $("#pg1-a-operand").value = 1; break;
    }
    switch (data[284]) {
        case 0: $("#pg1-b-operand").value = 0; break;
        case 1: $("#pg1-b-operand").value = 1; break;
    }
    switch (data[282]) {
        case 0: $("#pg1-func").value = 0; break;
        case 1: $("#pg1-func").value = 1; break;
        case 2: $("#pg1-func").value = 2; break;
    }

    value = parseInt(data[280], 10);
    if (!isNaN(value) && value >= 0 && value <= 22) $("#pg1-a-var").value = value.toString();
    value = parseInt(data[281], 10);
    if (!isNaN(value) && value >= 0 && value <= 22) $("#pg1-b-var").value = value.toString();

    let pg1_a_var = parseInt($("#pg1-a-var").value);
    let pg1_b_var = parseInt($("#pg1-b-var").value);
    let pg1_a_value = parseInt(getInt(data[286], data[285]));
    let pg1_b_value = parseInt(getInt(data[288], data[287]));

    if (pg1_a_var > 0 && pg1_a_var <= 6) pg1_a_value = pg1_a_value - 40;
    else if (pg1_a_var == 7) pg1_a_value = Math.round(mapRange(pg1_a_value, 0, 147, 0, 100));
    else if (pg1_a_var == 20) pg1_a_value = pg1_a_value / 60;
    else if (pg1_a_var == 11 || pg1_a_var == 12 || pg1_a_var == 15 || pg1_a_var == 18) pg1_a_value = parseFloat(pg1_a_value / 100);
    $("#pg1-a-value").value = pg1_a_value;

    if (pg1_b_var > 0 && pg1_b_var <= 6) pg1_b_value = pg1_b_value - 40;
    else if (pg1_b_var == 7) pg1_b_value = Math.round(mapRange(pg1_b_value, 0, 147, 0, 100));
    else if (pg1_b_var == 20) pg1_b_value = pg1_b_value / 60;
    else if (pg1_b_var == 11 || pg1_b_var == 12 || pg1_b_var == 15 || pg1_b_var == 18) pg1_b_value = parseFloat(pg1_b_value / 100);
    $("#pg1-b-value").value = pg1_b_value;
    limits_logic();

// === INPUTS
    const nodata = localization.key['ds.no.data'];
    const error = localization.key['ds.error'];
    const ok = localization.key['ds.ok'];
    const fake = localization.key['ds.fake'];

    $("#bsx-mode").value = data[266];
    data[308] == 1 ? $("#bs1-pullup").checked = "true" : $("#bs1-pullup").checked = "";
    data[309] == 1 ? $("#bs2-pullup").checked = "true" : $("#bs2-pullup").checked = "";

    switch (data[265]) {
        case 20: $("#vfd-mode").value = 0; break;
        case 30: $("#vfd-mode").value = 1; break;
        case 0:  $("#vfd-mode").value = 2; break;
        case 21: $("#vfd-mode").value = 3; break;
        case 31: $("#vfd-mode").value = 4; break;
        case 70: $("#vfd-mode").value = 5; break;
    }

    $("#aux0-sel").value = data[150];
    $("#aux1-sel").value = data[151];
    $("#aux2-sel").value = data[152];
    $("#aux3-sel").value = data[153];
    $("#aux4-sel").value = data[154];

    data[291] == 1 ? $("#a0-pullup").checked = "true" : $("#a0-pullup").checked = "";
    data[292] == 1 ? $("#a1-pullup").checked = "true" : $("#a1-pullup").checked = "";

    $("#ds0-sel").value = data[156];
    $("#ds1-sel").value = data[157];
    $("#ds2-sel").value = data[158];
    $("#ds3-sel").value = data[159];
    $("#ds4-sel").value = data[160];

    $("#ds0-addr").html(formatDSAddress(161));
    $("#ds1-addr").html(formatDSAddress(169));
    $("#ds2-addr").html(formatDSAddress(177));
    $("#ds3-addr").html(formatDSAddress(185));
    $("#ds4-addr").html(formatDSAddress(193));

    const statusConfig = {
        0: { text: nodata, action: (el) => el.removeAttr("style") },
        1: { text: error, color: "red" },
        239: { text: ok, color: "green" },
        default: { text: fake, color: "#ff6600" }
    };

    [398, 399, 400, 401, 402].forEach((dataIndex, index) => {
        const value = data[dataIndex];
        const selector = `#ds${index}`;
        const config = statusConfig[value] || statusConfig.default;

        $(`${selector}-stat`).html(config.text);

        if (config.action) {
            $(`${selector}-color`).each((i, el) => config.action($(el)));
        } else {
            $(`${selector}-color`).attr("style", `color:${config.color}`);
        }
    });

    $("#ds-online").html(data[403]);

    for (let i = 0; i < 5; i++) {
        const auxSelect = document.getElementById(`aux${i}-sel`);
        auxSelect.addEventListener('change', checkAllCollisions);
    }
    for (let i = 0; i < 5; i++) {
        const dsSelect = document.getElementById(`ds${i}-sel`);
        dsSelect.addEventListener('change', checkAllCollisions);
    }
    checkAllCollisions();

// === CALIBRATION
    a0_pullup = data[291];
    a1_pullup = data[292];

    $("#afr-0v").value = getInt(data[258], data[257]) / 100;
    $("#afr-5v").value = getInt(data[260], data[259]) / 100;
    $("#afr-filter").value = data[317];

    data[318] == 1 ? $("#eop-sel").value = "1" : $("#eop-sel").value = "0";
    data[319] == 1 ? $("#fp-sel").value = "1" : $("#fp-sel").value = "0";

    $("#map-0v").value = mF(data[301], data[300]);
    $("#map-1v").value = mF(data[303], data[302]);
    $("#map-0p").value = getInt(data[305], data[304]);
    $("#map-1p").value = getInt(data[307], data[306]);

    select_aux();
    step();

// === OTHER
    $("#vlt-corr").value = data[263] / 100;
    $("#spd-mult").value = data[325] / 100;
    $("#rpm-mult").value = data[326] / 100;
    $("#pim-mode").value = data[262];
    $("#pim-out").value = data[261] - 100;
    $("#mh-mode").value = data[320];
    let mhh = getUlong(data[397], data[396], data[395], data[394]);
    $("#mr-hist").html(parseInt(mhh / 60) + "h");
    checkPIM();
}

/* =====================================================
 *  SETTERS
 * ===================================================== */

function boolToMcuByte(checked) {
    return checked ? 1 : 0;
}

function boostSetBtn() {
    if (eInputs('boost-set-btn')) return;

    setDataValue(data, 225, $("#boost-status").checked ? 0 : 1, 'uint8');
    setDataValue(data, 226, parseInt($("#boost-target").val() || 0, 10) + 101, 'uint16');
    setDataValue(data, 228, parseInt($("#boost-duty").val() || 0, 10), 'uint8');

    const boostStartVal = parseInt($("#boost-start").val() || 0, 10);
    const boostThresholdVal = parseInt($("#boost-threshold").val() || 0, 10);
    if (boostThresholdVal <= boostStartVal) {
        setDataValue(data, 229, Math.round(boostStartVal / 5), 'uint8');
        setDataValue(data, 330, Math.round(boostThresholdVal / 5), 'uint8');
    } else fillData();

    setDataValue(data, 295, parseInt($("#boost-dc-corr").val() || 0, 10), 'uint8');

    const boostRpmStart = Math.round((parseInt($("#boost-rpm-start").val() || 0, 10) / 50));
    const boostRpmEnd = Math.round((parseInt($("#boost-rpm-end").val() || 0, 10) / 50));
    if (boostRpmStart < boostRpmEnd) {
        setDataValue(data, 245, boostRpmStart, 'uint8');
        setDataValue(data, 294, boostRpmEnd, 'uint8');
        setDataValue(data, 246, parseInt($("#boost-rpm-duty").val() || 0, 10), 'uint8');
    } else fillData();

    setDataValue(data, 264, parseInt($("#boost-map").val() || 0, 10), 'uint8');
    setDataValue(data, 314, parseInt($("#boost-map-filter").val() || 0, 10), 'uint8');
    setDataValue(data, 323, parseInt($("#boost-freq").val() || 0, 10), 'uint8');

    saveData();
}

function pidSetBtn() {
    if (eInputs('pid-set-btn')) return;

    setDataValue(data, 211, parseInt($("#pid-mode").val() || 0, 10), 'uint8');
    setDataValue(data, 212, parseInt($("#pid-freq").val() || 0, 10), 'uint8');
    setDataValue(data, 329, parseInt($("#pid-deadband").val() || 0, 10), 'uint8');

    setDataValue(data, 213, parseFloat($("#pid-kp").val() || 0), 'float32');
    setDataValue(data, 217, parseFloat($("#pid-ki").val() || 0), 'float32');
    setDataValue(data, 221, parseFloat($("#pid-kd").val() || 0), 'float32');

    saveData();
}

function boostGearSetBtn() {
    if (eInputs('boost-gear-set-btn')) return;

    setDataValue(data, 232, boolToMcuByte($("#boost-gear-status").prop("checked")), 'uint8');

    setDataValue(data, 233, parseInt($("#boost-g1-target").val() || 0, 10) + 101, 'uint16');
    setDataValue(data, 235, parseInt($("#boost-g2-target").val() || 0, 10) + 101, 'uint16');
    setDataValue(data, 237, parseInt($("#boost-g3-target").val() || 0, 10) + 101, 'uint16');
    setDataValue(data, 239, parseInt($("#boost-g4-target").val() || 0, 10) + 101, 'uint16');

    setDataValue(data, 241, parseInt($("#boost-g1-duty").val() || 0, 10), 'uint8');
    setDataValue(data, 242, parseInt($("#boost-g2-duty").val() || 0, 10), 'uint8');
    setDataValue(data, 243, parseInt($("#boost-g3-duty").val() || 0, 10), 'uint8');
    setDataValue(data, 244, parseInt($("#boost-g4-duty").val() || 0, 10), 'uint8');

    saveData();
}

function protectionSetBtn() {
    if (eInputs('p-set-btn')) return;

    setDataValue(data, 249, parseInt($("#max-ect").val() || 0, 10) + 40, 'uint8');
    setDataValue(data, 247, parseInt($("#max-eot").val() || 0, 10) + 40, 'uint8');
    setDataValue(data, 252, Math.round((parseFloat($("#max-egt").val() || 0) / 5)), 'uint8');

    setDataValue(data, 251, parseInt($("#max-iat").val() || 0, 10) + 40, 'uint8');
    setDataValue(data, 296, parseInt($("#max-atf").val() || 0, 10) + 40, 'uint8');
    setDataValue(data, 327, parseInt($("#max-aat").val() || 0, 10) + 40, 'uint8');
    setDataValue(data, 328, parseInt($("#max-ext").val() || 0, 10) + 40, 'uint8');

    setDataValue(data, 250, parseInt($("#min-ect").val() || 0, 10) + 40, 'uint8');
    setDataValue(data, 248, parseInt($("#min-eot").val() || 0, 10) + 40, 'uint8');
    setDataValue(data, 297, parseInt($("#min-atf").val() || 0, 10) + 40, 'uint8');

    setDataValue(data, 230, parseInt($("#boost-limit").val() || 0, 10) + 101, 'uint16');
    setDataValue(data, 256, Math.round(parseFloat($("#afr").val() || 0) * 10), 'uint8');

    setDataValue(data, 253, $("#low-eop").prop("checked") ? 1 : 0, 'uint8');
    setDataValue(data, 255, $("#low-fp").prop("checked") ? 1 : 0, 'uint8');
    setDataValue(data, 254, $("#knock").prop("checked") ? 1 : 0, 'uint8');

    saveData();
}

function fanActSetBtn() {
    if (eInputs('fan-set-btn')) return;

    setDataValue(data, 155, parseInt($("#fan-mode-sel").val() || 0, 10), 'uint8');
    setDataValue(data, 202, parseInt($("#fan-src-sel").val() || 0, 10), 'uint8');
    setDataValue(data, 203, parseInt($("#fan-target").val() || 0, 10) + 40, 'uint8');

    setDataValue(data, 208, $("#fan-ac").prop("checked") ? 1 : 0, 'uint8');
    setDataValue(data, 207, $("#fan-engine").prop("checked") ? 1 : 0, 'uint8');
    setDataValue(data, 293, $("#fan-test").prop("checked") ? 1 : 0, 'uint8');

    saveData();
}

function fanPwmSetBtn() {
    if (eInputs('pwm-set-btn')) return;

    setDataValue(data, 205, $("#pwm-invert").prop("checked") ? 1 : 0, 'uint8');
    setDataValue(data, 209, parseInt($("#pwm-spd").val() || 0, 10), 'uint8');
    setDataValue(data, 206, parseInt($("#pwm-width").val() || 0, 10), 'uint8');

    const displayedMinDc = parseInt($("#pwm-min-dc").val() || 0, 10);
    setDataValue(data, 299, Math.round((100 - displayedMinDc) * 255 / 100), 'uint8');

    setDataValue(data, 201, parseInt($("#pwm-freq").val() || 0, 10), 'uint8');

    saveData();
}

function fanSwSetBtn() {
    if (eInputs('sw-set-btn')) return;

    setDataValue(data, 210, parseInt($("#hyst").val() || 0, 10), 'uint8');
    setDataValue(data, 204, parseInt($("#sw-off-delay").val() || 0, 10), 'uint8');

    saveData();
}

function auxSetBtn() {
    if (checkAuxCollisions()) return;

    setDataValue(data, 150, parseInt($("#aux0-sel").val() || 0, 10), 'uint8');
    setDataValue(data, 151, parseInt($("#aux1-sel").val() || 0, 10), 'uint8');
    setDataValue(data, 152, parseInt($("#aux2-sel").val() || 0, 10), 'uint8');
    setDataValue(data, 153, parseInt($("#aux3-sel").val() || 0, 10), 'uint8');
    setDataValue(data, 154, parseInt($("#aux4-sel").val() || 0, 10), 'uint8');

    setDataValue(data, 291, $("#a0-pullup").prop("checked") ? 1 : 0, 'uint8');
    setDataValue(data, 292, $("#a1-pullup").prop("checked") ? 1 : 0, 'uint8');
    saveData();
}

function bsxSetBtn() {
    setDataValue(data, 266, parseInt($("#bsx-mode").val() || 0, 10), 'uint8');
    setDataValue(data, 308, $("#bs1-pullup").prop("checked") ? 1 : 0, 'uint8');
    setDataValue(data, 309, $("#bs2-pullup").prop("checked") ? 1 : 0, 'uint8');
    saveData();
}

function vfdSetBtn() {
    const mapping = { 0: 20, 1: 30, 2: 0, 3: 21, 4: 31, 5: 70 };
    const value = mapping[parseInt($("#vfd-mode").val())] || 0;
    setDataValue(data, 265, value, 'uint8');
    saveData();
}

function dsSetBtn() {
    if (checkDSCollisions()) return;

    setDataValue(data, 156, parseInt($("#ds0-sel").val() || 0, 10), 'uint8');
    setDataValue(data, 157, parseInt($("#ds1-sel").val() || 0, 10), 'uint8');
    setDataValue(data, 158, parseInt($("#ds2-sel").val() || 0, 10), 'uint8');
    setDataValue(data, 159, parseInt($("#ds3-sel").val() || 0, 10), 'uint8');
    setDataValue(data, 160, parseInt($("#ds4-sel").val() || 0, 10), 'uint8');

    saveData();
}

function psSetBtn() {
    setDataValue(data, 318, parseInt($("#eop-sel").val() || 0, 10), 'uint8');
    setDataValue(data, 319, parseInt($("#fp-sel").val() || 0, 10), 'uint8');
    saveData();

    eop_sensor = data[318];
    fp_sensor = data[319];
    limits_logic();
}

function afrSetBtn() {
    if (eInputs('afr-set-btn')) return;

    setDataValue(data, 257, Math.round(parseFloat($("#afr-0v").val() || 0) * 100), 'uint16');
    setDataValue(data, 259, Math.round(parseFloat($("#afr-5v").val() || 0) * 100), 'uint16');
    setDataValue(data, 317, parseInt($("#afr-filter").val() || 0, 10), 'uint8');

    saveData();
}

function mapSetBtn() {
    if (eInputs('map-set-btn')) return;

    const v0 = parseFloat($("#map-0v").val() || 0);
    const v1 = parseFloat($("#map-1v").val() || 0);
    const factor = 0.0048828;

    setDataValue(data, 300, Math.round(v0 / factor), 'uint16');
    setDataValue(data, 302, Math.round(v1 / factor), 'uint16');
    setDataValue(data, 304, parseInt($("#map-0p").val() || 0, 10), 'uint16');
    setDataValue(data, 306, parseInt($("#map-1p").val() || 0, 10), 'uint16');

    saveData();
}

function otherSetBtn() {
    if (eInputs('misc-set-btn')) return;

    setDataValue(data, 263, Math.round(parseFloat($("#vlt-corr").val() || 0) * 100), 'uint8');
    setDataValue(data, 325, Math.round(parseFloat($("#spd-mult").val() || 1) * 100), 'uint8');
    setDataValue(data, 326, Math.round(parseFloat($("#rpm-mult").val() || 1) * 100), 'uint8');
    setDataValue(data, 262, parseInt($("#pim-mode").val() || 0, 10), 'uint8');
    setDataValue(data, 261, parseInt($("#pim-out").val() || 0, 10) + 100, 'uint8');
    setDataValue(data, 320, parseInt($("#mh-mode").val()), 'uint8');

    saveData();
}

const calcVal = (pgVar, selector) => {
    const varInt = parseInt(pgVar), value = $(selector).val();
    return varInt > 0 && varInt <= 6 ? parseInt(value) + 40 :
        varInt === 7 ? mapRange(parseInt(value), 0, 100, 0, 147) :
        varInt === 20 ? parseInt(value) * 60 :
        [11, 12, 15, 18].includes(varInt) ? Math.round(parseFloat(value) * 100) : value;
};

function pg0SetBtn() {
    if (eInputs('pg0-set-btn')) return;

    let pg0_a_var = $("#pg0-a-var").val();
    let pg0_b_var = $("#pg0-b-var").val();
    let pg0_a_value = calcVal(pg0_a_var, "#pg0-a-value");
    let pg0_b_value = calcVal(pg0_b_var, "#pg0-b-value");

    setDataValue(data, 267, $("#pg0-status").prop("checked") ? 1 : 0, 'uint8');
    setDataValue(data, 269, parseInt($("#pg0-a-var").val() || 0, 10), 'uint8');
    setDataValue(data, 270, parseInt($("#pg0-b-var").val() || 0, 10), 'uint8');
    setDataValue(data, 271, parseInt($("#pg0-func").val() || 0, 10), 'uint8');
    setDataValue(data, 272, parseInt($("#pg0-a-operand").val() || 0, 10), 'uint8');
    setDataValue(data, 273, parseInt($("#pg0-b-operand").val() || 0, 10), 'uint8');

    setDataValue(data, 274, pg0_a_value, 'uint16');
    setDataValue(data, 276, pg0_b_value, 'uint16');

    setDataValue(data, 278, parseInt($("#pg0-off-delay").val() || 0, 10), 'uint8');
    setDataValue(data, 279, parseInt($("#pg0-on-delay").val() || 0, 10) * 25, 'uint8');
    setDataValue(data, 310, parseInt($("#pg0-on-limit").val() || 0, 10) * 25, 'uint8');

    setDataValue(data, 312, $("#pg0-loop").prop("checked") ? 1 : 0, 'uint8');
    setDataValue(data, 321, $("#pg0-engine-run").prop("checked") ? 1 : 0, 'uint8');

    saveData();
}

function pg1SetBtn() {
    if (eInputs('pg1-set-btn')) return;

    let pg1_a_var = $("#pg1-a-var").val();
    let pg1_b_var = $("#pg1-b-var").val();
    let pg1_a_value = calcVal(pg1_a_var, "#pg1-a-value");
    let pg1_b_value = calcVal(pg1_b_var, "#pg1-b-value");

    setDataValue(data, 268, $("#pg1-status").prop("checked") ? 1 : 0, 'uint8');
    setDataValue(data, 280, parseInt($("#pg1-a-var").val() || 0, 10), 'uint8');
    setDataValue(data, 281, parseInt($("#pg1-b-var").val() || 0, 10), 'uint8');
    setDataValue(data, 282, parseInt($("#pg1-func").val() || 0, 10), 'uint8');
    setDataValue(data, 283, parseInt($("#pg1-a-operand").val() || 0, 10), 'uint8');
    setDataValue(data, 284, parseInt($("#pg1-b-operand").val() || 0, 10), 'uint8');

    setDataValue(data, 285, pg1_a_value, 'uint16');
    setDataValue(data, 287, pg1_b_value, 'uint16');

    setDataValue(data, 289, parseInt($("#pg1-off-delay").val() || 0, 10), 'uint8');
    setDataValue(data, 290, parseInt($("#pg1-on-delay").val() || 0, 10) * 25, 'uint8');
    setDataValue(data, 311, parseInt($("#pg1-on-limit").val() || 0, 10) * 25, 'uint8');

    setDataValue(data, 313, $("#pg1-loop").prop("checked") ? 1 : 0, 'uint8');
    setDataValue(data, 322, $("#pg1-engine-run").prop("checked") ? 1 : 0, 'uint8');

    saveData();
}

/* =====================================================
 *  CALIBRATION SETTERS
 * ===================================================== */

const CALIB_VOLT = {
    0: [0, 2, 4, 6, 8, 10, 12, 14, 16, 18],
    1: [30, 32, 34, 36, 38, 40, 42, 44, 46, 48],
    2: [60, 62, 64, 66, 68, 70, 72, 74, 76, 78],
    3: [90, 92, 94, 96, 98, 100, 102, 104, 106, 108],
    4: [120, 122, 124, 126, 128, 130, 132, 134, 136, 138]
};

const CALIB_TEMP = {
    0: [20, 21, 22, 23, 24, 25, 26, 27, 28, 29],
    1: [50, 51, 52, 53, 54, 55, 56, 57, 58, 59],
    2: [80, 81, 82, 83, 84, 85, 86, 87, 88, 89],
    3: [110, 111, 112, 113, 114, 115, 116, 117, 118, 119],
    4: [140, 141, 142, 143, 144, 145, 146, 147, 148, 149]
};

function f32(x) { return Math.fround(x); }

function rConv(a) {
    const a_f = f32(parseInt(a, 10));
    return Math.floor(f32((f32(5.0) / f32(4700 + a_f)) * a_f * f32(100.0)) + 0.5);
}

function volt_calc_pos(pos) {
    const adc = f32(0.0048828125);
    return Math.floor(f32(1024 - f32(f32(pos) / adc / 100)) + 0.5);
}

function calibSetBtn() {
    if (eInputs('cal-aux-set-btn')) return;

    const aux = parseInt($("#aux-in").value, 10);
    const isPullup =
        (aux === 0 && a0_pullup == 1) ||
        (aux === 1 && a1_pullup == 1);

    let volt = [], temp = [];

    for (let i = 0; i < 10; i++) {
        volt.push(Number($("#aux-v" + i).value || 0));
        temp.push(Number($("#aux-t" + i).value || 0));
    }

    const idxV = CALIB_VOLT[aux];
    const idxT = CALIB_TEMP[aux];

    for (let i = 0; i < 10; i++) {
        let t = temp[i];
        if (t < 0 || t > 150) {
            xhrResponse(`${localization.key['dialog.token.err']}: ${t}℃`);
            return;
        }
        setDataValue(data, idxT[i], t & 0xFF, 'uint8');

        let sensor_voltage = 0;

        if (!isPullup) {
            let volts = volt[i];
            if (volts < 0.01 || volts > 5) {
                xhrResponse(`${localization.key['dialog.token.err']}: ${volts}V`);
                return;
            }
            const pos = Math.round(volts * 100);
            sensor_voltage = volt_calc_pos(pos);
        } else {
            const ohm = volt[i];
            if (ohm < 1 || ohm > 99000) {
                xhrResponse(`${localization.key['dialog.token.err']}: ${ohm}ohm`);
                return;
            }
            const pos_from_ohm = rConv(ohm);
            sensor_voltage = volt_calc_pos(pos_from_ohm);
        }

        if (sensor_voltage < 0 || sensor_voltage > 1023) return;

        setDataValue(data, idxV[i], sensor_voltage & 0xFFFF, 'uint16');
    }

    saveData();
    fillData();
}

/* =====================================================
 *  READY
 * ===================================================== */

$(document).ready(function () {
    fetchData();
    fillData();

    function createDebounce(delay = 3000) {
        const lockedButtons = new Set();

        return function (buttonId, callback) {
            return async function (...args) {
                if (lockedButtons.has(buttonId)) return;

                lockedButtons.add(buttonId);
                const $button = $(this);
                $button.prop('disabled', true);

                try {
                    await checkSig();
                    await callback.apply(this, args);
                } catch (err) {
                    console.error('[setter:' + buttonId + ']', err);
                    try {
                        xhrResponse(String(err && err.message || err));
                    } catch (_) { /* ignore */ }
                } finally {
                    setTimeout(() => {
                        lockedButtons.delete(buttonId);
                        $button.prop('disabled', false);
                    }, delay);
                }
            };
        };
    }

    const debounce = createDebounce(1000);

    // boost
    $("#boost-set-btn").on("click", debounce("boost-set-btn", boostSetBtn));
    $("#pid-set-btn").on("click", debounce("pid-set-btn", pidSetBtn));
    $("#boost-gear-set-btn").on("click", debounce("boost-gear-set-btn", boostGearSetBtn));

    // protection
    $("#p-set-btn").on("click", debounce("p-set-btn", protectionSetBtn));

    // fan
    $("#fan-set-btn").on("click", debounce("fan-set-btn", fanActSetBtn));
    $("#pwm-set-btn").on("click", debounce("pwm-set-btn", fanPwmSetBtn));
    $("#sw-set-btn").on("click", debounce("sw-set-btn", fanSwSetBtn));

    // logic
    $("#pg0-set-btn").on("click", debounce("pg0-set-btn", pg0SetBtn));
    $("#pg1-set-btn").on("click", debounce("pg1-set-btn", pg1SetBtn));

    // inputs
    $("#aux-set-btn").on("click", debounce("aux-set-btn", auxSetBtn));
    $("#bsx-set-btn").on("click", debounce("bsx-set-btn", bsxSetBtn));
    $("#vfd-set-btn").on("click", debounce("vfd-set-btn", vfdSetBtn));
    $("#ds-set-btn").on("click", debounce("ds-set-btn", dsSetBtn));

    // calibration
    $("#ps-set-btn").on("click", debounce("ps-set-btn", psSetBtn));
    $("#afr-set-btn").on("click", debounce("afr-set-btn", afrSetBtn));
    $("#map-set-btn").on("click", debounce("map-set-btn", mapSetBtn));
    $("#cal-aux-set-btn").on("click", debounce("cal-aux-set-btn", calibSetBtn));

    // other
    $("#pim-mode").on("change", checkPIM);
    $("#misc-set-btn").on("click", debounce("misc-set-btn", otherSetBtn));

    // config
    $("#config-upload-btn").on("click", debounce("config-upload-btn", cfgUpload));
    $("#config-download-btn").on("click", debounce("config-download-btn", cfgDownload));

    initChoicesSystem();
    setInterval(fetchData, POLLING_INTERVAL);
});
