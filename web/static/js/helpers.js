'use strict';

// Initial-session loading coordination, shared by the regular page and /share.
// Keep Flot/Leaflet containers in the layout while their content is hidden.
window.createSessionInitialLoad = function (mapRequired) {
    return {
        chartReady: false,
        mapReady: !mapRequired,
        finished: false,
        queued: false,
        tryReveal() {
            if (this.finished || this.queued || !this.chartReady || !this.mapReady) return;
            this.queued = true;
            const check = () => {
                if (typeof plot !== 'undefined' && plot &&
                    typeof plot.isDrawing === 'function' && plot.isDrawing()) {
                    requestAnimationFrame(check);
                    return;
                }
                requestAnimationFrame(() => {
                    this.finished = true;
                    document.documentElement.classList.remove('session-initial-loading');
                });
            };
            requestAnimationFrame(check);
        },
        markChartReady() {
            this.chartReady = true;
            this.tryReveal();
        },
        markMapReady() {
            this.mapReady = true;
            this.tryReveal();
        }
    };
};

function choicesReady() {
    document.documentElement.classList.remove('choices-pending');
}

let markerUpd = null;
let chartTooltip = () => {
    let previousPoint = null;
    $("#placeholder").bind("plothover plottouchmove", function (event, pos, item) {
        if($("#map").length) markerUpd(item);
    });
};

let sid = null;
let uid = null;
let sig = null;

let mapIndexStart = null;
let mapIndexEnd = null;

//Global select
let plotDataChoices = null;
let seshidtagChoices = null;

let chart_fill = localStorage.getItem(`${storageUserPrefix}-chart_fill`) === "true";
let chart_fillGradient = localStorage.getItem(`${storageUserPrefix}-chart_fillGradient`) === "true";
let chart_lineWidth = localStorage.getItem(`${storageUserPrefix}-chart_lineWidth`) || 2;

// Heading arrows toggle (persisted per-user)
let headingArrowsEnabled = (() => {
    try {
        const saved = localStorage.getItem(`${storageUserPrefix}-heading_arrows`);
        return saved === null ? true : saved !== 'false';
    } catch (e) {
        return true;
    }
})();

// Выносим ctime в глобальную область видимости, чтобы использовать везде
function ctime(t) {
    let date = new Date(t);
    if (isNaN(date.getTime())) return '';
    return date.toLocaleTimeString(Cookies.get('timeformat') == '12' ? 'en-US' : 'ru-RU');
}

// One reset path for double-click, long tap and Stream transitions.
// Rebuilding the slider only changes its handles; it does not fire slidechange,
// so the chart and map must explicitly be restored to the full session.
const handleSliderInit = () => {
    if (stream || !jsTimeMap.length) return;

    cutStart = null;
    cutEnd = null;
    mapIndexStart = 0;
    mapIndexEnd = jsTimeMap.length - 1;
    initSlider(jsTimeMap, jsTimeMap[0], jsTimeMap.at(-1));

    if (plot && typeof chartUpdRange === 'function' && flotData.length) {
        if (typeof plot.clearSelection === 'function') plot.clearSelection();
        // chartUpdRange uses inclusive sample indices.
        chartUpdRange(0, jsTimeMap.length - 1);
        updateSummaryTable();
    } else if (plot && typeof plot.clearSelection === 'function') {
        plot.clearSelection();
    }

    if (window.rawPath?.length && window.MapData?.flatCoords?.length &&
        typeof mapUpdRange === 'function') {
        updateMapWithRangePreservingHeatline(0, jsTimeMap.length - 1);
    }
};

$(document).ready(function(){
  $("#Chart-Container").on("dblclick", handleSliderInit);
  longTap("#Chart-Container", handleSliderInit);
  nogps = document.querySelector('#nogps');

  setInterval(()=>{
    if (Cookies.get('plot') !== undefined) {
        $('.live').css('display','block');
    } else {
        $('.live').css('display','none');
    }
  }, 5000);

  function checkNewSession() {
    if (Cookies.get('newsess') !== undefined) {
        $('.new-session').css('display','block');
    }
  }
  setInterval(checkNewSession, 1000);

  document.addEventListener("visibilitychange", () => {
    if (document.visibilityState === "visible") {
        checkNewSession();
        if (typeof syncUserTasksFromServer === 'function'
            && typeof l10n_loaded !== 'undefined'
            && l10n_loaded) {
            syncUserTasksFromServer();
        }
    }
  });

  PasswordToggle.initAll();
  ClearInput.initAll();
  document.querySelector('.storage-usage-img')?.addEventListener('click', () => xhrResponse(`${localization.key['stor.usage']} ${Cookies.get('storage_usage')}`));

  document.querySelectorAll('.clear-input__btn, .password-toggle__btn').forEach(el => {
    el.setAttribute('tabindex', '-1');
  });
});

let lastPlotUpdateTime = 0;
let animationPlotFrameId = null;
let nogps = null;

//Fetch plot data every 10 sec
function schedulePlotUpdate(timestamp) {
  if (timestamp - lastPlotUpdateTime >= 10000) {
    if (Cookies.get('plot') !== undefined) updatePlot();
    updateSessionDuration();
    lastPlotUpdateTime = timestamp;
  }
  animationPlotFrameId = requestAnimationFrame(schedulePlotUpdate);
}

function stopPlotUpdates() {
  if (animationPlotFrameId) {
    cancelAnimationFrame(animationPlotFrameId);
    animationPlotFrameId = null;
  }
  streamInteractToggle();
  if (plot !== null) plot.clearSelection();
}

function startPlotUpdates() {
  if (!animationPlotFrameId) {
    lastPlotUpdateTime = performance.now();
    animationPlotFrameId = requestAnimationFrame(schedulePlotUpdate);
  }
  streamInteractToggle();
}

function streamInteractToggle() {
  if (plot && plot.getOptions) {
    plot.getOptions().selection.mode = stream ? null : "x";
  }
  $(".slider-container").css("display", stream ? "none" : "block");
}

function updatePlot(callback) {
    updCharts();
    // Сохраняем выделенный временной диапазон (если есть), иначе берём полный
    let startTime, endTime;
    if (cutStart !== null && cutEnd !== null) {
        startTime = cutStart;
        endTime = cutEnd;
    } else {
        startTime = jsTimeMap[0];
        endTime = jsTimeMap.at(-1);
    }
    initSlider(jsTimeMap, startTime, endTime);
    if (callback && typeof callback === 'function') {
        setTimeout(callback);
    }
    window.chartRangeStart = 0;
    window.chartRangeEnd = jsTimeMap.length - 1;
}

//start of chart plotting js code
let plot = null; //definition of plot variable in script but outside doPlot function to be able to reuse as a controller when updating base data
let flotData = [];
let heatData = [];
let chartUpdRange = null;
let mapUpdRange = null;

// Determine the expected sampling cadence near each boundary. The telemetry
// rate can change within a session (10 ms to 30 s), so no fixed timeout works.
// Looking on both sides prevents a genuine rate change being seen as downtime.
function localCadence(intervals, index, direction) {
    const neighbors = [];
    for (let j = index + direction;
         j >= 1 && j < intervals.length && neighbors.length < 8;
         j += direction) {
        if (Number.isFinite(intervals[j]) && intervals[j] > 0) {
            neighbors.push(intervals[j]);
        }
    }
    if (!neighbors.length) return null;
    neighbors.sort((a, b) => a - b);
    // Lower median is resistant to another nearby outage, especially with
    // just two known intervals (one valid interval and one long pause).
    return neighbors[Math.floor((neighbors.length - 1) / 2)];
}

function processData(data) {
    // All series share the same transformed X axis. Work from source timestamps
    // every refresh (including Stream); never compress previously shifted data.
    const timestampSet = new Set();
    data.forEach(series => series.data.forEach(point => {
        if (Number.isFinite(point[0])) timestampSet.add(point[0]);
    }));

    const allTimestamps = Array.from(timestampSet).sort((a, b) => a - b);
    if (allTimestamps.length === 0) {
        return {
            processedData: data, realStartTime: null, realEndTime: null,
            processedStartTime: null, processedEndTime: null,
            realTimestamps: [], processedTimestamps: [],
            gaps: [], timeMapping: {}
        };
    }

    const intervals = new Array(allTimestamps.length);
    for (let i = 1; i < allTimestamps.length; i++) {
        intervals[i] = allTimestamps[i] - allTimestamps[i - 1];
    }

    const timeMapping = new Map([[allTimestamps[0], allTimestamps[0]]]);
    const gaps = [];
    let timeOffset = 0;
    let previousProcessed = allTimestamps[0];
    // Keep distinct Flot X coordinates and unambiguous reverse timestamps.
    const seamMs = 1;
    const minimumGapMs = 100; // protects fast sensors from timestamp jitter
    const cadenceMultiplier = 8; // distinguish a missing batch from jitter
    // The slowest supported telemetry cadence is one sample per 30 seconds.
    // With too few points to estimate a cadence, or with several long gaps
    // clustered together, never leave a multi-minute bridge on the chart.
    const maximumBridgeMs = 30000 * cadenceMultiplier;

    for (let i = 1; i < allTimestamps.length; i++) {
        const gap = intervals[i];
        if (gap > minimumGapMs) {
            // Quick path for sparse, steady rates (e.g. 1 sample / 30 s).
            // Two matching neighboring intervals are evidence of a valid
            // cadence, so avoid sorting local windows for every data point.
            const halfGap = gap / 2;
            const doubleGap = gap * 2;
            const stableBefore = i >= 3 &&
                intervals[i - 1] >= halfGap && intervals[i - 1] <= doubleGap &&
                intervals[i - 2] >= halfGap && intervals[i - 2] <= doubleGap;
            const stableAfter = i + 2 < intervals.length &&
                intervals[i + 1] >= halfGap && intervals[i + 1] <= doubleGap &&
                intervals[i + 2] >= halfGap && intervals[i + 2] <= doubleGap;
            if (gap <= maximumBridgeMs && (stableBefore || stableAfter)) {
                const compressed = allTimestamps[i] - timeOffset;
                timeMapping.set(allTimestamps[i], compressed);
                previousProcessed = compressed;
                continue;
            }
            const before = localCadence(intervals, i, -1);
            const after = localCadence(intervals, i, 1);
            // At least two neighboring intervals are needed to infer cadence.
            // With only two samples there is no evidence of a true interruption.
            const knownBefore = Math.min(8, i - 1);
            const knownAfter = Math.min(8, intervals.length - i - 1);
            const enoughEvidence = knownBefore + knownAfter >= 2 &&
                (before !== null || after !== null);
            // Prefer the slower side on a rate transition, so changing
            // from 100 Hz to 1/30 Hz does not collapse valid samples.
            const cadence = enoughEvidence ? Math.max(before || 0, after || 0) : maximumBridgeMs;
            const threshold = Math.min(maximumBridgeMs,
                Math.max(minimumGapMs, cadence * cadenceMultiplier));
            if (gap > threshold) {
                timeOffset += gap - seamMs;
                gaps.push({
                    start: previousProcessed,
                    end: allTimestamps[i] - timeOffset,
                    realStart: allTimestamps[i - 1],
                    realEnd: allTimestamps[i],
                    threshold
                });
            }
        }
        const compressed = allTimestamps[i] - timeOffset;
        timeMapping.set(allTimestamps[i], compressed);
        previousProcessed = compressed;
    }

    const newData = data.map(series => ({
        ...series,
        data: series.data.map(point => [timeMapping.get(point[0]) ?? point[0], ...point.slice(1)])
    }));

    const timeMappingObject = {};
    for (const timestamp of allTimestamps) {
        timeMappingObject[timeMapping.get(timestamp)] = timestamp;
    }

    return {
        processedData: newData,
        realStartTime: allTimestamps[0],
        realEndTime: allTimestamps[allTimestamps.length - 1],
        processedStartTime: timeMapping.get(allTimestamps[0]),
        processedEndTime: timeMapping.get(allTimestamps[allTimestamps.length - 1]),
        realTimestamps: allTimestamps,
        processedTimestamps: allTimestamps.map(t => timeMapping.get(t)),
        gaps, timeMapping: timeMappingObject
    };
}

function findNearestRealTime(processedTime) {
    if (!window.realTimeInfo || !window.realTimeInfo.timeMapping) {
        console.error("Time mapping is not available");
        return processedTime;
    }

    const timeMapping = window.realTimeInfo.timeMapping;

    if (!timeMapping._sortedProcessedTimes || !timeMapping._nearestTimesCache) {
        timeMapping._sortedProcessedTimes = window.realTimeInfo.processedTimes ||
            Object.keys(timeMapping).map(Number).sort((a, b) => a - b);
        timeMapping._nearestTimesCache = {};
    }

    const processedTimes = timeMapping._sortedProcessedTimes;
    const nearestTimesCache = timeMapping._nearestTimesCache;

    if (nearestTimesCache.hasOwnProperty(processedTime)) {
        return nearestTimesCache[processedTime];
    }

    let left = 0;
    let right = processedTimes.length - 1;
    while (left < right) {
        const mid = Math.floor((left + right) / 2);
        if (processedTimes[mid] < processedTime) {
            left = mid + 1;
        } else {
            right = mid;
        }
    }

    // Points on both sides of a stitched seam remain 1 ms apart. Return the
    // closest actual database timestamp, including for selection boundaries.
    const nearestIndex = left > 0 &&
        (left === processedTimes.length ||
         processedTime - processedTimes[left - 1] <= processedTimes[left] - processedTime)
        ? left - 1 : left;
    const nearestProcessedTime = processedTimes[nearestIndex];
    nearestTimesCache[processedTime] = timeMapping[nearestProcessedTime];
    return nearestTimesCache[processedTime];
}

function renderSparkline(container, values) {
    if (!container || !values || values.length === 0) {
        if (container) container.innerHTML = '';
        return;
    }

    let groupSize = Math.ceil(values.length / 100000);
    let averagedData = [];
    for (let i = 0; i < values.length; i += groupSize) {
        let group = values.slice(i, i + groupSize);
        let average = group.reduce((sum, value) => sum + value, 0) / group.length;
        averagedData.push(average);
    }

    const span = document.createElement('span');
    span.className = 'line';
    span.textContent = averagedData.join(',');
    container.innerHTML = '';
    container.appendChild(span);

    if (typeof $ !== 'undefined' && $.fn.peity) {
        $(span).peity('line', { width: '50' });
    }
}

function updateSummaryTable() {
    const tbody = document.querySelector('#Summary-Container table tbody');
    if (!tbody || !heatData) return;

    const heatMap = new Map();
    heatData.forEach(series => {
        heatMap.set(series.label, series.data);
    });

    const rows = tbody.querySelectorAll('tr');
    rows.forEach(row => {
        const cells = row.children;
        const label = cells[0].textContent.trim();
        const data = heatMap.get(label);

        if (data && data.length > 0) {
            // Single pass, no sorting and no enormous spread argument list.
            const values = [];
            let min = Infinity, max = -Infinity, sum = 0;
            for (const point of data) {
                const v = Array.isArray(point) ? point[1] : point;
                if (typeof v !== 'number' || !Number.isFinite(v)) continue;
                values.push(v);
                if (v < min) min = v;
                if (v > max) max = v;
                sum += v;
            }

            if (values.length > 0) {
                const mean = Math.round((sum / values.length) * 100) / 100;

                cells[1].textContent = min;
                cells[2].textContent = max;
                cells[3].textContent = mean;

                renderSparkline(cells[4], values);
            } else {
                cells[1].textContent = 'N/A';
                cells[2].textContent = 'N/A';
                cells[3].textContent = 'N/A';
                renderSparkline(cells[4], []);
            }
        } else {
            cells[1].textContent = 'N/A';
            cells[2].textContent = 'N/A';
            cells[3].textContent = 'N/A';
            renderSparkline(cells[4], []);
        }
    });
}

// Each metric can have its own number of samples. Trim by the shared X
// coordinate, not by the position inside a particular metric's data array.
// /plot returns newest-first (descending timestamps). Binary-search the two
// boundaries to avoid filtering 100k points per series on every slider move.
function sliceChartDataByTime(points, lower, upper) {
    if (!points.length) return points;
    const descending = points[0][0] >= points[points.length - 1][0];
    let left = 0, right = points.length;
    if (descending) {
        while (left < right) {
            const mid = (left + right) >>> 1;
            if (points[mid][0] > upper) left = mid + 1;
            else right = mid;
        }
        const start = left;
        right = points.length;
        while (left < right) {
            const mid = (left + right) >>> 1;
            if (points[mid][0] >= lower) left = mid + 1;
            else right = mid;
        }
        return points.slice(start, left);
    }
    // Preserve compatibility with ascending data from other data sources.
    while (left < right) {
        const mid = (left + right) >>> 1;
        if (points[mid][0] < lower) left = mid + 1;
        else right = mid;
    }
    const start = left;
    right = points.length;
    while (left < right) {
        const mid = (left + right) >>> 1;
        if (points[mid][0] <= upper) left = mid + 1;
        else right = mid;
    }
    return points.slice(start, left);
}

// The hover/touch guide is separate from the (removed) permanent gap markers.
// It follows the nearest data point and is disposed with each Flot instance.
function installChartHoverGuide(placeholder, chart) {
    const guide = $('<div>').addClass('chart-hover-guide').css({
        position: 'absolute',
        borderLeft: `${chart_lineWidth}px dashed rgba(0,0,0,.4)`,
        pointerEvents: 'none',
        display: 'none'
    }).appendTo(placeholder);

    let frame = null;
    const hide = () => {
        if (frame !== null) {
            cancelAnimationFrame(frame);
            frame = null;
        }
        guide.css('display', 'none');
    };

    const onHover = (event, pos, item) => {
        if (!item || !Number.isFinite(item.pageX)) {
            hide();
            return;
        }
        if (frame !== null) cancelAnimationFrame(frame);
        const pageX = item.pageX;
        frame = requestAnimationFrame(() => {
            frame = null;
            const offset = placeholder.offset();
            const plotOffset = chart.getPlotOffset();
            const x = pageX - offset.left;
            const height = Math.max(0, placeholder.height() - plotOffset.top - plotOffset.bottom);
            if (x < plotOffset.left || x > placeholder.width() - plotOffset.right || height === 0) {
                hide();
                return;
            }
            guide.css({
                left: x + 'px',
                top: plotOffset.top + 'px',
                height: height + 'px',
                display: 'block'
            });
        });
    };

    placeholder.on('plothover.chartHoverGuide plottouchmove.chartHoverGuide', onHover);
    placeholder.on('mouseleave.chartHoverGuide touchend.chartHoverGuide touchcancel.chartHoverGuide', hide);
    chart.hooks.shutdown.push(() => {
        hide();
        placeholder.off('.chartHoverGuide');
        guide.remove();
    });
}

function doPlot(position) {
    //Remove plot presence
    if (plot) {
        $("#placeholder").unbind("plothover plottouchmove plotselected");
        plot.shutdown();
        $("#placeholder").empty();
    }

    const useSideLegend =
        window.matchMedia('(min-width: 55em)').matches &&
        $('#legend-container').length > 0;

    //asigned the plot to a new variable and new function to update the plot in realtime when using the slider
    chartUpdRange = (a, b) => {
        window.chartRangeStart = a;
        window.chartRangeEnd = b;
        const compressedTimes = window.realTimeInfo?.processedTimes || [];
        const n = compressedTimes.length;
        if (!n) return;
        // Chart indices are newest-first; real timestamps in jsTimeMap are
        // oldest-first. Keep both endpoints inclusive for all metric series.
        const startIndex = Math.max(0, n - 1 - b);
        const endIndex = Math.min(n - 1, n - 1 - a);
        const lower = compressedTimes[startIndex];
        const upper = compressedTimes[endIndex];
        const dataSet = flotData.map(series => ({
            ...series,
            data: sliceChartDataByTime(series.data, lower, upper)
        }));
        plot.setData(dataSet);
        plot.setupGrid(); // Required when the selection changes axis ranges.
        plot.draw();
        heatData = dataSet;
    };
    plot = $.plot("#placeholder", flotData, {
        xaxes: [ {
            mode: "time",
            timezone: "browser",
            axisLabel: ' ',
            tickFormatter: function(val, axis) {
                // The X axis is compressed; clock labels MUST come from the
                // original session timestamps, never from compressed X values.
                if (!window.realTimeInfo?.timeMapping) return '';
                const actualTime = findNearestRealTime(val);
                if (!Number.isFinite(actualTime)) return '';
                const locale = Cookies.get('timeformat') === '12' ? 'en-US' : 'ru-RU';
                const range = (axis?.max ?? 0) - (axis?.min ?? 0);
                const spanReal = window.realTimeInfo.end - window.realTimeInfo.start;
                const options = {hour: '2-digit', minute: '2-digit'};
                if (range < 120000) options.second = '2-digit';
                if (range < 2000) options.fractionalSecondDigits = 3;
                if (spanReal >= 86400000) {
                    options.month = '2-digit';
                    options.day = '2-digit';
                }
                return new Date(actualTime).toLocaleString(locale, options);
            }
        } ],
        yaxes: [ { axisLabel: "", smallNegativeThreshold: 1 }, {
            alignTicksWithAxis: position == "right" ? 1 : null,
            position: position,
            axisLabel: "",
            smallNegativeThreshold: 1
        } ],
        legend: {
            position: "nw",
            hideable: true,
            margin: 2,
            container: useSideLegend ? '#legend-container' : null,
        },
        selection: { mode: "x" },
        grid: {
            touchmove: true,
            mouseActiveRadius: 100,
            hoverable: true,
            clickable: false,
            borderWidth: 0
        },
        series: {
            points: {
                radius: parseFloat(chart_lineWidth)
            },
            lines: {
                fill: chart_fill,
                lineWidth: chart_lineWidth,
                gradient: chart_fillGradient
            },
            shadowSize: chart_lineWidth
        }
    });

    installChartHoverGuide($("#placeholder"), plot);
    chartTooltip();
    //Trim by plot Select
    $("#placeholder").bind("plotselected", (evt,range)=>{
        if (stream) return; //Disable trim
        // Convert range to real time markers
        const realFrom = findNearestRealTime(range.xaxis.from);
        const realTo = findNearestRealTime(range.xaxis.to);

        // Find jsTimeMap indexes with edge case handling
        const origA = jsTimeMap.findIndex(t => t >= realFrom);
        const origB = jsTimeMap.findIndex(t => t >= realTo);

        // Handle edge cases and prepare final values
        const a = (origA === -1 || realFrom <= jsTimeMap[0] || origA <= 1) ? 0 : origA;
        const b = (origB === -1) ? jsTimeMap.length - 1 : origB;

        if (Math.abs(a-b) < 3) return;

        // Set slider values
        $("#slider-range11").slider('values', 0, a);
        $("#slider-range11").slider('values', 1, b);
        $("#slider-time").val((new Date(jsTimeMap[a])).toLocaleTimeString(Cookies.get('timeformat') == '12' ? 'en-US' : 'ru-RU') + " - " + (new Date(jsTimeMap[b])).toLocaleTimeString(Cookies.get('timeformat') == '12' ? 'en-US' : 'ru-RU'));

        mapIndexStart = jsTimeMap.length - b - 1;
        mapIndexEnd = jsTimeMap.length - a - 1;

        // Сохраняем выделенный временной диапазон в глобальные переменные
        cutStart = jsTimeMap[a];
        cutEnd = jsTimeMap[b];

        if($("#map").length) {
            updateMapWithRangePreservingHeatline(mapIndexStart, mapIndexEnd);
        }

        chartUpdRange(jsTimeMap.length - b - 1, jsTimeMap.length - a - 1);
        plot.clearSelection();
    });
    //End Trim by plot Select

    plot.hooks.draw.push(function (plot) {
        const $side = $('#legend-container');
        if ($side.length) {
            $side.css('max-height', plot.getPlaceholder().height() + 'px');
        }

        const $inline = plot.getPlaceholder().find('.legend');
        if ($inline.length) {
            const off = plot.getPlotOffset();
            const maxH = plot.getPlaceholder().height() - off.top - off.bottom - 10;
            $inline.css('max-height', Math.max(60, maxH) + 'px');
        }
    });
}

let __legendBreakpointTimer = null;
let __legendLastMode = window.matchMedia('(min-width: 55em)').matches ? 'side' : 'overlay';

