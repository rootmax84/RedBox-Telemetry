'use strict';

let markerUpd = null;
let chartTooltip = () => {
    let previousPoint = null;
    $("#placeholder").bind("plothover plottouchmove", function (event, pos, item) {
        if($("#map").length) markerUpd(item);
    });
};

const chart_fill = localStorage.getItem(`${username}-chart_fill`) === "true";
const chart_fillGradient = localStorage.getItem(`${username}-chart_fillGradient`) === "true";
const chart_steps = localStorage.getItem(`${username}-chart_steps`) === "true";
const chart_lineWidth = localStorage.getItem(`${username}-chart_lineWidth`) || 2;

$(document).ready(function(){
  // Activate Chosen on the selection drop down
  $("select#seshidtag").chosen({width: "100%"});
  $("select#selprofile").chosen({width: "100%", disable_search: true, allow_single_deselect: true});
  $("select#selyear").chosen({width: "100%", disable_search: true, allow_single_deselect: true});
  $("select#selmonth").chosen({width: "100%", disable_search: true, allow_single_deselect: true});
  $("select#plot_data").chosen({width: "100%"});
  // Center the selected element
  $("div#seshidtag_chosen a.chosen-single span").css('text-align', 'center');
  $("div#selprofile_chosen a.chosen-single span").css('text-align', 'center');
  $("div#selyear_chosen a.chosen-single span").css('text-align', 'center');
  $("div#selmonth_chosen a.chosen-single span").css('text-align', 'center');
  $("select#plot_data").chosen({no_results_text: "Oops, nothing found!"});
  $("select#plot_data").chosen({placeholder_text_multiple: "Choose data.."});
  // Reset flot zoom
  $("#Chart-Container").on("dblclick", ()=>{initSlider(jsTimeMap,jsTimeMap[0],jsTimeMap.at(-1))});
  $("#update-plot").on("dblclick", updatePlot);
});

function updatePlot() {
    updCharts();
    initSlider(jsTimeMap,jsTimeMap[0],jsTimeMap.at(-1));
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
            axisLabel: localization.key['chart.time.label'],
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
                steps: chart_steps,
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
        // Convert range to real time markers
        const realFrom = findNearestRealTime(range.xaxis.from);
        const realTo = findNearestRealTime(range.xaxis.to);
        // Find jsTimeMap indexes
        const a = jsTimeMap.findIndex(t => t >= realFrom);
        const b = jsTimeMap.findIndex(t => t >= realTo);
        if (Math.abs(a-b)<3) return;
        $("#slider-range11").slider('values',0,a);
        $("#slider-range11").slider('values',1,b);
        $("#slider-time").val( (new Date(jsTimeMap[a])).toLocaleTimeString($.cookie('timeformat') == '12' ? 'en-US' : 'ru-RU') + " - " + (new Date(jsTimeMap[b])).toLocaleTimeString($.cookie('timeformat') == '12' ? 'en-US' : 'ru-RU'));

        const mapIndexStart = jsTimeMap.length-b-1;
        const mapIndexEnd = jsTimeMap.length-a-1;

        if($("#map").length) {
            updateMapWithRangePreservingHeatline(mapIndexStart, mapIndexEnd);
        }

        chartUpdRange(jsTimeMap.length-b-1,jsTimeMap.length-a-1);
        plot.clearSelection();
    });
    //End Trim by plot Select
}

