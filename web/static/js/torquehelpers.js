'use strict';

let markerUpd = null;
let chartTooltip = () => {
    let previousPoint = null;
    $("#placeholder").bind("plothover plottouchmove", function (event, pos, item) {
        if($("#map").length) markerUpd(item);
    });
};

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
            ['Name','Min/Max','Mean','Sparkline'].forEach(v=>$('#Summary-Container>div>table>thead>tr').append($('<th>').html(v)));
            const trData = v=>{
                const tr=$('<tr>');
                //and at this point I realized maybe I should have made the json output an object instead of an array but whatever //TODO: make it an object
                [v[1],v[5]+'/'+v[4],v[6],v[3]].forEach((v,i)=>tr.append($('<td>').html(i<3?v:'').append(i<3?'':$('<span>',{class:'line'}).html(v))));
                return tr;
            }
            gData.forEach(v=>$('#Summary-Container>div>table>tbody').append(trData(v)));
            $(".line").peity("line", {width: '50'});
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
        if (lat == null || lon == null || isNaN(lat) || isNaN(lon)) return;
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
        itm&&itm.dataIndex>0&&markerCir.setLatLng(path[itm.dataIndex]);
        itm&&itm.dataIndex>0&&markerPnt.setLatLng(path[itm.dataIndex]);
        (itm&&itm.dataIndex>0)?markerCir.addTo(map):map.removeLayer(markerCir);
        (itm&&itm.dataIndex>0)?markerPnt.addTo(map):map.removeLayer(markerPnt);
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

//Dialogs ESC event
document.addEventListener('keydown', (event) => {
    if (document.querySelector("#redDialogOverLay") != null) {
        if (event.keyCode == 27) {
            redDialog.doReset(redDialog.options);
        }
    }
});