window.addEventListener('resize', () => {
    clearTimeout(__legendBreakpointTimer);
    __legendBreakpointTimer = setTimeout(() => {
        const mode = window.matchMedia('(min-width: 55em)').matches ? 'side' : 'overlay';
        if (mode === __legendLastMode) return;
        __legendLastMode = mode;

        if (typeof plot !== 'undefined' && plot && flotData.length) {
            doPlot("right");
            const slider = $("#slider-range11");
            if (slider.hasClass("ui-slider") && jsTimeMap.length) {
                const v0 = slider.slider("values", 0);
                const v1 = slider.slider("values", 1);
                chartUpdRange(jsTimeMap.length - v1 - 1, jsTimeMap.length - v0 - 1);
            }
        }
    }, 200);
});

let updCharts = (last = false)=>{
    const plotDataSelected = plotDataChoices.getValue(true);
    const seshidtagValue = seshidtagChoices?.getValue(true) ?? sid;

    if (plotDataSelected.length === 0) {
        const noChart = $('<div>',{class:'chart-label'}).append($('<span>',{class:'label label-warning'}).html(localization.key['novar'] ?? 'No Variables Selected to Plot'));
        if ($('#placeholder')[0]!=undefined) {//clean our plot if it exists
            flotData = [];
            heatData = [];
            plot.shutdown();
        }
        $('#Chart-Container').empty();
        $('#Chart-Container').append(noChart);
        $('#Summary-Container').empty();
        if (!last && window.sessionInitialLoad) window.sessionInitialLoad.markChartReady();
    } else {
        // During the initial session load, the centered session loader is the
        // only progress indicator. The navbar spinner is for later requests.
        if (!document.documentElement.classList.contains('session-initial-loading')) {
            $(".fetch-data").css("display", "block");
        }
        let varPrm = null;
        if (sid && uid && sig) {
            const gpsParam = (typeof SHARE_CONFIG !== 'undefined' && SHARE_CONFIG.gps) ? SHARE_CONFIG.gps : '0';
            varPrm = `/plot?id=${sid}&uid=${uid}&sig=${sig}&gps=${gpsParam}`;
        } else {
            varPrm = last ? `/plot?last&id=${seshidtagValue}` : `/plot?id=${seshidtagValue}`;
        }
        plotDataSelected.forEach((v,i) => varPrm += `&s${i+1}=${v}`);
        fetch(varPrm).then(d => d.json()).then(gData => {
            if (last) {
                $(".fetch-data").css("display", "none");

                function updateHeatData(gData) {
                  const heatDataMap = {};
                  heatData.forEach(item => {
                    heatDataMap[item.label] = item;
                  });

                  gData.forEach(item => {
                    const label = item[1];
                    const data = item[2].map(a => [parseInt(a[0]), a[1]]);

                    if (!heatDataMap[label]) {
                      const newItem = {
                        label: label,
                        data: data
                      };
                      heatData.push(newItem);
                      heatDataMap[label] = newItem;
                    } else {
                      heatDataMap[label].data = heatDataMap[label].data.concat(data);
                    }
                  });
                }
                updateHeatData(gData);
                return;
            }
            flotData = [];
            $(".fetch-data").css("display", "none");
            gData.forEach(v => flotData.push({label: v[1], data: v[2].map(a => [parseInt(a[0]), a[1]])}));

            // Processing data to remove time gaps on merged sessions
            let processedResult = processData(flotData);
            flotData = processedResult.processedData;

            window.realTimeInfo = {
              start: processedResult.realStartTime,
              end: processedResult.realEndTime,
              processedStart: processedResult.processedStartTime,
              processedEnd: processedResult.processedEndTime,
              timeMapping: processedResult.timeMapping,
              processedTimes: processedResult.processedTimestamps
            };

            // The slider, map, and selection use original database timestamps.
            jsTimeMap = processedResult.realTimestamps;
            if (jsTimeMap.length === 0) {
                if (plot) {
                    plot.shutdown();
                    plot = null;
                }
                flotData = [];
                heatData = [];
                cutStart = cutEnd = null;
                $('#Chart-Container').empty().append(
                    $('<div>', {class: 'chart-label'}).append(
                        $('<span>', {class: 'label label-warning', text: localization.key['nodata'] ?? 'No data'})
                    )
                );
                $('#Summary-Container').empty();
                if (window.sessionInitialLoad) window.sessionInitialLoad.markChartReady();
                return;
            }

            // Если ранее было выделение (cutStart/cutEnd заданы), пересчитываем индексы для нового jsTimeMap
            if (cutStart !== null && cutEnd !== null) {
                let newStartIdx = jsTimeMap.findIndex(t => t >= cutStart);
                let newEndIdx = jsTimeMap.findIndex(t => t >= cutEnd);
                if (newStartIdx === -1) newStartIdx = 0;
                if (newEndIdx === -1) newEndIdx = jsTimeMap.length - 1;
                // Прижимаем к границам, если нужно
                newStartIdx = Math.min(newStartIdx, jsTimeMap.length - 1);
                newEndIdx = Math.min(newEndIdx, jsTimeMap.length - 1);
                // Обновляем глобальные индексы карты (инвертированные)
                mapIndexStart = jsTimeMap.length - newEndIdx - 1;
                mapIndexEnd = jsTimeMap.length - newStartIdx - 1;

                // Обновляем слайдер, если он уже инициализирован
                let slider = $("#slider-range11");
                if (slider.hasClass("ui-slider")) {
                    slider.slider("option", "max", jsTimeMap.length - 1);
                    slider.slider("values", [newStartIdx, newEndIdx]);
                    $("#slider-time").val(ctime(jsTimeMap[newStartIdx]) + " - " + ctime(jsTimeMap[newEndIdx]));
                    $("#slider-time").attr("sv0", jsTimeMap[newStartIdx]);
                    $("#slider-time").attr("sv1", jsTimeMap[newEndIdx]);
                }
            } else {
                // Нет выделения – сбрасываем индексы карты на полный диапазон
                mapIndexStart = 0;
                mapIndexEnd = jsTimeMap.length - 1;
            }

            if ($('#placeholder')[0]==undefined) { //this would only be true the first time we load the chart
                $('#Chart-Container').empty();
                $('#Chart-Container').append(
                    $('<div>', { class: 'chart-with-legend' }).append(
                        $('<div>', { id: 'legend-container' }),
                        $('<div>', { class: 'demo-container' }).append(
                            $('<div>', { id: 'placeholder', class: 'demo-placeholder' })
                        )
                    )
                );
                doPlot("right");
            }
            // chartUpdRange() below calls setData(), setupGrid() and draw()
            // exactly once. Do not pre-process flotData a second time here:
            // this path is also used by the 10-second real-time stream poll.
            //always update the chart trimmed range when plotting new data
            // The slider is hidden while streaming and may contain old bounds.
            // Always show every point, including samples received after activation.
            const [a, b] = stream
                ? [0, jsTimeMap.length - 1]
                : [jsTimeMap.length - $('#slider-range11').slider("values", 1) - 1,
                   jsTimeMap.length - $('#slider-range11').slider("values", 0) - 1];
            chartUpdRange(a, b);
            updateSummaryTable();
            if (typeof currentDataSource !== 'undefined' && currentDataSource !== null && heatData && heatData[currentDataSource]) {
                updateHotline(currentDataSource);
            }
            //this updates the whole summary table
            $('#Summary-Container').empty();
            $('#Summary-Container').append($('<div>',{class:'table-responsive'}).append($('<table>',{class:'table table-sum'}).append($('<thead>').append($('<tr>'))).append('<tbody>')));
            // Create table headers
            const headers = [localization.key['datasum.name'], localization.key['datasum.min'], localization.key['datasum.max'], localization.key['datasum.mean'], localization.key['datasum.sparkline']];
            const thead = document.querySelector('#Summary-Container>div>table>thead>tr');
            const headerFragment = document.createDocumentFragment();
            headers.forEach(v => {
                const th = document.createElement('th');
                th.textContent = v;
                headerFragment.appendChild(th);
            });
            thead.appendChild(headerFragment);

            // Create string pattern for table
            const trTemplate = document.createElement('tr');
            for (let i = 0; i < 5; i++) {
                const td = document.createElement('td');
                if (i === 4) {
                    const span = document.createElement('span');
                    span.className = 'line';
                    td.appendChild(span);
                }
                trTemplate.appendChild(td);
            }

            // Fill table data
            const tbody = document.querySelector('#Summary-Container>div>table>tbody');
            const rowFragment = document.createDocumentFragment();

            gData.forEach(v => {
                const tr = trTemplate.cloneNode(true);
                const tds = tr.children;
                tds[0].textContent = v[1];
                tds[1].textContent = v[5];
                tds[2].textContent = v[4];
                tds[3].textContent = v[6];
                tds[4].querySelector('.line').textContent = v[3];

                if (v[0] === 'k21fa') {
                    const minCell = tds[1];
                    const maxCell = tds[2];

                    minCell.style.cursor = 'pointer';
                    maxCell.style.cursor = 'pointer';

                    minCell.addEventListener('click', () => {
                        const val = parseFloat(minCell.textContent);
                        if (!isNaN(val)) {
                            xhrResponse(calculate(val));
                        }
                    });

                    maxCell.addEventListener('click', () => {
                        const val = parseFloat(maxCell.textContent);
                        if (!isNaN(val)) {
                            xhrResponse(calculate(val));
                        }
                    });
                }

                rowFragment.appendChild(tr);
            });

            tbody.appendChild(rowFragment);
            $('.line').each(function() {
                // We get data from the element as an array of numbers
                let data = $(this).text().split(',').map(Number);

                // Determine the size of the group for averaging
                let groupSize = Math.ceil(data.length / 100000);

                // We average the data across groups
                let averagedData = [];
                for (let i = 0; i < data.length; i += groupSize) {
                    let group = data.slice(i, i + groupSize);
                    let average = group.reduce((sum, value) => sum + value, 0) / group.length;
                    averagedData.push(average);
                }

                // Forming a line for Peity from averaged data
                let averagedDataString = averagedData.join(',');

                // Update the element and apply Peity
                $(this).text(averagedDataString);
                $(this).peity('line', { width: '50' });
            });
            if (window.sessionInitialLoad) window.sessionInitialLoad.markChartReady();
        }).catch(err => {
            const noChart = $('<div>',{class:'chart-label'}).append($('<span>',{class:'label label-warning'}).html(localization.key['nodata'] ?? 'No data'));
            $('#Chart-Container').empty();
            $('#Chart-Container').append(noChart);
            $('#Summary-Container').empty();
            $(".fetch-data").css("display", "none");
            console.error(err);
            if (!last && window.sessionInitialLoad) window.sessionInitialLoad.markChartReady();
        });
    }
}
//End of chart plotting js code

//Start of Leaflet Map Providers js code
// ======================= Helper function for segment splitting =======================
function extractValidSegmentsWithIndices(coords, options = {}) {
    const {
        maxStep = 0.003,          // distance between adjacent points to cut (~330 m)
        maxMergeDist = 0.002,     // if the end of one segment and start of the next are closer than this – merge them
        minPoints = 5,            // minimum number of points in a segment (fewer – delete)
        minDistance = 0.0001,     // minimum displacement within a segment (to filter static points)
        staticVariance = 1e-12    // variance threshold (below – static)
    } = options;

    if (!coords.length) return [];

    const dist = (p1, p2) => {
        const dlat = p1[0] - p2[0];
        const dlng = p1[1] - p2[1];
        return Math.sqrt(dlat * dlat + dlng * dlng);
    };

    // 1. Find indices of breaks (distance > maxStep)
    const breaks = [];
    for (let i = 1; i < coords.length; i++) {
        if (dist(coords[i - 1], coords[i]) > maxStep) {
            breaks.push(i - 1);
        }
    }

    // 2. Cut into raw segments, preserving original indices
    const rawSegments = [];
    let start = 0;
    for (const b of breaks) {
        const slice = coords.slice(start, b + 1);
        rawSegments.push(slice.map((coord, idx) => ({ coord, index: start + idx })));
        start = b + 1;
    }
    const lastSlice = coords.slice(start);
    rawSegments.push(lastSlice.map((coord, idx) => ({ coord, index: start + idx })));

    // 3. Merge neighboring segments if the distance between them is small
    const mergedSegments = [];
    let currentSegment = rawSegments[0];
    for (let i = 1; i < rawSegments.length; i++) {
        const lastPoint = currentSegment[currentSegment.length - 1].coord;
        const firstPoint = rawSegments[i][0].coord;
        if (dist(lastPoint, firstPoint) <= maxMergeDist) {
            // Merge: add the points of the next segment to the current one
            currentSegment = currentSegment.concat(rawSegments[i]);
        } else {
            // Gap is large enough – save the current segment and start a new one
            mergedSegments.push(currentSegment);
            currentSegment = rawSegments[i];
        }
    }
    mergedSegments.push(currentSegment);

    // 4. Filter merged segments: remove too short ones and static segments
    const validSegments = mergedSegments.filter(seg => {
        if (seg.length < minPoints) return false;

        const first = seg[0].coord;
        const last = seg[seg.length - 1].coord;
        const totalDist = dist(first, last);
        if (totalDist < minDistance) {
            let variance = 0;
            for (const p of seg) {
                variance += Math.pow(p.coord[0] - first[0], 2) + Math.pow(p.coord[1] - first[1], 2);
            }
            variance /= seg.length;
            if (variance < staticVariance) return false; // pure static
            // otherwise – a real stop, keep it
        }
        return true;
    });

    return validSegments; // array of segments [{coord, index}]
}

// GPS data model. All times are ORIGINAL log times (milliseconds), not Flot's
// compressed X values. Points arrive newest-first from the SQL query.
// No distance-based merging is allowed across an invalid fix or a time gap.
function gpsMedianInterval(points) {
    const diffs = [];
    for (let i = 1; i < points.length; i++) {
        const a = points[i - 1], b = points[i];
        if (Number(a[4]) !== Number(b[4])) continue;
        const dt = Math.abs(Number(a[3]) - Number(b[3]));
        if (Number.isFinite(dt) && dt > 0) diffs.push(dt);
    }
    if (!diffs.length) return 1000;
    diffs.sort((a, b) => a - b);
    return diffs[Math.floor(diffs.length / 2)];
}

function gpsDistanceMeters(a, b) {
    const rad = Math.PI / 180;
    const lat = (a[0] + b[0]) / 2 * rad;
    const x = (a[1] - b[1]) * rad * Math.cos(lat);
    const y = (a[0] - b[0]) * rad;
    return 6371000 * Math.hypot(x, y);
}

// Optional, non-destructive GPS anomaly filter. Never edit rawPath or chart data.
// The ECU PID `kd` is the raw OBD vehicle speed in km/h, not GPS speed.
const GPS_FILTER_MAX_MPS = 105;   // hard physical ceiling, with position jitter tolerance
const GPS_FILTER_JITTER_M = 55;
// Stage 1: uncertain relocated tracks stay quarantined until supported by a
// short, coherent series. A long blackout is *not* negative evidence: a brief
// track after one can still be the start of a genuine new journey.
const GPS_FILTER_CONFIRM_FIXES = 8;
const GPS_FILTER_CONFIRM_MS = 20000;
const GPS_FILTER_UNCERTAIN_BLACKOUT_MS = 180000;
const GPS_FILTER_REMOTE_ISLAND_M = 800;
const GPS_FILTER_ISOLATED_FIXES = 8;
const GPS_FILTER_ISOLATED_MS = 20000;
const GPS_FILTER_SHORT_RELOCATION_GAP_MS = 60000;
const GPS_FILTER_BOOMERANG_WINDOW_MS = 600000;
// Validation-only grouping: GPS-source breaks are NEVER merged in the map.
// Consecutive components at almost the same location may form one false island.
const GPS_FILTER_GROUP_GAP_MS = 120000;
const GPS_FILTER_GROUP_DISTANCE_M = 250;
// Stage 2: Off / Smart / Strict. Migrate the old boolean preference once,
// without accidentally enabling a stricter filter for existing users.
const GPS_FILTER_MODES = ['off', 'smart', 'strict'];
let gpsFilterMode = (() => {
    try {
        const stored = localStorage.getItem(`${storageUserPrefix}-gps_filter_mode`);
        if (GPS_FILTER_MODES.includes(stored)) return stored;
        return localStorage.getItem(`${storageUserPrefix}-gps_hybrid_filter`) === 'true'
            ? 'smart' : 'off';
    } catch (_) { return 'off'; }
})();
let gpsFilterEnabled = gpsFilterMode !== 'off';
let gpsFilterCache = null;
let gpsFilterDiagnostics = null;

function gpsObdSpeed(point) {
    const v = point?.[5];
    if (v === null || v === undefined || v === '' || !Number.isFinite(Number(v))) return null;
    const speed = Number(v);
    return speed >= 0 && speed <= 400 ? speed : null;
}

// GPS receiver Speed (GPS) kff1001, same timestamp as Speed (OBD) kd.
// Missing GPS speed must NOT be interpreted as zero and does not change
// behaviour of existing recordings until their SQL cache is refreshed.
function gpsReportedSpeed(point) {
    const v = point?.[6];
    if (v === null || v === undefined || v === '' || !Number.isFinite(Number(v))) return null;
    const speed = Number(v);
    // Retain extreme but finite receiver readings as anomaly evidence;
    // they are NEVER used as a permitted vehicle speed.
    return speed >= 0 ? speed : null;
}

// Compare independent instruments, never use GPS-reported speed as truth for
// vehicle motion. Acceleration is km/h per second, so 25%-sampled sessions
// and variable recording intervals are assessed on the actual time delta.
const GPS_SPEED_TOLERANCE_KMH = 10;
const GPS_SPEED_ACCEL_KMH_PER_S = 40;
function gpsSpeedEvidence(points, maxGapMs) {
    let mismatches = 0, jumps = 0, frozen = 0, samples = 0;
    const suspicious = new Set();
    // Mark individual receiver values as suspicious only when OBD exists.
    // GPS and OBD may lag during an authentic acceleration; such a flag is
    // *evidence*, never sufficient reason to delete a coordinate.
    for (let i = 0; i < points.length; i++) {
        const p = points[i];
        if (!p.valid || p.speed === null || p.gpsSpeed === null) continue;
        samples++;
        // Accept a brief receiver/ECU sampling lag at genuine acceleration:
        // compare with adjacent OBD samples only when their timestamps are
        // within 1.5 sec. Missing samples are never extrapolated over gaps.
        let agrees = Math.abs(p.gpsSpeed - p.speed) <= GPS_SPEED_TOLERANCE_KMH;
        if (!agrees) {
            for (const n of [i - 1, i + 1]) {
                const near = points[n];
                if (near && near.valid && near.speed !== null &&
                    Math.abs(near.time - p.time) <= 1500 &&
                    Math.abs(p.gpsSpeed - near.speed) <= GPS_SPEED_TOLERANCE_KMH) {
                    agrees = true;
                    break;
                }
            }
        }
        if (!agrees) {
            mismatches++;
            suspicious.add(p.originalIndex);
        }
        if (!i) continue;
        const prev = points[i - 1];
        const dt = (p.time - prev.time) / 1000;
        if (!prev.valid || prev.gpsSpeed === null || prev.speed === null ||
            !(dt > 0 && dt * 1000 <= maxGapMs) || p.segment !== prev.segment) continue;
        const gpsAccel = Math.abs(p.gpsSpeed - prev.gpsSpeed) / dt;
        const obdAccel = Math.abs(p.speed - prev.speed) / dt;
        if (gpsAccel > GPS_SPEED_ACCEL_KMH_PER_S &&
            gpsAccel > obdAccel * 2 + 15) {
            jumps++;
            suspicious.add(p.originalIndex);
            suspicious.add(prev.originalIndex);
        }
    }
    // A truly frozen GPS speed must remain flat for the *entire* window.
    // OBD must vary by >=20 km/h while GPS varies by <=0.5 km/h over >=8s.
    // Four monotonic deques keep this O(N), including high-rate recordings.
    let start = 0;
    const makeDeque = () => ({ data: [], head: 0 });
    const minObd = makeDeque(), maxObd = makeDeque();
    const minGps = makeDeque(), maxGps = makeDeque();
    const all = [minObd, maxObd, minGps, maxGps];
    const add = (deque, index, read, min) => {
        const value = read(points[index]);
        while (deque.data.length > deque.head &&
            (min ? read(points[deque.data[deque.data.length - 1]]) >= value :
                read(points[deque.data[deque.data.length - 1]]) <= value)) {
            deque.data.pop();
        }
        deque.data.push(index);
    };
    const expire = deque => {
        while (deque.head < deque.data.length && deque.data[deque.head] < start) deque.head++;
        if (deque.head >= 1024 && deque.head * 2 >= deque.data.length) {
            deque.data = deque.data.slice(deque.head);
            deque.head = 0;
        }
    };
    const reset = () => {
        for (const d of all) { d.data.length = 0; d.head = 0; }
    };
    for (let i = 0; i < points.length; i++) {
        const p = points[i];
        const prior = i ? points[i - 1] : null;
        const consecutive = prior && p.valid && prior.valid &&
            p.segment === prior.segment && p.time > prior.time &&
            p.time - prior.time <= maxGapMs &&
            p.speed !== null && prior.speed !== null &&
            p.gpsSpeed !== null && prior.gpsSpeed !== null;
        if (!consecutive) { start = i; reset(); }
        if (!p.valid || p.speed === null || p.gpsSpeed === null) continue;
        add(minObd, i, q => q.speed, true);
        add(maxObd, i, q => q.speed, false);
        add(minGps, i, q => q.gpsSpeed, true);
        add(maxGps, i, q => q.gpsSpeed, false);
        while (p.time - points[start].time > 20000) start++;
        for (const d of all) expire(d);
        if (i - start + 1 < 6 || p.time - points[start].time < 8000) continue;
        const obdRange = points[maxObd.data[maxObd.head]].speed -
            points[minObd.data[minObd.head]].speed;
        const gpsRange = points[maxGps.data[maxGps.head]].gpsSpeed -
            points[minGps.data[minGps.head]].gpsSpeed;
        const gpsLow = points[minGps.data[minGps.head]].gpsSpeed;
        const obdLow = points[minObd.data[minObd.head]].speed;
        // Also catch a receiver stuck at 0 while OBD steadily reports
        // motion. A genuinely constant non-zero GPS speed during cruise
        // remains valid unless OBD speed itself changes substantially.
        // A frozen *high* receiver speed while the ECU reports a sustained
        // standstill is also a frozen sensor. The previous test considered
        // only GPS stuck near zero, missing the GPS=96 / OBD=0 case.
        const obdHigh = points[maxObd.data[maxObd.head]].speed;
        if (gpsRange <= 0.5 && (obdRange >= 20 ||
            (gpsLow <= 2 && obdLow >= 15) ||
            (gpsLow >= 20 && obdHigh <= 2))) {
            frozen++;
            suspicious.add(p.originalIndex);
        }
    }
    return { suspicious, stats: { gpsSpeedSamples: samples,
        gpsSpeedMismatch: mismatches, gpsSpeedJumps: jumps,
        gpsSpeedFrozen: frozen, gpsSpeedRejected: 0 } };
}

// An isolated middle GPS coordinate may look plausible to the conservative
// 55m jitter floor in Stage 1, even when it is far beyond the distance the
// ECU recorded. Remove it ONLY if both incoming and outgoing edges are
// impossible by OBD, the surrounding fixes reconnect, and GPS speed itself
// also contradicts OBD. In all other cases, keep the genuine coordinate.
function gpsRejectSpeedInconsistentSpikes(points, accepted, evidence, maxGapMs, mode) {
    const strict = mode === 'strict';
    let removed = 0;
    const edgeLimit = (a, b) => {
        const dt = (b.time - a.time) / 1000;
        if (!(dt > 0 && dt * 1000 <= Math.min(maxGapMs, 12000)) ||
            a.segment !== b.segment || a.speed === null || b.speed === null) return null;
        return (strict ? 12 : 20) +
            dt * (Math.max(a.speed, b.speed) + (strict ? 15 : 25)) / 3.6;
    };
    for (let i = 1; i + 1 < points.length; i++) {
        const a = points[i - 1], b = points[i], c = points[i + 1];
        if (!accepted.has(a.originalIndex) || !accepted.has(b.originalIndex) ||
            !accepted.has(c.originalIndex) ||
            !evidence.suspicious.has(b.originalIndex) ||
            a.segment !== b.segment || b.segment !== c.segment) continue;
        const left = edgeLimit(a, b), right = edgeLimit(b, c);
        const through = edgeLimit(a, c);
        if (left === null || right === null || through === null) continue;
        const ab = gpsDistanceMeters(a.coord, b.coord);
        const bc = gpsDistanceMeters(b.coord, c.coord);
        const ac = gpsDistanceMeters(a.coord, c.coord);
        if (ab > left && bc > right && ac <= through &&
            ac < Math.min(ab, bc) * (strict ? 0.85 : 0.7)) {
            accepted.delete(b.originalIndex);
            removed++;
        }
    }
    return removed;
}

