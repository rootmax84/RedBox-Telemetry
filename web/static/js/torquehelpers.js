'use strict';

let markerUpd = null;
let chartTooltip = () => {
    let previousPoint = null;
    $("#placeholder").bind("plothover plottouchmove", function (event, pos, item) {
        if($("#map").length) markerUpd(item);
    });
};

const chart_fill = localStorage.getItem("chart_fill") === "true";
const chart_steps = localStorage.getItem("chart_steps") === "true";

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
  $("#Chart-Container").on("dblclick",()=>{initSlider(jsTimeMap,minTimeStart,maxTimeEnd)});
});

//start of chart plotting js code
let plot = null; //definition of plot variable in script but outside doPlot function to be able to reuse as a controller when updating base data
let flotData = [];
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
    }
    plot = $.plot("#placeholder", flotData, {
        xaxes: [ {
            mode: "time",
            timezone: "browser",
            axisLabel: "Time",
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
            margin: 0
        },
        selection: { mode: "x" },
        grid: {
            touchmove: true,
            mouseActiveRadius: 100,
            hoverable: true,
            clickable: false
        },
        hooks: {
            drawOverlay: [drawGapLines]
        },
        lines: {
            fill: chart_fill,
            steps: chart_steps
        }
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
        if($("#map").length) mapUpdRange(jsTimeMap.length-b-1,jsTimeMap.length-a-1);
        chartUpdRange(jsTimeMap.length-b-1,jsTimeMap.length-a-1);
        plot.clearSelection();
    });
    //End Trim by plot Select
}

