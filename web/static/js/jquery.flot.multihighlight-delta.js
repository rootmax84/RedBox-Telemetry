/**
 *
 * User: Patrick de Lanauze
 * Date: 2013-03-21
 * Time: 10:59 AM
 *
 */

//Time conversion processing
function convertToRealTime(processedTime) {
  if (!window.realTimeInfo || !window.realTimeInfo.timeMapping) {
    return new Date(processedTime);
  }

  const timeMapping = window.realTimeInfo.timeMapping;
  if (!timeMapping._sortedProcessedTimes) {
    timeMapping._sortedProcessedTimes = Object.keys(timeMapping).map(Number).sort((a, b) => a - b);
  }

  const processedTimes = timeMapping._sortedProcessedTimes;

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
  const realTime = timeMapping[nearestProcessedTime];
  return new Date(realTime);
}

(function (name, definition) {
  var theModule = definition(),
  // this is considered "safe":
      hasDefine = typeof define === 'function' && define.amd,
  // hasDefine = typeof define === 'function',
      hasExports = typeof module !== 'undefined' && module.exports;

  if (hasDefine) { // AMD Module
    define(theModule);
  } else if (hasExports) { // Node.js Module
    module.exports = theModule;
  } else { // Assign to common namespaces or simply the global object (window)
    (this.jQuery || this.ender || this.$ || this)[name] = theModule;
  }
})('core', function () {

  var MultiHighlightDelta = {
    options: {
      multihighlightdelta: {
        mode: 'x',
        tooltipOffsetX: 20,
        tooltipOffsetY: 20,
		// 2015.08.17 - edit by surfrock66 - Added variable for displaying time in tooltip
        tooltipTemplate: '<table class="table" style="font-size:.8em;"><thead><tr><th><%= time[0] %></th><th><%= value %></th><th><%= change %></th><th><%= event %></th></tr></thead><tbody><%= body %></tbody></table>',
        dataPointTemplate: '<tr><td><%= series.label %></td><td><%= datapoint[1] %></td><td><%= (delta > 0 ? "+" : "") %><%= delta %></td><td><%= rlbc %></td></tr>',
        transformDataPointData: false,
        tooltipStyles: {
          position: 'absolute',
          display: 'none',
          'background': '#fff',
          'z-index': '100',
          'padding': '0.4em 0.6em',
          'border-radius': '0.5em',
          'font-size': '0.8em',
          'border': '1px solid #111'
        },
        delta: function (previousDataPoint, dataPoint) {
          if (!previousDataPoint) {
            return '';
          }
          var chng = dataPoint[1] - previousDataPoint[1];

          return (-1*chng).toFixed(2);
        }
      }
    }
  };
  var MultiHighlightDeltaPlugin = function (plot) {
    this.plot = plot;
  };

  /**
   * Thanks John Resig! [ http://ejohn.org/blog/javascript-micro-templating/ ]
   * Based heavily off john's implementation , but removed caching
   */
  var compileTemplate = function (str) {

    // Generate a reusable function that will serve as a template
    // generator (and should be cached by its caller).
    /* jshint -W121 */
    return new Function("obj",
        "var p=[],print=function(){p.push.apply(p,arguments);};" +

          // Introduce the data as local variables using with(){}
            "with(obj){p.push('" +

          // Convert the template into pure JavaScript
            str.replace(/[\r\t\n]/g, " ")
                .split("<%").join("\t")
                .replace(/((^|%>)[^\t]*)'/g, "$1\r")
                .replace(/\t=(.*?)%>/g, "',$1,'")
                .split("\t").join("');")
                .split("%>").join("p.push('")
                .split("\r").join("\\'") + "');}return p.join('');");
  };

  MultiHighlightDeltaPlugin.prototype = {
    initialize: function () {
      var ctx = this;

      var handlerProxies = {
        onPlotHover: $.proxy(ctx.onPlotHover, ctx),
        onMouseOut: $.proxy(ctx.onMouseOut, ctx)
      };

      this.plot.hooks.bindEvents.push(function (plot) {
        if (!plot.getOptions().multihighlightdelta) {
          return;
        }

        var options = ctx.plot.getOptions().multihighlightdelta || {};
        for (var key in MultiHighlightDelta.options.multihighlightdelta) {
          if (typeof options[key] === 'undefined') {
            options[key] = MultiHighlightDelta.options.multihighlightdelta[key];
          }
        }

        plot.getPlaceholder().on('plothover plottouchmove', handlerProxies.onPlotHover);
        plot.getPlaceholder().on('mouseout touchend', handlerProxies.onMouseOut);

        // Keep a cache of the template
        ctx.tooltipTemplate = compileTemplate(options.tooltipTemplate);
        ctx.dataPointTemplate = compileTemplate(options.dataPointTemplate);

      });
      this.plot.hooks.shutdown.push(function (plot) {
        plot.getPlaceholder().off('plothover plottouchmove', handlerProxies.onPlotHover);
        plot.getPlaceholder().off('mouseout touchend', handlerProxies.onMouseOut);
      });

      return this;
    },
    findOrCreateTooltip: function (tooltipStyles) {
      var $tip = null;
      if ($('#flotMultihighlightTip').length > 0) {
        $tip = $('#flotMultihighlightTip');
      }
      else {
        $tip = $('<div />').attr('id', 'flotMultihighlightTip').addClass('flot-tooltip').css(tooltipStyles).appendTo('body');
      }
      return $tip;
    },
    onPlotHover: function (event, position, item) {
      var data = this.plot.getData();
      var options = this.plot.getOptions().multihighlightdelta;
      var deltaFunction = options.delta;
      var mode = options.mode || 'x';
      var index = 0;
      if (mode === 'x') {
        index = 0;
      } else if (mode === 'y') {
        index = 1;
      } else {
        throw new Error('Mode \'' + mode + '\', is not recognized, must be x or y');
      }

      if (item) {

        this.plot.unhighlight();
        var matchingDataPoints = [];
        var showEventHeader = false;

        for (var i = 0 , ii = data.length; i < ii; i++) {
          // Find the data point in the other series that matches the current datapoint
          var seriesData = data[i].data;
          for (var j = 0 , jj = seriesData.length; j < jj; j++) {
            if (seriesData[j][index] === item.datapoint[index]) {
              matchingDataPoints.push({
                seriesData: data[i],
                dataPoint: seriesData[j],
                delta: deltaFunction(j > 0 ? seriesData[j - 1] : null, seriesData[j])
              });
              if (data[i]['label'].includes('Rollback')) {
                showEventHeader = true;
              }
            }
          }
        }

        var childrenTexts = [];
		// 2015.08.17 - edit by surfrock66 - define variable array to display the time in the tooltip
		var timeArray = [];
        for (var i = 0 , ii = matchingDataPoints.length; i < ii; i++) {
          var seriesData = matchingDataPoints[i].seriesData;
          var dataPoint = matchingDataPoints[i].dataPoint;
          var delta = matchingDataPoints[i].delta;
          this.plot.highlight(seriesData, dataPoint);

	if (seriesData['label'].includes('Rollback')) {
	     rlbc = calculate(dataPoint[1]);
	}
	else rlbc = "";

          var data = {
            series: seriesData,
            datapoint: dataPoint,
            delta: delta,
	    rlbc: rlbc
          };
          if (options.transformDataPointData){
            data = options.transformDataPointData(data);
          }
	  var text = this.dataPointTemplate(data);
		  childrenTexts.push(text);
		  // Convert time and format it
		  var realTimestamp = convertToRealTime(dataPoint[0]);
		  var xDateFormat = $.cookie('timeformat') == '12' ? "%d/%m/%Y  %I:%M:%S%p" : "%d/%m/%Y  %H:%M:%S";
                  timeArray[0] = $.plot.formatDate(realTimestamp, xDateFormat);
        }

        var tooltipText = this.tooltipTemplate({
          value : localization.key['chart.val'],
          change: localization.key['chart.change'],
          event : showEventHeader ? localization.key['chart.event'] : null,
          time: timeArray,
          body: childrenTexts.join('\n')
        });

        var $tooltip = this.findOrCreateTooltip(options.tooltipStyles);

        // If we are going to overflow outside the screen's dimensions, display it to the left instead


        var xPositionProperty = 'left';
        var yPositionProperty = 'top';
        var xPosition = position.pageX + options.tooltipOffsetX;
        var yPosition = position.pageY + options.tooltipOffsetY;
        $tooltip.html(tooltipText); // So that we can use dimensions right away, we set the content immediately
        var tooltipWidth = $tooltip.width();
        var tooltipHeight = $tooltip.height();
        var css = {
          top: 'auto',
          left: 'auto',
          right: 'auto',
          bottom: 'auto'
        };

        var pageWidth = window.innerWidth;
        var pageHeight = window.innerHeight;
        var plotWidth = $("#placeholder").width();

        css['width'] =  plotWidth <= 650 ? 'min-content' : 'auto';

        if (xPosition + tooltipWidth > plotWidth){
          xPositionProperty = 'right';
          xPosition = pageWidth - position.pageX + options.tooltipOffsetX;
        }
        if (yPosition + tooltipHeight > pageHeight){
          yPositionProperty = 'bottom';
          yPosition = pageHeight - position.pageY + options.tooltipOffsetY;
        }

        css[xPositionProperty] = xPosition;
        css[yPositionProperty] = yPosition;
        $tooltip.css(css).show();
      }
    },
    onMouseOut: function () {
      this.plot.unhighlight();
      $('#flotMultihighlightTip').hide().css({
        top: 'auto',
        left: 'auto',
        right: 'auto',
        bottom: 'auto'
      });
    }
  };

  MultiHighlightDelta.init = function (plot) {
    new MultiHighlightDeltaPlugin(plot).initialize();
  };

  // Wire up the plugin with flot
  this.jQuery.plot.plugins.push({
    init: MultiHighlightDelta.init,
    options: MultiHighlightDelta.options,
    name: 'multihighlightdelta',
    version: '0.1'
  });

  // Nothing to wire since we're injecting the plugin inside flot
  return {};

});