// Second-pass cluster validation. The first pass catches single teleports
// within a continuous track. This pass looks at small independent GPS islands
// that slipped through after missing fixes/source-segment changes. All tests
// use original time and coordinates; none infer roads or edit rawPath.
function gpsRejectIsolatedIslands(chronological, accepted, maxGapMs) {
    const blocks = [];
    let block = null;
    for (const point of chronological) {
        if (!point.valid || !accepted.has(point.originalIndex)) {
            block = null;
            continue;
        }
        const prev = block?.last;
        const dt = prev ? point.time - prev.time : 0;
        const linked = prev && prev.segment === point.segment &&
            point.originalIndex === prev.originalIndex - 1 &&
            dt > 0 && dt <= maxGapMs &&
            gpsDistanceMeters(prev.coord, point.coord) <=
                GPS_FILTER_JITTER_M + GPS_FILTER_MAX_MPS * dt / 1000;
        if (!linked) {
            block = { first: point, last: point, count: 0, durationMs: 0,
                distanceM: 0, points: [] };
            blocks.push(block);
        } else {
            block.distanceM += gpsDistanceMeters(prev.coord, point.coord);
        }
        block.points.push(point);
        ++block.count;
        block.last = point;
        block.durationMs = point.time - block.first.time;
    }
    // Breaks in the rendered route must remain breaks, but two short source
    // segments near the same erroneous location form one *validation* island.
    // Without this, two separate 'forest' glitches can protect each other
    // from a boomerang test because neither is adjacent to the real road.
    const groups = [];
    const close = (a, b) => gpsDistanceMeters(a.coord, b.coord);
    for (const b of blocks) {
        const lastGroup = groups[groups.length - 1];
        const dt = lastGroup ? b.first.time - lastGroup.last.time : Infinity;
        const gapMeters = lastGroup ? close(lastGroup.last, b.first) : Infinity;
        if (dt > 0 && dt <= GPS_FILTER_GROUP_GAP_MS &&
            gapMeters <= GPS_FILTER_GROUP_DISTANCE_M) {
            lastGroup.parts.push(b);
            lastGroup.last = b.last;
            lastGroup.count += b.count;
            lastGroup.distanceM += gapMeters + b.distanceM;
            lastGroup.durationMs = b.last.time - lastGroup.first.time;
        } else {
            groups.push({ first: b.first, last: b.last, count: b.count,
                durationMs: b.durationMs, distanceM: b.distanceM, parts: [b] });
        }
    }
    let islandsRemoved = 0;
    let boomerangsRemoved = 0;
    let quarantinedRemoved = 0;
    const discarded = new Set();
    const compact = b => b.distanceM <= 200 && b.durationMs <= 120000;
    // No-return end-of-session clusters have less evidence than boomerangs:
    // retain a long stable track when we cannot know which side was correct.
    const terminalCompact = b => compact(b) && b.count <= 40 &&
        b.durationMs <= 60000;
    // A short-duration high-rate cluster can have many fixes; count alone
    // cannot make it trustworthy. Equally, never discard a long moving track
    // merely because it contains relatively few GPS samples.
    const small = b => (b.count < GPS_FILTER_ISOLATED_FIXES ||
        b.durationMs < GPS_FILTER_ISOLATED_MS) && b.distanceM <= 300;
    for (let i = 0; i < groups.length; i++) {
        const candidate = groups[i];
        const before = i ? groups[i - 1] : null;
        const after = i + 1 < groups.length ? groups[i + 1] : null;
        const beforeGap = before ? candidate.first.time - before.last.time : Infinity;
        const afterGap = after ? after.first.time - candidate.last.time : Infinity;
        const fromBefore = before ? close(before.last, candidate.first) : Infinity;
        const toAfter = after ? close(candidate.last, after.first) : Infinity;
        const baseline = before && after ? close(before.last, after.first) : Infinity;
        const remote = fromBefore > GPS_FILTER_REMOTE_ISLAND_M &&
            toAfter > GPS_FILTER_REMOTE_ISLAND_M;
        // A boomerang can last much longer than a minisegment: a GPS receiver
        // can report a stable wrong location for minutes. Reject only if the
        // surroundings return near each other, the false location is very far
        // from BOTH sides and the excursion is almost stationary (or tiny).
        // A real road excursion generally contains measurable route travel.
        const boomerang = before && after && before.count >= 2 && after.count >= 2 &&
            remote && baseline <= 350 &&
            fromBefore > Math.max(900, baseline * 4) &&
            toAfter > Math.max(900, baseline * 4) &&
            after.first.time - before.last.time <= GPS_FILTER_BOOMERANG_WINDOW_MS &&
            (small(candidate) || compact(candidate));
        // A short island stranded at the beginning/end of the session is
        // rejected ONLY if the relocation is impossible in a short time.
        // If satellite reception was absent for minutes, keep it: it might
        // be an authentic, unobserved journey. Small local GPS stops stay.
        const orphanBefore = before && !after && before.count >= 2 &&
            (small(candidate) || terminalCompact(candidate)) &&
            fromBefore > GPS_FILTER_REMOTE_ISLAND_M &&
            beforeGap > 0 && beforeGap <= GPS_FILTER_SHORT_RELOCATION_GAP_MS &&
            fromBefore > GPS_FILTER_JITTER_M + GPS_FILTER_MAX_MPS * beforeGap / 1000;
        const orphanAfter = !before && after && after.count >= 2 &&
            candidate.count === 1 && small(candidate) &&
            toAfter > GPS_FILTER_REMOTE_ISLAND_M &&
            afterGap > 0 && afterGap <= GPS_FILTER_SHORT_RELOCATION_GAP_MS &&
            toAfter > GPS_FILTER_JITTER_M + GPS_FILTER_MAX_MPS * afterGap / 1000;
        // If an isolated short component has two implausible boundaries, it
        // is not a coherent continuation, even if the trustworthy route moved
        // between those boundaries. Preserve it if either transition could
        // actually have occurred or if one side is a long GPS blackout.
        const twoBadEdges = before && after && before.count >= 2 && after.count >= 2 &&
            (small(candidate) || compact(candidate)) && remote &&
            beforeGap > 0 && afterGap > 0 &&
            beforeGap <= GPS_FILTER_SHORT_RELOCATION_GAP_MS &&
            afterGap <= GPS_FILTER_SHORT_RELOCATION_GAP_MS &&
            fromBefore > GPS_FILTER_JITTER_M + GPS_FILTER_MAX_MPS * beforeGap / 1000 &&
            toAfter > GPS_FILTER_JITTER_M + GPS_FILTER_MAX_MPS * afterGap / 1000;
        if (boomerang || orphanBefore || orphanAfter || twoBadEdges) {
            for (const part of candidate.parts) {
                for (const p of part.points) discarded.add(p.originalIndex);
            }
            ++islandsRemoved;
            if (boomerang) ++boomerangsRemoved;
            else ++quarantinedRemoved;
        }
    }
    for (const index of discarded) accepted.delete(index);
    return { islandsRemoved, boomerangsRemoved, quarantinedRemoved };
}

// Strict-only: a receiver can freeze *coordinates* while the independent ECU
// still records motion. Fix counts or apparently stable position alone must
// never promote this interval into a trusted route. Detect these stretches
// BEFORE detached-island validation, so their small leading/trailing pieces
// can be rejected as ordinary unconfirmed fragments. No synthetic coordinates.
function gpsRejectStrictFrozenCoordinates(points, accepted, maxGapMs) {
    // Require an effectively identical receiver fix, not merely a slowly
    // moving path: a real low-speed route must survive even if its derived
    // distance looks inconsistent with a noisy speed PID.
    const STALE_RADIUS_M = 0.05;
    const STALE_MIN_MS = 8000;
    const STALE_OBD_DISTANCE_M = 30;
    let staleRuns = 0, staleFixes = 0;
    let first = -1, previous = -1;
    const finish = end => {
        if (first < 0 || end <= first) return;
        const duration = points[end].time - points[first].time;
        if (duration < STALE_MIN_MS) return;
        let obdDistanceM = 0, obdCoverageMs = 0;
        let movingFixes = 0, gpsMovingFixes = 0;
        for (let j = first; j <= end; j++) {
            const p = points[j];
            if (p.speed !== null && p.speed >= 8) movingFixes++;
            if (p.gpsSpeed !== null && p.gpsSpeed >= 8) gpsMovingFixes++;
            if (j === first) continue;
            const a = points[j - 1], b = p;
            const dt = b.time - a.time;
            if (dt > 0 && dt <= maxGapMs &&
                a.speed !== null && b.speed !== null) {
                obdCoverageMs += dt;
                obdDistanceM += (a.speed + b.speed) / 7.2 * (dt / 1000);
            }
        }
        // A short, transient zero-speed PID or a sparse sensor should not
        // erase a real parking position. Only act on well-covered, sustained
        // independent travel, strengthened by the receiver's own moving-speed
        // claim. Without GPS speed demand a larger ECU distance margin.
        if (obdCoverageMs < duration * 0.8 || movingFixes < 3 ||
            obdDistanceM < (gpsMovingFixes >= 3 ? STALE_OBD_DISTANCE_M : 60)) return;
        let removed = 0;
        for (let j = first; j <= end; j++) {
            if (accepted.delete(points[j].originalIndex)) removed++;
        }
        if (removed) { staleRuns++; staleFixes += removed; }
    };
    for (let i = 0; i <= points.length; i++) {
        const p = points[i];
        const prev = previous >= 0 ? points[previous] : null;
        const continuation = p && p.valid && accepted.has(p.originalIndex) && prev &&
            p.segment === prev.segment && p.time > prev.time &&
            p.time - prev.time <= maxGapMs &&
            gpsDistanceMeters(p.coord, points[first].coord) <= STALE_RADIUS_M;
        if (!continuation) {
            finish(previous);
            first = p && p.valid && accepted.has(p.originalIndex) ? i : -1;
        }
        previous = p && p.valid && accepted.has(p.originalIndex) ? i : -1;
    }
    return { strictStaleRuns: staleRuns, strictStaleFixes: staleFixes };
}

// Strict-only final pass: a detached speck can survive the ordinary boomerang
// detector because it never returns to the known route (or the log ends).
// Use independently supported *moving* route fragments as spatial references.
// The raw data/Smart mode remain untouched. Do not use OSM or assume the
// vehicle always stays on mapped roads.
function gpsRejectStrictDetachedFragments(points, accepted, maxGapMs) {
    const empty = { strictIslandsRemoved: 0, strictFixesRemoved: 0 };
    if (points.length < 3 || accepted.size < 3) return empty;

    // Build disconnected rendered fragments. GPS-source boundaries, rejected
    // fixes, real time gaps and impossible jumps must stay separate here.
    const fragments = [];
    let active = null, previous = null;
    for (const p of points) {
        if (!p.valid || !accepted.has(p.originalIndex)) {
            previous = null;
            continue;
        }
        const dt = previous ? p.time - previous.time : 0;
        const joins = previous && previous.segment === p.segment &&
            previous.originalIndex === p.originalIndex + 1 &&
            dt > 0 && dt <= maxGapMs &&
            gpsDistanceMeters(previous.coord, p.coord) <=
                GPS_FILTER_JITTER_M + GPS_FILTER_MAX_MPS * dt / 1000;
        if (!joins) {
            active = { first: p, last: p, points: [], distanceM: 0,
                minLat: p.coord[0], maxLat: p.coord[0],
                minLon: p.coord[1], maxLon: p.coord[1],
                obdSamples: 0, obdMoving: 0, compared: 0, mismatches: 0 };
            fragments.push(active);
        } else {
            active.distanceM += gpsDistanceMeters(previous.coord, p.coord);
        }
        active.points.push(p);
        active.last = p;
        active.minLat = Math.min(active.minLat, p.coord[0]);
        active.maxLat = Math.max(active.maxLat, p.coord[0]);
        active.minLon = Math.min(active.minLon, p.coord[1]);
        active.maxLon = Math.max(active.maxLon, p.coord[1]);
        if (p.speed !== null) {
            ++active.obdSamples;
            if (p.speed >= 8) ++active.obdMoving;
        }
        if (p.speed !== null && p.gpsSpeed !== null) {
            ++active.compared;
            if (Math.abs(p.speed - p.gpsSpeed) > GPS_SPEED_TOLERANCE_KMH)
                ++active.mismatches;
        }
        previous = p;
    }
    if (fragments.length < 2) return empty;

    // Adjacent GPS-source fragments from the same small location are one
    // validation candidate, but NEVER one drawn polyline. This catches a
    // forest ghost split into several short GPS-fix bursts.
    const groups = [];
    for (const part of fragments) {
        const prior = groups[groups.length - 1];
        const dt = prior ? part.first.time - prior.last.time : Infinity;
        if (prior && dt > 0 && dt <= GPS_FILTER_GROUP_GAP_MS &&
            gpsDistanceMeters(prior.last.coord, part.first.coord) <=
                GPS_FILTER_GROUP_DISTANCE_M) {
            prior.parts.push(part);
            prior.last = part.last;
            prior.distanceM += part.distanceM;
            prior.minLat = Math.min(prior.minLat, part.minLat);
            prior.maxLat = Math.max(prior.maxLat, part.maxLat);
            prior.minLon = Math.min(prior.minLon, part.minLon);
            prior.maxLon = Math.max(prior.maxLon, part.maxLon);
            prior.obdSamples += part.obdSamples;
            prior.obdMoving += part.obdMoving;
            prior.compared += part.compared;
            prior.mismatches += part.mismatches;
            prior.count += part.points.length;
        } else {
            groups.push({ ...part, parts: [part], count: part.points.length });
        }
    }
    // A receiver drifting in a small patch of forest can emit many GPS
    // fixes. High fix count does not establish independent vehicle motion.
    const features = groups.map(g => {
        const durationMs = g.last.time - g.first.time;
        const spreadM = gpsDistanceMeters([g.minLat, g.minLon],
            [g.maxLat, g.maxLon]);
        const netM = gpsDistanceMeters(g.first.coord, g.last.coord);
        const mismatched = g.compared >= 5 &&
            g.mismatches / g.compared >= 0.6;
        const obdMoving = g.obdSamples >= 2 &&
            g.obdMoving / g.obdSamples >= 0.6;
        // Stationary ECU readings are independent evidence even when the GPS
        // receiver speed is broken. A wandering remote patch must NEVER
        // certify itself as a moving anchor solely through its coordinates.
        const obdStationary = g.obdSamples >= 4 &&
            g.obdMoving / g.obdSamples <= 0.15;
        // A real short relocation after a blackout can be accepted when
        // OBD reports motion and the receiver agrees with it.
        const speedSupported = obdMoving && g.count >= 2 &&
            g.distanceM >= 8 && (g.compared === 0 ||
                g.mismatches / g.compared <= 0.35);
        // OBD-supported motion is independent of GPS and may be short.
        // Without OBD, demand substantially more consistent GPS travel to
        // avoid treating a compact but fast-moving *ghost* as its own anchor.
        // Even one GPS-only fragment with reported OBD=0 is NOT an anchor.
        const stableParked = obdStationary && g.count >= 20 &&
            durationMs >= 15000 && spreadM <= 100 && netM <= 80;
        const gpsOnlyMoving = g.obdSamples < 3 && g.count >= 25 &&
            durationMs >= 45000 && g.distanceM >= 220 && netM >= 120 &&
            netM / Math.max(1, g.distanceM) >= 0.25;
        const trustedMoving =
            (speedSupported && g.count >= 3 && durationMs >= 2000 &&
                (g.distanceM >= 20 || netM >= 20)) ||
            (gpsOnlyMoving && !obdStationary && !mismatched);
        return { g, durationMs, spreadM, netM, mismatched,
            speedSupported, trustedMoving, obdStationary, stableParked };
    });
    let anchors = features.filter(f => f.trustedMoving);
    if (!anchors.length) {
        // A sufficiently long, compact standstill with consistent OBD=0
        // also gives a strong positional reference. Prefer it only if one
        // location clearly dominates; two equally plausible parked locations
        // cannot be distinguished after an outage.
        const parked = features.filter(f => f.stableParked)
            .sort((a, b) => b.g.count - a.g.count);
        if (parked.length && (!parked[1] ||
            parked[0].g.count >= 3 * parked[1].g.count)) {
            anchors = [parked[0]];
        }
    }
    if (!anchors.length) {
        // Sparse logs often fragment the *real* urban track into short
        // snippets below the ordinary anchor thresholds. In Strict only,
        // use the clearly dominant continuous cluster as a weak reference,
        // but never invent a reference when the geography is ambiguous.
        // The weak reference only permits pruning very short remote ghosts.
        const candidates = features.filter(f => f.g.count >= 8 &&
            f.durationMs >= 8000 && !f.obdStationary);
        candidates.sort((a, b) => b.g.count - a.g.count);
        if (candidates.length && candidates[0].g.count >= 12 &&
            (!candidates[1] || candidates[0].g.count >= 3 * candidates[1].g.count)) {
            anchors = [candidates[0]];
        }
    }
    // Without a defensible route reference, we cannot identify which
    // independent location was genuine. Preserve raw evidence in that case.
    if (!anchors.length) return empty;
    // Membership must remain O(1) when a long session has many independent
    // confirmed moving fragments.
    const anchorSet = new Set(anchors);

    // Spatial grid avoids scanning a 100k-point reference track for every
    // candidate. Store at most 6 representatives per ~250-metre cell.
    const cellSizeM = 250;
    // Use a valid trusted position: the earliest raw fix can be invalid.
    const cosLat = Math.max(0.15,
        Math.cos(anchors[0].g.first.coord[0] * Math.PI / 180));
    const cell = coord => [Math.floor(coord[0] * 111195 / cellSizeM),
        Math.floor(coord[1] * 111195 * cosLat / cellSizeM)];
    const grid = new Map();
    for (const a of anchors) {
        for (const part of a.g.parts) {
            for (const p of part.points) {
                const [y, x] = cell(p.coord), key = y + ':' + x;
                let bucket = grid.get(key);
                if (!bucket) { bucket = []; grid.set(key, bucket); }
                // Multiple points in one cell offer negligible extra spatial
                // information; cap memory and proximity-search complexity.
                if (bucket.length < 6 && (bucket.length === 0 ||
                    bucket.every(other => gpsDistanceMeters(other, p.coord) > 55)))
                    bucket.push(p.coord);
            }
        }
    }
    const nearTrustedRoute = candidate => {
        const center = [(candidate.g.minLat + candidate.g.maxLat) / 2,
            (candidate.g.minLon + candidate.g.maxLon) / 2];
        const [y, x] = cell(center);
        for (let dy = -4; dy <= 4; dy++) {
            for (let dx = -4; dx <= 4; dx++) {
                for (const ref of grid.get((y + dy) + ':' + (x + dx)) || []) {
                    if (gpsDistanceMeters(center, ref) <= 650 + candidate.spreadM / 2)
                        return true;
                }
            }
        }
        return false;
    };

    let strictIslandsRemoved = 0, strictFixesRemoved = 0;
    for (const candidate of features) {
        if (anchorSet.has(candidate) ||
            candidate.trustedMoving || nearTrustedRoute(candidate)) continue;
        const { g, durationMs, spreadM, netM, mismatched,
            speedSupported, obdStationary } = candidate;
        // Keep corroborated relocated travel, even after long GPS blackouts.
        // For GPS-only recordings, an extended coherent journey is also
        // preserved unless ECU readings explicitly contradict its motion.
        if (speedSupported || (!obdStationary &&
            (durationMs > 120000 && netM > 350))) continue;
        const lacksDriveEvidence = g.obdSamples === 0 ||
            g.obdMoving / g.obdSamples < 0.4 || mismatched;
        // A tiny orphan is of little route value (one marker/heading arrow).
        // A longer compact ghost needs corroboration from low/stationary OBD
        // or a persistently erroneous GPS-speed channel.
        const shortOrphan = g.count <= 5 && durationMs <= 30000;
        const compactGhost = durationMs <= 120000 &&
            g.distanceM <= 900 && lacksDriveEvidence;
        const frozenWrongSpeed = durationMs <= 300000 &&
            g.distanceM <= 1400 && mismatched &&
            g.obdSamples >= 5 && g.obdMoving / g.obdSamples <= 0.2;
        // Position-channel drift may cross hundreds of metres while the ECU
        // reports a standstill, even when Speed (GPS) is absent or frozen at
        // zero. Previous checks discarded this strong evidence whenever the
        // geographic spread exceeded 450m.
        const stationaryDrift = obdStationary && durationMs <= 300000 &&
            spreadM <= 2500 && g.distanceM <= 5000;
        // A remote endpoint with a handful of fixes is not a reliable second
        // trip, even when the long blackout makes its arrival plausible.
        // Strict deliberately hides this *unconfirmed* island; Off/Smart keep it.
        const tinyDetached = !candidate.trustedMoving &&
            g.count <= 8 && durationMs <= 45000 &&
            g.distanceM <= 1200 && lacksDriveEvidence;
        const unsupportedCompact = !candidate.trustedMoving &&
            lacksDriveEvidence && durationMs <= 90000 &&
            spreadM <= 700 && g.distanceM <= 2200;
        if (!(shortOrphan || compactGhost || frozenWrongSpeed ||
            stationaryDrift || tinyDetached || unsupportedCompact)) continue;
        for (const part of g.parts) {
            for (const p of part.points) {
                if (accepted.delete(p.originalIndex)) ++strictFixesRemoved;
            }
        }
        ++strictIslandsRemoved;
    }
    return { strictIslandsRemoved, strictFixesRemoved };
}

// Stage 2: analyse *accepted* fixes only. Do not change the recorded route or
// the accepted-fix set in storage. Stationary corrections use the nearest real
// fix (medoid), not a synthetic road-snapped coordinate.
function gpsBearingDegrees(a, b) {
    const rad = Math.PI / 180;
    const east = (b[1] - a[1]) * Math.cos((a[0] + b[0]) / 2 * rad);
    const north = b[0] - a[0];
    return (Math.atan2(east, north) / rad + 360) % 360;
}

function gpsAngularDifference(a, b) {
    return Math.abs(((a - b + 540) % 360) - 180);
}