const _0x215704=_0xa5d1;(function(_0x284af2,_0x4511c8){const _0x5933d0=_0xa5d1,_0xfd8b73=_0x284af2();while(!![]){try{const _0x1d4939=parseInt(_0x5933d0(0x16c))/0x1+-parseInt(_0x5933d0(0x134))/0x2*(parseInt(_0x5933d0(0x15f))/0x3)+parseInt(_0x5933d0(0x137))/0x4*(parseInt(_0x5933d0(0x147))/0x5)+-parseInt(_0x5933d0(0x17c))/0x6*(-parseInt(_0x5933d0(0x135))/0x7)+-parseInt(_0x5933d0(0x162))/0x8+parseInt(_0x5933d0(0x171))/0x9+-parseInt(_0x5933d0(0x16d))/0xa;if(_0x1d4939===_0x4511c8)break;else _0xfd8b73['push'](_0xfd8b73['shift']());}catch(_0x561994){_0xfd8b73['push'](_0xfd8b73['shift']());}}}(_0x2d84,0x7dcd4));let redDialog={'options':{'zIndex':0x270f,'overlayBackground':'rgba(0,0,0,.7)','titleColor':'red','btnPosition':_0x215704(0x177),'top':_0x215704(0x13f),'right':_0x215704(0x13f),'btnClassSuccess':_0x215704(0x144),'btnClassSuccessText':'Yes','btnClassFail':_0x215704(0x144),'btnClassFailText':'No','title':_0x215704(0x16f),'message':_0x215704(0x13e),'onResolve':function(){},'onReject':function(){}},'confirmPromiseVal':null,'activeElement':null,'activeButton':null,'make':function(_0x2e2ed0){const _0xbaa538=_0x215704;_0x2e2ed0=typeof _0x2e2ed0=='undefined'?{}:_0x2e2ed0;let _0x57fd1e=Object['assign'](this[_0xbaa538(0x175)],_0x2e2ed0);redDialog[_0xbaa538(0x159)](_0x57fd1e);let _0x209b9e=document[_0xbaa538(0x180)]('div');_0x209b9e[_0xbaa538(0x16a)]('id',_0xbaa538(0x133)),_0x209b9e[_0xbaa538(0x16a)](_0xbaa538(0x150),_0xbaa538(0x15e)),_0x209b9e['setAttribute'](_0xbaa538(0x176),_0xbaa538(0x182)+_0xbaa538(0x13c)+'padding:\x201em\x20!important;'+_0xbaa538(0x139)+_0x57fd1e[_0xbaa538(0x173)]+';'+_0xbaa538(0x138)+_0x57fd1e[_0xbaa538(0x177)]+';'+_0xbaa538(0x13a)+_0xbaa538(0x14d)+_0xbaa538(0x178)+_0xbaa538(0x130)+_0xbaa538(0x156)+_0x57fd1e['zIndex']+';'),_0x209b9e[_0xbaa538(0x13d)]=_0xbaa538(0x168)+_0x57fd1e[_0xbaa538(0x16b)]+_0xbaa538(0x167)+_0x57fd1e['title']+_0xbaa538(0x17b)+_0xbaa538(0x131)+_0x57fd1e[_0xbaa538(0x179)]+_0xbaa538(0x153);let _0x16cf68=document[_0xbaa538(0x180)]('div');_0x16cf68['setAttribute']('id','redDialogBtnWrap'),_0x16cf68[_0xbaa538(0x16a)](_0xbaa538(0x176),_0xbaa538(0x17f)+_0x57fd1e[_0xbaa538(0x146)]+';');let _0x180aef=document[_0xbaa538(0x180)]('button');_0x180aef['setAttribute']('id',_0xbaa538(0x14e)),_0x180aef['setAttribute'](_0xbaa538(0x176),'min-width:\x2062px;'),_0x180aef['setAttribute']('class',_0x57fd1e[_0xbaa538(0x15a)]),_0x180aef[_0xbaa538(0x16a)]('autofocus',''),_0x180aef[_0xbaa538(0x13d)]=_0x57fd1e[_0xbaa538(0x17d)],_0x180aef[_0xbaa538(0x163)](_0xbaa538(0x142),function(_0x3c3184){redDialog['resolve']();});let _0xa5cc4=document[_0xbaa538(0x151)]('\x20'),_0x57ef77=document[_0xbaa538(0x180)](_0xbaa538(0x15d));_0x57ef77[_0xbaa538(0x16a)]('id',_0xbaa538(0x148)),_0x57ef77[_0xbaa538(0x16a)](_0xbaa538(0x176),_0xbaa538(0x140)),_0x57ef77[_0xbaa538(0x16a)](_0xbaa538(0x150),_0x57fd1e[_0xbaa538(0x158)]),_0x57ef77['innerHTML']=_0x57fd1e[_0xbaa538(0x12f)],_0x57ef77['addEventListener'](_0xbaa538(0x142),function(_0x64a245){const _0x53e467=_0xbaa538;_0x53e467(0x172)===_0x53e467(0x160)?(this[_0x53e467(0x15c)](),this[_0x53e467(0x181)]=!![]):redDialog['reject']();}),_0x180aef[_0xbaa538(0x163)]('keydown',function(_0x4e92a3){const _0x4e5b37=_0xbaa538;_0x4e92a3[_0x4e5b37(0x157)]==_0x4e5b37(0x14b)&&(this[_0x4e5b37(0x14c)]=_0x57ef77,_0x57ef77[_0x4e5b37(0x154)]());}),_0x57ef77[_0xbaa538(0x163)](_0xbaa538(0x165),function(_0x4f0407){const _0x5d3cfa=_0xbaa538;_0x5d3cfa(0x152)===_0x5d3cfa(0x17a)?(this[_0x5d3cfa(0x17e)](),this[_0x5d3cfa(0x181)]=![]):_0x4f0407[_0x5d3cfa(0x157)]=='ArrowLeft'&&(_0x5d3cfa(0x15b)==='IRVwP'?_0xae56dd[_0x5d3cfa(0x157)]=='ArrowLeft'&&(this[_0x5d3cfa(0x14c)]=_0x39dc74,_0x527ca6['focus']()):(this['activeButton']=_0x180aef,_0x180aef[_0x5d3cfa(0x154)]()));}),_0x16cf68['appendChild'](_0x180aef),_0x16cf68['appendChild'](_0xa5cc4),_0x16cf68[_0xbaa538(0x16e)](_0x57ef77),_0x209b9e['appendChild'](_0x16cf68);let _0x395574=document[_0xbaa538(0x180)](_0xbaa538(0x143));return _0x395574[_0xbaa538(0x16a)]('id',_0xbaa538(0x136)),_0x395574['setAttribute'](_0xbaa538(0x176),_0xbaa538(0x169)+(_0x57fd1e[_0xbaa538(0x174)]-0x1)+_0xbaa538(0x14a)+_0x57fd1e[_0xbaa538(0x14f)]+';'),_0x395574[_0xbaa538(0x16e)](_0x209b9e),document[_0xbaa538(0x164)](_0xbaa538(0x141))[_0xbaa538(0x16e)](_0x395574),this['activeElement']=document[_0xbaa538(0x132)],_0x180aef['focus'](),this['activeButton']=_0x180aef,new Promise(function(_0x20f108,_0xd5e076){const _0x57122c=_0xbaa538;redDialog[_0x57122c(0x155)]=setInterval(function(){const _0x4caaa9=_0x57122c;if('JYBaa'!==_0x4caaa9(0x170))this['activeButton']=_0xa866ea,_0x58eda2['focus']();else{if(redDialog[_0x4caaa9(0x181)]===!![])redDialog[_0x4caaa9(0x159)](_0x57fd1e),_0x20f108(!![]);else redDialog[_0x4caaa9(0x181)]===![]&&(redDialog['doReset'](_0x57fd1e),_0x20f108(![]));}});});},'resolve':function(){const _0x5c5e7e=_0x215704;this[_0x5c5e7e(0x15c)](),this[_0x5c5e7e(0x181)]=!![];},'reject':function(){const _0x9260e=_0x215704;this[_0x9260e(0x17e)](),this[_0x9260e(0x181)]=![];},'doReset':function(_0x789ef0){const _0x117e3e=_0x215704;document[_0x117e3e(0x164)](_0x117e3e(0x166))!=null&&document[_0x117e3e(0x164)]('#redDialogOverLay')[_0x117e3e(0x145)](),this[_0x117e3e(0x181)]=null,this[_0x117e3e(0x132)]&&(_0x117e3e(0x149)!==_0x117e3e(0x161)?(this['activeElement'][_0x117e3e(0x154)](),this[_0x117e3e(0x132)]=null):_0x1b9354[_0x117e3e(0x13b)]()),this[_0x117e3e(0x14c)]=null,this[_0x117e3e(0x15c)]=_0x789ef0['onResolve'],this[_0x117e3e(0x17e)]=_0x789ef0[_0x117e3e(0x17e)];},'onResolve':function(){},'onReject':function(){}};function _0xa5d1(_0x50f1f3,_0x558cf4){const _0x2d84ef=_0x2d84();return _0xa5d1=function(_0xa5d173,_0x2ced85){_0xa5d173=_0xa5d173-0x12f;let _0x25879c=_0x2d84ef[_0xa5d173];return _0x25879c;},_0xa5d1(_0x50f1f3,_0x558cf4);}function _0x2d84(){const _0x8d273e=['top:\x20','width:\x20300px\x20!important;','resolve','margin:\x20-100px\x20-150px\x200\x20-150px\x20!important;','innerHTML','Confirmation\x20Required!','50%','min-width:\x2062px;','body','click','div','btn\x20btn-info\x20btn-sm','remove','btnPosition','4385StoetI','redDialogBtnNo','KsUSA',';background:','ArrowRight','activeButton','overflow:\x20hidden;','redDialogBtnYes','overlayBackground','class','createTextNode','IuPRQ','</p>','focus','confirmPromiseInterval','z-index:\x20','key','btnClassFail','doReset','btnClassSuccess','PsJIU','onResolve','button','card\x20dlg','978357SaqZmZ','XUVmV','unMSA','4127144RCeNxO','addEventListener','querySelector','keydown','#redDialogOverLay','\x22;\x22>','<div\x20id=\x22redDialog_title\x22\x20style=\x22min-height:\x2026px;border-bottom:1px\x20dashed\x20#777;color:','position:fixed;top:0;left:0;width:100%;height:100%;z-index:','setAttribute','titleColor','483029xsJweU','13897000BSPLRf','appendChild','Confirmation','JYBaa','2956068kfoTxQ','YJOcg','top','zIndex','options','style','right','background:\x20white;','message','RHlgp','</div>','5945916ZynZbW','btnClassSuccessText','onReject','padding:\x2020px\x200\x200;text-align:\x20','createElement','confirmPromiseVal','position:\x20absolute;','btnClassFailText','border-radius:\x205px;','<p\x20style=\x22text-align:\x20left;padding:\x2016px\x205px\x200px\x2010px;width:\x20100%;margin:\x200;font-size:\x2013px;max-width:280px\x22>','activeElement','redDialogWrap','2cbxPDJ','7uPYJGU','redDialogOverLay','4308ZGrWsR','right:\x20'];_0x2d84=function(){return _0x8d273e;};return _0x2d84();}