'use strict;'

const POLLING_INTERVAL = 10000;
let eop_sensor = null;
let fp_sensor = null;
let map_limit = null;
let cfg_data = [];

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
        if (timeoutId) {
            clearTimeout(timeoutId);
        }
    }

    function executeCreateChoices() {
        cleanup();
        createChoices();
    }

    if (localStorage.getItem('translations-cache-ru')) {
        executeCreateChoices();
        return;
    }

    timeoutId = setTimeout(() => {
        console.warn('translations-cache-ru not found in localStorage after timeout');
        cleanup();
    }, MAX_WAIT_TIME);

    const originalSetItem = localStorage.setItem;
    localStorage.setItem = function(key, value) {
        originalSetItem.apply(this, arguments);
        if (key === 'translations-cache-ru') {
            executeCreateChoices();
        }
    };

    window.addEventListener('storage', (e) => {
        if (e.key === 'translations-cache-ru' && e.newValue) {
            executeCreateChoices();
        }
    });
}

// === HELPERS

// Переопределяем value
Object.defineProperty($.fn, 'value', {
    get: function() {
        return this.val();
    },
    set: function(value) {
        return this.val(value);
    },
    configurable: true
});

// Переопределяем checked
Object.defineProperty($.fn, 'checked', {
    get: function() {
        return this.prop('checked');
    },
    set: function(value) {
        const boolValue = !!(value && value !== "" && value !== "false");
        return this.prop('checked', boolValue);
    },
    configurable: true
});

// Для style.pointerEvents
Object.defineProperty($.fn, 'style', {
    get: function() {
        return {
            set pointerEvents(value) {
                $(this).css('pointer-events', value);
            },
            get pointerEvents() {
                return $(this).css('pointer-events');
            }
        };
    }
});

function checkCfg() {
    const fileInput = document.getElementById('cfgFile');
    const file = fileInput.files[0];

    if (!file) {
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            const decodedString = atob(e.target.result);
            const elements = decodedString.split(',');
            if (elements.length !== 405 || elements[404] !== '~') {
                serverError(localization.key['import.broken.el']);
                $("#cfgFile").value = '';
                return;
            }

            cfg_data = elements.slice(0, -1).map(Number);

        } catch (error) {
            serverError(error.message);
            $("#cfgFile").value = '';
        }
    };

    reader.onerror = function() {
        serverError();
        $("#cfgFile").value = '';
    };

    reader.readAsText(file);
}

function cfgUpload() {
    if (!cfg_data.length) return;
    $("#cfgFile").value = '';
    data = cfg_data;
    cfg_data = [];
    fillData();
    createChoices();
    saveData();
}