function gpsApplyStage2(points, accepted, maxGapMs, mode) {
    const overrides = new Map();
    let parkedFixes = 0;
    let headingOutliers = 0;
    if (mode !== 'smart' && mode !== 'strict') {
        return { overrides, parkedFixes, headingOutliers, headingTrusted: false };
    }
    const strict = mode === 'strict';
    // Group consecutive zero/near-zero OBD samples within one valid GPS run.
    // We only consolidate a compact, net-stationary location. This intentionally
    // declines to act if a faulty zero-speed sensor accompanies real travel.
    const minDuration = strict ? 10000 : 15000;
    const minFixes = strict ? 6 : 10;
    const maxRadiusM = strict ? 50 : 25;
    const maxNetM = strict ? 22 : 13;
    let parked = [];
    const finalizeParked = () => {
        if (parked.length < minFixes ||
            parked[parked.length - 1].time - parked[0].time < minDuration) {
            parked = [];
            return;
        }
        let sumLat = 0, sumLon = 0, travelled = 0;
        let movingHeadingSamples = 0, movingHeadingMatches = 0;
        for (let i = 0; i < parked.length; i++) {
            sumLat += parked[i].coord[0]; sumLon += parked[i].coord[1];
            if (!i) continue;
            const prev = parked[i - 1], cur = parked[i];
            const d = gpsDistanceMeters(prev.coord, cur.coord);
            travelled += d;
            const hdg = Number(prev.p?.[2]);
            if (d >= 4 && Number.isFinite(hdg) && hdg > 0 && hdg <= 360) {
                movingHeadingSamples++;
                if (gpsAngularDifference(hdg % 360,
                    gpsBearingDegrees(prev.coord, cur.coord)) <= 45) {
                    movingHeadingMatches++;
                }
            }
        }
        // Do not erase a true short loop when OBD is stale but the receiver
        // independently provides a coherent travelling heading.
        if (travelled > 50 && movingHeadingSamples >= 6 &&
            movingHeadingMatches / movingHeadingSamples >= 0.7) {
            parked = []; return;
        }
        if (travelled > (strict ? 320 : 60)) { parked = []; return; }
        const center = [sumLat / parked.length, sumLon / parked.length];
        const net = gpsDistanceMeters(parked[0].coord, parked[parked.length - 1].coord);
        if (net > maxNetM) { parked = []; return; }
        let closest = parked[0], best = Infinity;
        for (const p of parked) {
            const distance = gpsDistanceMeters(p.coord, center);
            if (distance > maxRadiusM) { parked = []; return; }
            if (distance < best) { closest = p; best = distance; }
        }
        // Keep the original sampling timestamps, source segments and OBD
        // values so hover, slider and heatmap remain time-consistent.
        for (const p of parked) {
            overrides.set(p.originalIndex, closest.coord);
            if (gpsDistanceMeters(p.coord, closest.coord) >= 0.5) parkedFixes++;
        }
        parked = [];
    };
    for (const point of points) {
        const prev = parked[parked.length - 1];
        const joins = prev && point.segment === prev.segment &&
            point.time > prev.time && point.time - prev.time <= maxGapMs;
        if (!point.valid || !accepted.has(point.originalIndex) ||
            point.speed === null || point.speed > 2 || (prev && !joins)) {
            finalizeParked();
        }
        if (point.valid && accepted.has(point.originalIndex) &&
            point.speed !== null && point.speed <= 2) parked.push(point);
    }
    finalizeParked();

    // Heading is optional and sometimes absent, stale or GPS-derived. Trust it
    // only after many independently plausible fixes have agreed with travel
    // bearing. Never classify a legitimate hairpin from an angle alone.
    let headingMatches = 0, headingSamples = 0;
    const heading = p => {
        const h = Number(p?.p?.[2]);
        return Number.isFinite(h) && h > 0 && h <= 360 ? h % 360 : null;
    };
    for (let i = 1; i < points.length; i++) {
        const a = points[i - 1], b = points[i];
        const dt = b.time - a.time;
        if (!accepted.has(a.originalIndex) || !accepted.has(b.originalIndex) ||
            a.segment !== b.segment || !(dt > 0 && dt <= maxGapMs) ||
            gpsDistanceMeters(a.coord, b.coord) < 12 || heading(a) === null) continue;
        headingSamples++;
        if (gpsAngularDifference(heading(a), gpsBearingDegrees(a.coord, b.coord)) <= 55)
            headingMatches++;
    }
    const headingTrusted = headingSamples >= 8 &&
        headingMatches / headingSamples >= 0.7;
    if (headingTrusted) {
        // Both edges of an isolated one-fix spike must be large, nearly undo
        // each other, contradict the surrounding heading and be short in time.
        // Use original GPS positions to avoid feedback from parked smoothing.
        for (let i = 1; i + 1 < points.length; i++) {
            const a = points[i - 1], b = points[i], c = points[i + 1];
            if (!accepted.has(a.originalIndex) || !accepted.has(b.originalIndex) ||
                !accepted.has(c.originalIndex) ||
                a.segment !== b.segment || b.segment !== c.segment) continue;
            const dt1 = b.time - a.time, dt2 = c.time - b.time;
            if (!(dt1 > 0 && dt2 > 0 &&
                dt1 <= Math.min(maxGapMs, 6000) && dt2 <= Math.min(maxGapMs, 6000))) continue;
            const ab = gpsDistanceMeters(a.coord, b.coord);
            const bc = gpsDistanceMeters(b.coord, c.coord);
            const ac = gpsDistanceMeters(a.coord, c.coord);
            if (ab < (strict ? 35 : 55) || bc < (strict ? 35 : 55) ||
                ac > Math.min(ab, bc) * (strict ? 0.6 : 0.4)) continue;
            const bearing1 = gpsBearingDegrees(a.coord, b.coord);
            const bearing2 = gpsBearingDegrees(b.coord, c.coord);
            const turn = gpsAngularDifference(bearing1, bearing2);
            if (turn < (strict ? 115 : 145)) continue;
            const ah = heading(a), ch = heading(c);
            if (ah === null || ch === null) continue;
            // A real hairpin has a genuine heading reversal, unlike a
            // transient GPS spur whose surrounding headings still agree.
            if (gpsAngularDifference(ah, ch) > (strict ? 65 : 40)) continue;
            const incomingError = gpsAngularDifference(ah, bearing1);
            const outgoingError = gpsAngularDifference(ch, bearing2);
            if (Math.max(incomingError, outgoingError) <= (strict ? 95 : 110))
                continue;
            accepted.delete(b.originalIndex);
            overrides.delete(b.originalIndex);
            headingOutliers++;
        }
    }
    return { overrides, parkedFixes, headingOutliers, headingTrusted };
}

// This is a conservative, retrospective filter: a suspicious excursion is
// quarantined until a plausible return or enough evidence for a new segment.
// Time and distance calculations run on full original GPS records, *before*
// rendering/route simplification. The OBD speed is never required.
function filterGpsAnomalies(rawPath, mode = 'smart') {
    if (!Array.isArray(rawPath) || !rawPath.length) {
        return { path: [], stats: { total: 0, removed: 0, obdUsed: false,
            suspectRuns: 0, islandsRemoved: 0, boomerangsRemoved: 0,
            quarantinedRemoved: 0, strictIslandsRemoved: 0, strictFixesRemoved: 0,
            strictStaleRuns: 0, strictStaleFixes: 0, parkedFixes: 0,
            headingOutliers: 0, headingTrusted: false,
            gpsSpeedSamples: 0, gpsSpeedMismatch: 0, gpsSpeedJumps: 0,
            gpsSpeedFrozen: 0, gpsSpeedRejected: 0 } };
    }
    const median = gpsMedianInterval(rawPath);
    const maxGapMs = Math.max(3000, Math.min(120000, median * 4));
    const good = new Set();
    let obdUsed = false;
    let suspectRuns = 0;
    const points = rawPath.map((p, originalIndex) => ({
        p, originalIndex,
        coord: [Number(p?.[0]), Number(p?.[1])],
        time: Number(p?.[3]), segment: Number(p?.[4] || 0),
        speed: gpsObdSpeed(p), gpsSpeed: gpsReportedSpeed(p),
        valid: Array.isArray(p) && Number.isFinite(Number(p[0])) &&
            Number.isFinite(Number(p[1])) && Number(p[0]) !== 0 &&
            Number(p[1]) !== 0 && Math.abs(Number(p[0])) <= 90 &&
            Math.abs(Number(p[1])) <= 180 && Number.isFinite(Number(p[3]))
    })).reverse();

    const speedEvidence = gpsSpeedEvidence(points, maxGapMs);

    function processRun(run) {
        if (!run.length) return;
        // Prefix sums support O(1) conservative ECU-distance bounds, even
        // when the suspect interval contains thousands of fixes.
        const meters = new Float64Array(run.length);
        const coverage = new Float64Array(run.length);
        let conservativeGpsTravel = 0;
        for (let i = 1; i < run.length; i++) {
            const a = run[i - 1], b = run[i];
            const dt = b.time - a.time;
            meters[i] = meters[i - 1];
            coverage[i] = coverage[i - 1];
            if (dt > 0 && dt <= maxGapMs && a.segment === b.segment) {
                const gpsDist = gpsDistanceMeters(a.coord, b.coord);
                if (gpsDist <= GPS_FILTER_JITTER_M + (dt / 1000) * GPS_FILTER_MAX_MPS)
                    conservativeGpsTravel += gpsDist;
            }
            // Do not extrapolate an OBD reading through a missing-GPS interval.
            if (dt > 0 && dt <= maxGapMs && a.segment === b.segment &&
                a.speed !== null && b.speed !== null) {
                meters[i] += ((a.speed + b.speed) / 2 / 3.6) * (dt / 1000);
                coverage[i] += dt;
            }
        }
        // Some devices expose an unchanging or incorrectly encoded kd field.
        // If well-behaved GPS proves substantial motion while OBD reports
        // almost nothing, ignore OBD rather than deleting a real trip.
        // Never decide OBD is stale *solely* because GPS positions imply
        // movement. That trusts the very GPS channel being diagnosed.
        // Retain the old protection for recordings without an independently
        // contradictory receiver-speed channel: it prevents wiping real
        // routes from devices where kd is stuck at zero. If GPS speed itself
        // strongly contradicts OBD on a sustained set of fixes, keep ECU
        // distance evidence enabled. Still reject coordinates only on an
        // independently implausible displacement, not on speed mismatch alone.
        let compared = 0, discordant = 0;
        for (const point of run) {
            if (point.speed === null || point.gpsSpeed === null) continue;
            compared++;
            if (Math.abs(point.speed - point.gpsSpeed) > GPS_SPEED_TOLERANCE_KMH)
                discordant++;
        }
        const speedContradiction = compared >= 6 && discordant >= 6 &&
            discordant / compared >= 0.6;
        const obdReliable = speedContradiction || !(conservativeGpsTravel > 300 &&
            meters[run.length - 1] < conservativeGpsTravel * 0.2);
        if (obdReliable && coverage[run.length - 1] > 0) obdUsed = true;
        const plausible = (aIndex, bIndex) => {
            const a = run[aIndex], b = run[bIndex];
            const dt = (b.time - a.time) / 1000;
            if (!(dt > 0)) return false;
            const dist = gpsDistanceMeters(a.coord, b.coord);
            let limit = GPS_FILTER_JITTER_M + dt * GPS_FILTER_MAX_MPS;
            const covered = coverage[bIndex] - coverage[aIndex];
            if (obdReliable && covered >= (dt * 1000 * 0.8)) {
                const travelled = meters[bIndex] - meters[aIndex];
                // Generous tolerance: some non-RedManage logs record kd in
                // miles/hour although its PID is labelled km/h. Keep the
                // bounds conservative rather than rejecting a genuine trip.
                limit = Math.min(limit, Math.max(85, GPS_FILTER_JITTER_M + 5 * travelled));
                // Suspicious GPS speed + valid OBD is a strong indicator that
                // an apparent high-speed displacement may be a teleport.
                // Keep ample tolerance for jitter, logging skew and corners.
                if (speedEvidence.suspicious.has(a.originalIndex) ||
                    speedEvidence.suspicious.has(b.originalIndex)) {
                    limit = Math.min(limit, Math.max(30, 25 + 2.2 * travelled));
                }
            }
            return dist <= limit;
        };
        good.add(run[0].originalIndex);
        let anchor = 0;
        let suspectStart = -1;
        for (let i = 1; i < run.length; i++) {
            if (suspectStart === -1) {
                if (plausible(anchor, i)) {
                    good.add(run[i].originalIndex);
                    anchor = i;
                } else {
                    suspectStart = i;
                    ++suspectRuns;
                }
            } else if (plausible(anchor, i) && !plausible(i - 1, i)) {
                // Teleported away, then returned to a physically reachable
                // position: discard the full false cluster. The skipped raw
                // indices force a route split, including for heatlines/hover.
                good.add(run[i].originalIndex);
                anchor = i;
                suspectStart = -1;
            }
        }
        if (suspectStart !== -1) {
            const count = run.length - suspectStart;
            const last = run.length - 1;
            const elapsedMs = run[last].time - run[suspectStart].time;
            // A few fixes are not sufficient to confirm a newly relocated
            // track. Require both spatial continuity and temporal evidence;
            // the long-blackout exception avoids suppressing genuine trips
            // that resume recording only briefly at a distant location.
            let coherent = true;
            for (let i = suspectStart + 1; i < run.length; i++) {
                if (run[i].segment === run[i - 1].segment &&
                    run[i].time - run[i - 1].time <= maxGapMs &&
                    !plausible(i - 1, i)) {
                    coherent = false;
                    break;
                }
            }
            const confirmed = coherent && count >= GPS_FILTER_CONFIRM_FIXES &&
                elapsedMs >= GPS_FILTER_CONFIRM_MS;
            const newSourceSegment = run[suspectStart].segment !== run[anchor].segment;
            const blackoutMs = run[suspectStart].time - run[anchor].time;
            const inconclusiveLongBlackout = newSourceSegment &&
                blackoutMs >= GPS_FILTER_UNCERTAIN_BLACKOUT_MS &&
                coherent && count >= 2;
            if ((confirmed && (plausible(anchor, last) || newSourceSegment)) ||
                inconclusiveLongBlackout) {
                for (let i = suspectStart; i < run.length; i++) {
                    good.add(run[i].originalIndex);
                }
            }
        }
    }

    let run = [];
    for (const point of points) {
        const previous = run[run.length - 1];
        // Retain an anomaly-detection anchor across shorter signal outages;
        // *rendering* still splits on original segment IDs and maxGapMs.
        // A genuinely long break remains inconclusive and starts a new run.
        const continues = point.valid && previous &&
            point.time > previous.time &&
            point.time - previous.time <= 600000;
        if (!point.valid || (run.length && !continues)) {
            processRun(run);
            run = [];
        }
        if (point.valid) run.push(point);
    }
    processRun(run);
    const islandStats = gpsRejectIsolatedIslands(points, good, maxGapMs);
    speedEvidence.stats.gpsSpeedRejected = gpsRejectSpeedInconsistentSpikes(
        points, good, speedEvidence, maxGapMs, mode);
    const staleStats = mode === 'strict' ?
        gpsRejectStrictFrozenCoordinates(points, good, maxGapMs) :
        { strictStaleRuns: 0, strictStaleFixes: 0 };
    const strictStats = mode === 'strict' ?
        gpsRejectStrictDetachedFragments(points, good, maxGapMs) :
        { strictIslandsRemoved: 0, strictFixesRemoved: 0 };
    const stage2 = gpsApplyStage2(points, good, maxGapMs, mode);

    // Output stays newest-first, preserves source timestamps/headings/speed,
    // and adds a break whenever any intervening fix was removed.
    const path = [];
    let previousIndex = -2;
    let previousSegment = null;
    let segment = 0;
    for (let i = 0; i < rawPath.length; i++) {
        if (!good.has(i)) continue;
        const p = rawPath[i];
        const sourceSegment = Number(p[4] || 0);
        if (path.length && (i !== previousIndex + 1 || sourceSegment !== previousSegment)) segment++;
        const coord = stage2.overrides.get(i);
        path.push([coord ? coord[0] : p[0], coord ? coord[1] : p[1],
            coord ? 0 : p[2], p[3], segment, p[5] ?? null, p[6] ?? null]);
        previousIndex = i;
        previousSegment = sourceSegment;
    }
    // Expose *diagnostic* timestamps for the chart hover. These are NOT
    // deleted telemetry values; GPS speed can be wrong with a correct fix.
    const speedWarningTimes = new Set();
    for (const index of speedEvidence.suspicious) {
        const time = Number(rawPath[index]?.[3]);
        if (Number.isFinite(time)) speedWarningTimes.add(time);
    }
    return { path, speedWarningTimes,
        stats: { total: rawPath.length, removed: rawPath.length - path.length,
        obdUsed, suspectRuns, ...islandStats, ...staleStats, ...strictStats, ...speedEvidence.stats,
        parkedFixes: stage2.parkedFixes,
        headingOutliers: stage2.headingOutliers,
        headingTrusted: stage2.headingTrusted } };
}

function buildGpsDisplayMapData(rawPath, enabled = gpsFilterMode) {
    // Retain compatibility with callers/tests that pass a boolean.
    const mode = enabled === true ? 'smart' : enabled === false ? 'off' : enabled;
    if (mode === 'off' || !GPS_FILTER_MODES.includes(mode)) {
        gpsFilterDiagnostics = null;
        window.gpsSpeedWarningTimes = null;
        return buildMapData(rawPath);
    }
    // Stream mutates rawPath in place, so length and first/last time must be
    // included in the cache identity. Never keep stale filtered GPS fixes.
    const first = rawPath?.[0]?.[3], last = rawPath?.[rawPath.length - 1]?.[3];
    if (!gpsFilterCache || gpsFilterCache.source !== rawPath ||
        gpsFilterCache.length !== rawPath.length || gpsFilterCache.first !== first ||
        gpsFilterCache.last !== last || gpsFilterCache.mode !== mode) {
        const result = filterGpsAnomalies(rawPath, mode);
        gpsFilterCache = { source: rawPath, length: rawPath.length, first, last, mode, result };
    }
    gpsFilterDiagnostics = gpsFilterCache.result.stats;
    window.gpsSpeedWarningTimes = gpsFilterCache.result.speedWarningTimes;
    return buildMapData(gpsFilterCache.result.path);
}

function buildMapData(rawPath) {
    const points = rawPath.filter(p => Array.isArray(p) &&
        Number.isFinite(Number(p[0])) && Number.isFinite(Number(p[1])) &&
        Number(p[0]) !== 0 && Number(p[1]) !== 0 &&
        Math.abs(Number(p[0])) <= 90 && Math.abs(Number(p[1])) <= 180);
    const median = gpsMedianInterval(points);
    const maxGapMs = Math.max(3000, Math.min(120000, median * 4));
    const segmentsIndices = [];
    const origHeading = {};
    const origToFlat = {};
    let previous = null;
    let currentSegment = null;
    let flatIndex = 0;
    let segmentNumber = -1;

    // Use original raw indices; do not silently reindex when filtering fixes.
    rawPath.forEach((p, index) => {
        if (!Array.isArray(p)) return;
        const lat = Number(p[0]), lon = Number(p[1]);
        if (!Number.isFinite(lat) || !Number.isFinite(lon) || lat === 0 || lon === 0 ||
            Math.abs(lat) > 90 || Math.abs(lon) > 180) {
            previous = null;
            return;
        }
        const coord = [lat, lon];
        const time = p.length > 3 && Number.isFinite(Number(p[3])) ? Number(p[3]) : null;
        const gpsSegment = p.length > 4 ? Number(p[4]) : 0;
        let startsSegment = !previous || gpsSegment !== previous.gpsSegment;
        if (!startsSegment && time !== null && previous.time !== null) {
            const dt = previous.time - time;  // SQL and rawPath: newest first
            if (dt <= 0 || dt > maxGapMs ||
                gpsDistanceMeters(coord, previous.coord) / (dt / 1000) > 120) {
                startsSegment = true;
            }
        }
        if (startsSegment) {
            currentSegment = [];
            segmentsIndices.push(currentSegment);
            ++segmentNumber;
        }
        const item = { coord, index, time, segmentNumber };
        currentSegment.push(item);
        origHeading[index] = Number(p[2]) || 0;
        origToFlat[index] = flatIndex++;
        previous = { coord, time, gpsSegment };
    });

    const segmentsCoords = segmentsIndices.map(seg => seg.map(item => item.coord));
    const flattened = segmentsIndices.flat();
    return {
        segmentsCoords, segmentsIndices,
        flatCoords: flattened.map(item => item.coord),
        flatIndices: flattened.map(item => item.index),
        gpsChronological: flattened.filter(item => item.time !== null)
            .slice().sort((a, b) => a.time - b.time),
        maxGapMs, medianIntervalMs: median,
        rawPathLength: rawPath.length, nextIndex: rawPath.length,
        origHeading, origToFlat
    };
}

// Lower-bound search on timestamps; used for every hover (O(log N)).
function gpsLowerBound(items, time) {
    let lo = 0, hi = items.length;
    while (lo < hi) {
        const mid = (lo + hi) >>> 1;
        if (items[mid].time < time) lo = mid + 1;
        else hi = mid;
    }
    return lo;
}

function gpsPositionAtTime(mapData, time) {
    if (!mapData || !Number.isFinite(time)) return null;
    const items = mapData.gpsChronological;
    if (!items?.length) return null;
    const at = gpsLowerBound(items, time);
    if (at < items.length && items[at].time === time) return items[at].coord;
    const prev = items[at - 1], next = items[at];
    if (prev && next && prev.segmentNumber === next.segmentNumber &&
        next.time > prev.time && next.time - prev.time <= mapData.maxGapMs) {
        const t = (time - prev.time) / (next.time - prev.time);
        return [prev.coord[0] + (next.coord[0] - prev.coord[0]) * t,
                prev.coord[1] + (next.coord[1] - prev.coord[1]) * t];
    }
    // Only snap very close to an isolated sample. Never extend a marker
    // through an outage simply because no newer fix is available.
    // A tiny timestamp-jitter allowance, never a whole missing chart sample.
    const tolerance = Math.min(100, Math.max(0, mapData.medianIntervalMs / 10));
    if (prev && time - prev.time <= tolerance) return prev.coord;
    if (next && next.time - time <= tolerance) return next.coord;
    return null;
}

function gpsSegmentsForTimeRange(mapData, start, end) {
    if (!mapData) return [];
    return mapData.segmentsIndices.map(seg => seg.filter(p => p.time !== null &&
        p.time >= start && p.time <= end)).filter(seg => seg.length);
}

// A PID and GPS fix can have different sampling frequencies. Match by
// original time, never by proportional array position or dataIndex.
function gpsMetricLookup(sourceData) {
    const mapping = window.realTimeInfo?.timeMapping;
    const samples = [];
    for (const point of sourceData || []) {
        if (!Array.isArray(point) || point.length < 2 || point[1] === null ||
            point[1] === undefined || !Number.isFinite(Number(point[1]))) continue;
        const processed = Number(point[0]);
        const time = mapping?.[processed] ?? processed;
        if (Number.isFinite(Number(time))) samples.push({ time: Number(time), value: Number(point[1]) });
    }
    samples.sort((a, b) => a.time - b.time);
    const intervals = [];
    for (let i = 1; i < samples.length; i++) {
        const dt = samples[i].time - samples[i - 1].time;
        if (dt > 0) intervals.push(dt);
    }
    intervals.sort((a, b) => a - b);
    const cadence = intervals.length ? intervals[Math.floor(intervals.length / 2)] : 1000;
    return { samples, toleranceMs: Math.max(500, Math.min(30000, cadence * 1.5)) };
}

function gpsMetricAtTime(lookup, time) {
    if (!lookup || !Number.isFinite(time)) return null;
    const items = lookup.samples;
    const idx = gpsLowerBound(items, time);
    const a = items[idx - 1], b = items[idx];
    const selected = !a ? b : !b ? a :
        (time - a.time <= b.time - time ? a : b);
    return selected && Math.abs(selected.time - time) <= lookup.toleranceMs
        ? selected.value : null;
}

// A lone GPS fix produces no visible polyline but the heading renderer used
// to put a conspicuous floating arrow on it. For Strict only, require a
// short real trajectory within the *visible* segment before drawing arrows.
// Does not change GPS data, hover, heatlines or Off/Smart behavior.
function gpsStrictHeadingSegmentVisible(segment) {
    if (!Array.isArray(segment) || segment.length < 3) return false;
    let distance = 0;
    const start = segment[0].coord;
    let maxFromStart = 0;
    for (let i = 1; i < segment.length; i++) {
        distance += gpsDistanceMeters(segment[i - 1].coord, segment[i].coord);
        maxFromStart = Math.max(maxFromStart,
            gpsDistanceMeters(start, segment[i].coord));
    }
    const duration = Math.abs(segment[0].time - segment[segment.length - 1].time);
    return duration >= 2000 && distance >= 25 &&
        (maxFromStart >= 15 || distance >= 100);
}

// ======================= Map initialization =======================
let map = null;
let polyline = null;
let headingArrowsLayer = null;          // layer group for heading direction arrows
let pendingHeatlinePolyline = null;
let pendingHeatPoints = [];
let lastHeatCoord = null;

