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

$(document).ready(function(){
  // Reset flot zoom
  const handleSliderInit = () => {
    if (!stream) {
        // Reset map indexes
        mapIndexStart = 0;
        mapIndexEnd = jsTimeMap.length - 1;
        initSlider(jsTimeMap, jsTimeMap[0], jsTimeMap.at(-1));
    }
  };
  $("#Chart-Container").on("dblclick", handleSliderInit);
  longTap("#Chart-Container", handleSliderInit);
  nogps = document.querySelector('#nogps');

  setInterval(()=>{
    if ($.cookie('plot') !== undefined) {
        $('.live').css('display','block');
    } else {
        $('.live').css('display','none');
    }
  }, 5000);

  //new session notify
  function checkNewSession() {
    if ($.cookie('newsess') !== undefined) {
        $('.new-session').css('display','block');
    }
  }
  setInterval(checkNewSession, 1000);

  document.addEventListener("visibilitychange", () => {
    if (document.visibilityState === "visible") {
        checkNewSession();
    }
  });
});

let lastPlotUpdateTime = 0;
let animationPlotFrameId = null;
let nogps = null;

//Fetch plot data every 10 sec
function schedulePlotUpdate(timestamp) {
  if (timestamp - lastPlotUpdateTime >= 10000) {
    if ($.cookie('plot') !== undefined) updatePlot();
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
    initSlider(jsTimeMap,jsTimeMap[0],jsTimeMap.at(-1));

    if (callback && typeof callback === 'function') {
        setTimeout(callback);
    }
}

//start of chart plotting js code
let plot = null; //definition of plot variable in script but outside doPlot function to be able to reuse as a controller when updating base data
let flotData = [];
let heatData = [];
let chartUpdRange = null;
let mapUpdRange = null;

function processData(data, maxGap = $.cookie('gap') !== undefined ? $.cookie('gap') : 5000) {
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
    ctx.lineWidth = 1;
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
    chartUpdRange = (a,b) => {
        let dataSet = [];
        flotData.forEach(i=>dataSet.push({label:i.label,data:i.data.slice(a,b)}));
        plot.setData(dataSet);
        plot.draw();
        heatData = dataSet;
    }
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
                return date.toLocaleTimeString($.cookie('timeformat') == '12' ? 'en-US' : 'ru-RU', {
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
        borderLeft: '1px dotted rgba(0,0,0,0.5)',
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
        $("#slider-time").val((new Date(jsTimeMap[a])).toLocaleTimeString($.cookie('timeformat') == '12' ? 'en-US' : 'ru-RU') + " - " + (new Date(jsTimeMap[b])).toLocaleTimeString($.cookie('timeformat') == '12' ? 'en-US' : 'ru-RU'));

        mapIndexStart = jsTimeMap.length - b - 1;
        mapIndexEnd = jsTimeMap.length - a - 1;

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

        const noChart = $('<div>',{align:'center',style:'display:flex; justify-content:center;'}).append($('<h5>').append($('<span>',{class:'label label-warning'}).html(localization.key['novar'] ?? 'No Variables Selected to Plot')));
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

            // update jsTimeMap with real time markers
            jsTimeMap = Object.values(processedResult.timeMapping);

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
            const noChart = $('<div>',{align:'center',style:'display:flex; justify-content:center;'}).append($('<h5>').append($('<span>',{class:'label label-warning'}).html(localization.key['nodata'] ?? 'No data')));
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
let map = null;
let polyline = null;
let initMapLeaflet = () => {
    let osm = new L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap'
    });

    let esri = new L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        className: 'esri-dark',
        maxZoom: 19,
        attribution: '© Esri'});

    let path = window.MapData.path;
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

    let baseMaps = {
        [localization.key['layer.map'] ?? 'Map']: osm,
        [localization.key['layer.sat'] ?? 'Satellite']: esri
    };

    let layerControl = L.control.layers(baseMaps).addTo(map);

    // Live stream control
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
        streamBtn_svg = svg;

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

    let hotlineLayer = null;
    let currentDataSource = null;

    function createDataSourceSelector() {
        let control = L.control({position: 'bottomleft'});

        control.onAdd = function(map) {
            let div = L.DomUtil.create('div', 'data-source-selector');

            // Создаем базовую структуру селектора
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
        if (!select) {
            setTimeout(updateDataSourceSelector, 500);
            return;
        }

        let currentValue = select.value;

        while (select.options.length > 1) {
            select.remove(1);
        }

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

            if (updateTimeout) {
                clearTimeout(updateTimeout);
            }

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
            updateMapWithRangePreservingHeatline(mapIndexStart, mapIndexEnd);
            lastFlotDataLength = heatData.length;
            updateDataSourceSelector();
        }
    }, 1000);

    function prepareHotlineData(sourceIndex, rangeIndices) {
        if (!heatData || !heatData[sourceIndex] || !heatData[sourceIndex].data) {
            return null;
        }

        const sourceData = heatData[sourceIndex].data;

        if (!sourceData || sourceData.length === 0) {
            return null;
        }

        const coordinates = window.routeCoordinates || polyline.getLatLngs();

        if (!coordinates || coordinates.length === 0) {
            return null;
        }

        let dataToUse = sourceData;
        let coordsToUse = coordinates;

        if (rangeIndices && rangeIndices.length === 2) {
            const [startIdx, endIdx] = rangeIndices;
            dataToUse = sourceData.slice(startIdx, endIdx + 1);
            coordsToUse = coordinates.slice(startIdx, endIdx + 1);
        }

        const minLength = Math.min(dataToUse.length, coordsToUse.length);
        if (dataToUse.length !== coordsToUse.length) {
            dataToUse = dataToUse.slice(0, minLength);
            coordsToUse = coordsToUse.slice(0, minLength);
        }

        const points = [];
        let min = Infinity;
        let max = -Infinity;

        for (let i = 0; i < dataToUse.length; i++) {
            let value, timestamp;

            if (Array.isArray(dataToUse[i]) && dataToUse[i].length >= 2) {
                timestamp = dataToUse[i][0];
                value = dataToUse[i][1];
            } else {
                value = dataToUse[i];
                timestamp = (window.timeData && window.timeData[i]) || null;
            }

            if (value === null || value === undefined || isNaN(value)) {
                continue;
            }

            let lat, lng;

            if (coordsToUse[i] instanceof L.LatLng) {
                lat = coordsToUse[i].lat;
                lng = coordsToUse[i].lng;
            } else if (Array.isArray(coordsToUse[i])) {
                lat = coordsToUse[i][0];
                lng = coordsToUse[i][1];
            } else {
                continue;
            }

            const latLng = L.latLng(lat, lng, value);

            latLng.alt = value;
            if (timestamp) {
                latLng.time = timestamp;
            }

            points.push(latLng);

            min = Math.min(min, value);
            max = Math.max(max, value);
        }

        if (points.length === 0) {
            return null;
        }

        if (min === max) {
            min = min - 0.1;
            max = max + 0.1;
        }

        return {
            points,
            min,
            max
        };
    }

    function findClosestPoint(latlng, points) {
        if (!points || points.length === 0) return null;

        let minDist = Infinity;
        let closestPoint = null;

        for (let i = 0; i < points.length; i++) {
            const dist = latlng.distanceTo(points[i]);
            if (dist < minDist) {
                minDist = dist;
                closestPoint = points[i];
            }
        }

        return minDist < 100 ? closestPoint : null;
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

            const use12HourFormat = $.cookie('timeformat') === '12';

            if (use12HourFormat) {
                timeDisplay = realTime.toLocaleTimeString('en-US', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true
                });
            } else {
                timeDisplay = realTime.toLocaleTimeString('en-US', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: false
                });
            }
        }

        const tooltipContent = timeDisplay ? 
            `${timeDisplay}<br>${heatData[sourceIndex].label}: ${point.alt}` : 
            `${heatData[sourceIndex].label}: ${point.alt}`;

        L.tooltip({
            permanent: false,
            direction: 'top',
            className: 'heat-data-tooltip'
        })
        .setLatLng(point)
        .setContent(tooltipContent)
        .addTo(map);

        if ('ontouchstart' in window) {
            if (tooltipHideTimer) {
                clearTimeout(tooltipHideTimer);
                tooltipHideTimer = null;
            }

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

    function updateHotline(sourceIndex, rangeIndices) {

        if (hotlineLayer) {
            map.removeLayer(hotlineLayer);
            hotlineLayer = null;
        }

        if (sourceIndex === null || sourceIndex === "") {
            currentDataSource = null;
            if (!map.hasLayer(polyline)) {
                polyline.addTo(map);
            }

            if (hotlineLegend) {
                map.removeControl(hotlineLegend);
                hotlineLegend = null;
            }
            return;
        }

        let hotlineData = prepareHotlineData(sourceIndex, rangeIndices);
        if (!hotlineData) {
            if (!map.hasLayer(polyline)) {
                polyline.addTo(map);
            }

            if (hotlineLegend) {
                map.removeControl(hotlineLegend);
                hotlineLegend = null;
            }
            return;
        }

        if (map.hasLayer(polyline)) {
            map.removeLayer(polyline);
        }

        currentDataSource = sourceIndex;

        try {
            hotlineLayer = L.hotline(hotlineData.points, {
                min: hotlineData.min,
                max: hotlineData.max,
                palette: {
                    0.0: 'green',
                    0.5: 'yellow',
                    1.0: 'red'
                },
                weight: 3,
                outlineColor: '#444',
                outlineWidth: 1
            }).addTo(map);

            hotlineLayer.hotlineData = hotlineData;
            hotlineLayer.sourceIndex = sourceIndex;

            hotlineLayer.on('mousemove', function(e) {
                const closestPoint = findClosestPoint(e.latlng, this.hotlineData.points);
                if (closestPoint) {
                    showTooltipAtPoint(closestPoint, this.sourceIndex);
                }
            });

            hotlineLayer.on('mouseout', function() {
                if (!('ontouchstart' in window)) {
                    map.eachLayer(layer => {
                        if (layer instanceof L.Tooltip && layer.options.className === 'heat-data-tooltip') {
                            map.removeLayer(layer);
                        }
                    });
                }
            });

            hotlineLayer.on('click', function(e) {
                const closestPoint = findClosestPoint(e.latlng, this.hotlineData.points);
                if (closestPoint) {
                    showTooltipAtPoint(closestPoint, this.sourceIndex);
                }
            });

            updateLegend(hotlineData.min, hotlineData.max);
        } catch (error) {
            console.error('Error in updateHotline:', error);

            if (!map.hasLayer(polyline)) {
                polyline.addTo(map);
            }

            if (hotlineLegend) {
                map.removeControl(hotlineLegend);
                hotlineLegend = null;
            }
        }
    }

    let hotlineLegend = null;

    function updateLegend(min, max) {
        if (hotlineLegend) {
            map.removeControl(hotlineLegend);
        }

        hotlineLegend = L.control.hotlineLegend({
            min: Number.isInteger(min) ? min : min.toFixed(2),
            mid: Number.isInteger(min + max) ? Math.round((min+max)/2) : ((min+max)/2).toFixed(2),
            max: Number.isInteger(max) ? max : max.toFixed(2),
            palette: {0: 'green', 0.5: 'yellow', 1: 'red'},
            position: 'bottomright'
        }).addTo(map);
    }

    L.Control.HotlineLegend = L.Control.extend({
        options: {
            position: 'bottomright',
            min: 0,
            mid: .5,
            max: 1,
            palette: {0: 'green', 0.5: 'yellow', 1: 'red'},
            width: 15,
            height: 80
        },

        initialize: function(options) {
            L.Util.setOptions(this, options);
        },

        onAdd: function(map) {
            this._container = L.DomUtil.create('div', 'hotline-legend-container');
            this._container.style.display = 'flex';
            this._container.style.flexDirection = 'row';

            var labelsContainer = L.DomUtil.create('div', 'hotline-legend-labels', this._container);
            labelsContainer.style.display = 'flex';
            labelsContainer.style.flexDirection = 'column';
            labelsContainer.style.justifyContent = 'space-between';
            labelsContainer.style.marginRight = '2px';
            labelsContainer.style.fontSize = '10px';
            labelsContainer.style.fontWeight = 'bold';

            function createLabel(container, value) {
                var label = L.DomUtil.create('div', 'hotline-legend-label', container);
                label.style.border = '2px solid rgba(0, 0, 0, 0.2)';
                label.style.borderRadius = '4px';
                label.style.padding = '2px';
                label.style.background = '#fff';
                label.style.backgroundClip = 'padding-box';
                label.style.textAlign = 'center';
                label.innerHTML = value;
                return label;
            }

            var maxLabel = createLabel(labelsContainer, this.options.max);
            var midLabel = createLabel(labelsContainer, this.options.mid);
            var minLabel = createLabel(labelsContainer, this.options.min);

            var canvas = L.DomUtil.create('canvas', 'hotline-legend-canvas', this._container);
            canvas.width = this.options.width;
            canvas.height = this.options.height;
            canvas.style.display = 'block';
            canvas.style.borderRadius = '4px';

            var ctx = canvas.getContext('2d');
            var gradient = ctx.createLinearGradient(0, canvas.height, 0, 0);

            for (var stop in this.options.palette) {
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

    //Dynamic tracking marker when stream is open
    const rate = Number($.cookie('tracking-rate')) || 1000;
    setInterval(()=>{
        let marker = null;
        let lat = stream ? parseFloat($('#lat').html()) : null;
        let lon = stream ? parseFloat($('#lon').html()) : null;
        let spd = stream ? ($('#spd').length != 0 ? $('#spd').html() : localization.key['nospd']) : null;
        let spd_unit = stream ? ($('#spd-unit').length != 0 ? $('#spd-unit').html() : "") : null;
        if (lat == null || lon == null || isNaN(lat) || isNaN(lon) || (lat == 0 && lon == 0)) return;
        if (stream) {
            marker = new L.marker([lat, lon]).bindTooltip(
                `${spd}${spd === localization.key['nospd'] ? '' : ' ' + spd_unit}`,
                {permanent:true, direction:'right', className:"stream-marker"}
            ).addTo(map);
            map.setView(marker.getLatLng(), map.getZoom());

            //update travel line/end point
            if ($.cookie('plot') !== undefined) {
                path.unshift([lat,lon]);
                endcir.setLatLng(path.at(0));

                if (currentDataSource !== null) {
                    if (heatData && heatData[currentDataSource]) {
                        updCharts(true);
                        if (hotlineLayer) {
                            let currentLatLngs = hotlineLayer.getLatLngs();
                            let latestDataPoint = heatData[currentDataSource].data.at(-1);

                            if (latestDataPoint) {
                                const currentTime = Date.now();

                                let newPoint = L.latLng(lat, lon, latestDataPoint[1]);
                                newPoint.alt = latestDataPoint[1];
                                newPoint.time = currentTime;

                                heatData[currentDataSource].data.unshift([currentTime, latestDataPoint[1]]);

                                if (Array.isArray(currentLatLngs[0])) {
                                    currentLatLngs[0].unshift(newPoint);
                                } else {
                                    currentLatLngs.unshift(newPoint);
                                }

                                hotlineLayer.setLatLngs(currentLatLngs);

                                // update hotlineData.points for tooltip
                                if (hotlineLayer.hotlineData && Array.isArray(hotlineLayer.hotlineData.points)) {
                                    hotlineLayer.hotlineData.points.unshift(newPoint);

                                    const newValue = latestDataPoint[1];
                                    const newMin = Math.min(hotlineLayer.hotlineData.min, newValue);
                                    const newMax = Math.max(hotlineLayer.hotlineData.max, newValue);

                                    hotlineLayer.options.min = newMin;
                                    hotlineLayer.options.max = newMax;
                                    hotlineLayer.redraw();

                                    if (hotlineLegend) {
                                        const labels = hotlineLegend._container.querySelectorAll('.hotline-legend-label');
                                        if (labels.length >= 3) {
                                            labels[0].innerHTML = Number.isInteger(newMax) ? newMax : newMax.toFixed(2);
                                            labels[1].innerHTML = Number.isInteger((newMin+newMax)/2) ? 
                                                Math.round((newMin+newMax)/2) : ((newMin+newMax)/2).toFixed(2);
                                            labels[2].innerHTML = Number.isInteger(newMin) ? newMin : newMin.toFixed(2);
                                        }
                                    }

                                    hotlineLayer.hotlineData.min = newMin;
                                    hotlineLayer.hotlineData.max = newMax;
                                }
                            } else {
                                updateHotline(currentDataSource);
                            }
                        } else {
                            updateHotline(currentDataSource);
                        }
                    } else {
                        if (!map.hasLayer(polyline)) {
                            polyline.setLatLngs(path);
                            polyline.addTo(map);
                        } else {
                            polyline.setLatLngs(path);
                        }
                    }
                } else {
                    polyline.setLatLngs(path);
                }
            }
            setTimeout(()=>{map.removeLayer(marker)}, rate);
        }
    }, rate);

    // start and end point marker
    let pathL = path.length;
    let endCrd = path[0];
    let startCrd = path[pathL-1];

    // start marker
    const playSvgIcon = L.divIcon({
        html: `<svg width="20" height="20" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                 <polygon points="5,3 21,12 5,21" fill="green" stroke="white" stroke-width="1"/>
               </svg>`,
        className: 'svg-icon',
        iconAnchor: [12, 12]
    });

    // end marker
    const stopSvgIcon = L.divIcon({
        html: `<svg width="20" height="20" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
             <rect x="5" y="5" width="14" height="14" fill="black" stroke="white" stroke-width="1"/>
               </svg>`,
        className: 'svg-icon',
        iconAnchor: [12, 12]
    });

    const startcir = L.marker(startCrd, {icon: playSvgIcon, alt: 'Start Point'}).addTo(map);
    const endcir = L.marker(endCrd, {icon: stopSvgIcon, alt: 'End Point'}).addTo(map);

    startcir.unbindTooltip().bindTooltip(localization.key['travel.start'] ?? 'Start', {className: 'travel-tooltip'});
    endcir.unbindTooltip().bindTooltip(localization.key['travel.end'] ?? 'End', {className: 'travel-tooltip'});

    // travel line
    polyline = L.polyline(path, {
        color: '#000000',
        dashArray: '5, 5',
        weight: 3,
        opacity: 0.9,
        className: 'travel-line-stroke'
    }).addTo(map);

    // zoom the map to the polyline
    map.fitBounds(polyline.getBounds());

    mapUpdRange = (a,b) => {
        path = window.MapData.path.slice(a,b).filter(([a,b])=>(a>0||a<0||b>0||b<0));
        if (!path.length) return;

        startcir.setLatLng(path[path.length-1]);
        endcir.setLatLng(path[0]);

        if (currentDataSource !== null) {
            updateHotline(currentDataSource, [a, b]);
        } else {
            polyline.setLatLngs(path);
            polyline.addTo(map);
        }

        map.fitBounds(polyline.getBounds(), {maxZoom: 15});
    };

    const markerCir = L.circleMarker(startCrd, {color:'purple',alt:'Start Point',radius:10,weight:1, className: 'circle-marker-stroke'});
    const markerPnt = L.circleMarker(startCrd, {color:'purple',alt:'End Point',radius:5,weight:1,fillOpacity:1, className: 'circle-marker-stroke'});

    markerUpd = itm => {
        map.eachLayer(layer => {
            if (layer instanceof L.Tooltip && layer.options.className === 'heat-data-tooltip') {
                map.removeLayer(layer);
            }
        });

        if (itm && itm.dataIndex > 0) {
            const pos = path[itm.dataIndex] || path.at(-1) || [0,0];
            [markerCir, markerPnt].forEach(marker => {
                marker.setLatLng(pos).addTo(map);
            });

            if (currentDataSource !== null && heatData && heatData[currentDataSource]) {
                let dataPoint = heatData[currentDataSource].data[itm.dataIndex];
                if (dataPoint) {
                    let value = dataPoint[1];
                    let label = heatData[currentDataSource].label;

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
        } else {
            [markerCir, markerPnt].forEach(marker => map.removeLayer(marker));
        }
    }

    //coords
    let c = new L.Control.Coordinates({
        latitudeText: localization.key['lat'] ?? 'Latitude',
        longitudeText: localization.key['lon'] ?? 'Longitude',
    });
    c.addTo(map);

    function onMapClick(e) {
        c.setCoordinates(e);
    }
    map.on('click', onMapClick);
}
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

    function ctime(t) {//covert the epoch time to local readable 
        let date = new Date(t);

        if (isNaN(date.getTime())) {
            return '';
        }

        return  date.toLocaleTimeString($.cookie('timeformat') == '12' ? 'en-US' : 'ru-RU');
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
                if ($.cookie('plot') === undefined) updateMapWithRangePreservingHeatline(a, b);
            }
            if ($(".demo-container").length) chartUpdRange(a,b);
        });
    });
}
//End slider js code

function updateMapWithRangePreservingHeatline(startIndex = null, endIndex = null) {
    if (startIndex === null || endIndex === null) {
        mapIndexStart = 0;
        mapIndexEnd = jsTimeMap.length - 1;
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
    .register('/static/js/sw.js')
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
    //Close active dialog
    if (document.querySelector("#redDialogOverLay") != null) {
        if (event.key === 'Escape') {
            redDialog.doReset(redDialog.options);
        }
    }
    //Delete current session dialog
    if (typeof delSession === 'function') {
        if (event.key === 'Delete') {
            delSession();
        }
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
            map.invalidateSize();
            map.fitBounds(polyline.getBounds());
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
  const currSessionText = localization?.key?.['get.sess.curr'];

  $(`.choices__item:contains("${currSessionText}")`).text((i, oldText) =>
    oldText.replace(
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

    make(customOptions = {}) {
        const options = {...this.options, ...customOptions};
        this.doReset(options);

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
            z-index: ${options.zIndex};`;

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