let updCharts = ()=>{
    if ($('#plot_data').chosen().val().length == 0) {
        const noChart = $('<div>',{align:'center'}).append($('<h5>').append($('<span>',{class:'label label-warning'}).html(localization.key['novar'])));
        const noChart2 = $('<div>',{align:'center',style:'display:flex; justify-content:center;'}).append($('<h5>').append($('<span>',{class:'label label-warning'}).html(localization.key['novar'])));
        if ($('#placeholder')[0]!=undefined) {//clean our plot if it exists
            flotData = [];
            heatData = [];
            updateMapWithRangePreservingHeatline(null,null,true);
            plot.shutdown();
        }
        $('#Chart-Container').empty();
        $('#Chart-Container').append(noChart2);
        $('#Summary-Container').empty();
        $('#Summary-Container').append(noChart);
    } else if ($('#plot_data').chosen().val().length <= 10){
        $("#chart-load").css("display","block");
        let varPrm = 'plot.php?id='+$('#seshidtag').chosen().val();
        $('#plot_data').chosen().val().forEach((v,i)=>varPrm+='&s'+(i+1)+'='+v);
        fetch(varPrm).then(d => d.json()).then(gData => {
            flotData = [];
            $("#chart-load").css("display","none");
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
            const headers = [localization.key['datasum.name'], localization.key['datasum.mm'], localization.key['datasum.mean'], localization.key['datasum.sparkline']];
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
            for (let i = 0; i < 4; i++) {
                const td = document.createElement('td');
                if (i === 3) {
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
                tds[1].textContent = v[5] + '/' + v[4];
                tds[2].textContent = v[6];
                tds[3].querySelector('.line').textContent = v[3];
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
            if ($('#plot_data').chosen().val() == null) updCharts();
        }).catch(err => {
            const noChart = $('<div>',{align:'center'}).append($('<h5>').append($('<span>',{class:'label label-warning'}).html(localization.key['nodata'])));
            const noChart2 = $('<div>',{align:'center',style:'display:flex; justify-content:center;'}).append($('<h5>').append($('<span>',{class:'label label-warning'}).html(localization.key['nodata'])));
            $('#Chart-Container').empty();
            $('#Chart-Container').append(noChart2);
            $('#Summary-Container').empty();
            $('#Summary-Container').append(noChart);
            $('#chart-load').hide();
            console.error(err);
        });
    }
    else{
        const noChart = $('<div>',{align:'center'}).append($('<h5>').append($('<span>',{class:'label label-danger'}).html(localization.key['overdata'])));
        $('#Chart-Container').empty();
        $('#Chart-Container').append(noChart);
   }
}
//End of chart plotting js code

//Start of Leaflet Map Providers js code
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
    let map = new L.Map("map", {
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
        [localization.key['layer.map']]: osm,
        [localization.key['layer.sat']]: esri
    };

    let layerControl = L.control.layers(baseMaps).addTo(map);

    let c = new L.Control.Coordinates({
        latitudeText: localization.key['lat'],
        longitudeText: localization.key['lon'],
    });
    c.addTo(map);

    function onMapClick(e) {
        c.setCoordinates(e);
    }
    map.on('click', onMapClick);
    L.control.locate({position: "bottomright", showPopup: false}).addTo(map);

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
                        this.title = selectedOption.text;
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

    function handleSelectorChange(e) {
        let sourceIndex = e.target.value;
        updateHotline(sourceIndex);
    }

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
            updateMapWithRangePreservingHeatline(null,null,true);
            lastFlotDataLength = heatData.length;
            updateDataSourceSelector();
        }
    }, 1000);

    function prepareHotlineData(sourceIndex, rangeIndices) {

        if (sourceIndex === null || sourceIndex === "" || !heatData || !heatData[sourceIndex]) {
            return null;
        }

        let source = heatData[sourceIndex];
        let dataPoints = source.data;

        let filteredPath = path;
        if (rangeIndices) {
            filteredPath = path.slice(rangeIndices[0], rangeIndices[1]);
        }

        if (!filteredPath.length || !dataPoints.length) {
            return null;
        }

        let values = dataPoints.map(point => point[1]);
        let minValue = Math.min(...values);
        let maxValue = Math.max(...values);

        if (minValue === maxValue) {
            minValue = minValue - 1;
            maxValue = maxValue + 1;

            if (minValue === -1 && maxValue === 1) {
                return null;
            }
        }

        let hotlinePoints = [];

        const minLength = Math.min(filteredPath.length, dataPoints.length);

        for (let i = 0; i < minLength; i++) {
            let point = L.latLng(filteredPath[i][0], filteredPath[i][1]);
            point.alt = dataPoints[i][1];
            hotlinePoints.push(point);
        }

        return {
            points: hotlinePoints,
            min: minValue,
            max: maxValue
        };
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
                outlineColor: '#aaa',
                outlineWidth: 1
            }).addTo(map);

            updateLegend(hotlineData.min, hotlineData.max);

        } catch (error) {
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
            min: Math.round(min),
            max: Math.round(max),
            palette: {0: 'green', 0.5: 'yellow', 1: 'red'},
            position: 'bottomright'
        }).addTo(map);
    }

    L.Control.HotlineLegend = L.Control.extend({
        options: {
            position: 'bottomright',
            min: 0,
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
            this._container.style.textShadow = "0px 0 2px rgb(255 255 255)";

            var labelsContainer = L.DomUtil.create('div', 'hotline-legend-labels', this._container);
            labelsContainer.style.display = 'flex';
            labelsContainer.style.flexDirection = 'column';
            labelsContainer.style.justifyContent = 'space-between';
            labelsContainer.style.marginRight = '3px';
            labelsContainer.style.fontSize = '10px';
            labelsContainer.style.fontWeight = 'bold';

            var maxLabel = L.DomUtil.create('div', 'hotline-legend-label', labelsContainer);
            maxLabel.innerHTML = this.options.max;

            var minLabel = L.DomUtil.create('div', 'hotline-legend-label', labelsContainer);
            minLabel.innerHTML = this.options.min;

            var canvas = L.DomUtil.create('canvas', 'hotline-legend-canvas', this._container);
            canvas.width = this.options.width;
            canvas.height = this.options.height;
            canvas.style.display = 'block';

            var ctx = canvas.getContext('2d');
            var gradient = ctx.createLinearGradient(0, canvas.height, 0, 0);

            for (var stop in this.options.palette) {
                gradient.addColorStop(stop, this.options.palette[stop]);
            }

            ctx.fillStyle = gradient;
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.strokeStyle = 'black';
            ctx.strokeRect(0, 0, canvas.width, canvas.height);

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
            if (path.at(0)[0] != lat && path.at(0)[1] != lon) {
                path.unshift([lat,lon]);
                endcir.setLatLng(path.at(0));

                if (currentDataSource !== null) {
                    if (heatData && heatData[currentDataSource]) {
                        if (hotlineLayer) {
                            let currentLatLngs = hotlineLayer.getLatLngs();

                            let latestDataPoint = heatData[currentDataSource].data[0];

                            if (latestDataPoint) {
                                let newPoint = L.latLng(lat, lon, latestDataPoint[1]);

                                if (Array.isArray(currentLatLngs[0])) {
                                    currentLatLngs[0].unshift(newPoint);
                                } else {
                                    currentLatLngs.unshift(newPoint);
                                }

                                hotlineLayer.setLatLngs(currentLatLngs);
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
    const startcir = L.circleMarker(startCrd, {color:'green',title:'Start',alt:'Start Point',radius:6,weight:1}).addTo(map);
    const endcir = L.circleMarker(endCrd, {color:'black',title:'End',alt:'End Point',radius:6,weight:1}).addTo(map);

    // travel line
    let polyline = L.polyline(path, {
        color: '#3d3d3d',
        dashArray: '3, 3',
        weight: '2'
    }).addTo(map);

    // zoom the map to the polyline
    map.fitBounds(polyline.getBounds(), {maxZoom: 15});

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

    const markerCir = L.circleMarker(startCrd, {color:'purple',alt:'Start Point',radius:10,weight:1});
    const markerPnt = L.circleMarker(startCrd, {color:'purple',alt:'End Point',radius:5,weight:1});

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
}
//End of Leaflet Map Providers js code

//slider js code
let initSlider = (jsTimeMap,start,end)=>{
    $("#slider-range11").off();
    if ($("#slider-range11").hasClass("ui-slider")) {
        $("#slider-range11").slider("destroy");
        initSlider(jsTimeMap,start,end);
    }

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
            return localization.key['nodata'];
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
            if ($("#map").length) {
                updateMapWithRangePreservingHeatline(a, b);
            }
            if ($(".demo-container").length) chartUpdRange(a,b);
        });
    });
}
//End slider js code

function updateMapWithRangePreservingHeatline(startIndex, endIndex, reset = false) {
    const dataSourceSelect = document.getElementById('heat-dataSourceSelect');

    if (reset) {
        dataSourceSelect.value = "";

        const changeEvent = new Event('change');
        dataSourceSelect.dispatchEvent(changeEvent);
        return;
    }

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
 switch (localStorage.getItem("theme")) {
  case "default":
   localStorage.setItem("theme", "dark");
   let head = document.getElementsByTagName('head')[0];
   let link = document.createElement('link');
   link.rel = 'stylesheet';
   link.href = 'static/css/dark.css';
   head.appendChild(link);
  break;
  case "dark":
   localStorage.setItem("theme", "default");
   let lNode =  document.querySelector('link[href*="static/css/dark.css"]');
   lNode.parentNode.removeChild(lNode);
  break;
 }
}

function logout() {
 location.href='.?logout=true';
}

const alarm = new Audio("data:audio/mpeg;base64,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8Q18DK5S9Rtim2ZAwxbYC339u3mIAEA5RILAEC6gGziEsujIAVLW+ZrRoQgbKmb1tuX56tc3J/rxVHEAUAqgyvAuJRGKpcIwglJWiiVGJKLpUM0A7LTj7j7jbC+8EMG/S9Kb8V7/+1Bk84Ly0zDc8Y9mAAAAD/AAAAEKQMN1xLB1CAAAP8AAAARFu4AHcIcHYGUIcA5oGwAAAAAMBvmqadlRq52aGykta2S+MoKXFnldJE1HJ+ZkmAJZFSkQYvFwTzpGRsDdEHKJRImVRSAn00KQs4ckgv6BPmZkyKFWCkxBTUUzLjEwMFVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVV//tQZPMC8j4o3vEjZKIAAA/wAAABCcyld8S9OAgAAD/AAAAEVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVf/7QGT9gvJzKV3xI2SiAAAP8AAAAQrQv3PUlgAIAAA/woAABFVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVV//sgZPQAAqApV34eQAIAAA/wwAAAAAAB/hwAACAAAD/DgAAEVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVQ==");
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
            <div id="redDialog_title" style="min-height: 26px;border-bottom:1px dashed #777;color:${options.titleColor};">${options.title}</div>
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