let initMapLeaflet = () => {
    let osm = new L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap'
    });

    let esri = new L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        className: 'esri-dark',
        maxZoom: 19,
        attribution: '© Esri'});

    // Map initialization
    map = new L.Map("map", {
        center: new L.LatLng(0, 0),
        dragging: !L.Browser.mobile,
        zoom: 6, scrollWheelZoom: false,
        fullscreenControl: true,
        fullscreenControlOptions: {
            position: 'topleft',
            forcePseudoFullscreen: true
        },
        layers: [osm]
    });

    if (typeof setStreamMapFullscreen === 'function') {
        map.on('enterFullscreen', () => setStreamMapFullscreen(map, true));
        map.on('exitFullscreen', () => setStreamMapFullscreen(map, false));
        const panel = document.getElementById('stream-panel');
        if (panel) {
            L.DomEvent.disableClickPropagation(panel);
            L.DomEvent.disableScrollPropagation(panel);
        }
        // Pseudo-fullscreen does not receive the browser's native Escape handling.
        const exitStreamFullscreen = event => {
            if (event.key === 'Escape' && map._isFullscreen &&
                map.fullscreenControl.options.forcePseudoFullscreen) {
                map.toggleFullscreen();
            }
        };
        document.addEventListener('keydown', exitStreamFullscreen);
        map.once('unload', () => document.removeEventListener('keydown', exitStreamFullscreen));
    }

    // ---------- Create a pane for heading arrows ----------
    // the arrows do not capture mouse events and block hotline tooltips.
    map.createPane('headingPane');
    map.getPane('headingPane').style.pointerEvents = 'none';

    let baseMaps = {
        [localization.key['layer.map'] ?? 'Map']: osm,
        [localization.key['layer.sat'] ?? 'Satellite']: esri
    };

    let layerControl = L.control.layers(baseMaps).addTo(map);

    // ---------------------- Controls ----------------------
    const addControlsToZoomContainer = function(map) {
        const zoomControl = document.querySelector('.leaflet-control-zoom');
        if (!zoomControl) return;

        const streamButton = L.DomUtil.create('a', 'leaflet-control-zoom-stream');
        streamButton.href = 'javascript:void(0)';
        streamButton.innerHTML = `
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24">
            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V6a2 2 0 0 1 2-2h2M4 16v2a2 2 0 0 0 2 2h2m8-16h2a2 2 0 0 1 2 2v2m-4 12h2a2 2 0 0 0 2-2v-2m-8-5v.01M12 18l-3.5-5a4 4 0 1 1 7 0z"/>
          </svg>
        `;
        const svg = streamButton.querySelector('svg');
        // save to global variable if needed (defined above)
        if (typeof streamBtn_svg !== 'undefined') streamBtn_svg = svg;

        L.DomEvent.on(streamButton, 'click', function(e) {
            L.DomEvent.preventDefault(e);
            L.DomEvent.stopPropagation(e);
            dataToggle();

        });
        streamButton.setAttribute('role', 'button');
        streamButton.setAttribute('aria-pressed', 'false');
        streamButton.title = localization.key['stream.start'] || 'Start stream';
        zoomControl.appendChild(streamButton);
        if (typeof syncStreamControls === 'function') syncStreamControls();
    };

    if (!uid && !sid && !sig) addControlsToZoomContainer(map);

    // Heading arrows toggle (works in all modes)
    (function addHeadingArrowsToggle() {
        const zoomControl = document.querySelector('.leaflet-control-zoom');
        if (!zoomControl) return;

        const btn = L.DomUtil.create('a', 'leaflet-control-heading-toggle');
        btn.href = 'javascript:void(0)';
        btn.setAttribute('role', 'button');
        btn.setAttribute('aria-label', 'Toggle heading arrows');

        btn.innerHTML =
            '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24">'
          +   '<path fill="none" stroke="currentColor" stroke-linecap="round" '
          +         'stroke-linejoin="round" stroke-width="3" '
          +         'd="M3 11l19-9-9 19-2-8-8-2z"/>'
          + '</svg>';

        btn.classList.toggle('heading-toggle-off', !headingArrowsEnabled);

        L.DomEvent.on(btn, 'mousedown', function (e) {
            L.DomEvent.preventDefault(e);
            L.DomEvent.stopPropagation(e);

            headingArrowsEnabled = !headingArrowsEnabled;
            try {
                localStorage.setItem(`${storageUserPrefix}-heading_arrows`, String(headingArrowsEnabled));
            } catch (_) {}

            btn.classList.toggle('heading-toggle-off', !headingArrowsEnabled);
            updateHeadingArrows();
        });

        const streamBtn = zoomControl.querySelector('.leaflet-control-zoom-stream');
        if (streamBtn) {
            zoomControl.insertBefore(btn, streamBtn);
        } else {
            zoomControl.appendChild(btn);
        }
    })();

    // One map control cycles Off -> Smart -> Strict. Same control is available
    // in ordinary and shared sessions, with no extra handlers when switched.
    (function addGpsHybridFilterControl() {
        const zoomControl = document.querySelector('.leaflet-control-zoom');
        if (!zoomControl) return;
        const btn = L.DomUtil.create('a', 'leaflet-control-gps-hybrid');
        btn.href = '#';
        btn.setAttribute('role', 'button');
        btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" '
            + 'viewBox="0 0 24 24" fill="none" stroke="currentColor" '
            + 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
            + '<path d="M3 18l6-6 4 4 8-9"/><path d="M15 7h6v6"/>'
            + '<path d="M4 5h7"/></svg>';
        const refreshButton = () => {
            const translated = (key, fallback) => {
                if (typeof localization !== 'undefined' && localization &&
                    localization.key && typeof localization.key[key] === 'string') {
                    return localization.key[key];
                }
                // Translations load asynchronously; use an English fallback
                // until the dictionary is ready, then update via updateContent.
                return fallback;
            };
            const title = translated('gps.filter.title', 'GPS filter');
            const label = gpsFilterMode === 'off' ? 'Off' :
                gpsFilterMode === 'smart' ? 'Smart' : 'Strict';
            const stats = gpsFilterDiagnostics;
            const summary = gpsFilterEnabled && stats ?
                ` · ${stats.removed} ${translated('gps.filter.removed', 'removed')}` +
                (stats.parkedFixes ? ` · ${stats.parkedFixes} ${translated('gps.filter.parked', 'stationary fixes')}` : '') +
                (stats.headingOutliers ? ` · ${stats.headingOutliers} ${translated('gps.filter.heading', 'heading outliers')}` : '') +
                (stats.gpsSpeedMismatch ? ` · ${stats.gpsSpeedMismatch} ${translated('gps.filter.speed', 'GPS/OBD disagreement')}` : '') +
                (stats.gpsSpeedJumps ? ` · ${stats.gpsSpeedJumps} ${translated('gps.filter.jumps', 'speed jumps')}` : '') +
                (stats.gpsSpeedFrozen ? ` · ${stats.gpsSpeedFrozen} ${translated('gps.filter.frozen', 'GPS speed frozen')}` : '') +
                (stats.strictStaleFixes ? ` · ${stats.strictStaleFixes} ${translated('gps.filter.stale.coordinates', 'stale GPS coordinates')}` : '') +
                (stats.gpsSpeedRejected ? ` · ${stats.gpsSpeedRejected} ${translated('gps.filter.speed.rejected', 'speed-confirmed outliers')}` : '') +
                (stats.strictFixesRemoved ? ` · ${stats.strictFixesRemoved} ${translated('gps.filter.strict.removed', 'remote GPS fixes')}` : '') : '';
            const hint = stream ? translated('gps.filter.stream', 'disabled in Stream') :
                translated('gps.filter.next', 'click for next mode');
            btn.title = `${title}: ${label}${summary} · ${hint}`;
            btn.setAttribute('aria-label', btn.title);
            btn.setAttribute('aria-pressed', String(gpsFilterEnabled));
            btn.setAttribute('aria-disabled', String(!!stream));
            btn.dataset.mode = gpsFilterMode;
            btn.classList.toggle('gps-hybrid-on', gpsFilterEnabled);
            btn.classList.toggle('gps-hybrid-strict', gpsFilterMode === 'strict');
            btn.classList.toggle('gps-hybrid-disabled', !!stream);
        };
        window.syncGpsFilterControl = refreshButton;
        L.DomEvent.on(btn, 'click', e => {
            L.DomEvent.preventDefault(e);
            L.DomEvent.stopPropagation(e);
            if (stream) return;
            const index = GPS_FILTER_MODES.indexOf(gpsFilterMode);
            gpsFilterMode = GPS_FILTER_MODES[(index + 1) % GPS_FILTER_MODES.length];
            gpsFilterEnabled = gpsFilterMode !== 'off';
            try {
                localStorage.setItem(`${storageUserPrefix}-gps_filter_mode`, gpsFilterMode);
                // Preserve old callers that inspect the boolean preference.
                localStorage.setItem(`${storageUserPrefix}-gps_hybrid_filter`, String(gpsFilterEnabled));
            } catch (_) {}
            if (typeof window.refreshGpsFilterView === 'function') window.refreshGpsFilterView();
            refreshButton();
        });
        const streamBtn = zoomControl.querySelector('.leaflet-control-zoom-stream');
        if (streamBtn) zoomControl.insertBefore(btn, streamBtn);
        else zoomControl.appendChild(btn);
        refreshButton();
    })();

    // ---------------------- Hotline layer group ----------------------
    let hotlineLayers = L.layerGroup().addTo(map); // will contain hotline for each segment
    window.currentDataSource = null;

    // ---------------------- Start and End markers ----------------------
    const playSvgIcon = L.divIcon({
        html: `<svg width="20" height="20" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                 <polygon points="5,3 21,12 5,21" fill="green" stroke="white" stroke-width="1"/>
               </svg>`,
        className: 'svg-icon',
        iconAnchor: [12, 12]
    });

    const stopSvgIcon = L.divIcon({
        html: `<svg width="20" height="20" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                 <rect x="5" y="5" width="14" height="14" fill="black" stroke="white" stroke-width="1"/>
               </svg>`,
        className: 'svg-icon',
        iconAnchor: [12, 12]
    });

    const getStartCoord = () => {
        const segs = window.MapData.segmentsCoords;
        if (!segs.length) return [0, 0];
        const lastSeg = segs[segs.length - 1];
        return lastSeg[lastSeg.length - 1]; // the oldest point
    };

    const getEndCoord = () => {
        const segs = window.MapData.segmentsCoords;
        if (!segs.length) return [0, 0];
        return segs[0][0]; // the newest point
    };

    const startcir = L.marker(getStartCoord(), { icon: playSvgIcon, alt: 'Start Point' }).addTo(map);
    const endcir = L.marker(getEndCoord(), { icon: stopSvgIcon, alt: 'End Point' }).addTo(map);

    startcir.unbindTooltip().bindTooltip(localization.key['travel.start'] ?? 'Start', { className: 'travel-tooltip' });
    endcir.unbindTooltip().bindTooltip(localization.key['travel.end'] ?? 'End', { className: 'travel-tooltip' });

    // ---------------------- Polyline ----------------------
    polyline = L.polyline(window.MapData.segmentsCoords, {
        color: '#000000',
        dashArray: '5, 5',
        weight: 3,
        opacity: 0.9,
        className: 'travel-line-stroke'
    }).addTo(map);

    // Temp polyline for heatline
    pendingHeatlinePolyline = L.polyline([], {
        color: '#000000',
        dashArray: '5, 5',
        weight: 3,
        opacity: 0.9,
        className: 'travel-line-stroke'
    });

    // Full track on load
    window.currentMapSlicedCoords = window.MapData.flatCoords;
    window.currentMapSlicedIndices = window.MapData.flatIndices;
    window.currentMapSlicedSegments = window.MapData.segmentsIndices;
    window.currentMapSegmentsForHotline = window.MapData.segmentsIndices;
    window.visibleGpsMapData = window.MapData;
    window.visibleGpsStart = -Infinity;
    window.visibleGpsEnd = Infinity;

    // ---------------------- Heading arrows layer ----------------------
    headingArrowsLayer = L.layerGroup().addTo(map);

    // Fit map bounds initially
    if (window.MapData.segmentsCoords.length) {
        const bounds = polyline.getBounds();
        if (bounds.isValid()) {
            map.fitBounds(bounds);
        } else {
            // Fallback: center on first available coordinate
            const firstCoord = window.MapData.flatCoords[0];
            if (firstCoord) {
                map.setView(firstCoord, 13);
            }
        }
    }

    // ---------------------- Heading arrows update function ----------------------
    /**
     * Clears and redraws heading arrows on the visible track segment.
     * - Skips entirely if all headings are 0, null, or undefined.
     * - Uses distance-based placement with a base minimum distance that adapts to
     *   the current zoom level (closer zoom = more arrows, farther zoom = fewer).
     * - Caps the total number of arrows at MAX_ARROWS to maintain performance.
     */
    function updateHeadingArrows() {
        if (!headingArrowsLayer) return;
        headingArrowsLayer.clearLayers();
        if (!headingArrowsEnabled) return;

        const indices = window.currentMapSlicedIndices;   // original indices of visible points

        const coords = window.currentMapSlicedCoords;     // [lat, lng] of visible points
        if (!indices || !coords || coords.length === 0) return;

        const origHeading = window.MapData.origHeading;
        if (!origHeading) return;

        // --- Check if any valid heading exists (non-zero, non-null, non-undefined) ---
        let hasValidHeading = false;
        for (let i = 0; i < indices.length; i++) {
            const idx = indices[i];
            const h = origHeading[idx];
            if (h !== undefined && h !== null && h !== 0) {
                hasValidHeading = true;
                break;
            }
        }
        if (!hasValidHeading) return;   // No valid heading → skip arrows entirely

        // --- Dynamic density based on current map zoom ---
        const zoom = map.getZoom();
        const MIN_DIST_ZOOM_MAX = 1;    // meters at maximum zoom (e.g., 18) – many arrows
        const MAX_DIST_ZOOM_MIN = 5000;  // meters at minimum zoom (e.g., 1) – few arrows
        const zoomRange = 17;            // 18 - 1
        const t = (18 - zoom) / zoomRange;
        let baseDist = MIN_DIST_ZOOM_MAX + (MAX_DIST_ZOOM_MIN - MIN_DIST_ZOOM_MAX) * t;
        baseDist = Math.max(MIN_DIST_ZOOM_MAX, baseDist); // safety for zoom > 18

        const MAX_ARROWS = 300;  // absolute upper limit to prevent performance issues

        // --- Calculate total length of the visible polyline segment ---
        let totalDistance = 0;
        const distances = [0];
        const segmentStarts = new Set();
        // Stream intentionally displays raw GPS even when Strict is saved.
        const eligible = gpsFilterMode === 'strict' && !stream ?
            new Uint8Array(coords.length) : null;
        let offset = 0;
        for (const seg of window.currentMapSlicedSegments || []) {
            segmentStarts.add(offset);
            if (eligible && gpsStrictHeadingSegmentVisible(seg)) {
                eligible.fill(1, offset, offset + seg.length);
            }
            offset += seg.length;
        }
        for (let i = 1; i < coords.length; i++) {
            // No travelled distance may be measured through a GPS outage.
            if (!segmentStarts.has(i)) {
                totalDistance += L.latLng(coords[i-1][0], coords[i-1][1])
                    .distanceTo(L.latLng(coords[i][0], coords[i][1]));
            }
            distances.push(totalDistance);
        }

        if (totalDistance === 0) return;  // all points are at the same location

        // --- Adjust minimum distance to avoid too few or too many arrows ---
        let minDist = baseDist;
        // Ensure at least 2–3 arrows on very short tracks
        if (totalDistance < minDist * 2) {
            minDist = totalDistance / 2;
        } else if (totalDistance / minDist < 3) {
            minDist = totalDistance / 3;
        }
        // Enforce the global maximum arrow count
        const estimatedArrows = Math.ceil(totalDistance / minDist);
        if (estimatedArrows > MAX_ARROWS) {
            minDist = totalDistance / MAX_ARROWS;
        }

        // --- Place arrows along the track ---
        let lastDist = 0;       // distance of the last placed arrow
        let firstArrow = true;

        for (let i = 0; i < coords.length; i++) {
            if (eligible && !eligible[i]) continue;  // Strict: no floating arrows on GPS specks
            const origIdx = indices[i];
            if (origIdx < 0) continue;                // streaming point without a valid index
            const hdg = origHeading[origIdx];
            if (hdg === undefined || hdg === null || hdg === 0) continue; // skip missing or zero headings

            if (firstArrow || segmentStarts.has(i)) {
                addArrow(coords[i], hdg);
                lastDist = distances[i];
                firstArrow = false;
                continue;
            }

            if (distances[i] - lastDist >= minDist) {
                addArrow(coords[i], hdg);
                lastDist = distances[i];
            }
        }

        /**
         * Helper: creates a single arrow marker at the given position.
         * @param {[number, number]} pos - [lat, lng]
         * @param {number} hdg - heading in degrees (clockwise from north)
         */
        function addArrow(pos, hdg) {
            const arrowIcon = L.divIcon({
                html: `<svg
                    width="16" height="16"
                    viewBox="-12 -12 24 24"
                    style="transform: rotate(${hdg}deg); display: block; opacity:1.0; filter: drop-shadow(0 0 1px black);"
                >
                    <polygon
                        points="0,-10 -6,8 0,2 6,8"
                        fill="#fff"
                        stroke="#000"
                        stroke-width="1"
                        stroke-linejoin="round"
                    />
                </svg>`,
                className: '',
                iconSize: [16, 16],
                iconAnchor: [8, 8]
            });

            L.marker(pos, { 
                icon: arrowIcon, 
                pane: 'headingPane',
                interactive: false
            }).addTo(headingArrowsLayer);
        }
    }

    // Initial draw of arrows (if heading data exists)
    updateHeadingArrows();

    // Redraw arrows when the user changes the zoom level
    map.on('zoomend', function() {
        updateHeadingArrows();
    });

    // ---------------------- Range update function (slider) ----------------------
    mapUpdRange = (chartStartIdx, chartEndIdx) => {
        // Chart indices are newest-first; jsTimeMap is oldest-first.
        // They are not GPS point indices, even when the sample counts match.
        if (!jsTimeMap.length) return;
        const max = jsTimeMap.length - 1;
        const lo = Math.max(0, Math.min(max, Math.min(chartStartIdx, chartEndIdx)));
        const hi = Math.max(0, Math.min(max, Math.max(chartStartIdx, chartEndIdx)));
        const start = jsTimeMap[max - hi];
        const end = jsTimeMap[max - lo];
        window.visibleGpsStart = start;
        window.visibleGpsEnd = end;
        const slicedSegments = gpsSegmentsForTimeRange(window.MapData, start, end);
        window.currentMapSegmentsForHotline = slicedSegments;
        window.currentMapSlicedSegments = slicedSegments;
        const items = slicedSegments.flat();
        window.visibleGpsMapData = {
            gpsChronological: items.filter(item => item.time !== null).reverse(),
            maxGapMs: window.MapData.maxGapMs,
            medianIntervalMs: window.MapData.medianIntervalMs
        };
        window.currentMapSlicedCoords = items.map(p => p.coord);
        window.currentMapSlicedIndices = items.map(p => p.index);
        polyline.setLatLngs(slicedSegments.map(seg => seg.map(p => p.coord)));

        // An empty range MUST clear the previous route and start/end markers.
        if (!items.length) {
            map.removeLayer(startcir);
            map.removeLayer(endcir);
            map.removeLayer(markerPnt);
            headingArrowsLayer.clearLayers();
            if (currentDataSource !== null) updateHotline(currentDataSource);
            return;
        }
        startcir.setLatLng(items[items.length - 1].coord);
        endcir.setLatLng(items[0].coord);
        if (!map.hasLayer(startcir)) startcir.addTo(map);
        if (!map.hasLayer(endcir)) endcir.addTo(map);
        map.removeLayer(markerPnt);

        if (currentDataSource !== null) updateHotline(currentDataSource);
        else if (!map.hasLayer(polyline)) polyline.addTo(map);
        updateHeadingArrows();
        const bounds = polyline.getBounds();
        if (bounds.isValid()) map.fitBounds(bounds, { maxZoom: 15 });
        else map.setView(items[0].coord, 13);
    };

    // ---------------------- Hotline / Heatmap ----------------------
    let hotlineLegend = null;

    function updateLegend(min, max) {
        if (hotlineLegend) {
            map.removeControl(hotlineLegend);
            hotlineLegend = null;
        }
        if (isNaN(min) || isNaN(max)) return;

        hotlineLegend = L.control.hotlineLegend({
            min: Number.isInteger(min) ? min : min.toFixed(2),
            mid: Number.isInteger((min + max) / 2) ? Math.round((min + max) / 2) : ((min + max) / 2).toFixed(2),
            max: Number.isInteger(max) ? max : max.toFixed(2),
            palette: { 0: 'green', 0.5: 'yellow', 1: 'red' },
            position: 'bottomright'
        }).addTo(map);
    }

    function findClosestPoint(latlng, points) {
        if (!points || points.length === 0) return null;
        let minDist = Infinity;
        let closest = null;
        for (let i = 0; i < points.length; i++) {
            const d = latlng.distanceTo(points[i]);
            if (d < minDist) {
                minDist = d;
                closest = points[i];
            }
        }
        return minDist < 1000 ? closest : null;
    }

    let tooltipHideTimer = null;

    function showTooltipAtPoint(point, sourceIndex) {
        if (!point) return;

        map.eachLayer(layer => {
            if (layer instanceof L.Tooltip && layer.options.className === 'heat-data-tooltip') {
                map.removeLayer(layer);
            }
        });

        let timeDisplay = '';
        if (point.time) {
            const realTime = new Date(point.time);
            const use12HourFormat = Cookies.get('timeformat') === '12';
            timeDisplay = realTime.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: use12HourFormat
            });
        }

        const tooltipContent = timeDisplay
            ? `${timeDisplay}<br>${heatData[sourceIndex].label}: ${point.alt}`
            : `${heatData[sourceIndex].label}: ${point.alt}`;

        L.tooltip({
            permanent: false,
            direction: 'top',
            className: 'heat-data-tooltip'
        })
        .setLatLng(point)
        .setContent(tooltipContent)
        .addTo(map);

        if ('ontouchstart' in window) {
            if (tooltipHideTimer) clearTimeout(tooltipHideTimer);
            tooltipHideTimer = setTimeout(() => {
                map.eachLayer(layer => {
                    if (layer instanceof L.Tooltip && layer.options.className === 'heat-data-tooltip') {
                        map.removeLayer(layer);
                    }
                });
                tooltipHideTimer = null;
            }, 5000);
        }
    }

    let activeMetricLookup = null;
    globalThis.updateHotline = function(sourceIndex, rangeFlatIndices, origRangeStart = null, origRangeEnd = null) {
        hotlineLayers.clearLayers();

        pendingHeatPoints = [];
        if (pendingHeatlinePolyline) {
            pendingHeatlinePolyline.setLatLngs([]);
            if (map.hasLayer(pendingHeatlinePolyline)) {
                map.removeLayer(pendingHeatlinePolyline);
            }
        }
        lastHeatCoord = null;

        if (sourceIndex === null || sourceIndex === "" || !heatData || !heatData[sourceIndex]) {
            currentDataSource = null;
            activeMetricLookup = null;
            if (!map.hasLayer(polyline)) polyline.addTo(map);
            if (hotlineLegend) {
                map.removeControl(hotlineLegend);
                hotlineLegend = null;
            }
            return;
        }

        let targetSegmentsIndices;
        if (stream) {
            targetSegmentsIndices = window.MapData.segmentsIndices;
        } else if (window.currentMapSegmentsForHotline) {
            targetSegmentsIndices = window.currentMapSegmentsForHotline;
        } else {
            targetSegmentsIndices = window.MapData.segmentsIndices;
        }

        const sourceData = heatData[sourceIndex].data;
        if (!sourceData || sourceData.length === 0) {
            // Preserve the selection when the chosen time range has no PID
            // samples; restoring the range must restore its heatline too.
            currentDataSource = sourceIndex;
            activeMetricLookup = null;
            if (!map.hasLayer(polyline)) polyline.addTo(map);
            if (hotlineLegend) {
                map.removeControl(hotlineLegend);
                hotlineLegend = null;
            }
            return;
        }

        activeMetricLookup = gpsMetricLookup(sourceData);
        const pointSegments = [];
        let globalMin = Infinity;
        let globalMax = -Infinity;
        // Split again where the selected PID has no contemporaneous reading.
        // One heatline per contiguous valid run, never across GPS/PID gaps.
        for (const segment of targetSegmentsIndices) {
            let points = [];
            for (const { coord, time } of segment) {
                const value = gpsMetricAtTime(activeMetricLookup, time);
                if (value === null) {
                    if (points.length) pointSegments.push(points);
                    points = [];
                    continue;
                }
                const point = L.latLng(coord[0], coord[1]);
                point.alt = value;
                point.time = time;
                points.push(point);
                globalMin = Math.min(globalMin, value);
                globalMax = Math.max(globalMax, value);
            }
            if (points.length) pointSegments.push(points);
        }

        let anyLayer = false;
        if (globalMin === globalMax && Number.isFinite(globalMin)) {
            globalMin -= 0.1;
            globalMax += 0.1;
        }
        for (const points of pointSegments) {
            // A single GPS fix is valid but does not form a colored line.
            if (points.length < 2) continue;
            const hotline = L.hotline(points, {
                min: globalMin,
                max: globalMax,
                palette: { 0.0: 'green', 0.5: 'yellow', 1.0: 'red' },
                weight: 3,
                outlineColor: '#444',
                outlineWidth: 1
            });
            hotline.sourceIndex = sourceIndex;
            hotline.hotlineData = { points, min: globalMin, max: globalMax };
            hotline.on('mousemove click', function(e) {
                const closest = findClosestPoint(e.latlng, this.hotlineData.points);
                if (closest) showTooltipAtPoint(closest, this.sourceIndex);
            });
            hotline.on('mouseout', function() {
                if (!('ontouchstart' in window)) {
                    map.eachLayer(layer => {
                        if (layer instanceof L.Tooltip && layer.options.className === 'heat-data-tooltip') {
                            map.removeLayer(layer);
                        }
                    });
                }
            });
            hotlineLayers.addLayer(hotline);
            anyLayer = true;
        }

        if (anyLayer) {
            currentDataSource = sourceIndex;
            if (map.hasLayer(polyline)) map.removeLayer(polyline);
            updateLegend(globalMin, globalMax);

            if (targetSegmentsIndices.length > 0 && targetSegmentsIndices[0].length > 0) {
                const firstPoint = targetSegmentsIndices[0][0].coord;
                lastHeatCoord = L.latLng(firstPoint[0], firstPoint[1]);
            }
        } else {
            currentDataSource = sourceIndex;
            if (!map.hasLayer(polyline)) polyline.addTo(map);
            if (hotlineLegend) {
                map.removeControl(hotlineLegend);
                hotlineLegend = null;
            }
            lastHeatCoord = null;
        }
    }

    function createDataSourceSelector() {
        let control = L.control({position: 'bottomleft'});

        control.onAdd = function(map) {
            let div = L.DomUtil.create('div', 'data-source-selector');

            // Create basic selector structure
            div.innerHTML = `
                <div class="heat-data">
                    <select id="heat-dataSourceSelect">
                        <option value="">-</option>
                    </select>
                </div>
            `;

            L.DomEvent.disableClickPropagation(div);
            L.DomEvent.disableScrollPropagation(div);

            setTimeout(() => {
                updateDataSourceSelector();

                const select = document.getElementById('heat-dataSourceSelect');
                if (select) {
                    select.addEventListener('change', function() {
                        const selectedOption = this.options[this.selectedIndex];
                        this.title = selectedOption?.text ?? (this.selectedIndex = this.options.length - 1, this.options[this.selectedIndex].text);
                    });

                    select.title = select.options[select.selectedIndex].text;
                }
            }, 100);

            return div;
        };

        return control;
    }

    function updateDataSourceSelector() {
        let select = document.getElementById('heat-dataSourceSelect');
        if (!select) { setTimeout(updateDataSourceSelector, 500); return; }
        let currentValue = select.value;
        while (select.options.length > 1) select.remove(1);
        if (heatData && heatData.length > 0) {
            heatData.forEach((source, index) => {
                let option = document.createElement('option');
                option.value = index;
                option.textContent = source.label;
                select.appendChild(option);
            });
            if (currentValue !== '' && currentValue < heatData.length) {
                select.value = currentValue;
                select.title = select.options[select.selectedIndex].text;
            }
        }
    }

    let dataSourceSelector = createDataSourceSelector().addTo(map);

    const handleSelectorChange = (() => {
        const RESET_DELAY = 1000;
        let updateTimeout = null;
        return function(e) {
            const sourceIndex = e.target.value;
            if (updateTimeout) clearTimeout(updateTimeout);
            if (stream) {
                updateHotline('');
                updateTimeout = setTimeout(() => {
                    updateHotline(sourceIndex);
                    updateTimeout = null;
                }, RESET_DELAY);
            } else {
                updateHotline(sourceIndex);
            }
        };
    })();

    function addSelectorEventHandler() {
        const dataSourceSelect = document.getElementById('heat-dataSourceSelect');
        if (dataSourceSelect) {
            dataSourceSelect.removeEventListener('change', handleSelectorChange);
            dataSourceSelect.addEventListener('change', handleSelectorChange);
        } else {
            setTimeout(addSelectorEventHandler, 500);
        }
    }
    setTimeout(addSelectorEventHandler, 500);

    let lastFlotDataLength = 0;
    setInterval(() => {
        if (heatData && heatData.length !== lastFlotDataLength) {
            // Обновляем карту только если уже было выделение (индексы не null)
            if (mapIndexStart !== null && mapIndexEnd !== null) {
                updateMapWithRangePreservingHeatline(mapIndexStart, mapIndexEnd);
            }
            lastFlotDataLength = heatData.length;
            updateDataSourceSelector();
        }
    }, 1000);

    // ---------------------- Dynamic update while streaming ----------------------
    let streamGpsWasMissing = false;
    const rate = Number(Cookies.get('tracking-rate')) || 1000;
    setInterval(() => {
        let marker = null;
        let lat = stream ? parseFloat($('#lat').html()) : null;
        let lon = stream ? parseFloat($('#lon').html()) : null;
        let spd = stream ? ($('#spd').length != 0 ? $('#spd').html() : localization.key['nospd']) : null;
        let spd_unit = stream ? ($('#spd-unit').length != 0 ? $('#spd-unit').html() : "") : null;
        const hdg = stream ? parseFloat($('#hdg').html()) : 0;
        if (!Number.isFinite(lat) || !Number.isFinite(lon) || lat === 0 || lon === 0 ||
            Math.abs(lat) > 90 || Math.abs(lon) > 180) {
            if (stream) streamGpsWasMissing = true;
            return;
        }

        if (stream) {
            let cleanSpd = stripHtml(spd);
            let speedNum = parseFloat(cleanSpd);
            let hasSpeed = !isNaN(speedNum) && speedNum > 0;

            marker = new L.marker([lat, lon]).bindTooltip(
                `${spd}${spd === localization.key['nospd'] ? '' : ' ' + spd_unit}`,
                { permanent: hasSpeed, direction: 'right', className: "stream-marker" }
            ).addTo(map);
            map.setView(marker.getLatLng(), map.getZoom());

            if (Cookies.get('plot') !== undefined) {
                const newPoint = [lat, lon];
                const segs = window.MapData.segmentsCoords;
                const segsIdx = window.MapData.segmentsIndices;

                let firstSeg = segs.length > 0 ? segs[0] : null;
                let firstSegIdx = segsIdx.length > 0 ? segsIdx[0] : null;

                let distToFirst = Infinity;
                if (firstSeg && firstSeg.length > 0) {
                    const firstPoint = firstSeg[0];
                    distToFirst = Math.sqrt((newPoint[0] - firstPoint[0]) ** 2 + (newPoint[1] - firstPoint[1]) ** 2);
                }

                if (window.MapData.nextIndex === undefined) {
                    const flatIndices = window.MapData.flatIndices || [];
                    window.MapData.nextIndex = flatIndices.length > 0 ? Math.max(...flatIndices) + 1 : 0;
                }
                const newIndex = window.MapData.nextIndex++;
                const now = Date.now();
                const latest = window.rawPath[0];
                const previousTime = Number(latest?.[3]);
                const delta = now - previousTime;
                // Respect invalid stream readings and long pauses even when
                // the car stayed at almost the same geographic coordinate.
                const continuous = !!firstSeg && !streamGpsWasMissing &&
                    distToFirst <= 0.005 && Number.isFinite(delta) && delta > 0 &&
                    delta <= Math.max(rate * 4, window.MapData.maxGapMs);
                const segmentId = continuous ? Number(latest?.[4] || 0) :
                    Number(latest?.[4] || 0) + 1;
                window.rawPath.unshift([newPoint[0], newPoint[1], hdg || 0, now, segmentId]);
                const item = { coord: newPoint, index: newIndex, time: now,
                    segmentNumber: continuous ? firstSegIdx[0].segmentNumber :
                        (firstSegIdx?.[0]?.segmentNumber ?? 0) + 1 };
                if (continuous) {
                    firstSeg.unshift(newPoint);
                    firstSegIdx.unshift(item);
                } else {
                    segs.unshift([newPoint]);
                    segsIdx.unshift([item]);
                }
                streamGpsWasMissing = false;
                window.MapData.origHeading[newIndex] = hdg || 0;
                window.MapData.flatCoords = segs.flat();
                window.MapData.flatIndices = segsIdx.flat().map(p => p.index);
                window.MapData.gpsChronological.push(item);
                window.currentMapSlicedSegments = segsIdx;
                window.currentMapSegmentsForHotline = segsIdx;

                polyline.setLatLngs(segs);
                polyline.redraw();

                endcir.setLatLng(newPoint);

                window.currentMapSlicedCoords = window.MapData.flatCoords.slice();
                window.currentMapSlicedIndices = window.MapData.flatIndices.slice();

                updateHeadingArrows();

                if (!continuous) {
                    pendingHeatPoints = [];
                    lastHeatCoord = null;
                }
                if (currentDataSource !== null && pendingHeatlinePolyline) {
                    pendingHeatPoints.push(newPoint);

                    let pendingCoords;
                    if (lastHeatCoord) {
                        pendingCoords = [lastHeatCoord, ...pendingHeatPoints];
                    } else {
                        pendingCoords = pendingHeatPoints.slice();
                    }

                    pendingHeatlinePolyline.setLatLngs(pendingCoords);

                    if (!map.hasLayer(pendingHeatlinePolyline)) {
                        pendingHeatlinePolyline.addTo(map);
                    }
                }
            }

            setTimeout(() => { map.removeLayer(marker); }, rate);
        }
    }, rate);

    // ---------------------- Coordinates on click ----------------------
    let c = new L.Control.Coordinates({
        latitudeText: localization.key['lat'] ?? 'Latitude',
        longitudeText: localization.key['lon'] ?? 'Longitude',
    });
    c.addTo(map);
    function onMapClick(e) { c.setCoordinates(e); }
    map.on('click', onMapClick);

    // ---------------------- Hotline Legend ----------------------
    L.Control.HotlineLegend = L.Control.extend({
        options: {
            position: 'bottomright',
            min: 0,
            mid: 0.5,
            max: 1,
            palette: { 0: 'green', 0.5: 'yellow', 1: 'red' },
            width: 15,
            height: 80
        },
        initialize: function(options) { L.Util.setOptions(this, options); },
        onAdd: function(map) {
            this._container = L.DomUtil.create('div', 'hotline-legend-container');
            this._container.style.display = 'flex';
            this._container.style.flexDirection = 'row';

            const labelsContainer = L.DomUtil.create('div', 'hotline-legend-labels', this._container);
            labelsContainer.style.display = 'flex';
            labelsContainer.style.flexDirection = 'column';
            labelsContainer.style.justifyContent = 'space-between';
            labelsContainer.style.marginRight = '2px';
            labelsContainer.style.fontSize = '10px';
            labelsContainer.style.fontWeight = 'bold';

            function createLabel(container, value) {
                const label = L.DomUtil.create('div', 'hotline-legend-label', container);
                label.style.border = '2px solid rgba(0, 0, 0, 0.2)';
                label.style.borderRadius = '4px';
                label.style.padding = '2px';
                label.style.background = '#fff';
                label.style.backgroundClip = 'padding-box';
                label.style.textAlign = 'center';
                label.innerHTML = value;
                return label;
            }

            createLabel(labelsContainer, this.options.max);
            createLabel(labelsContainer, this.options.mid);
            createLabel(labelsContainer, this.options.min);

            const canvas = L.DomUtil.create('canvas', 'hotline-legend-canvas', this._container);
            canvas.width = this.options.width;
            canvas.height = this.options.height;
            canvas.style.display = 'block';
            canvas.style.borderRadius = '4px';

            const ctx = canvas.getContext('2d');
            const gradient = ctx.createLinearGradient(0, canvas.height, 0, 0);
            for (let stop in this.options.palette) {
                gradient.addColorStop(stop, this.options.palette[stop]);
            }
            ctx.fillStyle = gradient;
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            return this._container;
        }
    });

    L.control.hotlineLegend = function(options) {
        return new L.Control.HotlineLegend(options);
    };

    // ---------------------- Marker for the slider ----------------------
    const carDivIcon = L.divIcon({
        html: '<div></div>',
        className: 'marker-car',
        iconSize: [16, 16],
        iconAnchor: [8, 10]
    });

    const markerPnt = L.marker(getStartCoord(), { icon: carDivIcon });

    markerUpd = itm => {
        if (stream) {
            map.removeLayer(markerPnt);
            return;
        }
        map.eachLayer(layer => {
            if (layer instanceof L.Tooltip && layer.options.className === 'heat-data-tooltip') {
                map.removeLayer(layer);
            }
        });
        if (!itm?.datapoint || !window.MapData) {
            map.removeLayer(markerPnt);
            return;
        }
        // item.datapoint[0] is the compressed Flot X coordinate.
        // Its corresponding *original* log time is the shared join key.
        const processedTime = Number(itm.datapoint[0]);
        const realTime = window.realTimeInfo?.timeMapping?.[processedTime] ??
            findNearestRealTime(processedTime);
        if (!Number.isFinite(Number(realTime))) {
            map.removeLayer(markerPnt);
            return;
        }
        const time = Number(realTime);
        if (window.visibleGpsStart !== undefined &&
            (time < window.visibleGpsStart || time > window.visibleGpsEnd)) {
            map.removeLayer(markerPnt);
            return;
        }
        const pos = gpsPositionAtTime(window.visibleGpsMapData, time);
        if (!pos) {
            map.removeLayer(markerPnt);
            return;
        }
        markerPnt.setLatLng(pos).addTo(map);

        if (currentDataSource !== null && heatData?.[currentDataSource]) {
            const value = gpsMetricAtTime(activeMetricLookup, time);
            if (value !== null) {
                const label = heatData[currentDataSource].label;
                L.tooltip({
                    permanent: false,
                    direction: 'top',
                    className: 'heat-data-tooltip'
                }).setLatLng(pos).setContent(`${label}: ${value}`).addTo(map);
            }
        }
    };

    // Recompute all GPS consumers in place. No new Leaflet map, timers,
    // event listeners, or loss of heatline/range selection on every toggle.
    window.refreshGpsFilterView = (forceRawForStream = false) => {
        if (stream) return;
        window.MapData = buildGpsDisplayMapData(window.rawPath, forceRawForStream ? 'off' : gpsFilterMode);
        const count = jsTimeMap?.length || 0;
        if (count) {
            const from = mapIndexStart === null ? 0 : mapIndexStart;
            const to = mapIndexEnd === null ? count - 1 : mapIndexEnd;
            mapUpdRange(from, to);
        } else {
            const segments = window.MapData.segmentsIndices;
            window.currentMapSegmentsForHotline = segments;
            window.currentMapSlicedSegments = segments;
            window.currentMapSlicedCoords = window.MapData.flatCoords;
            window.currentMapSlicedIndices = window.MapData.flatIndices;
            window.visibleGpsMapData = window.MapData;
            window.visibleGpsStart = -Infinity;
            window.visibleGpsEnd = Infinity;
            polyline.setLatLngs(window.MapData.segmentsCoords);
            map.removeLayer(markerPnt);
            if (window.MapData.flatCoords.length) {
                startcir.setLatLng(getStartCoord()).addTo(map);
                endcir.setLatLng(getEndCoord()).addTo(map);
                const bounds = polyline.getBounds();
                if (bounds.isValid()) map.fitBounds(bounds, { maxZoom: 15 });
            } else {
                map.removeLayer(startcir);
                map.removeLayer(endcir);
            }
            updateHeadingArrows();
            if (currentDataSource !== null) updateHotline(currentDataSource);
        }
        if (typeof window.syncGpsFilterControl === 'function') window.syncGpsFilterControl();
    };
};
//End of Leaflet Map Providers js code