let updCharts = ()=>{
    if ($('#plot_data').chosen().val()==null) {
        if ($('#placeholder')[0]!=undefined) {//clean our plot if it exists
            flotData = [];
            plot.shutdown();
            const noChart = $('<div>',{align:'center'}).append($('<h5>').append($('<span>',{class:'label label-warning'}).html('No Variables Selected to Plot')));
            const noChart2 = $('<div>',{align:'center',style:'display:flex; justify-content:center;'}).append($('<h5>').append($('<span>',{class:'label label-warning'}).html('No Variables Selected to Plot')));
            $('#Chart-Container').empty();
            $('#Chart-Container').append(noChart2);
            $('#Summary-Container').empty();
            $('#Summary-Container').append(noChart);
        }
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
                $('#Chart-Container').append($('<div>',{class:'demo-container'}).append($('<div>',{id:'placeholder',class:'demo-placeholder',style:'height:350px;touch-action:pan-y'})));
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
            const headers = ['Name', 'Min/Max', 'Mean', 'Sparkline'];
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
            const noChart = $('<div>',{align:'center'}).append($('<h5>').append($('<span>',{class:'label label-warning'}).html('No Data')));
            const noChart2 = $('<div>',{align:'center',style:'display:flex; justify-content:center;'}).append($('<h5>').append($('<span>',{class:'label label-warning'}).html('No Data')));
            $('#Chart-Container').empty();
            $('#Chart-Container').append(noChart2);
            $('#Summary-Container').empty();
            $('#Summary-Container').append(noChart);
            $('#chart-load').hide();
            console.error(err);
        });
    }
    else{
        const noChart = $('<div>',{align:'center'}).append($('<h5>').append($('<span>',{class:'label label-danger'}).html('Too much variables for plotting!')));
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
        "Map": osm,
        "Satellite": esri
    };

    let layerControl = L.control.layers(baseMaps).addTo(map);

    let c = new L.Control.Coordinates();
    c.addTo(map);

    function onMapClick(e) {
        c.setCoordinates(e);
    }
    map.on('click', onMapClick);

    L.control.locate({position: "bottomright"}).addTo(map);

    //Dynamic tracking marker when stream is open
    const rate = $.cookie('tracking-rate') !== undefined ? $.cookie('tracking-rate') : 1000;
    setInterval(()=>{
        let marker = null;
        let lat = stream ? parseFloat($('#lat').html()) : null;
        let lon = stream ? parseFloat($('#lon').html()) : null;
        let spd = stream ? ($('#spd').length != 0 ? $('#spd').html() : "No speed data in stream") : null;
        let spd_unit = stream ? ($('#spd-unit').length != 0 ? $('#spd-unit').html() : "") : null;
        if (lat == null || lon == null || isNaN(lat) || isNaN(lon) || (lat == 0 && lon == 0)) return;
        if (stream) {
            marker = new L.marker([lat, lon]).bindTooltip(spd+" "+spd_unit,{permanent:true,direction:'right',className:"stream-marker"}).addTo(map);
            map.setView(marker.getLatLng(), map.getZoom());
            //update travel line/end point
            if (path.at(0)[0] != lat && path.at(0)[1] != lon) {
                path.unshift([lat,lon]);
                polyline.setLatLngs(path);
                endcir.setLatLng(path.at(0));
            }
        }
        setTimeout(()=>{map.removeLayer(marker)}, rate);
    }, rate);

    // start and end point marker
    let pathL = path.length;
    let endCrd = path[0];
    let startCrd = path[pathL-1];
    const startcir = L.circleMarker(startCrd, {color:'green',title:'Start',alt:'Start Point',radius:6,weight:1}).addTo(map);
    const endcir = L.circleMarker(endCrd, {color:'black',title:'End',alt:'End Point',radius:6,weight:1}).addTo(map);
    // travel line
    let polyline = L.polyline(path, {color: 'red'}).addTo(map);
    // zoom the map to the polyline
    map.fitBounds(polyline.getBounds(), {maxZoom: 15});

    mapUpdRange = (a,b) => {//new function to update the map sources according to the trim slider
        path = window.MapData.path.slice(a,b).filter(([a,b])=>(a>0||a<0||b>0||b<0));
        if (!path.length) return;
        polyline.setLatLngs(path);
        startcir.setLatLng(path[path.length-1]);
        endcir.setLatLng(path[0]);
        map.fitBounds(polyline.getBounds(), {maxZoom: 15});
    };

    const markerCir = L.circleMarker(startCrd, {color:'purple',alt:'Start Point',radius:10,weight:1});
    const markerPnt = L.circleMarker(startCrd, {color:'purple',alt:'End Point',radius:5,weight:1});
    markerUpd = itm => {//this functions updates the marker while hovering the chart and clears it when not hovering
        if (itm && itm.dataIndex > 0) {
            const pos = path[itm.dataIndex] || path.at(-1) || [0,0];
            [markerCir, markerPnt].forEach(marker => {
                marker.setLatLng(pos).addTo(map);
            });
        } else {
            [markerCir, markerPnt].forEach(marker => map.removeLayer(marker));
        }
    }
}
//End of Leaflet Map Providers js code

