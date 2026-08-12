'use strict';

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

let chart_fill = localStorage.getItem(`${username}-chart_fill`) === "true";
let chart_fillGradient = localStorage.getItem(`${username}-chart_fillGradient`) === "true";
let chart_lineWidth = localStorage.getItem(`${username}-chart_lineWidth`) || 2;

// Выносим ctime в глобальную область видимости, чтобы использовать везде
function ctime(t) {
    let date = new Date(t);
    if (isNaN(date.getTime())) return '';
    return date.toLocaleTimeString(Cookies.get('timeformat') == '12' ? 'en-US' : 'ru-RU');
}

$(document).ready(function(){
  // Reset flot zoom
  const handleSliderInit = () => {
    if (!stream) {
        // Reset map indexes
        mapIndexStart = 0;
        mapIndexEnd = jsTimeMap.length - 1;
        // Сбрасываем сохранённое выделение
        cutStart = null;
        cutEnd = null;
        initSlider(jsTimeMap, jsTimeMap[0], jsTimeMap.at(-1));
    }
  };
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

  //new session notify
  function checkNewSession() {
    if (Cookies.get('newsess') !== undefined) {
        $('.new-session').css('display','block');
    }
  }
  setInterval(checkNewSession, 1000);

  document.addEventListener("visibilitychange", () => {
    if (document.visibilityState === "visible") {
        checkNewSession();
    }
  });

  PasswordToggle.initAll();
  ClearInput.initAll();
  document.querySelector('.storage-usage-img')?.addEventListener('click', () => xhrResponse(`${localization.key['stor.usage']} ${Cookies.get('storage_usage')}%`));

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

function processData(data, maxGap = Cookies.get('gap') !== undefined ? Cookies.get('gap') : 5000) {
    // Set for unique timestamps
    const timestampSet = new Set();
    data.forEach(series => series.data.forEach(point => timestampSet.add(point[0])));

    // Set->sort array
    const allTimestamps = Array.from(timestampSet).sort((a, b) => a - b);

    const newTimestamps = [allTimestamps[0]];
    let timeOffset = 0;
    let lastTimestamp = allTimestamps[0];

    const timeMapping = new Map();
    timeMapping.set(lastTimestamp, lastTimestamp);

    const gaps = [];

    for (let i = 1; i < allTimestamps.length; i++) {
        const currentTimestamp = allTimestamps[i];
        const gap = currentTimestamp - lastTimestamp;

        if (gap > maxGap) {
            timeOffset += gap - maxGap;
            gaps.push({
                start: newTimestamps[i-1],
                end: currentTimestamp - timeOffset,
                realStart: lastTimestamp,
                realEnd: currentTimestamp
            });
        }

        const newTimestamp = currentTimestamp - timeOffset;
        newTimestamps.push(newTimestamp);
        timeMapping.set(currentTimestamp, newTimestamp);
        lastTimestamp = currentTimestamp;
    }

    const newData = data.map(series => ({
        ...series,
        data: series.data.map(point => [
            timeMapping.get(point[0]),
            point[1]
        ])
    }));

    // timeMapping object, keep order
    const timeMappingObject = {};
    allTimestamps.forEach(t => {
        timeMappingObject[timeMapping.get(t)] = t;
    });

    return {
        processedData: newData,
        realStartTime: allTimestamps[0],
        realEndTime: allTimestamps[allTimestamps.length - 1],
        processedStartTime: newTimestamps[0],
        processedEndTime: newTimestamps[newTimestamps.length - 1],
        gaps: gaps,
        timeMapping: timeMappingObject
    };
}

function drawGapLines(plot, ctx) {
    let axes = plot.getAxes();
    let plotOffset = plot.getPlotOffset();

    ctx.save();
    ctx.translate(plotOffset.left, plotOffset.top);
    ctx.lineWidth = chart_lineWidth;
    ctx.strokeStyle = 'rgba(255, 0, 0, .2)';
    ctx.setLineDash([5, 3]);

    window.gapInfo.forEach(gap => {
        let x1 = axes.xaxis.p2c(gap.start);
        let x2 = axes.xaxis.p2c(gap.end);

        if (x1 >= 0 && x1 <= plot.width()) {
            ctx.beginPath();
            ctx.moveTo(x1, 0);
            ctx.lineTo(x1, plot.height());
            ctx.stroke();
        }

        if (x2 >= 0 && x2 <= plot.width()) {
            ctx.beginPath();
            ctx.moveTo(x2, 0);
            ctx.lineTo(x2, plot.height());
            ctx.stroke();
        }
    });

    ctx.restore();
}

function findNearestRealTime(processedTime) {
    if (!window.realTimeInfo || !window.realTimeInfo.timeMapping) {
        console.error("Time mapping is not available");
        return processedTime;
    }

    const timeMapping = window.realTimeInfo.timeMapping;

    if (!timeMapping._sortedProcessedTimes || !timeMapping._nearestTimesCache) {
        timeMapping._sortedProcessedTimes = Object.keys(timeMapping).map(Number).sort((a, b) => a - b);
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

    const nearestIndex = left;
    const nearestProcessedTime = processedTimes[nearestIndex];

    nearestTimesCache[processedTime] = timeMapping[nearestProcessedTime];
    return nearestTimesCache[processedTime];
}

function doPlot(position) {
    // Reset map indexes
    mapIndexStart = 0;
    mapIndexEnd = jsTimeMap.length - 1;

    //Remove plot presence
    if (plot) {
        $("#placeholder").unbind("plothover plottouchmove plotselected");
        plot.shutdown();
        $("#placeholder").empty();
    }

    //asigned the plot to a new variable and new function to update the plot in realtime when using the slider
    chartUpdRange = (a, b) => {
        window.chartRangeStart = a;
        window.chartRangeEnd = b;
        let dataSet = [];
        flotData.forEach(i => dataSet.push({label: i.label, data: i.data.slice(a, b)}));
        plot.setData(dataSet);
        plot.draw();
        heatData = dataSet;
    };
    plot = $.plot("#placeholder", flotData, {
        xaxes: [ {
            mode: "time",
            timezone: "browser",
            axisLabel: ' ',
            tickFormatter: function(val, axis) {
                if (!window.realTimeInfo || !window.realTimeInfo.timeMapping) return "";
                const processedTimes = Object.keys(window.realTimeInfo.timeMapping).map(Number);
                const nearestProcessedTime = processedTimes.reduce((prev, curr) => 
                    Math.abs(curr - val) < Math.abs(prev - val) ? curr : prev
                );
                const realTime = window.realTimeInfo.timeMapping[nearestProcessedTime];
                let date = new Date(realTime);
                return date.toLocaleTimeString(Cookies.get('timeformat') == '12' ? 'en-US' : 'ru-RU', {
                  hour: '2-digit',
                  minute: '2-digit',
                });
            }
        } ],
        yaxes: [ { axisLabel: "" }, {
            alignTicksWithAxis: position == "right" ? 1 : null,
            position: position,
            axisLabel: ""
        } ],
        legend: {
            position: "nw",
            hideable: true,
            backgroundOpacity: 0.1,
            margin: 2
        },
        selection: { mode: "x" },
        grid: {
            touchmove: true,
            mouseActiveRadius: 100,
            hoverable: true,
            clickable: false,
            borderWidth: 0
        },
        hooks: {
            drawOverlay: [drawGapLines]
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

    //Hover vertical marker
    let placeholder = $("#placeholder");
    let verticalLine = $('<div>').css({
        position: 'absolute',
        borderLeft: `${chart_lineWidth}px dashed rgba(0,0,0,.4)`,
        pointerEvents: 'none',
        display: 'none'
    }).appendTo(placeholder);

    let rafId = null;
    let lastX = null;

    placeholder.bind("plothover plottouchmove", function(event, pos, item) {
        if (rafId) {
            cancelAnimationFrame(rafId);
        }

        rafId = requestAnimationFrame(() => {
            if (item) {
                let offset = placeholder.offset();
                let plotOffset = plot.getPlotOffset();
                let xPos = item.pageX - offset.left;

                if (lastX !== xPos) {
                    lastX = xPos;
                    verticalLine.css({
                        left: xPos + 'px',
                        top: plotOffset.top + 'px',
                        height: (placeholder.height() - plotOffset.bottom - plotOffset.top) + 'px',
                        display: 'block'
                    });
                }
            } else {
                lastX = null;
                verticalLine.css('display', 'none');
            }
        });
    });

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
}

let updCharts = (last = false)=>{
    const plotDataSelected = plotDataChoices.getValue(true);
    const seshidtagValue = seshidtagChoices?.getValue(true) ?? sid;

    if (plotDataSelected.length === 0) {
        // Reset map indexes
        mapIndexStart = 0;
        mapIndexEnd = jsTimeMap.length - 1;

        const noChart = $('<div>',{class:'chart-label'}).append($('<span>',{class:'label label-warning'}).html(localization.key['novar'] ?? 'No Variables Selected to Plot'));
        if ($('#placeholder')[0]!=undefined) {//clean our plot if it exists
            flotData = [];
            heatData = [];
            plot.shutdown();
        }
        $('#Chart-Container').empty();
        $('#Chart-Container').append(noChart);
        $('#Summary-Container').empty();
    } else {
        $(".fetch-data").css("display", "block");
        let varPrm = null;
        if (sid && uid && sig) {
            varPrm = `plot.php?id=${sid}&uid=${uid}&sig=${sig}`;
        } else {
            varPrm = last ? `plot.php?last&id=${seshidtagValue}` : `plot.php?id=${seshidtagValue}`;
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
              timeMapping: processedResult.timeMapping
            };
            window.gapInfo = processedResult.gaps;

            // update jsTimeMap with real time markers – обязательно сортируем
            jsTimeMap = Object.values(processedResult.timeMapping).sort((a, b) => a - b);

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
                $('#Chart-Container').append($('<div>',{class:'demo-container'}).append($('<div>',{id:'placeholder',class:'demo-placeholder'})));
                doPlot("right");
            } else {
                // refresh chart data
                plot.setData(flotData);
                plot.setupGrid();
                plot.draw();
            }
            //always update the chart trimmed range when plotting new data
            const [a,b] = [jsTimeMap.length-$('#slider-range11').slider("values",1)-1,jsTimeMap.length-$('#slider-range11').slider("values",0)-1];
            chartUpdRange(a,b);
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
        }).catch(err => {
            const noChart = $('<div>',{class:'chart-label'}).append($('<span>',{class:'label label-warning'}).html(localization.key['nodata'] ?? 'No data'));
            $('#Chart-Container').empty();
            $('#Chart-Container').append(noChart);
            $('#Summary-Container').empty();
            $(".fetch-data").css("display", "none");
            console.error(err);
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

// ======================= Map initialization =======================
let map = null;
let polyline = null;
let headingArrowsLayer = null;          // layer group for heading direction arrows

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

        L.DomEvent.on(streamButton, 'mousedown', function(e) {
            L.DomEvent.preventDefault(e);
            L.DomEvent.stopPropagation(e);
            dataToggle();
            if (map._isFullscreen) {
                $('html, body').animate({ scrollTop: $(document).height() });
            }
        });
        zoomControl.appendChild(streamButton);
    };

    if (!uid && !sid && !sig) addControlsToZoomContainer(map);

    // ---------------------- Hotline layer group ----------------------
    let hotlineLayers = L.layerGroup().addTo(map); // will contain hotline for each segment
    let currentDataSource = null;

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

    // Full track on load
    window.currentMapSlicedCoords = window.MapData.flatCoords;
    window.currentMapSlicedIndices = window.MapData.flatIndices;

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
        headingArrowsLayer.clearLayers();
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
        const distances = [0];   // cumulative distance from the first point
        for (let i = 1; i < coords.length; i++) {
            const d = L.latLng(coords[i-1][0], coords[i-1][1])
                        .distanceTo(L.latLng(coords[i][0], coords[i][1]));
            totalDistance += d;
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
            const origIdx = indices[i];
            if (origIdx < 0) continue;                // streaming point without a valid index
            const hdg = origHeading[origIdx];
            if (hdg === undefined || hdg === null || hdg === 0) continue; // skip missing or zero headings

            if (firstArrow) {
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
    mapUpdRange = (origStartIdx, origEndIdx) => {
        const origToFlat = window.MapData.origToFlat;
        if (!origToFlat) return;

        // Ensure indices are in correct order (start ≤ end)
        let s = Math.min(origStartIdx, origEndIdx);
        let e = Math.max(origStartIdx, origEndIdx);

        // Find the nearest valid indices in the flat array
        let flatStart = origToFlat[s];
        let flatEnd = origToFlat[e];
        while (flatStart === undefined && s <= e) { s++; flatStart = origToFlat[s]; }
        while (flatEnd === undefined && e >= s) { e--; flatEnd = origToFlat[e]; }
        if (flatStart === undefined || flatEnd === undefined) return;

        const flat = window.MapData.flatCoords;
        const sliced = flat.slice(flatStart, flatEnd + 1).filter(([lat, lng]) => lat !== 0 || lng !== 0);
        if (!sliced.length) return;

        // Rebuild segments for display (without short segment filtering)
        const slicedSegmentsWithIndices = extractValidSegmentsWithIndices(sliced, {
            maxStep: 0.003,
            minPoints: 1,
            minDistance: 0
        });
        const slicedSegmentsCoords = slicedSegmentsWithIndices.map(seg => seg.map(p => p.coord));

        // Save the current slice of coordinates and indices for markerUpd and other needs
        if (!slicedSegmentsCoords.length) {
            // No visible segments after filtering – clear polyline and markers
            polyline.setLatLngs([]);
            startcir.setLatLng([0,0]);
            endcir.setLatLng([0,0]);
            map.removeLayer(markerPnt);
            headingArrowsLayer.clearLayers();
            return;
        }

        const slicedIndices = window.MapData.flatIndices
            .slice(flatStart, flatEnd + 1)
            .filter((_, i) => {
                const coord = flat[flatStart + i];
                return coord[0] !== 0 || coord[1] !== 0;
            });
        window.currentMapSlicedCoords = sliced;
        window.currentMapSlicedIndices = slicedIndices;

        // Update polyline and markers
        polyline.setLatLngs(slicedSegmentsCoords);
        startcir.setLatLng(sliced[sliced.length - 1]);
        endcir.setLatLng(sliced[0]);

        // If Hotline/Heatmap is active
        if (currentDataSource !== null) {
            updateHotline(currentDataSource, [flatStart, flatEnd], s, e);
        } else {
            if (!map.hasLayer(polyline)) polyline.addTo(map);
        }

        // Update heading arrows to match the new visible segment
        updateHeadingArrows();

        // Safe bounds fitting
        const bounds = polyline.getBounds();
        if (bounds.isValid()) {
            map.fitBounds(bounds, { maxZoom: 15 });
        } else if (sliced.length > 0) {
            map.setView(sliced[0], 13);
        }
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
            const realTimeValue = findNearestRealTime(point.time);
            const realTime = new Date(realTimeValue);
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

    function updateHotline(sourceIndex, rangeFlatIndices, origRangeStart = null, origRangeEnd = null) {
        // Remove all previous hotline layers
        hotlineLayers.clearLayers();

        // If no source selected or no data – return to regular polyline
        if (sourceIndex === null || sourceIndex === "" || !heatData || !heatData[sourceIndex]) {
            currentDataSource = null;
            if (!map.hasLayer(polyline)) polyline.addTo(map);
            if (hotlineLegend) {
                map.removeControl(hotlineLegend);
                hotlineLegend = null;
            }
            return;
        }

        // Determine which segments to use
        let targetSegmentsIndices = window.MapData.segmentsIndices; // all segments with indices
        if (rangeFlatIndices && rangeFlatIndices.length === 2) {
            // Restrict segments to flat index range
            const [flatStart, flatEnd] = rangeFlatIndices;
            const flatIndices = window.MapData.flatIndices;
            const flatCoords = window.MapData.flatCoords;
            const sliced = flatCoords.slice(flatStart, flatEnd + 1);
            if (sliced.length === 0) {
                // Empty range – reset to polyline
                currentDataSource = null;
                if (!map.hasLayer(polyline)) polyline.addTo(map);
                if (hotlineLegend) {
                    map.removeControl(hotlineLegend);
                    hotlineLegend = null;
                }
                return;
            }
            // Rebuild segments for the slice (without short segment filtering)
            const slicedWithIndices = extractValidSegmentsWithIndices(sliced, {
                maxStep: 0.003,
                minPoints: 1,
                minDistance: 0
            });
            // Replace local indices (0..sliced.length-1) with global original indices
            targetSegmentsIndices = slicedWithIndices.map(seg =>
                seg.map((p, localIdx) => {
                    const globalIdx = flatIndices[flatStart + localIdx];
                    return { coord: p.coord, index: globalIdx };
                })
            );
        }

        const sourceData = heatData[sourceIndex].data;
        if (!sourceData || sourceData.length === 0) {
            // No data to display – return to polyline
            currentDataSource = null;
            if (!map.hasLayer(polyline)) polyline.addTo(map);
            if (hotlineLegend) {
                map.removeControl(hotlineLegend);
                hotlineLegend = null;
            }
            return;
        }

        // Use origRangeStart if provided, otherwise current chart range
        const dataOffset = origRangeStart !== null ? origRangeStart : (window.chartRangeStart || 0);

        let globalMin = Infinity;
        let globalMax = -Infinity;
        let anyLayer = false;

        // Build hotline for each segment
        targetSegmentsIndices.forEach(segWithIdx => {
            const points = [];
            segWithIdx.forEach(({ coord, index }) => {
                // Skip points without index (e.g., added by streaming)
                if (index < 0) return;

                // Calculate position in the trimmed sourceData
                const dataIdx = index - dataOffset;
                if (dataIdx < 0 || dataIdx >= sourceData.length) return;

                let value = null;
                let timestamp = null;
                const raw = sourceData[dataIdx];
                if (Array.isArray(raw) && raw.length >= 2) {
                    timestamp = raw[0];
                    value = raw[1];
                } else {
                    value = raw;
                    timestamp = (window.timeData && window.timeData[index]) || null;
                }
                if (value === null || value === undefined || isNaN(value)) return;

                const latLng = L.latLng(coord[0], coord[1], value);
                latLng.alt = value;
                if (timestamp) latLng.time = timestamp;
                points.push(latLng);

                if (value < globalMin) globalMin = value;
                if (value > globalMax) globalMax = value;
            });

            if (points.length === 0) return;

            // Just in case all values are the same
            if (globalMin === globalMax) {
                globalMin -= 0.1;
                globalMax += 0.1;
            }

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

            // Event handlers (similar to the original code)
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
        });

        if (anyLayer) {
            currentDataSource = sourceIndex;
            if (map.hasLayer(polyline)) map.removeLayer(polyline);
            updateLegend(globalMin, globalMax);
        } else {
            // If no layers were created – return to polyline
            currentDataSource = null;
            if (!map.hasLayer(polyline)) polyline.addTo(map);
            if (hotlineLegend) {
                map.removeControl(hotlineLegend);
                hotlineLegend = null;
            }
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
    const rate = Number(Cookies.get('tracking-rate')) || 1000;
    setInterval(() => {
        let marker = null;
        let lat = stream ? parseFloat($('#lat').html()) : null;
        let lon = stream ? parseFloat($('#lon').html()) : null;
        let spd = stream ? ($('#spd').length != 0 ? $('#spd').html() : localization.key['nospd']) : null;
        let spd_unit = stream ? ($('#spd-unit').length != 0 ? $('#spd-unit').html() : "") : null;
        if (lat == null || lon == null || isNaN(lat) || isNaN(lon) || (lat == 0 && lon == 0)) return;

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

                let lastSeg = segs.length > 0 ? segs[segs.length - 1] : null;
                let lastSegIdx = segsIdx.length > 0 ? segsIdx[segsIdx.length - 1] : null;

                let distToLast = Infinity;
                if (lastSeg && lastSeg.length > 0) {
                    const lastPoint = lastSeg[lastSeg.length - 1];
                    distToLast = Math.sqrt((newPoint[0] - lastPoint[0]) ** 2 + (newPoint[1] - lastPoint[1]) ** 2);
                }

                if (window.MapData.nextIndex === undefined) {
                    const flatIndices = window.MapData.flatIndices || [];
                    window.MapData.nextIndex = flatIndices.length > 0 ? Math.max(...flatIndices) + 1 : 0;
                }
                const newIndex = window.MapData.nextIndex++;

                if (lastSeg && distToLast <= 0.0005) {
                    lastSeg.push(newPoint);
                    lastSegIdx.push({ coord: newPoint, index: newIndex });
                } else {
                    segs.push([newPoint]);
                    segsIdx.push([{ coord: newPoint, index: newIndex }]);
                    lastSeg = segs[segs.length - 1];
                    lastSegIdx = segsIdx[segsIdx.length - 1];
                }

                window.MapData.flatCoords = segs.flat();
                window.MapData.flatIndices = segsIdx.flat().map(p => p.index);

                polyline.setLatLngs(segs);
                polyline.redraw();

                endcir.setLatLng(newPoint);

                if (mapIndexStart !== null && mapIndexEnd !== null) {
                    window.currentMapSlicedCoords.push(newPoint);
                    window.currentMapSlicedIndices.push(newIndex);
                } else {
                    window.currentMapSlicedCoords = window.MapData.flatCoords.slice();
                    window.currentMapSlicedIndices = window.MapData.flatIndices.slice();
                }

                updateHeadingArrows();

                if (currentDataSource !== null && heatData && heatData[currentDataSource]) {
                    const sourceData = heatData[currentDataSource].data;
                    let value = null;
                    if (sourceData.length > 0) {
                        const last = sourceData[sourceData.length - 1];
                        value = Array.isArray(last) ? last[1] : last;
                    } else {
                        value = 0;
                    }
                    const now = Date.now();
                    sourceData.push([now, value]);

                    updateHotline(currentDataSource);
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
        // Remove previous tooltips
        map.eachLayer(layer => {
            if (layer instanceof L.Tooltip && layer.options.className === 'heat-data-tooltip') {
                map.removeLayer(layer);
            }
        });

        if (itm && itm.dataIndex >= 0) {
            // itm.dataIndex – index in the current trimmed chart window.
            // Convert it to the global original index.
            const origIdx = (window.chartRangeStart || 0) + itm.dataIndex;

            // Look for the point in what is currently drawn on the map
            const posInSliced = window.currentMapSlicedIndices?.indexOf(origIdx);
            if (posInSliced === undefined || posInSliced === -1) {
                // Point is not within the map's visible range – hide marker
                map.removeLayer(markerPnt);
                return;
            }

            const pos = window.currentMapSlicedCoords[posInSliced];
            markerPnt.setLatLng(pos).addTo(map);

            // Show HeatData if a source is selected
            if (currentDataSource !== null && heatData && heatData[currentDataSource]) {
                const sourceData = heatData[currentDataSource].data;
                // Index in the trimmed heatData window = origIdx - chartRangeStart
                const dataIdx = origIdx - (window.chartRangeStart || 0);
                if (dataIdx >= 0 && dataIdx < sourceData.length) {
                    const dataPoint = sourceData[dataIdx];
                    if (dataPoint) {
                        const value = Array.isArray(dataPoint) ? dataPoint[1] : dataPoint;
                        const label = heatData[currentDataSource].label;
                        L.tooltip({
                            permanent: false,
                            direction: 'top',
                            className: 'heat-data-tooltip'
                        })
                        .setLatLng(pos)
                        .setContent(`${label}: ${value}`)
                        .addTo(map);
                    }
                }
            }
        } else {
            map.removeLayer(markerPnt);
        }
    };
};
//End of Leaflet Map Providers js code

//slider js code
let [cutStart, cutEnd] = [null, null];
let initSlider = (jsTimeMap,start,end)=>{
    $("#slider-range11").off();
    if ($("#slider-range11").hasClass("ui-slider")) {
        $("#slider-range11").slider("destroy");
        initSlider(jsTimeMap,start,end);
    }

    const [sessStart, sessEnd] = [jsTimeMap[0], jsTimeMap.at(-1)]

    let TimeStartv = timelookup(start);
    let TimeEndv = timelookup(end);

    function timelookup(t) { //retrun array index, used for slider steps/value, RIP IE, no polyfill 
        let fx = (e) => e == t;
        let out = jsTimeMap.findIndex(fx);
        return out;
    }

    let sv = $(function() {//jquery range slider
        $( "#slider-range11" ).slider({
            range: true,
            min: 0 ,
            max:  jsTimeMap.length -1,
            values: [ TimeStartv, TimeEndv ],
            slide: function( event, ui ) {
                $( "#slider-time" ).val( ctime(jsTimeMap[ui.values[ 0 ]]) + " - " + ctime(jsTimeMap[ui.values[ 1 ]]));
        }});
        $( "#slider-time" ).val( ctime(jsTimeMap[$( "#slider-range11" ).slider( "values", 0 )]) +  " - " + ctime(jsTimeMap[$( "#slider-range11" ).slider( "values", 1 )])); 
        //merged the 2 listeners in 1 and added functions to visually trim map data and plot in realtime when using the trim session slider
        $( "#slider-range11" ).on( "slidechange", (event,ui)=>{
            $('#slider-time').attr("sv0", jsTimeMap[$('#slider-range11').slider("values", 0)])
            $('#slider-time').attr("sv1", jsTimeMap[$('#slider-range11').slider("values", 1)])
            const [a,b] = [jsTimeMap.length-$('#slider-range11').slider("values",1)-1,jsTimeMap.length-$('#slider-range11').slider("values",0)-1];
            if (Math.abs(a-b)<3) return;

            [cutStart, cutEnd] = [jsTimeMap[$('#slider-range11').slider("values",0)], jsTimeMap[$('#slider-range11').slider("values",1)]];
            if (cutStart === sessStart && cutEnd === sessEnd) {
                [cutStart, cutEnd] = [null, null];
            }

            if ($("#map").length) {
                if (Cookies.get('plot') === undefined) updateMapWithRangePreservingHeatline(a, b);
            }
            if ($(".demo-container").length) chartUpdRange(a,b);
        });
    });
}
//End slider js code

function updateMapWithRangePreservingHeatline(startIndex = null, endIndex = null) {
    // Если индексы не заданы – ничего не делаем
    if (startIndex === null || endIndex === null) {
        return;
    }

    const dataSourceSelect = document.getElementById('heat-dataSourceSelect');

    if (dataSourceSelect) {
        const prevValue = dataSourceSelect.value;

        if (prevValue !== "") {
            dataSourceSelect.value = "";

            const changeEvent = new Event('change');
            dataSourceSelect.dispatchEvent(changeEvent);

            mapUpdRange(startIndex, endIndex);

            setTimeout(() => {
                dataSourceSelect.value = prevValue;
                dataSourceSelect.dispatchEvent(changeEvent);
            }, 300);
        } else {
            mapUpdRange(startIndex, endIndex);
        }
    } else {
        mapUpdRange(startIndex, endIndex);
    }
}

if ('serviceWorker' in navigator) {
  navigator.serviceWorker
    .register('static/js/sw.js')
    .then(() => { console.log('Service Worker Registered'); });
}

function toggle_dark() {
document.querySelector('html').style.transition = ".2s"
 switch (localStorage.getItem(`${username}-theme`)) {
  case "default":
   localStorage.setItem(`${username}-theme`, "dark");
   let head = document.getElementsByTagName('head')[0];
   let link = document.createElement('link');
   link.rel = 'stylesheet';
   link.href = darkCssUrl;
   head.appendChild(link);
  break;
  case "dark":
   localStorage.setItem(`${username}-theme`, "default");
   let lNode =  document.querySelector(`link[href*="${darkCssUrl}"]`);
   lNode.parentNode.removeChild(lNode);
  break;
 }
}

function logout() {
 location.href='.?logout=true';
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
            redDialog.doReset(redDialog.options);
        }

        const menuToggle = document.querySelector('.menu-toggle');
        if (menuToggle) {
            menuToggle.checked = false;
        }
    } else if (event.key === 'Delete' && typeof delSession === 'function') {
        delSession();
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
    $(".resizer").css("display","none");
    return;
  }

  const container = document.querySelector('.split-container');
  const resizer = document.querySelector('.resizer');
  const leftPane = document.querySelector('.left');
  const rightPane = document.querySelector('.right');
  const STORAGE_KEY = `${username}-splitter_left_width`;

  let isResizing = false;

  function isHorizontal() {
    return getComputedStyle(container).flexDirection === 'row';
  }

  let wasHorizontal = isHorizontal();

  function handleResize() {
    const isNowHorizontal = isHorizontal();
    if (isNowHorizontal !== wasHorizontal) {
        restoreSplitterPosition()
        mapResize();
        wasHorizontal = isNowHorizontal;
    }
  }

  const resizeObserver = new ResizeObserver(() => {
    handleResize();
  });

  resizeObserver.observe(container);

  function startResize() {
    if (!isHorizontal()) return;
    isResizing = true;
    document.body.style.cursor = 'col-resize';
  }

  function stopResize() {
    if (isResizing) {
      saveSplitterPosition();
    }
    isResizing = false;
    document.body.style.cursor = 'default';
  }

  function resize(x) {
    if (!isResizing || !isHorizontal()) return;

    const containerRect = container.getBoundingClientRect();
    const containerWidth = containerRect.width;
    const pointerRelativeX = x - containerRect.left;

    const leftMin = 300;
    const rightMin = 300;
    const maxLeft = containerWidth - rightMin;
    const minLeft = leftMin;

    if (pointerRelativeX <= minLeft) {
            leftPane.style.width = `${(minLeft / containerWidth) * 100}%`;
            rightPane.style.width = `${(rightMin / containerWidth) * 100}%`;
        } else if (pointerRelativeX >= maxLeft) {
            leftPane.style.width = `${(maxLeft / containerWidth) * 100}%`;
            rightPane.style.width = `${(rightMin / containerWidth) * 100}%`;
        } else {
            const leftWidthPercent = (pointerRelativeX / containerWidth) * 100;
            const rightWidthPercent = 100 - leftWidthPercent;
            leftPane.style.width = `${leftWidthPercent}%`;
            rightPane.style.width = `${rightWidthPercent}%`;
        }

    mapResize();
  }

  function saveSplitterPosition() {
    if (!isHorizontal()) return;
    const width = leftPane.getBoundingClientRect().width;
    const containerWidth = container.offsetWidth;
    const percent = (width / containerWidth) * 100;
    localStorage.setItem(STORAGE_KEY, percent.toFixed(2));
  }

  function restoreSplitterPosition() {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved) {
        const percent = parseFloat(saved);
        requestAnimationFrame(() => {
            if (isHorizontal() && percent > 10 && percent < 90) {
                leftPane.style.width = `${percent}%`;
                rightPane.style.width = `${100 - percent}%`;
            }
            mapResize();
        });
    }
  }

  function resetSplitterPosition() {
    localStorage.removeItem(STORAGE_KEY);
    leftPane.style.width = '50%';
    rightPane.style.width = '50%';
    mapResize();
  }

  // Mouse
  resizer.addEventListener('mousedown', e => {
    e.preventDefault();
    startResize();
  });

  document.addEventListener('mousemove', e => resize(e.clientX));
  document.addEventListener('mouseup', stopResize);

  // Touch
  resizer.addEventListener('touchstart', e => startResize());
  document.addEventListener('touchmove', e => {
    if (e.touches.length > 0) {
      resize(e.touches[0].clientX);
    }
  });
  document.addEventListener('touchend', stopResize);

  // Double click to reset
  resizer.addEventListener('dblclick', () => {
    resetSplitterPosition();
  });

  // Long tap to reset
  longTap('.resizer', resetSplitterPosition);

  // Restore on load
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

    const fillKey = `${username}-chart_fill`;
    const gradientKey = `${username}-chart_fillGradient`;
    const widthKey = `${username}-chart_lineWidth`;

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
    location.href = "./fav_sessions.php";
}

function delSessions() {
    location.href = "./del_sessions.php";
}

function pidEdit() {
    location.href = "./pid_edit.php";
}

function usersSettings() {
    location.href = "./users_settings.php";
}

function remoteRa() {
    location.href = "./users_remote.php";
}

function showToken() {
    $("#wait_layout").show();

    fetch("users_handler.php?get_token")
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
                message: token,
                onResolve: function() {
                    copyToClipboard(token);
                },
                onReject: function() {
                    $("#wait_layout").show();
                    fetch("users_handler.php?renew_token")
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
        onReject: () => {}
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
        yesBtn.addEventListener('keydown', e => {
            if (e.key === 'ArrowRight') {
                this.activeButton = noBtn;
                noBtn.focus();
            }
        });

        noBtn.addEventListener('keydown', e => {
            if (e.key === 'ArrowLeft') {
                this.activeButton = yesBtn;
                yesBtn.focus();
            }
        });

        // Assemble the dialog
        btnWrap.append(yesBtn, ' ', noBtn);
        dialogDiv.appendChild(btnWrap);

        const overlayDiv = document.createElement('div');
        overlayDiv.id = 'redDialogOverLay';
        overlayDiv.style = `position:fixed;top:0;left:0;width:100%;height:100%;z-index:${options.zIndex - 1};background:${options.overlayBackground};`;
        overlayDiv.appendChild(dialogDiv);
        document.body.appendChild(overlayDiv);

        const titleElement = document.getElementById("redDialog_title");
        titleElement.addEventListener("click", () => {
            this.doReset(options);
        });

        setTimeout(() => {
            dialogDiv.style.opacity = "1";
            dialogDiv.style.transform = "translate(50%, -50%) scale(1.05)";
            setTimeout(() => {
                dialogDiv.style.transform = "translate(50%, -50%) scale(1)";
            }, 180);
        }, 20);

        // Save active element and focus on Yes button
        this.activeElement = document.activeElement;
        yesBtn.focus();
        this.activeButton = yesBtn;

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

        clearInterval(this.confirmPromiseInterval);
    },

    onResolve() {},
    onReject() {}
};