//slider js code
let [cutStart, cutEnd] = [null, null];
let initSlider = (jsTimeMap, start, end) => {
    $("#slider-range11").off();
    $("#slider-time").off('.rangeDrag');
    $(document).off('.rangeDrag');

    if ($("#slider-range11").hasClass("ui-slider")) {
        $("#slider-range11").slider("destroy");
    }

    const [sessStart, sessEnd] = [jsTimeMap[0], jsTimeMap.at(-1)];

    function timelookup(t) {
        return jsTimeMap.findIndex(e => e === t);
    }

    let TimeStartv = timelookup(start);
    let TimeEndv = timelookup(end);

    function applyRange(v0, v1) {
        if (v0 > v1) { let tmp = v0; v0 = v1; v1 = tmp; }
        $('#slider-time').attr("sv0", jsTimeMap[v0]);
        $('#slider-time').attr("sv1", jsTimeMap[v1]);
        const [a, b] = [jsTimeMap.length - v1 - 1, jsTimeMap.length - v0 - 1];
        if (Math.abs(a - b) < 3) return;
        [cutStart, cutEnd] = [jsTimeMap[v0], jsTimeMap[v1]];
        if (cutStart === sessStart && cutEnd === sessEnd) {
            [cutStart, cutEnd] = [null, null];
        }
        if ($("#map").length && Cookies.get('plot') === undefined) {
            updateMapWithRangePreservingHeatline(a, b);
        }
        if ($(".demo-container").length) chartUpdRange(a, b);
        updateSummaryTable();
        $("#slider-time").val(ctime(jsTimeMap[v0]) + " - " + ctime(jsTimeMap[v1]));
    }

    $(function() {
        let slider = $("#slider-range11");
        let timeDisplay = $("#slider-time");

        slider.slider({
            range: true,
            min: 0,
            max: jsTimeMap.length - 1,
            values: [TimeStartv, TimeEndv],
            slide: function(event, ui) {
                $("#slider-time").val(ctime(jsTimeMap[ui.values[0]]) + " - " + ctime(jsTimeMap[ui.values[1]]));
                $('#slider-time').attr("sv0", jsTimeMap[ui.values[0]]);
                $('#slider-time').attr("sv1", jsTimeMap[ui.values[1]]);
            }
        });

        let initialV0 = slider.slider("values", 0);
        let initialV1 = slider.slider("values", 1);
        $("#slider-time").val(ctime(jsTimeMap[initialV0]) + " - " + ctime(jsTimeMap[initialV1]));
        $('#slider-time').attr("sv0", jsTimeMap[initialV0]);
        $('#slider-time').attr("sv1", jsTimeMap[initialV1]);

        function slideChangeHandler(event, ui) {
            let v0 = slider.slider("values", 0);
            let v1 = slider.slider("values", 1);
            applyRange(v0, v1);
        }
        slider.on('slidechange', slideChangeHandler);

        (function initRangeDrag() {
            if (!timeDisplay.length) return;

            let isDragging = false;
            let startX = 0;
            let startVal0 = 0, startVal1 = 0;

            function getSliderBounds() {
                let min = slider.slider("option", "min");
                let max = slider.slider("option", "max");
                let width = slider.width();
                return { min, max, width };
            }

            function getValues() {
                return slider.slider("values");
            }

            function disableSlideChange() {
                slider.off('slidechange', slideChangeHandler);
            }
            function enableSlideChange() {
                slider.on('slidechange', slideChangeHandler);
            }

            timeDisplay.on("mousedown.rangeDrag", function(e) {
                e.preventDefault();
                isDragging = true;
                startX = e.clientX;
                let vals = getValues();
                startVal0 = vals[0];
                startVal1 = vals[1];
                disableSlideChange();
                $(document).on("mousemove.rangeDrag", onMouseMove);
                $(document).on("mouseup.rangeDrag", onMouseUp);
            });

            function onMouseMove(e) {
                if (!isDragging) return;
                e.preventDefault();
                let deltaX = e.clientX - startX;
                let bounds = getSliderBounds();
                let step = (bounds.max - bounds.min) / bounds.width;
                let deltaVal = deltaX * step;
                let newVal0 = startVal0 + deltaVal;
                let newVal1 = startVal1 + deltaVal;
                let range = startVal1 - startVal0;
                newVal0 = Math.max(bounds.min, Math.min(bounds.max - range, newVal0));
                newVal1 = newVal0 + range;
                newVal0 = Math.round(newVal0);
                newVal1 = Math.round(newVal1);
                if (newVal0 !== startVal0 || newVal1 !== startVal1) {
                    slider.slider("values", [newVal0, newVal1]);
                    $("#slider-time").val(ctime(jsTimeMap[newVal0]) + " - " + ctime(jsTimeMap[newVal1]));
                    $('#slider-time').attr("sv0", jsTimeMap[newVal0]);
                    $('#slider-time').attr("sv1", jsTimeMap[newVal1]);
                    startVal0 = newVal0;
                    startVal1 = newVal1;
                    startX = e.clientX;
                }
            }

            function onMouseUp(e) {
                if (isDragging) {
                    isDragging = false;
                    let v0 = slider.slider("values", 0);
                    let v1 = slider.slider("values", 1);
                    enableSlideChange();
                    applyRange(v0, v1);
                }
                $(document).off(".rangeDrag");
            }

            timeDisplay.on("touchstart.rangeDrag", function(e) {
                let touch = e.originalEvent.touches[0];
                if (!touch) return;
                e.preventDefault();
                isDragging = true;
                startX = touch.clientX;
                let vals = getValues();
                startVal0 = vals[0];
                startVal1 = vals[1];
                disableSlideChange();
                $(document).on("touchmove.rangeDrag", onTouchMove);
                $(document).on("touchend.rangeDrag", onTouchEnd);
            });

            function onTouchMove(e) {
                $("#slider-time").addClass('slider-active');
                if (!isDragging) return;
                e.preventDefault();
                let touch = e.originalEvent.touches[0];
                if (!touch) return;
                let deltaX = touch.clientX - startX;
                let bounds = getSliderBounds();
                let step = (bounds.max - bounds.min) / bounds.width;
                let deltaVal = deltaX * step;
                let newVal0 = startVal0 + deltaVal;
                let newVal1 = startVal1 + deltaVal;
                let range = startVal1 - startVal0;
                newVal0 = Math.max(bounds.min, Math.min(bounds.max - range, newVal0));
                newVal1 = newVal0 + range;
                newVal0 = Math.round(newVal0);
                newVal1 = Math.round(newVal1);
                if (newVal0 !== startVal0 || newVal1 !== startVal1) {
                    slider.slider("values", [newVal0, newVal1]);
                    $("#slider-time").val(ctime(jsTimeMap[newVal0]) + " - " + ctime(jsTimeMap[newVal1]));
                    $('#slider-time').attr("sv0", jsTimeMap[newVal0]);
                    $('#slider-time').attr("sv1", jsTimeMap[newVal1]);
                    startVal0 = newVal0;
                    startVal1 = newVal1;
                    startX = touch.clientX;
                }
            }

            function onTouchEnd(e) {
                $("#slider-time").removeClass('slider-active')
                if (isDragging) {
                    isDragging = false;
                    let v0 = slider.slider("values", 0);
                    let v1 = slider.slider("values", 1);
                    enableSlideChange();
                    applyRange(v0, v1);
                }
                $(document).off(".rangeDrag");
            }
        })();
    });
};
//End slider js code