//slider js code
let initSlider = (jsTimeMap,minTimeStart,maxTimeEnd)=>{
    let TimeStartv = timelookup(minTimeStart);
    let TimeEndv = timelookup(maxTimeEnd);

    function timelookup(t) { //retrun array index, used for slider steps/value, RIP IE, no polyfill 
        let fx = (e) => e == t;
        let out = jsTimeMap.findIndex(fx);
        return out;
    }

    function ctime(t) {//covert the epoch time to local readable 
        let date = new Date(t);
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
            if ($("#map").length) mapUpdRange(a,b);
            if ($(".demo-container").length) chartUpdRange(a,b);
        });
    } );
}
//End slider js code

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
function stream_alarm_handler() {
setTimeout(stream_alarm_handler, 3000);
 if ($("#rollback").length && $("#rollback").text() != "OK") {
  alarm.play();
 }
}

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
        onResolve: function() {},
        onReject: function() {}
    },
    confirmPromiseVal: null,
    activeElement: null,
    activeButton: null,
    make: function(customOptions) {
        customOptions = typeof customOptions == 'undefined' ? {} : customOptions;
        let options = Object.assign(this.options, customOptions);
        redDialog.doReset(options);

        let dialogDiv = document.createElement('div');
        dialogDiv.setAttribute('id', 'redDialogWrap');
        dialogDiv.setAttribute('class', 'card dlg');
        dialogDiv.setAttribute('style', 
            'position: absolute;' +
            'width: 300px !important;' +
            'padding: 1em !important;' +
            'top: ' + options.top + ';' +
            'right: ' + options.right + ';' +
            'margin: -100px -150px 0 -150px !important;' +
            'background: white;' +
            'border-radius: 5px;' +
            'z-index: ' + options.zIndex + ';'
        );

        dialogDiv.innerHTML = 
            `<div id="redDialog_title" style="min-height: 26px;border-bottom:1px dashed #777;color:` + 
            options.titleColor + ';">' + options.title + `</div>` +
            `<p style="text-align: left;padding: 16px 5px 0px 10px;width: 100%;margin: 0;font-size: 13px;max-width:280px">` + 
            options.message + `</p>`;

        let btnWrap = document.createElement('div');
        btnWrap.setAttribute('id', 'redDialogBtnWrap');
        btnWrap.setAttribute('style', 'padding: 20px 0 0;text-align: ' + options.btnPosition + ';');

        let yesBtn = document.createElement('button');
        yesBtn.setAttribute('id', 'redDialogBtnYes');
        yesBtn.setAttribute('style', 'min-width: 62px;');
        yesBtn.setAttribute('class', options.btnClassSuccess);
        yesBtn.setAttribute('autofocus', '');
        yesBtn.innerHTML = options.btnClassSuccessText;
        yesBtn.addEventListener('click', function(event) {
            redDialog.resolve();
        });

        let space = document.createTextNode(' ');

        let noBtn = document.createElement('button');
        noBtn.setAttribute('id', 'redDialogBtnNo');
        noBtn.setAttribute('style', 'min-width: 62px;');
        noBtn.setAttribute('class', options.btnClassFail);
        noBtn.innerHTML = options.btnClassFailText;
        noBtn.addEventListener('click', function(event) {
            redDialog.reject();
        });

        yesBtn.addEventListener('keydown', function(event) {
            if (event.key == 'ArrowRight') {
                this.activeButton = noBtn;
                noBtn.focus();
            }
        });

        noBtn.addEventListener('keydown', function(event) {
            if (event.key == 'ArrowLeft') {
                this.activeButton = yesBtn;
                yesBtn.focus();
            }
        });

        btnWrap.appendChild(yesBtn);
        btnWrap.appendChild(space);
        btnWrap.appendChild(noBtn);
        dialogDiv.appendChild(btnWrap);

        let overlayDiv = document.createElement('div');
        overlayDiv.setAttribute('id', 'redDialogOverLay');
        overlayDiv.setAttribute('style', 
            'position:fixed;top:0;left:0;width:100%;height:100%;z-index:' + 
            (options.zIndex - 1) + 
            ';background:' + options.overlayBackground + ';'
        );
        overlayDiv.appendChild(dialogDiv);
        document.querySelector('body').appendChild(overlayDiv);

        this.activeElement = document.activeElement;
        yesBtn.focus();
        this.activeButton = yesBtn;

        return new Promise(function(resolve, reject) {
            redDialog.confirmPromiseInterval = setInterval(function() {
                if (redDialog.confirmPromiseVal === true) {
                    redDialog.doReset(options);
                    resolve(true);
                } else if (redDialog.confirmPromiseVal === false) {
                    redDialog.doReset(options);
                    resolve(false);
                }
            });
        });
    },

    resolve: function() {
        this.onResolve();
        this.confirmPromiseVal = true;
    },

    reject: function() {
        this.onReject();
        this.confirmPromiseVal = false;
    },

    doReset: function(options) {
        if (document.querySelector('#redDialogOverLay') != null) {
            document.querySelector('#redDialogOverLay').remove();
        }
        this.confirmPromiseVal = null;
        if (this.activeElement) {
            this.activeElement.focus();
            this.activeElement = null;
        }
        this.activeButton = null;
        this.onResolve = options.onResolve;
        this.onReject = options.onReject;
    },

    onResolve: function() {},
    onReject: function() {}
};