function cfgDownload() {
    const dataToDownload = [...data, '~'];

    for (let i = 394; i < 404; i++) { //Zeroing
        dataToDownload[i] = 0;
    }

    if (dataToDownload.length !== 405 || dataToDownload[404] !== "~") {
        serverError();
        return;
    }

    const dataString = btoa(dataToDownload.join(','));
    const fileName = `rbx_cfg_server_${Date.now()}.b64`;
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

function numberToBytesTyped(number, type = 'auto') {
    const types = {
        'uint8': 1,
        'int8': 1,
        'uint16': 2,
        'int16': 2,
        'uint32': 4,
        'int32': 4,
        'float32': 4
    };

    if (type === 'auto') {
        type = Number.isInteger(number) ? 'int32' : 'float32';
    }

    const byteLength = types[type];
    
    if (byteLength === 1) {
        return [number & 0xFF];
    } else if (byteLength === 2) {
        // Little-endian для uint16/int16
        return [
            number & 0xFF,           // младший байт
            (number >> 8) & 0xFF     // старший байт
        ];
    } else if (byteLength === 4) {
        if (type === 'float32') {
            const floatArray = new Float32Array(1);
            floatArray[0] = number;
            const uintArray = new Uint8Array(floatArray.buffer);
            return Array.from(uintArray); // Float уже в little-endian
        } else {
            // Little-endian для uint32/int32
            return [
                number & 0xFF,           // младший байт
                (number >> 8) & 0xFF,
                (number >> 16) & 0xFF,
                (number >> 24) & 0xFF    // старший байт
            ];
        }
    }
    
    throw new Error('Unsupported type');
}

function setDataValue(data, address, value, type = 'auto') {
    const bytes = numberToBytesTyped(value, type);
    
    for (let i = 0; i < bytes.length; i++) {
        data[address + i] = bytes[i];
    }
}

function saveData() {
    const storedDataJson = localStorage.getItem("data");
    const storedData = storedDataJson ? JSON.parse(storedDataJson) : [];

    if (JSON.stringify(data) === JSON.stringify(storedData)) {
        return;
    }

    localStorage.setItem("data", JSON.stringify(data));
    stor_data = JSON.parse(JSON.stringify(data));

    const dataToSend = [...data, '~', Date.now()];

    const headers = {
        'Content-Type': 'application/x-www-form-urlencoded'
    };

    if (typeof token !== 'undefined') {
        headers['Authorization'] = token;
    }

    fetch('remote.php', {
        method: 'POST',
        headers: headers,
        body: new URLSearchParams({
            data: dataToSend.join(','),
            lang: lang
        })
    })
    .then(response => {
        if (response.status === 200) {
            xhrResponse(localization.key['set.common.updated']);
            fetchData();
        } else {
            xhrResponse(localization.key['redlog.err']);
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        xhrResponse(localization.key['redlog.err']);
    });
}

async function fetchData() {
    try {
        const headers = {
            'Content-Type': 'application/x-www-form-urlencoded'
        };

        if (typeof token !== 'undefined') {
            headers['Authorization'] = token;
        }

        const response = await fetch('remote.php', {
            method: 'POST',
            headers: headers,
            body: new URLSearchParams({
                data: 'fetch',
                lang: lang
            })
        });

        if (response.status === 200) {
            const result = await response.text();

            // Преобразуем строку ответа в массив
            const newData = result.split(',').map(item => {
                const num = parseInt(item, 10);
                return isNaN(num) ? item : num;
            });

            // Создаем копию newData без двух последних элементов для сравнения
            const newDataForComparison = [...newData];
            newDataForComparison.splice(newDataForComparison.length - 2, 2);
            
            // Создаем копию текущего data для сравнения
            const currentDataForComparison = [...data];
            
            // Функция для сравнения массивов
            const arraysEqual = (arr1, arr2) => {
                if (arr1.length !== arr2.length) return false;
                for (let i = 0; i < arr1.length; i++) {
                    if (arr1[i] !== arr2[i]) return false;
                }
                return true;
            };
            
            // Сравниваем массивы (исключая два последних элемента)
            const dataChanged = !arraysEqual(currentDataForComparison, newDataForComparison);

            // Обновляем глобальный массив data
            data.length = 0; // Очищаем текущий массив
            data.push(...newData); // Заполняем новыми данными
            
            // Обновляем timestamp
            const date = new Date(data.at(-1));
            let date_res = date.toLocaleString($.cookie('timeformat') == '12' ? 'en-US' : 'sv-SE', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: $.cookie('timeformat') == '12'
            });
            date_res = date_res.replace(/-/g, '.').replace(', ', ' ');
            $("#timestamp").html(date_res);
            
            data.splice(data.length - 2, 2); //удаляем ~ и timestamp
            
            // Выполняем fillData() только если данные изменились
            if (dataChanged) {
                xhrResponse(localization.key['remote.mcu.update.dialog']);
                fillData();
                createChoices();
            }
            
            return newData;
        } else if (response.status === 204) {
            location.reload();
        } else {
            return null;
        }
    } catch (error) {
        xhrResponse(`Fetch error: ' ${error}`);
        console.error('Fetch error:', error);
        return null;
    }
}

//Increase input button
function iv(idx, intx, step) {
  let el = document.getElementById(idx);
  let value = intx == 1 ? parseInt(el.value) : parseFloat(el.value);
  value = isNaN(value) ? 0 : value;

  // Получаем минимальное значение из атрибута min
  let min = parseFloat(el.getAttribute('min'));
  if (!isNaN(min)) {
    value < min ? value = min - step : '';
  }

  value += step;
  value = Math.round(value / step) * step;
  document.getElementById(idx).value = Math.round(value * 100) / 100;
}

//Decrease input button
function dv(idx, intx, step) {
  let el = document.getElementById(idx);
  let value = intx == 1 ? parseInt(el.value) : parseFloat(el.value);
  value = isNaN(value) ? 0 : value;

  // Получаем минимальное значение из атрибута min
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

//forbid to print letters on digital inputs
function nolet(e) {
    let evt = e;
    if (evt.keyCode === 69) {
        return false;
    }
}

//Check inputs limit
function vlim(input,lim) {
let el = document.getElementById(input);
let limit = lim;
 if (el.value > limit) {
  el.value = limit;
 }
 else if(el.value < 0) {
  el.value = '0';
 }
 return el.value;
}

function getFloat(b1,b2,b3,b4) {
 let data = [b1, b2, b3, b4];
 let buf = new ArrayBuffer(4);
 let view = new DataView(buf);
  data.forEach(function (b, i) {
     view.setUint8(i, b);
  });
 let num = view.getFloat32(0);
 num = Math.round(num * 100.0) / 100.0;
return num;
}

function getInt(b1,b2) {
 let num = (b1 & 0xff) << 8 | (b2 & 0xff);
return num;
}

function getUlong(b1,b2,b3,b4) {
 let num = (b1 & 0xff) << 24 | (b2 & 0xff) << 16 | (b3 & 0xff) << 8 | (b4 & 0xff);
return num;
}

// === BOOST
let map_custom;
let selected_map;

function limits_boost() {
    const mapType = $("#boost-map").prop("selectedIndex");
    const fields = $("#boost-target, #boost-start, #boost-g1-target, #boost-g2-target, #boost-g3-target, #boost-g4-target");
    
    // Минимальное значение
    fields.each(function() {
        if ($(this).val() < 0) $(this).val('0');
    });
    
    // Максимальное значение
    const maxValues = {1: 0, 2: 150, 3: 200, 4: map_custom};
    const maxValue = maxValues[mapType];
    
    if (maxValue !== undefined) {
        fields.each(function() {
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

// === PROTECTION
function limits_protection() {
    const input = $("#boost-limit");
    
    if (input.value < 0) input.value = '0';

    const limits = {
        0: 0,        // obd1
        1: 150,      // 250kpa
        2: 200,      // 300kpa
        3: map_custom // Custom
    };

    if (input.value > limits[map_limit]) {
        input.value = limits[map_limit];
    }
}

// === FAN
function limits_fan() {
 if($("#fan-target").value<0){$("#fan-target").value='0';}

    if (document.getElementById("fan-mode-sel").selectedIndex == "1"){ //sw
     if($("#fan-target").value>125){$("#fan-target").value='125';}
    }
    else if (document.getElementById("fan-mode-sel").selectedIndex == "2"){ //pwm
     if($("#fan-target").value>105){$("#fan-target").value='105';}
    }
}

// === LOGIC
function step_pg0a() {
 let inp = document.getElementById("pg0-a-var").selectedIndex
    if (inp == "12" || inp == "13" || inp == "16" || inp == "19"){
    return 0.01;
    }
    else return 1;
}

function step_pg0b() {
 let inp = document.getElementById("pg0-b-var").selectedIndex
    if (inp == "12" || inp == "13" || inp == "16" || inp == "19"){
    return 0.01;
    }
    else return 1;
}

function step_pg1a() {
 let inp = document.getElementById("pg1-a-var").selectedIndex
    if (inp == "12" || inp == "13" || inp == "16" || inp == "19"){
    return 0.01;
    }
    else return 1;
}

function limits_logic() {
    // Конфигурация параметров
    const paramConfig = {
        '1': { max: 0, unit: '', step: 1, hidden: true },    // none
        '2': { max: 125, unit: '°C', step: 1 },              // ltemp
        '3': { max: 125, unit: '°C', step: 1 },              // ltemp
        '4': { max: 125, unit: '°C', step: 1 },              // ltemp
        '5': { max: 125, unit: '°C', step: 1 },              // ltemp
        '6': { max: 125, unit: '°C', step: 1 },              // ltemp
        '7': { max: 125, unit: '°C', step: 1 },              // ltemp
        '8': { max: 90, unit: '%', step: 1 },                // tps
        '9': { max: 250, unit: 'km/h', step: 1 },            // spd
        '10': { max: 65534, unit: '', step: 1, hidden: true }, // rlc
        '11': { max: 1, unit: '', step: 1, hidden: true },   // bst
        '12': { max: () => eop_sensor == 1 ? 7 : 10, unit: 'bar', step: 0.01 }, // eop
        '13': { max: () => fp_sensor == 1 ? 7 : 10, unit: 'bar', step: 0.01 },  // fp
        '14': { max: () => {
            switch(map_limit) {
                case 0: return 0;
                case 1: return 250;
                case 2: return 300;
                case 3: return map_custom;
                default: return 0;
            }
        }, unit: 'kPa', step: 1 }, // map
        '15': { max: 10000, unit: '', step: 1, hidden: true }, // rpm
        '16': { max: 20, unit: 'V', step: 0.01 },            // vlt
        '17': { max: 1000, unit: '°C', step: 1 },            // egt
        '18': { max: 1, unit: '', step: 1, hidden: true },   // knk
        '19': { max: 25, unit: 'A/F', step: 0.01 },          // afr
        '20': { max: 65534, unit: 'sec', step: 1 },          // ert
        '21': { max: 1092, unit: 'h', step: 1 },             // mh
        '22': { max: 1, unit: '', step: 1, hidden: true },   // bs1
        '23': { max: 1, unit: '', step: 1, hidden: true }    // bs2
    };

    // Обработка всех полей
    const fields = ['pg0-a', 'pg0-b', 'pg1-a', 'pg1-b'];
    
    fields.forEach(field => {
        const select = $(`#${field}-var`)[0];
        const selectedIndex = select.selectedIndex.toString();
        const config = paramConfig[selectedIndex];
        
        if (!config) return;
        
        const valueField = $(`#${field}-value`);
        const labelField = $(`#${field}-label`);
        
        // Получаем максимальное значение (может быть функцией)
        const maxValue = typeof config.max === 'function' ? config.max() : config.max;
        
        // Проверка и установка границ
        if (valueField.val() < 0) valueField.val('0');
        if (valueField.val() > maxValue) valueField.val(maxValue.toString());
        
        // Установка шага
        valueField.attr('step', config.step.toString());
        
        // Настройка label
        if (config.hidden) {
            labelField.attr('hidden', '');
        } else {
            labelField.removeAttr('hidden');
            labelField.html(config.unit === '°C' ? ' &#8451' : ` ${config.unit}`);
        }
    });
}

function step_pg1b() {
 let inp = document.getElementById("pg1-b-var").selectedIndex
    if (inp == "12" || inp == "13" || inp == "16" || inp == "19"){
    return 0.01;
    }
    else return 1;
}

// === INPUTS
let toHex = function (str) {
  let hex = Number(str).toString(16);
  if (hex.length < 2) {
       hex = "0" + hex;
  }
  hex = hex.toUpperCase();
  return hex;
};

function checkAuxCollisions() {
    const getValues = (prefix, count) => 
        Array.from({length: count}, (_, i) => $(`#${prefix}${i}-sel`).value);
    
    const aux = getValues('aux', 5).filter(v => v !== '0');
    const ds = getValues('ds', 5);

    // Проверка коллизий между AUX входами
    if (new Set(aux).size !== aux.length) {
        xhrResponse(localization.key['inputs.aux.collide']);
        return true;
    }

    // Проверка коллизий между AUX и DS входами
    if (aux.some(a => ds.includes(a))) {
        xhrResponse(localization.key['inputs.auxds.collide']);
        return true;
    }

    return false;
}

function checkDSCollisions() {
    const getValues = (prefix, count) => 
        Array.from({length: count}, (_, i) => $(`#${prefix}${i}-sel`).value);
    
    const ds = getValues('ds', 5).filter(v => v !== '0');
    const aux = getValues('aux', 5);

    // Проверка коллизий между DS входами
    if (new Set(ds).size !== ds.length) {
        xhrResponse(localization.key['inputs.ds.collide']);
        return true;
    }

    // Проверка коллизий между DS и AUX входами
    if (ds.some(d => aux.includes(d))) {
        xhrResponse(localization.key['inputs.dsaux.collide']);
        return true;
    }

    return false;
}

// Подсвечиваем коллизии входов
function checkCollisions(prefix, count) {
    let colCount = 0;
    // Получаем все значения селектов с заданным префиксом
    const values = [];
    for (let i = 0; i < count; i++) {
        const select = document.getElementById(`${prefix}${i}-sel`);
        if (select) {
            values.push(select.value);
        } else {
            values.push(null); // или можно использовать ''
        }
    }

    // Проверяем каждый селект на совпадения
    for (let i = 0; i < count; i++) {
        const currentSelect = document.getElementById(`${prefix}${i}-sel`);
        
        // Если селект не существует, пропускаем
        if (!currentSelect) {
            continue;
        }
        
        const currentValue = values[i];

        // Находим родительский div с классами choices__inner choices__settings__text
        const parentDiv = currentSelect.closest('.choices__inner.choices__settings__text');
        
        // Если родительский div не найден, пропускаем
        if (!parentDiv) {
            continue;
        }

        // Игнорируем пустые значения и "0"
        if (currentValue === '' || currentValue === '0' || currentValue === null) {
            parentDiv.classList.remove('collision');
            continue;
        }

        // Ищем совпадения с другими селектами того же типа
        const hasCollision = values.some((value, index) =>
            index !== i &&
            value === currentValue &&
            value !== '' &&
            value !== '0' &&
            value !== null
        );

        // Ищем совпадения с селектами другого типа
        const otherPrefix = prefix === 'aux' ? 'ds' : 'aux';
        const hasOtherCollision = Array.from({length: count}, (_, j) => {
            const otherSelect = document.getElementById(`${otherPrefix}${j}-sel`);
            if (!otherSelect) return false;
            const otherValue = otherSelect.value;
            return otherValue !== '' &&
                   otherValue !== '0' &&
                   otherValue === currentValue;
        }).some(Boolean);

        // Добавляем или удаляем класс collision, отключаем кнопки
        if (hasCollision || hasOtherCollision) {
            parentDiv.classList.add('collision');
            $("#aux-set-btn").prop("disabled",true);
            $("#ds-set-btn").prop("disabled",true);
        } else {
            parentDiv.classList.remove('collision');
            $("#aux-set-btn").prop("disabled",false);
            $("#ds-set-btn").prop("disabled",false);
        }
    }
}

// Функция для проверки всех селектов
function checkAllCollisions() {
    checkCollisions('aux', 5);
    checkCollisions('ds', 5);
}

function formatDSAddress(startIndex) {
    return Array.from({length: 8}, (_, i) => toHex(data[startIndex + i])).join(':');
}

// === CALIBRATION
let a0_pullup,a1_pullup;

function pF(b1,b2) { //Volt
    let num = Math.round((5 - (getInt(b1,b2) * 0.0048828)) * 100.0) / 100.0;
    return num;
}

function mF(b1,b2) { //Custom MAP
    let num = Math.round((getInt(b1,b2) * 0.0048828) * 100.0) / 100.0;
    return num;
}

function pT(b1,b2) { //Omh
    let num = Math.round((5 - (getInt(b1,b2) * 0.0048828)) * 4700 / (5 - (5 - (getInt(b1,b2) * 0.0048828))));
    return !isFinite(num) ? 0 : num;
}

function select_aux() {
  const auxIn = $("#aux-in").value;

  // Обработка значений v0-v9
  if (auxIn == "0" || auxIn == "1") {
    const baseIndex = auxIn * 30;
    const isPullup = (auxIn == "0" && a0_pullup == 1) || (auxIn == "1" && a1_pullup == 1);
    $("#nom").html(isPullup ? "Ohm" : "Volt");

    const processFunc = isPullup ? pT : pF;
    for (let i = 0; i < 10; i++) {
      const dataIndex = baseIndex + i*2;
      $("#aux-v" + i).value = processFunc(data[dataIndex + 1], data[dataIndex]);
    }
  } else {
    $("#nom").html("Volt");
    const baseIndex = (auxIn == "2") ? 60 : (auxIn == "3") ? 90 : 120;

    for (let i = 0; i < 10; i++) {
      const dataIndex = baseIndex + i*2;
      $("#aux-v" + i).value = pF(data[dataIndex + 1], data[dataIndex]);
    }
  }

  // Заполнение значений t0-t9
  switch (auxIn) {
    case "0":
      for (let i = 0; i < 10; i++) {
        $("#aux-t" + i).value = data[20 + i];
      }
      break;
    case "1":
      for (let i = 0; i < 10; i++) {
        $("#aux-t" + i).value = data[50 + i];
      }
      break;
    case "2":
      for (let i = 0; i < 10; i++) {
        $("#aux-t" + i).value = data[80 + i];
      }
      break;
    case "3":
      for (let i = 0; i < 10; i++) {
        $("#aux-t" + i).value = data[110 + i];
      }
      break;
    case "4":
      for (let i = 0; i < 10; i++) {
        $("#aux-t" + i).value = data[140 + i];
      }
      break;
  }
}

function limits_calibration() {
 switch ($("#aux-in").value) {
  case "0":
  if (a0_pullup != 1) { return 5;
     } else { return 99000;
    }
  break;

  case "1":
    if (a1_pullup != 1) { return 5;
     } else { return 99000;
    }
  break;

  default:
    return 5;
  break;
 }
}

function step_def() {
    $('[id^="aux-v"]').each(function() {
        $(this)
            .attr("step", "0.01")
            .attr("min", "0.01")
            .attr("max", "5");
    });
}

function step_pullup() {
    $('[id^="aux-v"]').each(function() {
        $(this)
            .attr("step", "1")
            .attr("min", "1")
            .attr("max", "99000");
    });
}

function step() {
 switch ($("#aux-in").value) {
  case "0":
   if (a0_pullup != 1) { step_def(); return 0.01; }
   else { step_pullup(); return 1; }
  break;

  case "1":
   if (a1_pullup != 1) { step_def(); return 0.01; }
   else { step_pullup(); return 1; }
  break;

  default:
   step_def();
   return 0.01;
  break;
 }
}

// === OTHER
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
    //main
    $("#boost-target").value = data[0];
    $("#boost-start").value = data[10];
    $("#boost-target").value = getInt(data[227], data[226]) - 101;
    $("#boost-start").value = data[229] * 5;
    $("#boost-duty").value = data[228];
    $("#boost-dc-corr").value = data[295];
    $("#boost-rpm-start").value = data[245] * 50;
    $("#boost-rpm-end").value = data[294] * 50;
    $("#boost-rpm-duty").value = data[246];

    data[225] != 1 ? $("#boost-status").checked = "true" : $("#boost-status").checked = "";
    map_custom = (getInt(data[307], data[306])) - 100;
    $("#boost-map-filter").val(data[314]);

    switch (data[264]) {
      case 0: //OBD1
        $("#boost-map").value = 0;
        break;
      case 1: //250
        $("#boost-map").value = 1;
        break;
      case 2: //300
        $("#boost-map").value = 2;
        break;
      case 3: //Custom
        $("#boost-map").value = 3;
        break;
    }
    selected_map = document.getElementById("boost-map").selectedIndex;
    $("#boost-freq").val(data[323] == 0 ? "0" : "1");

    //pid
    $("#pid-kp").value = getFloat(data[216],data[215],data[214],data[213]);
    $("#pid-ki").value = getFloat(data[220],data[219],data[218],data[217]);
    $("#pid-kd").value = getFloat(data[224],data[223],data[222],data[221]);
    $("#pid-freq").value = data[212];

    switch (data[211]) {
      case 0: //P-on-m
        $("#pid-mode").value = 0;
        break;
      case 1: //P-on-e
        $("#pid-mode").value = 1;
        break;
    }

    //gear boost
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

    //boost limit depends on map
    switch (data[264]) {
    case 0: //obd1
    map_limit = 0;
    break;
    case 1: //250kpa
    map_limit = 1;
    break;
    case 2: //300kpa
    map_limit = 2;
    break;
    case 3: //Custom
    map_limit = 3;
    break;
    }
   map_custom = (getInt(data[307], data[306])) - 100;


// === FAN
    switch (data[155]) {
      case 0: //SW
        $("#fan-mode-sel").value = 0;
        break;
      case 1: //PWM
        $("#fan-mode-sel").value = 1;
        break;
    }
    data[208] == 1 ? $("#fan-ac").checked = "true" : $("#fan-ac").checked = "";
    data[207] == 1 ? $("#fan-engine").checked = "true" : $("#fan-engine").checked = "";
    data[293] == 1 ? $("#fan-test").checked = "true" : $("#fan-test").checked = "";
    $("#fan-target").value = data[203] - 40;

    switch (data[202]) {
      case 0: //off
        $("#fan-src-sel").value = 0;
        break;
      case 1: //ect
        $("#fan-src-sel").value = 1;
        break;
      case 2: //eot
        $("#fan-src-sel").value = 2;
        break;
      case 3: //iat
        $("#fan-src-sel").value = 3;
        break;
      case 4: //atf
        $("#fan-src-sel").value = 4;
        break;
      case 5: //aat
        $("#fan-src-sel").value = 5;
        break;
      case 6: //ext
        $("#fan-src-sel").value = 6;
        break;
    }

    data[205] == 1 ? $("#pwm-invert").checked = "true" : $("#pwm-invert").checked = "";
    $("#pwm-spd").value = data[209];
    $("#pwm-width").value = data[206];
    $("#pwm-min-dc").value = Math.round(100 - (data[299] * 100 / 255));
    switch (data[201]) {
      case 0: //490
        $("#pwm-freq").value = 0;
        break;
      case 1: //120
        $("#pwm-freq").value = 1;
        break;
     case 2: //30
        $("#pwm-freq").value = 2;
        break;
    }

    $("#sw-off-delay").value = data[204];
    $("#hyst").value = data[210];
    limits_fan();


// === LOGIC
    let value = null;
    //Check wich MAP is used
    switch (data[264]) {
     case 0: //obd1
      map_limit = 0;
     break;
     case 1: //250kpa
      map_limit = 1;
     break;
     case 2: //300kpa
      map_limit = 2;
     break;
     case 3: //Custom
      map_limit = 3;
     break;
    }
    map_custom = getInt(data[307], data[306]);

    //Used pressure sensors
    eop_sensor = data[318];
    fp_sensor = data[319];

    //PG0
    data[267] == 1 ? $("#pg0-status").checked = "true" : $("#pg0-status").checked = "";
    data[321] == 1 ? $("#pg0-engine-run").checked = "true" : $("#pg0-engine-run").checked = "";
    data[312] == 1 ? $("#pg0-loop").checked = "true" : $("#pg0-loop").checked = "";
    $("#pg0-off-delay").value = data[278];
    $("#pg0-on-delay").value = data[279] / 25;
    $("#pg0-on-limit").value = data[310] / 25;

    switch (data[272]) { //pg0 a operand
      case 0:
        $("#pg0-a-operand").value = 0;
        break;
      case 1:
        $("#pg0-a-operand").value = 1;
        break;
    }

    switch (data[273]) { //pg0 b operand
      case 0:
        $("#pg0-b-operand").value = 0;
        break;
      case 1:
        $("#pg0-b-operand").value = 1;
        break;
    }

    switch (data[271]) { //pg0 func
      case 0:
        $("#pg0-func").value = 0;
        break;
      case 1:
        $("#pg0-func").value = 1;
        break;
      case 2:
        $("#pg0-func").value = 2;
        break;
    }

    //pg0 a var
    value = parseInt(data[269], 10);
    if (!isNaN(value) && value >= 0 && value <= 22) {
        $("#pg0-a-var").value = value.toString();
    }

    //pg0 b var
    value = parseInt(data[270], 10);
    if (!isNaN(value) && value >= 0 && value <= 22) {
        $("#pg0-b-var").value = value.toString();
    }

    let pg0_a_var = parseInt($("#pg0-a-var").value);
    let pg0_b_var = parseInt($("#pg0-b-var").value);
    let pg0_a_value = parseInt(getInt(data[275], data[274]));
    let pg0_b_value = parseInt(getInt(data[277], data[276]));

      if (pg0_a_var > 0 && pg0_a_var <= 6){
       pg0_a_value = pg0_a_value - 40;
      }
      else if (pg0_a_var == 7) {
       pg0_a_value = Math.round(map(pg0_a_value,0,147,0,100));
      }
      else if (pg0_a_var == 20) {
       pg0_a_value = pg0_a_value / 60;
      }
      else if (pg0_a_var == 11 || pg0_a_var == 12 || pg0_a_var == 15 || pg0_a_var == 18) {
       pg0_a_value = parseFloat(pg0_a_value / 100);
      }
       $("#pg0-a-value").value = pg0_a_value;

      if (pg0_b_var > 0 && pg0_b_var <= 6){
       pg0_b_value = pg0_b_value - 40;
      }
      else if (pg0_b_var == 7) {
       pg0_b_value = Math.round(map(pg0_b_value,0,147,0,100));
      }
      else if (pg0_b_var == 20) {
       pg0_b_value = pg0_b_value / 60;
      }
      else if (pg0_b_var == 11 || pg0_b_var == 12 || pg0_b_var == 15 || pg0_b_var == 18) {
       pg0_b_value = parseFloat(pg0_b_value / 100);
      }
       $("#pg0-b-value").value = pg0_b_value;

    //PG1
    data[268] == 1 ? $("#pg1-status").checked = "true" : $("#pg1-status").checked = "";
    data[322] == 1 ? $("#pg1-engine-run").checked = "true" : $("#pg1-engine-run").checked = "";
    data[313] == 1 ? $("#pg1-loop").checked = "true" : $("#pg1-loop").checked = "";
    $("#pg1-off-delay").value = data[289];
    $("#pg1-on-delay").value = data[290] / 25;
    $("#pg1-on-limit").value = data[311] / 25;

    switch (data[283]) { //pg1 a operand
      case 0:
        $("#pg1-a-operand").value = 0;
        break;
      case 1:
        $("#pg1-a-operand").value = 1;
        break;
    }

    switch (data[284]) { //pg1 b operand
      case 0:
        $("#pg1-b-operand").value = 0;
        break;
      case 1:
        $("#pg1-b-operand").value = 1;
        break;
    }

    switch (data[282]) { //pg1 func
      case 0:
        $("#pg1-func").value = 0;
        break;
      case 1:
        $("#pg1-func").value = 1;
        break;
      case 2:
        $("#pg1-func").value = 2;
        break;
    }

    //pg1 a var
    value = parseInt(data[280], 10);
    if (!isNaN(value) && value >= 0 && value <= 22) {
        $("#pg1-a-var").value = value.toString();
    }

    //pg1 b var
    value = parseInt(data[281], 10);
    if (!isNaN(value) && value >= 0 && value <= 22) {
        $("#pg1-b-var").value = value.toString();
    }

    let pg1_a_var = parseInt($("#pg1-a-var").value);
    let pg1_b_var = parseInt($("#pg1-b-var").value);
    let pg1_a_value = parseInt(getInt(data[286], data[285]));
    let pg1_b_value = parseInt(getInt(data[288], data[287]));

      if (pg1_a_var > 0 && pg1_a_var <= 6){
       pg1_a_value = pg1_a_value - 40;
      }
      else if (pg1_a_var == 7) {
       pg1_a_value = Math.round(map(pg1_a_value,0,147,0,100));
      }
      else if (pg1_a_var == 20) {
       pg1_a_value = pg1_a_value / 60;
      }
      else if (pg1_a_var == 11 || pg1_a_var == 12 || pg1_a_var == 15 || pg1_a_var == 18) {
       pg1_a_value = parseFloat(pg1_a_value / 100);
      }
       $("#pg1-a-value").value = pg1_a_value;

      if (pg1_b_var > 0 && pg1_b_var <= 6){
       pg1_b_value = pg1_b_value - 40;
      }
      else if (pg1_b_var == 7) {
       pg1_b_value = Math.round(map(pg1_b_value,0,147,0,100));
      }
      else if (pg1_b_var == 20) {
       pg1_b_value = pg1_b_value / 60;
      }
      else if (pg1_b_var == 11 || pg1_b_var == 12 || pg1_b_var == 15 || pg1_b_var == 18) {
       pg1_b_value = parseFloat(pg1_b_value / 100);
      }
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

    switch (data[265]) { //vfd
      case 20:
        $("#vfd-mode").value = 0;
        break;
      case 30:
        $("#vfd-mode").value = 1;
        break;
      case 0:
        $("#vfd-mode").value = 2;
        break;
      case 21:
        $("#vfd-mode").value = 3;
        break;
      case 31:
        $("#vfd-mode").value = 4;
        break;
      case 70:
        $("#vfd-mode").value = 5;
        break;
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
    // Добавляем слушатели событий для aux селектов
    for (let i = 0; i < 5; i++) {
        const auxSelect = document.getElementById(`aux${i}-sel`);
        auxSelect.addEventListener('change', checkAllCollisions);
    }

    // Добавляем слушатели событий для ds селектов
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
    let mhh = getUlong(data[397],data[396],data[395],data[394]);
    $("#mr-hist").html(parseInt(mhh / 60) + "h");
    checkPIM();

}

// ====== Remote setters (add to remote.js) ======
// Helper boolean -> MCU byte mapping: existing code uses checked ? 0 : 1 pattern
function boolToMcuByte(checked) {
    return checked ? 1 : 0;
}

// --- BOOST (main) ---
function boostSetBtn() {
    if (eInputs('boost-set-btn')) return;
    // boost enable: data[225] (server index)
    setDataValue(data, 225, $("#boost-status").checked ? 0 : 1, 'uint8');

    // boost target: 226..227 (uint16) stored as value + 101
    setDataValue(data, 226, parseInt($("#boost-target").val() || 0, 10) + 101, 'uint16');

    // boost duty: data[228]
    setDataValue(data, 228, parseInt($("#boost-duty").val() || 0, 10), 'uint8');

    // boost start (single byte, presented as *5 when read) data[229]
    setDataValue(data, 229, Math.round((parseInt($("#boost-start").val() || 0, 10) / 5)), 'uint8');

    // boost DC correction: data[295]
    setDataValue(data, 295, parseInt($("#boost-dc-corr").val() || 0, 10), 'uint8');

    // boost rpm start/end/duty: start data[245], end data[294], duty data[246]
    setDataValue(data, 245, Math.round((parseInt($("#boost-rpm-start").val()||0,10) / 50)), 'uint8');
    setDataValue(data, 294, Math.round((parseInt($("#boost-rpm-end").val()||0,10) / 50)), 'uint8');
    setDataValue(data, 246, parseInt($("#boost-rpm-duty").val() || 0, 10), 'uint8');

    // map selection: data[264]
    setDataValue(data, 264, parseInt($("#boost-map").val()||0,10), 'uint8');

    // boost map filter: data[314]
    setDataValue(data, 314, parseInt($("#boost-map-filter").val()||0,10), 'uint8');

    // boost frequency: mapping already used in UI (0/1), MCU stored at data[323] (0/1)
    setDataValue(data, 323, parseInt($("#boost-freq").val()||0,10), 'uint8');

    saveData();
}

// --- BOOST PID ---
function pidSetBtn() {
    if (eInputs('pid-set-btn')) return;
    // pid mode: data[211] (0/1)
    setDataValue(data, 211, parseInt($("#pid-mode").val()||0,10), 'uint8');

    // pid freq: data[212]
    setDataValue(data, 212, parseInt($("#pid-freq").val()||0,10), 'uint8');

    // Kp at addr 213 (4 bytes float32)
    setDataValue(data, 213, parseFloat($("#pid-kp").val() || 0), 'float32');

    // Ki at addr 217
    setDataValue(data, 217, parseFloat($("#pid-ki").val() || 0), 'float32');

    // Kd at addr 221
    setDataValue(data, 221, parseFloat($("#pid-kd").val() || 0), 'float32');

    saveData();
}

// --- BOOST gear (targets + duties) ---
function boostGearSetBtn() {
    if (eInputs('boost-gear-set-btn')) return;
    // gear enable: data[232]
    setDataValue(data, 232, boolToMcuByte($("#boost-gear-status").prop("checked")), 'uint8');

    // gear targets: pairs (lowAddr, highAddr) starting at 233 (uint16 low)
    setDataValue(data, 233, parseInt($("#boost-g1-target").val()||0,10) + 101, 'uint16');
    setDataValue(data, 235, parseInt($("#boost-g2-target").val()||0,10) + 101, 'uint16');
    setDataValue(data, 237, parseInt($("#boost-g3-target").val()||0,10) + 101, 'uint16');
    setDataValue(data, 239, parseInt($("#boost-g4-target").val()||0,10) + 101, 'uint16');

    // gear duties: data[241]..[244]
    setDataValue(data, 241, parseInt($("#boost-g1-duty").val()||0,10), 'uint8');
    setDataValue(data, 242, parseInt($("#boost-g2-duty").val()||0,10), 'uint8');
    setDataValue(data, 243, parseInt($("#boost-g3-duty").val()||0,10), 'uint8');
    setDataValue(data, 244, parseInt($("#boost-g4-duty").val()||0,10), 'uint8');

    saveData();
}

// --- PROTECTION (p-set-btn) ---
function protectionSetBtn() {
    if (eInputs('p-set-btn')) return;
    // max/min temps: many displayed as data[index] - 40, so stored = val +40
    setDataValue(data, 249, parseInt($("#max-ect").val()||0,10) + 40, 'uint8'); // max-ect
    setDataValue(data, 247, parseInt($("#max-eot").val()||0,10) + 40, 'uint8'); // max-eot
    // max egt stored as data[252] and displayed as *5
    setDataValue(data, 252, Math.round((parseFloat($("#max-egt").val()||0) / 5)), 'uint8');

    setDataValue(data, 251, parseInt($("#max-iat").val()||0,10) + 40, 'uint8'); // max-iat
    setDataValue(data, 296, parseInt($("#max-atf").val()||0,10) + 40, 'uint8'); // max-atf
    setDataValue(data, 327, parseInt($("#max-aat").val()||0,10) + 40, 'uint8'); // max-aat
    setDataValue(data, 328, parseInt($("#max-ext").val()||0,10) + 40, 'uint8'); // max-ext

    setDataValue(data, 250, parseInt($("#min-ect").val()||0,10) + 40, 'uint8'); // min-ect
    setDataValue(data, 248, parseInt($("#min-eot").val()||0,10) + 40, 'uint8'); // min-eot
    setDataValue(data, 297, parseInt($("#min-atf").val()||0,10) + 40, 'uint8'); // min-atf

    // boost-limit two bytes at low=230 (uint16, stored +101)
    setDataValue(data, 230, parseInt($("#boost-limit").val()||0,10) + 101, 'uint16');

    // AFR displayed as data[256]/10
    setDataValue(data, 256, Math.round(parseFloat($("#afr").val()||0) * 10), 'uint8');

    // low-eop (data[253]), low-fp (255), knock (254)
    setDataValue(data, 253, $("#low-eop").prop("checked") ? 1 : 0, 'uint8');
    setDataValue(data, 255, $("#low-fp").prop("checked") ? 1 : 0, 'uint8');
    setDataValue(data, 254, $("#knock").prop("checked") ? 1 : 0, 'uint8');

    saveData();
}

// --- FAN (Activation block) ---
function fanActSetBtn() {
    if (eInputs('fan-set-btn')) return;
    // fan mode: data[155] (0=SW,1=PWM)
    setDataValue(data, 155, parseInt($("#fan-mode-sel").val()||0,10), 'uint8');

    // fan source: data[202]
    setDataValue(data, 202, parseInt($("#fan-src-sel").val()||0,10), 'uint8');

    // fan target: displayed data[203]-40 => store +40
    setDataValue(data, 203, parseInt($("#fan-target").val()||0,10) + 40, 'uint8');

    // fan AC / engine flags / test flags
    setDataValue(data, 208, $("#fan-ac").prop("checked") ? 1 : 0, 'uint8');
    setDataValue(data, 207, $("#fan-engine").prop("checked") ? 1 : 0, 'uint8');
    setDataValue(data, 293, $("#fan-test").prop("checked") ? 1 : 0, 'uint8');

    saveData();
}

// --- FAN (PWM block) ---
function fanPwmSetBtn() {
    if (eInputs('pwm-set-btn')) return;
    // pwm invert: data[205]
    setDataValue(data, 205, $("#pwm-invert").prop("checked") ? 1 : 0, 'uint8');

    // pwm speed / pwm width / pwm min DC / freq mapping
    setDataValue(data, 209, parseInt($("#pwm-spd").val()||0,10), 'uint8'); // spd
    setDataValue(data, 206, parseInt($("#pwm-width").val()||0,10), 'uint8'); // width

    // pwm-min-dc inverse mapping: displayed = Math.round(100 - (data[299] * 100 / 255))
    // => store data[299] = round((100 - displayed) * 255 / 100)
    const displayedMinDc = parseInt($("#pwm-min-dc").val()||0,10);
    setDataValue(data, 299, Math.round((100 - displayedMinDc) * 255 / 100), 'uint8');

    // pwm freq mapping stored at data[201]: UI 0->490,1->120,2->30 but both UI and stored use indexes 0/1/2
    setDataValue(data, 201, parseInt($("#pwm-freq").val()||0,10), 'uint8');

    saveData();
}


// --- FAN (SW block) ---
function fanSwSetBtn() {
    if (eInputs('sw-set-btn')) return;
    // hyst -> data[210]
    setDataValue(data, 210, parseInt($("#hyst").val()||0,10), 'uint8');

    // sw off delay -> data[204]
    setDataValue(data, 204, parseInt($("#sw-off-delay").val()||0,10), 'uint8');

    saveData();
}

// --- AUX bindings (aux-set-btn) ---
function auxSetBtn() {
    if (checkAuxCollisions()) {
        return;
    }

    // aux0..aux4 -> data[150..154]
    setDataValue(data, 150, parseInt($("#aux0-sel").val()||0,10), 'uint8');
    setDataValue(data, 151, parseInt($("#aux1-sel").val()||0,10), 'uint8');
    setDataValue(data, 152, parseInt($("#aux2-sel").val()||0,10), 'uint8');
    setDataValue(data, 153, parseInt($("#aux3-sel").val()||0,10), 'uint8');
    setDataValue(data, 154, parseInt($("#aux4-sel").val()||0,10), 'uint8');

    // pullups
    setDataValue(data, 291, $("#a0-pullup").prop("checked") ? 1 : 0, 'uint8');
    setDataValue(data, 292, $("#a1-pullup").prop("checked") ? 1 : 0, 'uint8');
    saveData();
}

// --- BSx (bsx-set-btn) ---
function bsxSetBtn() {
    // bsx mode -> data[266]
    setDataValue(data, 266, parseInt($("#bsx-mode").val()||0,10), 'uint8');

    // bs1 pullup -> data[308], bs2 -> data[309]
    setDataValue(data, 308, $("#bs1-pullup").prop("checked") ? 1 : 0, 'uint8');
    setDataValue(data, 309, $("#bs2-pullup").prop("checked") ? 1 : 0, 'uint8');

    saveData();
}

// --- VFD (vfd-set-btn) ---
function vfdSetBtn() {
    const mapping = {0: 20, 1: 30, 2: 0, 3: 21, 4: 31, 5: 70};
    const value = mapping[parseInt($("#vfd-mode").val())] || 0;
    setDataValue(data, 265, value, 'uint8');

    saveData();
}

// --- DS bindings (ds-set-btn) ---
function dsSetBtn() {
    if (checkDSCollisions()) {
        return;
    }

    // ds0..ds4 -> data[156..160]
    setDataValue(data, 156, parseInt($("#ds0-sel").val()||0,10), 'uint8');
    setDataValue(data, 157, parseInt($("#ds1-sel").val()||0,10), 'uint8');
    setDataValue(data, 158, parseInt($("#ds2-sel").val()||0,10), 'uint8');
    setDataValue(data, 159, parseInt($("#ds3-sel").val()||0,10), 'uint8');
    setDataValue(data, 160, parseInt($("#ds4-sel").val()||0,10), 'uint8');

    saveData();
}

// --- Pressure sensors (ps-set-btn) ---
function psSetBtn() {
    // eop-sel -> data[318] (1 => 7bar, 0 => 10bar per fillData)
    setDataValue(data, 318, parseInt($("#eop-sel").val()||0,10), 'uint8');
    setDataValue(data, 319, parseInt($("#fp-sel").val()||0,10), 'uint8');
    saveData();
}

// --- AFR calibration (afr-set-btn) ---
function afrSetBtn() {
    if (eInputs('afr-set-btn')) return;
    // afr 0v/5v are uint16 stored at low indexes 257 (0v low) and 259? From fillData: getInt(data[258],data[257])
    // low index for 0V is 257 (uint16 little-endian)
    setDataValue(data, 257, Math.round(parseFloat($("#afr-0v").val()||0) * 100), 'uint16');
    // afr-5v -> low index 259
    setDataValue(data, 259, Math.round(parseFloat($("#afr-5v").val()||0) * 100), 'uint16');
    // afr filter -> data[317]
    setDataValue(data, 317, parseInt($("#afr-filter").val()||0,10), 'uint8');

    saveData();
}

// --- Custom MAP (map-set-btn) ---
function mapSetBtn() {
    if (eInputs('map-set-btn')) return;
    // Custom MAP voltages: map-0v low index 300 (uint16 ADC counts)
    const v0 = parseFloat($("#map-0v").val() || 0);
    const v1 = parseFloat($("#map-1v").val() || 0);
    // Using same formula as mF/pF in code: storedCount = round( value / 0.0048828 )
    const factor = 0.0048828;
    setDataValue(data, 300, Math.round(v0 / factor), 'uint16');
    setDataValue(data, 302, Math.round(v1 / factor), 'uint16');

    // Custom MAP pressures: map-0p low index 304, map-1p low index 306 (uint16)
    setDataValue(data, 304, parseInt($("#map-0p").val()||0,10), 'uint16');
    setDataValue(data, 306, parseInt($("#map-1p").val()||0,10), 'uint16');

    saveData();
}

// --- OTHER (vlt-corr, multipliers, PIM) ---
function otherSetBtn() {
    if (eInputs('misc-set-btn')) return;
    // vlt-corr stored as data[263] = val * 100
    setDataValue(data, 263, Math.round(parseFloat($("#vlt-corr").val()||0) * 100), 'uint8');

    // speed / rpm multipliers stored as percentages (data[325], data[326]) and were displayed as /100
    setDataValue(data, 325, Math.round(parseFloat($("#spd-mult").val()||1) * 100), 'uint8');
    setDataValue(data, 326, Math.round(parseFloat($("#rpm-mult").val()||1) * 100), 'uint8');

    // PIM mode/data
    setDataValue(data, 262, parseInt($("#pim-mode").val()||0,10), 'uint8');
    setDataValue(data, 261, parseInt($("#pim-out").val()||0,10) + 100, 'uint8');

    // Motorhours mode
    setDataValue(data, 320, parseInt($("#mh-mode").val()), 'uint8'); // enable (fillData used 267)

    saveData();
}

// --- LOGIC PG0 / PG1 (basic fields) ---
const calcVal = (pgVar, selector) => {
    const varInt = parseInt(pgVar), value = $(selector).val();
    return varInt > 0 && varInt <= 6 ? parseInt(value) + 40 :
           varInt === 7 ? map(parseInt(value), 0, 100, 0, 147) :
           varInt === 20 ? parseInt(value) * 60 :
           [11,12,15,18].includes(varInt) ? parseFloat(value) * 100.01 : value;
};

// PG0 setter
function pg0SetBtn() {
    if (eInputs('pg0-set-btn')) return;
    let pg0_a_var = $("#pg0-a-var").val();
    let pg0_b_var = $("#pg0-b-var").val();
    let pg0_a_value = calcVal(pg0_a_var, "#pg0-a-value");
    let pg0_b_value = calcVal(pg0_b_var, "#pg0-b-value");

  try {
    // PG0 enable/state (server idx 267)
    setDataValue(data, 267, $("#pg0-status").prop("checked") ? 1 : 0, 'uint8');

    // PG0 variables / function / operands
    setDataValue(data, 269, parseInt($("#pg0-a-var").val() || 0, 10), 'uint8'); // var A (server 269)
    setDataValue(data, 270, parseInt($("#pg0-b-var").val() || 0, 10), 'uint8'); // var B (server 270)
    setDataValue(data, 271, parseInt($("#pg0-func").val()  || 0, 10), 'uint8'); // func (server 271)
    setDataValue(data, 272, parseInt($("#pg0-a-operand").val() || 0,10), 'uint8'); // operand A (server 272)
    setDataValue(data, 273, parseInt($("#pg0-b-operand").val() || 0,10), 'uint8'); // operand B (server 273)

    // PG0 Val A (uint16) at server index 274 (MCU 276..277)
    setDataValue(data, 274, pg0_a_value, 'uint16');

    // PG0 Val B (uint16) at server index 276 (MCU 278..279)
    setDataValue(data, 276, pg0_b_value, 'uint16');

    // Delays: PG0 delay (sec) server 278, start delay server 279
    setDataValue(data, 278, parseInt($("#pg0-off-delay").val() || 0, 10), 'uint8');
    setDataValue(data, 279, parseInt($("#pg0-on-delay").val() || 0, 10) * 25, 'uint8');
    setDataValue(data, 310, parseInt($("#pg0-on-limit").val() || 0, 10) * 25, 'uint8');

    // Additional PG0 flags:
    setDataValue(data, 312, $("#pg0-loop").prop("checked") ? 1 : 0, 'uint8');
    setDataValue(data, 321, $("#pg0-engine-run").prop("checked") ? 1 : 0, 'uint8');

    saveData();
  } catch (e) {
    xhrResponse(e);
    console.error("pg0SetBtn error:", e);
  }
}

// PG1 setter
function pg1SetBtn() {
    if (eInputs('pg1-set-btn')) return;
    let pg1_a_var = $("#pg1-a-var").val();
    let pg1_b_var = $("#pg1-b-var").val();
    let pg1_a_value = calcVal(pg1_a_var, "#pg1-a-value");
    let pg1_b_value = calcVal(pg1_b_var, "#pg1-b-value");

  try {
    // PG1 enable/state (server idx 268)
    setDataValue(data, 268, $("#pg1-status").prop("checked") ? 1 : 0, 'uint8');

    // PG1 variables / function / operands
    setDataValue(data, 280, parseInt($("#pg1-a-var").val() || 0, 10), 'uint8'); // var A (server 280)
    setDataValue(data, 281, parseInt($("#pg1-b-var").val() || 0, 10), 'uint8'); // var B (server 281)
    setDataValue(data, 282, parseInt($("#pg1-func").val()  || 0, 10), 'uint8'); // func (server 282)
    setDataValue(data, 283, parseInt($("#pg1-a-operand").val() || 0,10), 'uint8'); // operand A (server 283)
    setDataValue(data, 284, parseInt($("#pg1-b-operand").val() || 0,10), 'uint8'); // operand B (server 284)

    // PG1 Val A (uint16) at server index 285 (MCU 287..288)
    setDataValue(data, 285, pg1_a_value, 'uint16');

    // PG1 Val B (uint16) at server index 287 (MCU 289..290)
    setDataValue(data, 287, pg1_b_value, 'uint16');

    // Delays: PG1 delay server 289, start delay server 290
    setDataValue(data, 289, parseInt($("#pg1-off-delay").val() || 0, 10), 'uint8');
    setDataValue(data, 290, parseInt($("#pg1-on-delay").val() || 0, 10) * 25, 'uint8');
    setDataValue(data, 311, parseInt($("#pg1-on-limit").val() || 0, 10) * 25, 'uint8');

    // Additional PG1 flags:
    setDataValue(data, 313, $("#pg1-loop").prop("checked") ? 1 : 0, 'uint8');
    setDataValue(data, 322, $("#pg1-engine-run").prop("checked") ? 1 : 0, 'uint8');

    saveData();
  } catch (e) {
    xhrResponse(e);
    console.error("pg1SetBtn error:", e);
  }
}

// ================== CALIBRATION =======================
const CALIB_VOLT = {
  0: [0,2,4,6,8,10,12,14,16,18],
  1: [30,32,34,36,38,40,42,44,46,48],
  2: [60,62,64,66,68,70,72,74,76,78],
  3: [90,92,94,96,98,100,102,104,106,108],
  4: [120,122,124,126,128,130,132,134,136,138]
};

const CALIB_TEMP = {
  0: [20,21,22,23,24,25,26,27,28,29],
  1: [50,51,52,53,54,55,56,57,58,59],
  2: [80,81,82,83,84,85,86,87,88,89],
  3: [110,111,112,113,114,115,116,117,118,119],
  4: [140,141,142,143,144,145,146,147,148,149]
};

function f32(x) {
    return Math.fround(x);
}

function rConv(a) {
    const a_f = f32(parseInt(a, 10));
    return Math.floor(f32((f32(5.0) / f32(4700 + a_f)) * a_f * f32(100.0)) + 0.5);
}

function volt_calc_pos(pos) {
    const adc = f32(0.0048828125); // float32
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

    // ===== TEMPERATURE =====
    let t = temp[i];
    if (t < 0 || t > 150) {
      xhrResponse(`${localization.key['dialog.token.err']}: ${t}℃`);
      console.error("Temperature out of range:", t);
      return;
    }
    setDataValue(data, idxT[i], t & 0xFF, 'uint8'); // uint8

    // ===== VOLTAGE =====
    let sensor_voltage = 0;

    if (!isPullup) {
      // -------- V-mode ----------
      let volts = volt[i];
      if (volts < 0.01 || volts > 5) {
        console.error("Invalid volt:", volts);
        xhrResponse(`${localization.key['dialog.token.err']}: ${volts}V`);
        return;
      }
      const pos = Math.floor(f32(volts * 100.01));
      sensor_voltage = volt_calc_pos(pos);
    } else {
      // -------- R-mode ----------
      const ohm = volt[i];
      if (ohm < 1 || ohm > 99000) {
        xhrResponse(`${localization.key['dialog.token.err']}: ${ohm}ohm`);
        console.error("Invalid ohm:", ohm);
        return;
      }
      const pos_from_ohm = rConv(ohm);
      sensor_voltage = volt_calc_pos(pos_from_ohm);
    }

    if (sensor_voltage < 0 || sensor_voltage > 1023) {
      console.error("MCU sensor_voltage out of range:", sensor_voltage);
      return;
    }

    setDataValue(data, idxV[i], sensor_voltage & 0xFFFF, 'uint16');
  }

  saveData();
}

// =============
// === READY ===
// =============

$(document).ready(function() {
    fetchData();
    fillData();

    function createDebounce(delay = 3000) {
        const lockedButtons = new Set();

        return function(buttonId, callback) {
            return function(...args) {
                if (lockedButtons.has(buttonId)) {
                    return;
                }

                lockedButtons.add(buttonId);

                // Lock
                const $button = $(this);
                $button.prop('disabled', true);

                // Execute
                callback.apply(this, args);

                // Unlock
                setTimeout(() => {
                    lockedButtons.delete(buttonId);
                    $button.prop('disabled', false);
                }, delay);
            };
        };
    }

    const debounce = createDebounce(1000);

    // === BINDINGS
    //boost
    $("#boost-set-btn").on("click", debounce("boost-set-btn", boostSetBtn));
    $("#pid-set-btn").on("click", debounce("pid-set-btn", pidSetBtn));
    $("#boost-gear-set-btn").on("click", debounce("boost-gear-set-btn", boostGearSetBtn));

    //protection
    $("#p-set-btn").on("click", debounce("p-set-btn", protectionSetBtn));

    //fan
    $("#fan-set-btn").on("click", debounce("fan-set-btn", fanActSetBtn));
    $("#pwm-set-btn").on("click", debounce("pwm-set-btn", fanPwmSetBtn));
    $("#sw-set-btn").on("click", debounce("sw-set-btn", fanSwSetBtn));

    //logic
    $("#pg0-set-btn").on("click", debounce("pg0-set-btn", pg0SetBtn));
    $("#pg1-set-btn").on("click", debounce("pg1-set-btn", pg1SetBtn));

    //inputs
    $("#aux-set-btn").on("click", debounce("aux-set-btn", auxSetBtn));
    $("#bsx-set-btn").on("click", debounce("bsx-set-btn", bsxSetBtn));
    $("#vfd-set-btn").on("click", debounce("vfd-set-btn", vfdSetBtn));
    $("#ds-set-btn").on("click", debounce("ds-set-btn", dsSetBtn));

    //calibration
    $("#ps-set-btn").on("click", debounce("ps-set-btn", psSetBtn));
    $("#afr-set-btn").on("click", debounce("afr-set-btn", afrSetBtn));
    $("#map-set-btn").on("click", debounce("map-set-btn", mapSetBtn));
    $("#cal-aux-set-btn").on("click", debounce("cal-aux-set-btn", calibSetBtn));

    //other
    $("#pim-mode").on("change", checkPIM);
    $("#misc-set-btn").on("click", debounce("misc-set-btn", otherSetBtn));

    //config
    $("#config-upload-btn").on("click", debounce("config-upload-btn", cfgUpload));
    $("#config-download-btn").on("click", debounce("config-download-btn", cfgDownload));

    initChoicesSystem();
    setInterval(fetchData, POLLING_INTERVAL);
});