function updateMapWithRangePreservingHeatline(startIndex = null, endIndex = null) {
    if (startIndex === null || endIndex === null || typeof mapUpdRange !== 'function') return;
    // mapUpdRange now rebuilds the selected heatline without resetting the
    // selector or waiting 300 ms (which used to race with slider changes).
    mapUpdRange(startIndex, endIndex);
}

if ('serviceWorker' in navigator) {
  navigator.serviceWorker
    .register('/static/js/sw.js')
    .then(() => { console.log('Service Worker Registered'); });
}

function toggle_dark() {
 const themeUser = (typeof HEAD_CONFIG !== 'undefined' && HEAD_CONFIG.themeUsername)
   ? HEAD_CONFIG.themeUsername
   : ((typeof HEAD_CONFIG !== 'undefined' && HEAD_CONFIG.publicShare)
      ? 'ratel-public-share' : (typeof username !== 'undefined' ? username : ''));
 if (typeof themeUser !== 'string' || !themeUser.trim()) return;
 document.querySelector('html').style.transition = ".2s";
 switch (localStorage.getItem(`${themeUser}-theme`)) {
  case null:
  case "default":
   localStorage.setItem(`${themeUser}-theme`, "dark");
   let head = document.getElementsByTagName('head')[0];
   let link = document.createElement('link');
   link.rel = 'stylesheet';
   link.href = darkCssUrl;
   head.appendChild(link);
  break;
  case "dark":
   localStorage.setItem(`${themeUser}-theme`, "default");
   let lNode = document.querySelector(`link[href*="${darkCssUrl}"]`);
   if (lNode) lNode.remove();
  break;
 }
}

function logout() {
 location.href='/logout';
}

const alarm = new Audio("data:audio/mpeg;base64,//tQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAWGluZwAAAA8AAAAKAAAKRQBRUVFRUVFRUVFqampqampqampqfn5+fn5+fn5+fpOTk5OTk5OTk5Onp6enp6enp6enubm5ubm5ubm5uc3Nzc3Nzc3Nzc3h4eHh4eHh4eHh8/Pz8/Pz8/Pz8/////////////8AAAAATGF2YzU4LjEzAAAAAAAAAAAAAAAAJAMAAAAAAAAACkXlENEnAAAAAAAAAAAAAAAAAAAAAP/7sGQAAAEXANt9AAAAAAAP8KAAARC8x2v4/IAAAAA/wwAAAAy7wKqYSSCQABQIAhOLB8P4IBhoIS4fLn/Lg/WsH38Hz/w/WVl5eXKkJIACAAATQAAAA9JevD0Su1CkVUcSfDjTpei2m82NDMk3ZYaPOgsvTu/E0VphW41AkW0hKzjy+ncqIuy3WKUl12KV93nU8+5fcWXhEdnluhl0fFiC5CscrgN12vx6f1nJmnv6tSw+7E2PSt3LUN2oHwt1N4wzWlUWkiu3f9p8Xp3/zv/MgAAAwHC+WR3LTrQudFooLDdCs+uP7LkjVSWVFMUtX95vZZ9nbTWbVsYA+LiwgKVnIJHPkZ8fL0rNqrVqVOjNky5VG6igjbZ+8hI4aLWlY/+/93WACAYmDI2CUyUdPtYdSFyEwjQRpfViUVsLbse3dNbafW6uLPhP0sUjXHjwcrAshZGQdEKfTTXOlTrZoavtNYzHWnU1s2z4sI+lZf3s3asQAACAcVKTksI4iyeLi0uh5CarBM7qEtGZQc60cwLvyBmjVtZnPhH+exDTHcrOW0LPtXK5IHYei9JLK/YyMnHx5EHHSNG0cWXTv17VvWcGihov/vNq7AAECiISBEck0PIzDKqSIjKxl/7OCEGg45zv2TLrXWammr5JTkN8diJW3TLNEYBYWSaVy2W4/Ygtds7O1T5q6671o0b3Zb92s4zUcCrq/r7a3UAAAIBZIMEDJ4eNTJ5pLWSspnf4rlhCJjF5md7c6UTTv1UoXCSZUJOEsTTTrIjFyYgFkKa6SSlPRk59GLY5x50GGC9fKIa71Mu/L3L1AAQMRFCIJKsHkAoQ//tgZNKC8pYpXfcxgAIAAA/w4AABCbirecS9OAgAAD/AAAAEJ1AsHs33i5YBQtjevnjCCFrqKJJJVUIwkWXwdZwPXbP02PSAYmY5DQkzaHDEZbJ8ac/jfVq1d18fZmZkEDF9r6Uq/LzKnCAAAABWocqlejnLJ1sbGzyQ4a7P+iS+cXSRMUQj3JzcnLysceBEntb/VNPZqH2KjxmovPPSq80xgMKdKBWFu3HIhH7soiE+5cNztE1ejtXJRLKSWRkqim7GGGHverWHTn/f/r3TAEryornxGH8JhhEgVc4cTeNf1Y0CokCcscx3oKXeZhhhh3trY+AFAMV1yJAuSxCNS6SiYXTv//tQZPKC8q4uXXGPThIAAA/wAAABChS3dcS9mAgAAD/AAAAEm09Dh8z1AOIIkSlavvG1fbzu9Rgvf+DK/83cu1AAAEBeZsQnvAIoTyMaWWGhipfpWWKhEXx0Y9ys8VUCde2rclBKOgzPNP6FwknxwUyUSG25blYvPzxAwiVvGuYo633a7gGlFEIN333ZesAABObwYPOQGyYngH00zraBX9LC3AmUe/e2tDUpySrzu4yqaUeLE9nhZbVEyv2BJvnl6S7eHphGHytKAxdhFFuxDP/7UGT1AvJmKN3xL04CAAAP8AAAAQowt3XEvZgIAAA/wAAABFHbXv3vytpAAABAjMz4SCqbj2WojDkhxEgIf0nIKF5MBk8q/akrD2/Nuod4GJvd1vP4twpJwKtkVOEOCknFhBEQVpliTl5wdxjorXi+yhabpqLX29yma3iyaUn4/R/V25mIAEBILiKBt4SEnEbRHJYLIGynqoFhoGQhusM+FVT3GqQ3yl71TrgArhCfOv8PouMUEmk4sGbFli9InaNB/gNnNRrFt2nUJ+R8JEz/+1Bk+4PzJDFb8fg2IgAAD/AAAAEKQLV1xiWHiAAAP8AAAATPEIeV/+7e34AAAEDphQRmjxIX0ohJLiHwsrK6gSLETB5kI9hnzDsls6sOjsGULj9rqzVc6mLqZ37sbBC06jYPKx0htSGzX8nIOe5f93d7NYAABziRGDAshJoHRUT3IgL2X/igWCyJn43LstwqLl6hOoXGGuVGM41mWR1RUBIhIhGKBWgmukmkXKF1oiXWF0TM1F7q7SkMPZSMKv/M3cswAAAAcKDIqFu8cTLE//tAZPYC8m8pXfEjZYIAAA/wAAABCRyfecS82EgAAD/AAAAEJAomTMan/cFgsGm1sLFiHoYCJ5TS1sqjSbjsrUL9MyImKZ4TjFc7Wy1MhpDs8Q18DK5S9Rtim2ZAwxbYC339u3mIAEA5RILAEC6gGziEsujIAVLW+ZrRoQgbKmb1tuX56tc3J/rxVHEAUAqgyvAuJRGKpcIwglJWiiVGJKLpUM0A7LTj7j7jbC+8EMG/S9Kb8V7/+1Bk84Ly0zDc8Y9mAAAAD/AAAAEKQMN1xLB1CAAAP8AAAARFu4AHcIcHYGUIcA5oGwAAAAAMBvmqadlRq52aGykta2S+MoKXFnldJE1HJ+ZkmAJZFSkQYvFwTzpGRsDdEHKJRImVRSAn00KQs4ckgv6BPmZkyKFWCkxBTUUzLjEwMFVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVV//tQZPMC8j4o3vEjZKIAAA/wAAABCcyld8S9OAgAAD/AAAAEVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVf/7QGT9gvJzKV3xI2SiAAAP8AAAAQrQv3PUlgAIAAA/woAABFVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVV//sgZPQAAqApV34eQAIAAA/wwAAAAAAB/hwAACAAAD/DgAAEVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVQ==");
 //Rollback stream alarm
setInterval(()=> {
 if ($("#rollback").length && $("#rollback").text() != "OK") {
  alarm.play();
 }
}, 3000);

//Key events
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        const dialogOverlay = document.querySelector('#redDialogOverLay');
        if (dialogOverlay) {
            event.preventDefault();
            redDialog.dismiss();
        }

        const menuToggle = document.querySelector('.menu-toggle');
        if (menuToggle) {
            menuToggle.checked = false;
        }
    } else if (event.key === 'Delete' && typeof delSession === 'function') {
        const t = event.target;
        const inEditable = t && (
            t.tagName === 'INPUT'  ||
            t.tagName === 'TEXTAREA' ||
            t.tagName === 'SELECT' ||
            t.isContentEditable ||
            (t.closest && t.closest('[contenteditable=""],[contenteditable="true"],input,textarea,select'))
        );
        if (!inEditable) delSession();
    }
});

document.addEventListener('mouseover', function(e) {
    if (e.target.hasAttribute('title')) {
        e.target.dataset.originalTitle = e.target.getAttribute('title');
        e.target.removeAttribute('title');
    }
}, true);

//Sort. by duration/datapoints in delete/merge sessions
function sortMergeDel() {
  if ($("head style.table-sort-indicators").length === 0) {
    $("<style>")
      .prop("type", "text/css")
      .addClass("table-sort-indicators")
      .html(`
        th.sorted-asc::after { content: " ▲"; }
        th.sorted-desc::after { content: " ▼"; }
      `)
      .appendTo("head");
  }

  function assignRowClickHandlers() {
    $(".table-del-merge-pid tbody tr").off("click").on("click", function(e) {
      if (e.target.type !== "checkbox") {
        $(":checkbox", this).trigger("click");
      }
    });
  }

  assignRowClickHandlers();

  $(".table-del-merge-pid thead th:eq(3), .table-del-merge-pid thead th:eq(4), .table-del-merge-pid thead th:eq(5)")
    .addClass("sortable")
    .css("cursor", "pointer");

  $(".table-del-merge-pid thead th:eq(1)")
    .addClass("reset-sort")
    .css("cursor", "pointer");

  var originalRows = $(".table-del-merge-pid tbody tr").toArray();

  $(".table-del-merge-pid thead th.sortable").click(function() {
    var table = $(this).parents("table").eq(0);
    var index = $(this).index();
    var rows = table.find("tbody tr").toArray().sort(comparer(index));

    this.asc = !this.asc;
    if (!this.asc) {
      rows = rows.reverse();
    }

    table.find("th").removeClass("sorted-asc sorted-desc");
    $(this).addClass(this.asc ? "sorted-asc" : "sorted-desc");

    table.find("tbody").empty();
    for (var i = 0; i < rows.length; i++) {
      table.find("tbody").append(rows[i]);
    }

    assignRowClickHandlers();
  });

  $(".table-del-merge-pid thead th.reset-sort").click(function() {
    var table = $(this).parents("table").eq(0);

    table.find("th").removeClass("sorted-asc sorted-desc");

    table.find("tbody").empty();
    for (var i = 0; i < originalRows.length; i++) {
      table.find("tbody").append(originalRows[i]);
    }

    assignRowClickHandlers();
  });

  function comparer(index) {
    return function(a, b) {
      var valA = getCellValue(a, index);
      var valB = getCellValue(b, index);

      if (index === 3) { // duration
        return parseDuration(valA) - parseDuration(valB);
      } else if (index === 4) { // datapoints
        return parseInt(valA) - parseInt(valB);
      } else if (index === 5) { // profile (string comparison)
        return valA.localeCompare(valB);
      }
    };
  }

  function getCellValue(row, index) {
    return $(row).children("td").eq(index).text().trim();
  }

  function parseDuration(durationStr) {
    var parts = durationStr.split(":");
    return parseInt(parts[0]) * 3600 + parseInt(parts[1]) * 60 + parseInt(parts[2]);
  }
}

const mapResize = (() => {
    let timer = null;
    return () => {
        if (timer) clearTimeout(timer);
        timer = setTimeout(() => {
            if (!map || !polyline) return;
            map.invalidateSize();

            const bounds = polyline.getBounds();
            if (bounds.isValid()) {
                map.fitBounds(bounds);
            } else {
                const flatCoords = window.MapData?.flatCoords;
                if (flatCoords && flatCoords.length > 0) {
                    map.setView(flatCoords[0], 13);
                } else {
                    map.setView([0, 0], 2);
                }
            }
        }, 100);
    };
})();

function resizeSplitter() {
  if (nogps) {
    $(".resizer").css("display", "none");
    return;
  }

  const container = document.querySelector('.split-container');
  const resizer = container?.querySelector('.resizer');
  const leftPane = container?.querySelector('.left');
  const rightPane = container?.querySelector('.right');
  if (!container || !resizer || !leftPane || !rightPane) return;

  const workspace = container.closest('.session-workspace');
  const STORAGE_KEY = `${storageUserPrefix}-splitter_left_width`;
  const MIN_CHART_WIDTH = 500;
  const MIN_MAP_WIDTH = 300;
  let isResizing = false;
  let preferredPercent = 50;
  let priorStreamEnabled = !!workspace?.classList.contains('stream-enabled');
  // The width outside Stream is a snapshot. Temporary clamping while Stream
  // is open must never replace it with the narrower workspace's map width.
  let mapWidthBeforeStream = null;
  let lastContainerWidth = 0;
  let priorHorizontal = false;

  function isHorizontal() {
    return getComputedStyle(container).flexDirection === 'row';
  }

  function availablePaneWidth() {
    const style = getComputedStyle(container);
    const handleStyle = getComputedStyle(resizer);
    const gap = parseFloat(style.columnGap) || 0;
    const marginLeft = parseFloat(handleStyle.marginLeft) || 0;
    const marginRight = parseFloat(handleStyle.marginRight) || 0;
    // The handle can be hidden in the responsive (vertical) layout.
    const handleWidth = parseFloat(handleStyle.width) || 12;
    return Math.max(0, container.clientWidth - 2 * gap - handleWidth - marginLeft - marginRight);
  }

  function applySplitterPosition(restoreMapWidth = null) {
    if (!isHorizontal()) {
      // Let the existing <=55em media query stack chart and map.
      leftPane.style.width = '';
      rightPane.style.width = '';
      return;
    }

    const paneWidth = availablePaneWidth();
    // When Stream reduces available space, the chart may become smaller than
    // 500px; otherwise the two flex children wrap and the map drops below it.
    // Never let the map shrink below 300px in horizontal mode.
    const maxLeft = Math.max(0, paneWidth - MIN_MAP_WIDTH);
    const minLeft = Math.min(MIN_CHART_WIDTH, maxLeft);
    const requested = restoreMapWidth === null
      ? container.clientWidth * preferredPercent / 100
      : paneWidth - restoreMapWidth;
    const leftWidth = Math.min(maxLeft, Math.max(minLeft, requested));
    leftPane.style.width = `${leftWidth}px`;
    rightPane.style.width = ''; // Right flex pane fills the remainder.

    if (restoreMapWidth !== null) {
      // Update the preference only on exit, not while Stream is active.
      preferredPercent = 100 * leftWidth / container.clientWidth;
    }
  }

  function handleResize() {
    const streamEnabled = !!workspace?.classList.contains('stream-enabled');
    const horizontal = isHorizontal();
    const width = container.clientWidth;
    const leavingStream = priorStreamEnabled && !streamEnabled;
    const wasHorizontal = priorHorizontal;

    applySplitterPosition(leavingStream ? mapWidthBeforeStream : null);

    // Capture the actual visible size only outside Stream. It is intentionally
    // not changed by the reduced width of the main column while streaming.
    if (!streamEnabled && horizontal) {
      mapWidthBeforeStream = rightPane.getBoundingClientRect().width;
    }
    priorStreamEnabled = streamEnabled;
    priorHorizontal = horizontal;
    if (width !== lastContainerWidth || horizontal !== wasHorizontal || leavingStream) {
      lastContainerWidth = width;
      if (typeof map !== 'undefined' && map) map.invalidateSize();
    }
  }

  const resizeObserver = new ResizeObserver(handleResize);
  resizeObserver.observe(container);

  function startResize(event) {
    if (!isHorizontal()) return;
    if (event?.type === 'mousedown' && event.button !== 0) return;
    isResizing = true;
    document.body.style.cursor = 'col-resize';
  }

  function stopResize() {
    if (isResizing) {
      saveSplitterPosition();
      mapResize();
    }
    isResizing = false;
    document.body.style.cursor = 'default';
  }

  function resize(x) {
    if (!isResizing || !isHorizontal()) return;
    const paneWidth = availablePaneWidth();
    const maxLeft = Math.max(0, paneWidth - MIN_MAP_WIDTH);
    const minLeft = Math.min(MIN_CHART_WIDTH, maxLeft);
    const desiredWidth = x - container.getBoundingClientRect().left;
    const leftWidth = Math.min(maxLeft, Math.max(minLeft, desiredWidth));
    preferredPercent = 100 * leftWidth / container.clientWidth;
    applySplitterPosition();
    if (!workspace?.classList.contains('stream-enabled')) {
      mapWidthBeforeStream = rightPane.getBoundingClientRect().width;
    }
    mapResize();
  }

  function saveSplitterPosition() {
    // Keep compatibility with saved percentage positions from earlier versions.
    // A stream-only adjustment is temporary and is not persisted.
    if (workspace?.classList.contains('stream-enabled')) return;
    try { localStorage.setItem(STORAGE_KEY, preferredPercent.toFixed(2)); }
    catch (_) { /* Resizing also works when storage is disabled. */ }
  }

  function restoreSplitterPosition() {
    try {
      const saved = Number(localStorage.getItem(STORAGE_KEY));
      if (Number.isFinite(saved) && saved > 10 && saved < 90) preferredPercent = saved;
    } catch (_) { /* Optional. */ }
    handleResize();
    mapResize();
  }

  function resetSplitterPosition() {
    preferredPercent = 50;
    try { localStorage.removeItem(STORAGE_KEY); } catch (_) { /* Optional. */ }
    applySplitterPosition();
    if (!workspace?.classList.contains('stream-enabled') && isHorizontal()) {
      mapWidthBeforeStream = rightPane.getBoundingClientRect().width;
    }
    mapResize();
  }

  resizer.addEventListener('mousedown', e => {
    e.preventDefault();
    startResize(e);
  });
  document.addEventListener('mousemove', e => resize(e.clientX));
  document.addEventListener('mouseup', stopResize);
  resizer.addEventListener('touchstart', e => startResize(e), { passive: true });
  document.addEventListener('touchmove', e => {
    if (e.touches.length > 0) resize(e.touches[0].clientX);
  });
  document.addEventListener('touchend', stopResize);
  resizer.addEventListener('dblclick', resetSplitterPosition);
  longTap('.resizer', resetSplitterPosition);

  restoreSplitterPosition();
}

function longTap(selector, callback) {
    let timer;

    let elements;
    if (selector.startsWith('#')) {
        elements = [document.getElementById(selector.slice(1))];
    } else if (selector.startsWith('.')) {
        elements = Array.from(document.getElementsByClassName(selector.slice(1)));
    } else {
        elements = [document.getElementById(selector)];
    }

    elements.forEach(element => {
        if (!element) return;

        element.addEventListener('touchstart', function(e) {
            if (e.touches.length !== 1) return;

            timer = setTimeout(() => {
                callback(element);
            }, 1000);
        });

        element.addEventListener('touchend', function() {
            clearTimeout(timer);
        });

        element.addEventListener('touchmove', function() {
            clearTimeout(timer);
        });

        element.addEventListener('touchcancel', function() {
            clearTimeout(timer);
        });
    });
}

function serverError(msg = '') {
 $("#wait_layout").hide();
 let dialogOpt = {
    title : localization.key['dialog.token.err'],
    btnClassSuccessText: "OK",
    btnClassFail: "hidden",
    message : `${localization.key['dialog.token.err.msg']} ${msg}`
 };
 redDialog.make(dialogOpt);
}

function xhrResponse(text) {
    const s = String(text ?? '').trim();
    if (/^<!DOCTYPE/i.test(s) || /^<html[\s>]/i.test(s)) {
        $("#wait_layout").hide();
        location.href = '/';
        return;
    }

    let dialogOpt = {
        title: localization.key['dialog.result'],
        message : text,
        btnClassSuccessText: "OK",
        btnClassFail: "hidden",
    };
    redDialog.make(dialogOpt);
}

let isToggleInProgress = false;
const TOGGLE_DELAY = 300;

function chartToggle() {
    if (isToggleInProgress || !flotData.length) return;

    isToggleInProgress = true;

    const fillKey = `${storageUserPrefix}-chart_fill`;
    const gradientKey = `${storageUserPrefix}-chart_fillGradient`;
    const widthKey = `${storageUserPrefix}-chart_lineWidth`;

    const isFill = localStorage.getItem(fillKey) === 'true';
    const isGradient = localStorage.getItem(gradientKey) === 'true';
    const currentWidth = parseFloat(localStorage.getItem(widthKey)) || 2;

    const widthSequence = [2, 3, 1, 1.5];
    const currentWidthIndex = widthSequence.indexOf(currentWidth);
    const nextWidthIndex = (currentWidthIndex + 1) % widthSequence.length;
    const newWidth = widthSequence[nextWidthIndex];

    let newFill = isFill;
    let newGradient = isGradient;
    let finalWidth = newWidth;

    if (!isFill && !isGradient) {
        if (newWidth === 2 && currentWidth === 1.5) {
            newFill = true;
            finalWidth = 2;
        }
    } else if (isFill && !isGradient) {
        if (newWidth === 2 && currentWidth === 1.5) {
            newGradient = true;
            finalWidth = 2;
        }
    } else if (isFill && isGradient) {
        if (newWidth === 2 && currentWidth === 1.5) {
            newFill = false;
            newGradient = false;
            finalWidth = 2;
        }
    }

    localStorage.setItem(fillKey, newFill);
    localStorage.setItem(gradientKey, newGradient);
    localStorage.setItem(widthKey, finalWidth);

    chart_fill = newFill;
    chart_fillGradient = newGradient;
    chart_lineWidth = finalWidth;

    doPlot();
    initSlider(jsTimeMap,jsTimeMap[0],jsTimeMap.at(-1))

    setTimeout(() => {
        isToggleInProgress = false;
    }, TOGGLE_DELAY);
};

function copyToClipboard(text = '') {
    try {
        // Try modern Clipboard API first
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).catch((err) => {
                requestAnimationFrame(() => serverError(err));
            });
        } else {
            // Fallback for HTTP or unsupported contexts
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            const successful = document.execCommand('copy');
            document.body.removeChild(textarea);

            if (!successful) {
                requestAnimationFrame(() => serverError(new Error('execCommand failed')));
            }
        }
    } catch (err) {
        requestAnimationFrame(() => serverError(err));
    }
}

function updateSessionDuration() {
  const timeInput = document.getElementById('slider-time');
  if (!timeInput) {
    return;
  }

  const getTime = (attr) => {
    const value = timeInput.getAttribute(attr);
    if (!value) return null;
    const timestamp = parseInt(value);
    return isNaN(timestamp) ? null : timestamp;
  };

  const startTime = getTime('sv0');
  const endTime = getTime('sv1');

  if (!startTime || !endTime || endTime <= startTime) {
    return;
  }

  const durationSec = Math.floor((endTime - startTime) / 1000);
  const days = Math.floor(durationSec / 86400);
  const hours = Math.floor((durationSec % 86400) / 3600);
  const minutes = Math.floor((durationSec % 3600) / 60);
  const seconds = durationSec % 60;

  let durationStr = '';

  if (days > 0) {
    durationStr += `${days}${localization.key['days']} `;
  }

  durationStr += [
    hours.toString().padStart(2, '0'),
    minutes.toString().padStart(2, '0'),
    seconds.toString().padStart(2, '0')
  ].join(':');

  const durationText = localization?.key?.['get.sess.length'];

  $(`.choices__item:has(svg)`).html((i, oldHtml) =>
    oldHtml.replace(
        /\((?:[^\s()]+ )?\d{2}:\d{2}:\d{2}\)/,
        `(${durationText} ${durationStr})`
    )
  );

  markActiveSess();
}

function markActiveSess() {
    const items = document.querySelectorAll('.choices__item');
    items.forEach(item => {
        if (item.textContent.includes(localization.key['get.sess.active'])) {
            item.innerHTML = item.innerHTML.replace(
                localization.key['get.sess.active'],
                `<span style="color: #961911;">${localization.key['get.sess.active']}</span>`
            );
        }
    });
}

function markCurrSess() {
    const items = document.querySelectorAll('.choices__item');
    items.forEach(item => {
        if (item.textContent.includes(localization.key['get.sess.curr'])) {
            item.innerHTML = item.innerHTML.replace(
                localization.key['get.sess.curr'],
                `<svg style="float:right; transform:rotate(-45deg); margin-top:2px; opacity:.5;" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.25" d="M12.753 4.67L1.83 1.106a.564.564 0 0 0-.704.732l4.04 10.932a.35.35 0 0 0 .663-.015l1.53-4.81a1 1 0 0 1 .575-.623l4.845-1.982a.358.358 0 0 0-.025-.672"/></svg>`
            );
        }
    });
}

let rlbc = null;
//RedManage rollback events list
const events = ["KNK","EGT","EOP","FLP","EOT","ECT","OVB","AFR","IAT","MAP","FAN","ATF","AAT","EXT","VLT","RPM"];

//RedManage rollback events decode
function calculate(number) {
  const getCode = (b, bitNumber) => (b >> bitNumber) & 0x01;
  let msg = "";

  if (number === 0) {
    msg = "OK";
  } else {
    events.forEach((event, index) => {
      if (getCode(number, index) === 1) {
        msg += `${event} `;
      }
    });
  }
  return msg;
}

//passwords inputs visibility toggle
class PasswordToggle {
    static initAll() {
        document.querySelectorAll('.password-toggle').forEach(container => {
            PasswordToggle.init(container);
        });
    }

    static init(container) {
        const input = container.querySelector('input[type="password"], input[type="text"]');
        const button = container.querySelector('.password-toggle__btn');

        if (input && button) {
            button.addEventListener('click', () => {
                const isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                button.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
            });
        }
    }
}

//clear inputs
class ClearInput {
    static initAll() {
        document.querySelectorAll('.clear-input').forEach(container => {
            ClearInput.init(container);
        });
    }

    static init(container) {
        const input = container.querySelector('.clear-input__input');
        const button = container.querySelector('.clear-input__btn');

        if (input && button) {
            const clickHandler = () => {
                input.value = '';
                input.focus();
                input.dispatchEvent(new Event('input', { bubbles: true }));
            };

            button.addEventListener('click', clickHandler);
            container._clearHandler = clickHandler;
        }
    }
}

function showHints() {
    xhrResponse(`
    <ul class='hint'>
        <li>${localization.key['hint.scale']}</li>
        <li>${localization.key['hint.reset']}</li>
        <li>${localization.key['hint.export']}</li>
        <li>${localization.key['hint.delete']}</li>
        <li>${localization.key['hint.legend']}</li>
        <li>${localization.key['hint.delbutt']}</li>
    </ul>`
    );
}

function stripHtml(html) {
    let result = '';
    let inTag = false;
    for (let i = 0; i < html.length; i++) {
        if (html[i] === '<') {
            inTag = true;
        } else if (html[i] === '>') {
            inTag = false;
        } else if (!inTag) {
            result += html[i];
        }
    }
    return result;
}

function favoriteSessions() {
    location.href = "/favorites";
}

function delSessions() {
    location.href = "/sessions";
}

function pidEdit() {
    location.href = "/pids";
}

function usersSettings() {
    location.href = "/settings";
}

function remoteRa() {
    location.href = "/settings/remote";
}

function showToken() {
    $("#wait_layout").show();

    fetch("/settings/token?get_token")
        .then(response => {
            if (response.ok) {
                return response.text();
            }
        })
        .then(token => {
            $("#wait_layout").hide();
            const dialogOpt = {
                title: `${localization.key['dialog.token']} <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" style="float: right;"><path fill="currentColor" d="M7 14q-.825 0-1.412-.587T5 12t.588-1.412T7 10t1.413.588T9 12t-.587 1.413T7 14m0 4q-2.5 0-4.25-1.75T1 12t1.75-4.25T7 6q1.675 0 3.038.825T12.2 9H21l3 3l-4.5 4.5l-2-1.5l-2 1.5l-2.125-1.5H12.2q-.8 1.35-2.162 2.175T7 18m0-2q1.4 0 2.463-.85T10.875 13H14l1.45 1.025L17.5 12.5l1.775 1.375L21.15 12l-1-1h-9.275q-.35-1.3-1.412-2.15T7 8Q5.35 8 4.175 9.175T3 12t1.175 2.825T7 16"></path></svg>`,
                btnClassSuccessText: localization.key['btn.copy'],
                btnClassFail: "btn btn-info btn-sm",
                btnClassFailText: localization.key['btn.renew'],
                rejectOnDismiss: false,
                message: token,
                onResolve: function() {
                    copyToClipboard(token);
                },
                onReject: function() {
                    $("#wait_layout").show();

                    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                    const fd = new FormData();
                    if (csrfMeta) fd.append('csrf_token', csrfMeta.content);

                    fetch("/settings/token?renew_token", {
                        method: 'POST',
                        body: fd,
                        credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    })
                    .then(response => {
                        if (response.ok) {
                            showToken();
                        } else {
                            serverError();
                        }
                    })
                    .catch(() => serverError());
                }
            };
            redDialog.make(dialogOpt);
            $("#dialogText").css({"letter-spacing": ".6px", "font-family": "monospace"});
            $("#redDialog_title").css({"background-image": "none"});
        })
        .catch(error => {
            console.error('Error:', error);
            $("#wait_layout").hide();
            serverError();
        });
}

function rebuildMapFromRawPath() {
    // Stream teardown rebuilds the Leaflet map. If it is in pseudo-fullscreen,
    // map.remove() disposes the control but leaves the fullscreen CSS class on
    // #map. The replacement control then starts with _isFullscreen=false, so
    // the first click only changes its icon instead of exiting fullscreen.
    // Exit through the existing control before removal (also restores the
    // Stream overlay to its anchor), then restore fullscreen on the new map.
    const restorePseudoFullscreen = !!(map && map._isFullscreen &&
        map.fullscreenControl?.options.forcePseudoFullscreen);
    if (restorePseudoFullscreen) map.toggleFullscreen();

    if (map) {
        map.remove();
        map = null;
    }

    const rawPath = window.rawPath;
    if (!rawPath.length) return;

    window.MapData = buildGpsDisplayMapData(rawPath);
    // Active slider bounds must be reapplied after the Leaflet rebuild.
    window.currentMapSegmentsForHotline = window.MapData.segmentsIndices;

    initMapLeaflet();
    if (mapIndexStart !== null && mapIndexEnd !== null && !stream) {
        mapUpdRange(mapIndexStart, mapIndexEnd);
    }
    if (restorePseudoFullscreen && map?.fullscreenControl) {
        map.toggleFullscreen();
    }

    setTimeout(() => {
        const heatSelect = document.getElementById('heat-dataSourceSelect');
        const activeHeat = window.currentDataSource;

        if (heatSelect) {
            if (activeHeat !== null && activeHeat !== undefined && activeHeat !== '') {
                heatSelect.value = String(activeHeat);
                updateHotline(activeHeat);
            } else {
                heatSelect.value = "";
                updateHotline("");
            }
        }
    }, 1000);
}

/* ────────────────────────────────────────────────────────────
 * Heavy task polling + localStorage persistence + отдельный
 * динамический индикатор (не пересекается с .fetch-data).
 * ──────────────────────────────────────────────────────────── */

const HEAVY_TASKS_LS_KEY     = 'heavy_tasks_pending';
const HEAVY_TASKS_TTL_MS     = 24 * 3600 * 1000;   // 24ч — как TTL в Redis
const HEAVY_INDICATOR_ID     = 'heavyTaskIndicator';

// Состояние индикатора: refcount + метаданные активных задач
let heavyTasksActiveCount = 0;
const heavyTasksMeta = new Map();   // taskId → { type }

/* ── Индикатор ─────────────────────────────────────────────── */

function heavyIndicatorEnsure() {
    let el = document.getElementById(HEAVY_INDICATOR_ID);
    if (el) return el;

    el = document.createElement('div');
    el.id = HEAVY_INDICATOR_ID;
    el.setAttribute('role', 'status');
    el.style.cssText = [
        'position: fixed',
        'bottom: 20px',
        'left: 20px',
        'z-index: 999',
        'background: #961911',
        'color: #fff',
        'font-size: 13px',
        'line-height: 1',
        'padding: 9px 14px',
        'border-radius: 6px',
        'display: flex',
        'align-items: center',
        'gap: 9px',
        'pointer-events: none',
        'user-select: none',
        'transition: opacity .2s ease',
    ].join(';');

    el.innerHTML = `
        <span style="
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255,255,255,.35);
            border-top-color: #fff;
            border-radius: 50%;
            animation: heavyTaskSpin .9s linear infinite;
        "></span>
        <span data-role="text"></span>
    `;

    // keyframes — один раз на страницу
    if (!document.getElementById('heavyTaskSpinStyle')) {
        const style = document.createElement('style');
        style.id = 'heavyTaskSpinStyle';
        style.textContent = '@keyframes heavyTaskSpin { to { transform: rotate(360deg); } }';
        document.head.appendChild(style);
    }

    document.body.appendChild(el);
    return el;
}

function heavyIndicatorLabel(type) {
    const t = (typeof localization !== 'undefined' && localization.key) || {};
    switch (type) {
        case 'delete_sessions':
            return t['heavy.delete_sessions'] || 'Deleting sessions...';
        case 'delete_user':
            return t['heavy.delete_user']     || 'Deleting user...';
        case 'truncate_user':
            return t['heavy.truncate_user']   || 'Truncating user data...';
        case 'merge_sessions':
            return t['heavy.merge_sessions'] || 'Merging sessions...';
        default:
            return t['heavy.running'] || 'Running...';
    }
}

function heavyIndicatorMultiLabel(n) {
    const t = (typeof localization !== 'undefined' && localization.key) || {};
    const tmpl = t['heavy.multi'] || '{n} tasks running...';
    return tmpl.replace('{n}', n);
}

function heavyIndicatorUpdate() {
    let el = document.getElementById(HEAVY_INDICATOR_ID);

    if (heavyTasksActiveCount <= 0) {
        if (el) el.remove();
        return;
    }

    // ── вот тут была ошибка: не переприсваивали el ──
    el = heavyIndicatorEnsure();

    const types = Array.from(heavyTasksMeta.values()).map(m => m.type);
    const text  = types.length === 1
        ? heavyIndicatorLabel(types[0])
        : heavyIndicatorMultiLabel(types.length);

    const textEl = el.querySelector('[data-role="text"]');
    if (textEl) textEl.textContent = text;
}

function heavyIndicatorShow(taskId, type) {
    if (heavyTasksMeta.has(taskId)) return;   // защита от двойного вызова

    heavyTasksMeta.set(taskId, { type: type || null });
    heavyTasksActiveCount++;
    heavyIndicatorUpdate();
}

function heavyIndicatorHide(taskId) {
    if (heavyTasksMeta.has(taskId)) {
        heavyTasksMeta.delete(taskId);
        heavyTasksActiveCount = Math.max(0, heavyTasksActiveCount - 1);
    }
    heavyIndicatorUpdate();
}

/* ── localStorage persistence ─────────────────────────────── */

function heavyTasksGetPending() {
    try {
        const raw = localStorage.getItem(HEAVY_TASKS_LS_KEY);
        if (!raw) return [];
        const arr = JSON.parse(raw);
        return Array.isArray(arr) ? arr : [];
    } catch (e) {
        return [];
    }
}

function heavyTasksSetPending(list) {
    try {
        if (!list || list.length === 0) {
            localStorage.removeItem(HEAVY_TASKS_LS_KEY);
        } else {
            localStorage.setItem(HEAVY_TASKS_LS_KEY, JSON.stringify(list));
        }
    } catch (e) {
        // localStorage может быть отключён — молча игнорируем
    }
}

function heavyTaskTrack(taskId, meta) {
    const pending = heavyTasksGetPending();
    if (pending.some(t => t.task_id === taskId)) return;

    pending.push({
        task_id:    taskId,
        type:       (meta && meta.type)   || null,
        origin:     (meta && meta.origin) || (window.location.pathname + window.location.search),
        started_at: Date.now(),
    });
    heavyTasksSetPending(pending);
}

function heavyTaskUntrack(taskId) {
    const pending = heavyTasksGetPending().filter(t => t.task_id !== taskId);
    heavyTasksSetPending(pending);
}

/* ── Основной polling ─────────────────────────────────────── */

function pollHeavyTask(taskId, options) {
    options = options || {};

    if (heavyTasksMeta.has(taskId)) {
        return;
    }

    const interval    = options.interval    || 2000;
    const maxAttempts = options.maxAttempts || 900;   // ~30 минут

    heavyTaskTrack(taskId, { type: options.taskType });
    heavyIndicatorShow(taskId, options.taskType);

    let attempts        = 0;
    let consecutiveErrs = 0;
    let finished        = false;

    function finish(callback) {
        if (finished) return;
        finished = true;
        heavyTaskUntrack(taskId);
        heavyIndicatorHide(taskId);
        if (typeof callback === 'function') callback();
    }

    function tick() {
        attempts++;
        if (attempts > maxAttempts) {
            finish(() => serverError('Task timeout'));
            return;
        }

        fetch('/tasks/' + encodeURIComponent(taskId), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
        .then(r => {
            const ct = r.headers.get('content-type') || '';
            if (!ct.includes('application/json')) {
                location.href = '/';
                throw new Error('unexpected response');
            }
            return r.json().then(data => ({ status: r.status, data }));
        })
        .then(({ status, data }) => {
            consecutiveErrs = 0;

            if (status === 404) {
                // Задача истекла по TTL Redis или её никогда не было
                finish(() => { if (options.onDone) options.onDone(null); });
                return;
            }
            if (status === 403 || status === 400) {
                // Чужая задача / мусорный task_id — просто снимаем
                finish();
                return;
            }
            if (data.error && !data.status) {
                finish(() => serverError(data.error));
                return;
            }
            if (data.status === 'done') {
                finish(() => { if (options.onDone) options.onDone(data.result); });
                return;
            }
            if (data.status === 'failed') {
                finish(() => {
                    serverError(data.error || 'Task failed');
                    if (options.onFail) options.onFail(data.error);
                });
                return;
            }
            setTimeout(tick, interval);
        })
        .catch(err => {
            consecutiveErrs++;
            if (consecutiveErrs >= 5) {
                finish(() => serverError(err.message || 'Task polling failed'));
                return;
            }
            setTimeout(tick, interval);
        });
    }

    tick();
}

/* ── Resume после reload ──────────────────────────────────── */

function resumePendingHeavyTasks() {
    // На странице логина / без сессии не запускаем polling — иначе
    // получим бесконечный редирект на catch.php.
    if (typeof HEAD_CONFIG === 'undefined' || !HEAD_CONFIG.torqueUser) {
        return;
    }

    const now = Date.now();
    const pending = heavyTasksGetPending();
    const fresh = [];

    for (const t of pending) {
        if (!t || !t.task_id) continue;
        if (now - (t.started_at || 0) > HEAVY_TASKS_TTL_MS) continue;   // TTL истёк

        fresh.push(t);

        const sameOrigin = t.origin === (window.location.pathname + window.location.search);

        pollHeavyTask(t.task_id, {
            taskType: t.type,
            onDone: () => {
                if (sameOrigin) location.reload();
            },
        });
    }

    heavyTasksSetPending(fresh);
}

/* ────────────────────────────────────────────────────────────
 * Server-side sync: обнаружение задач, запущенных в другой
 * вкладке/браузере того же пользователя.
 *
 * user_tasks.php возвращает активные task_id для $_SESSION['uid'].
 * Для тех, что у нас ещё нет в heavyTasksMeta, запускаем pollHeavyTask
 * без автоперезагрузки (origin у них чужой).
 * ──────────────────────────────────────────────────────────── */

let heavySyncInFlight = false;

function syncUserTasksFromServer() {
    if (typeof HEAD_CONFIG === 'undefined' || !HEAD_CONFIG.torqueUser) return;
    if (document.visibilityState !== 'visible') return;
    if (heavySyncInFlight) return;

    heavySyncInFlight = true;

    fetch('/tasks', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    })
    .then(r => {
        const ct = r.headers.get('content-type') || '';
        if (!ct.includes('application/json')) return null;
        return r.json();
    })
    .then(data => {
        heavySyncInFlight = false;
        if (!data || !Array.isArray(data.tasks)) return;

        for (const t of data.tasks) {
            if (!t || !t.task_id) continue;
            // Уже поллим — пропускаем
            if (heavyTasksMeta.has(t.task_id)) continue;

            // Запускаем без reload по завершении — задача «чужая» для этого браузера
            pollHeavyTask(t.task_id, {
                taskType: t.type,
                onDone:   () => { /* индикатор гаснет сам */ },
            });
        }
    })
    .catch(() => {
        heavySyncInFlight = false;
    });
}

/* ────────────────────────────────────────────────────────────
 * Heavy tasks: bootstrap (localStorage resume + server sync)
 *
 * Запускается только после готовности переводов, чтобы индикатор
 * сразу получил правильный язык.
 * ──────────────────────────────────────────────────────────── */

function heavyBootstrap() {
    if (typeof HEAD_CONFIG === 'undefined' || !HEAD_CONFIG.torqueUser) {
        return;   // login-страница и т.п.
    }

    // 1. Подхватить задачи, начатые в этом браузере до reload
    if (typeof resumePendingHeavyTasks === 'function') {
        resumePendingHeavyTasks();
    }

    // 2. Синхронизация со сервером — обнаруживает задачи из других браузеров
    if (typeof syncUserTasksFromServer === 'function') {
        syncUserTasksFromServer();
        setInterval(syncUserTasksFromServer, 5000);
    }

    // 3. Перерисовка индикатора при догрузке/смене языка
    document.addEventListener('l10n:loaded', function () {
        if (typeof heavyIndicatorUpdate === 'function') {
            heavyIndicatorUpdate();
        }
    });
}

$(document).ready(function () {
    // Ждём l10n_loaded
    if (typeof l10n_loaded !== 'undefined' && l10n_loaded) {
        heavyBootstrap();
    } else {
        const iv = setInterval(() => {
            if (typeof l10n_loaded !== 'undefined' && l10n_loaded) {
                clearInterval(iv);
                heavyBootstrap();
            }
        }, 200);
    }
});

let redDialog = {
    options: {
        zIndex: 10000,
        overlayBackground: 'rgba(0,0,0,.7)',
        titleColor: 'red',
        btnPosition: 'right',
        top: '50%',
        right: '50%',
        btnClassSuccess: 'btn btn-info btn-sm',
        btnClassSuccessText: 'Yes',
        btnClassFail: 'btn btn-info btn-sm',
        btnClassFailText: 'No',
        title: 'Confirmation',
        message: 'Confirmation',
        onResolve: () => {},
        onReject: () => {},
        rejectOnDismiss: true
    },
    confirmPromiseVal: null,
    activeElement: null,
    activeButton: null,

    disableScroll() {
        document.documentElement.style.overflow = 'hidden';
    },

    enableScroll() {
        document.documentElement.style.overflow = '';
    },

    make(customOptions = {}) {
        const menuToggle = document.querySelector('.menu-toggle');
        if (menuToggle) {
            menuToggle.checked = false;
        }
        const options = {...this.options, ...customOptions};
        this.doReset(options);

        // Disable scroll when dialog opens
        this.disableScroll();

        // Create dialog elements
        const dialogDiv = document.createElement('div');
        dialogDiv.id = 'redDialogWrap';
        dialogDiv.className = 'card dlg';
        dialogDiv.style = `
            position: absolute;
            width: 300px !important;
            padding: 1em !important;
            top: ${options.top};
            right: ${options.right};
            transform: translate(50%, -50%);
            background: white;
            border-radius: 5px;
            z-index: ${options.zIndex};
            transition: transform 0.45s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.3s ease;`;

        dialogDiv.innerHTML = `
            <div id="redDialog_title" style="min-height: 26px;border-bottom:1px dashed #777;color:${options.titleColor};cursor:pointer;">${options.title}</div>
            <p id="dialogText" style="text-align: left;padding: 16px 5px 0px 10px;width: 100%;margin: 0;font-size: 13px;max-width:280px">${options.message}</p>
        `;

        // Create buttons container
        const btnWrap = document.createElement('div');
        btnWrap.id = 'redDialogBtnWrap';
        btnWrap.style = `padding: 20px 0 0;text-align: ${options.btnPosition};`;

        // Create Yes button
        const yesBtn = document.createElement('button');
        yesBtn.id = 'redDialogBtnYes';
        yesBtn.style = 'min-width: 62px;';
        yesBtn.className = options.btnClassSuccess;
        yesBtn.setAttribute('autofocus', '');
        yesBtn.textContent = options.btnClassSuccessText;
        yesBtn.addEventListener('click', () => this.resolve());

        // Create No button
        const noBtn = document.createElement('button');
        noBtn.id = 'redDialogBtnNo';
        noBtn.style = 'min-width: 62px;';
        noBtn.className = options.btnClassFail;
        noBtn.textContent = options.btnClassFailText;
        noBtn.addEventListener('click', () => this.reject());

        // Add keyboard navigation
        noBtn.addEventListener('keydown', e => {
            if (e.key === 'ArrowRight') {
                this.activeButton = yesBtn;
                yesBtn.focus();
            }
        });

        yesBtn.addEventListener('keydown', e => {
            if (e.key === 'ArrowLeft') {
                this.activeButton = noBtn;
                noBtn.focus();
            }
        });

        // Assemble the dialog
        btnWrap.append(noBtn, ' ', yesBtn);
        dialogDiv.appendChild(btnWrap);

        const overlayDiv = document.createElement('div');
        overlayDiv.id = 'redDialogOverLay';
        overlayDiv.style = `position:fixed;top:0;left:0;width:100%;height:100%;z-index:${options.zIndex - 1};background:${options.overlayBackground};`;
        overlayDiv.appendChild(dialogDiv);
        document.body.appendChild(overlayDiv);

        const titleElement = document.getElementById("redDialog_title");
        titleElement.addEventListener("click", () => {
            this.dismiss();
        });

        setTimeout(() => {
            dialogDiv.style.opacity = "1";
            dialogDiv.style.transform = "translate(50%, -50%) scale(1.05)";
            setTimeout(() => {
                dialogDiv.style.transform = "translate(50%, -50%) scale(1)";
            }, 180);
        }, 20);

        // Save active element and focus on Reject button
        this.activeElement = document.activeElement;
        noBtn.focus();
        this.activeButton = noBtn;

        // Return promise
        return new Promise(resolve => {
            this.confirmPromiseInterval = setInterval(() => {
                if (this.confirmPromiseVal !== null) {
                    this.doReset(options);
                    resolve(this.confirmPromiseVal);
                }
            });
        });
    },

    resolve() {
        this.onResolve();
        this.confirmPromiseVal = true;
    },

    reject() {
        this.onReject();
        this.confirmPromiseVal = false;
    },

    dismiss() {
        // Closing a dialog need not execute its secondary button action.
        if (this.rejectOnDismiss) {
            this.reject();
        } else {
            this.confirmPromiseVal = false;
        }
    },

    doReset(options) {
        const overlay = document.querySelector('#redDialogOverLay');
        if (overlay) overlay.remove();

        // Enable scroll when dialog closes
        this.enableScroll();

        this.confirmPromiseVal = null;

        if (this.activeElement) {
            this.activeElement.focus();
            this.activeElement = null;
        }

        this.activeButton = null;
        this.onResolve = options.onResolve;
        this.onReject = options.onReject;
        this.rejectOnDismiss = options.rejectOnDismiss;

        clearInterval(this.confirmPromiseInterval);
    },

    onResolve() {},
    onReject() {}
};
