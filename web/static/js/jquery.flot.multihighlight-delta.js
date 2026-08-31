/**
 *
 * User: Patrick de Lanauze
 * Date: 2013-03-21
 * Time: 10:59 AM
 *
 * - Highlight nearest metric in tooltip
 * - performance optimizations with caching and requestAnimationFrame
 * - Ignore hidden series (based on points.show and lines.show)
 * - Compatible with hiddenGraphs plugin
 */

//Time conversion processing
function convertToRealTime(processedTime) {
  if (!window.realTimeInfo || !window.realTimeInfo.timeMapping) {
    return new Date(processedTime);
  }

  const timeMapping = window.realTimeInfo.timeMapping;
  if (!timeMapping._sortedProcessedTimes) {
    timeMapping._sortedProcessedTimes = Object.keys(timeMapping)
      .map(Number)
      .sort((a, b) => a - b);
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
  const theModule = definition();
  const hasDefine = typeof define === 'function' && define.amd;
  const hasExports = typeof module !== 'undefined' && module.exports;

  if (hasDefine) {
    define(theModule);
  } else if (hasExports) {
    module.exports = theModule;
  } else {
    (this.jQuery || this.ender || this.$ || this)[name] = theModule;
  }
})('core', function () {
  const MultiHighlightDelta = {
    options: {
      multihighlightdelta: {
        mode: 'x',
        tooltipOffsetX: 20,
        tooltipOffsetY: 20,
        tooltipTemplate: '<table class="table flot-tooltip-table"><thead><tr><th><%= time[0] %></th><th><%= value %></th><th><%= change %></th><th><%= event %></th></tr></thead><tbody><%= body %></tbody></table>',
        dataPointTemplate: '<tr style="<% if (isClosest) { %>font-weight:bold;<% } %>"><td><%= series.label %></td><td><%= datapoint[1] %></td><td><%= (delta > 0 ? "+" : "") %><%= delta %></td><td><%= rlbc %></td></tr>',
        transformDataPointData: false,
        tooltipStyles: {
          position: 'absolute',
          display: 'none',
          background: '#fff',
          'z-index': '100',
          padding: '0.4em 0.6em',
          'border-radius': '0.5em',
          'font-size': '0.8em',
          border: '1px dashed rgba(0,0,0,.4)'
        },
        delta: function (previousDataPoint, dataPoint) {
          if (!previousDataPoint) {
            return '';
          }
          const change = dataPoint[1] - previousDataPoint[1];
          return (-1 * change).toFixed(2);
        }
      }
    }
  };

  class MultiHighlightDeltaPlugin {
    constructor(plot) {
      this.plot = plot;
      this._rafId = null;
      this._pendingData = null;
    }

    static compileTemplate(str) {
      return new Function(
        'obj',
        "var p=[],print=function(){p.push.apply(p,arguments);};" +
          "with(obj){p.push('" +
          str
            .replace(/[\r\t\n]/g, ' ')
            .split('<%').join('\t')
            .replace(/((^|%>)[^\t]*)'/g, '$1\r')
            .replace(/\t=(.*?)%>/g, "',$1,'")
            .split('\t').join("');")
            .split('%>').join("p.push('")
            .split('\r').join("\\'") +
          "');}return p.join('');"
      );
    }

    initialize() {
      const ctx = this;

      const handlerProxies = {
        onPlotHover: (event, position, item) => ctx._handleHover(event, position, item),
        onMouseOut: () => ctx._handleMouseOut()
      };

      this.plot.hooks.bindEvents.push((plot) => {
        if (!plot.getOptions().multihighlightdelta) {
          return;
        }

        const options = plot.getOptions().multihighlightdelta || {};
        for (const key in MultiHighlightDelta.options.multihighlightdelta) {
          if (typeof options[key] === 'undefined') {
            options[key] = MultiHighlightDelta.options.multihighlightdelta[key];
          }
        }

        plot.getPlaceholder().on('plothover plottouchmove', handlerProxies.onPlotHover);
        plot.getPlaceholder().on('mouseout touchend', handlerProxies.onMouseOut);

        ctx.tooltipTemplate = MultiHighlightDeltaPlugin.compileTemplate(options.tooltipTemplate);
        ctx.dataPointTemplate = MultiHighlightDeltaPlugin.compileTemplate(options.dataPointTemplate);
      });

      this.plot.hooks.shutdown.push((plot) => {
        plot.getPlaceholder().off('plothover plottouchmove', handlerProxies.onPlotHover);
        plot.getPlaceholder().off('mouseout touchend', handlerProxies.onMouseOut);
      });

      return this;
    }

    findOrCreateTooltip(tooltipStyles) {
      let $tip = $('#flotMultihighlightTip');
      if ($tip.length === 0) {
        $tip = $('<div />')
          .attr('id', 'flotMultihighlightTip')
          .addClass('flot-tooltip')
          .css(tooltipStyles)
          .appendTo('body');
      }
      return $tip;
    }

    _handleHover(event, position, item) {
      if (this._rafId) {
        cancelAnimationFrame(this._rafId);
        this._rafId = null;
      }
      this._pendingData = { event, position, item };
      this._rafId = requestAnimationFrame(() => {
        this._processHover(this._pendingData.event, this._pendingData.position, this._pendingData.item);
        this._pendingData = null;
        this._rafId = null;
      });
    }

    _processHover(event, position, item) {
      const data = this.plot.getData();
      const options = this.plot.getOptions().multihighlightdelta;
      const deltaFunction = options.delta;
      const mode = options.mode || 'x';
      let index = 0;

      if (mode === 'x') {
        index = 0;
      } else if (mode === 'y') {
        index = 1;
      } else {
        throw new Error(`Mode '${mode}' is not recognized, must be x or y`);
      }

      if (!item) {
        this._handleMouseOut();
        return;
      }

      const matchingDataPoints = [];
      let showEventHeader = false;

      for (const series of data) {
        const isHidden = (series.points && series.points.show === false) &&
                         (series.lines && series.lines.show === false);
        if (isHidden) {
          continue;
        }

        const seriesData = series.data;
        for (let j = 0; j < seriesData.length; j++) {
          if (seriesData[j][index] === item.datapoint[index]) {
            matchingDataPoints.push({
              seriesData: series,
              dataPoint: seriesData[j],
              delta: deltaFunction(j > 0 ? seriesData[j - 1] : null, seriesData[j])
            });
            if (series.label && series.label.includes('Rollback')) {
              showEventHeader = true;
            }
          }
        }
      }

      if (matchingDataPoints.length === 0) {
        this._handleMouseOut();
        return;
      }

      const axes = this.plot.getAxes();
      const placeholder = this.plot.getPlaceholder();
      const offset = placeholder.offset();
      const plotOffset = this.plot.getPlotOffset();
      const canvasY = position.pageY - offset.top - plotOffset.top;

      let closestIndex = -1;
      let minDist = Infinity;
      for (let i = 0; i < matchingDataPoints.length; i++) {
        const yVal = matchingDataPoints[i].dataPoint[1];
        const pixelY = axes.yaxis.p2c(yVal);
        const dist = Math.abs(canvasY - pixelY);
        if (dist < minDist) {
          minDist = dist;
          closestIndex = i;
        }
      }

      const childrenTexts = [];
      const timeArray = [];

      for (let i = 0; i < matchingDataPoints.length; i++) {
        const seriesData = matchingDataPoints[i].seriesData;
        const dataPoint = matchingDataPoints[i].dataPoint;
        const delta = matchingDataPoints[i].delta;

        let rlbc = '';
        if (seriesData.label && seriesData.label.includes('Rollback')) {
          rlbc = calculate(dataPoint[1]);
        }

        let templateData = {
          series: seriesData,
          datapoint: dataPoint,
          delta,
          rlbc,
          isClosest: (i === closestIndex) && (matchingDataPoints.length > 1)
        };

        if (options.transformDataPointData) {
          templateData = options.transformDataPointData(templateData);
        }

        const text = this.dataPointTemplate(templateData);
        childrenTexts.push(text);

        if (i === 0) {
          const realTimestamp = convertToRealTime(dataPoint[0]);
          const xDateFormat = Cookies.get('timeformat') == '12'
            ? '%d/%m/%Y  %I:%M:%S%p'
            : '%d/%m/%Y  %H:%M:%S';
          timeArray[0] = $.plot.formatDate(realTimestamp, xDateFormat);
        }
      }

      const tooltipText = this.tooltipTemplate({
        value: localization.key['chart.val'],
        change: localization.key['chart.change'],
        event: showEventHeader ? localization.key['chart.event'] : null,
        time: timeArray,
        body: childrenTexts.join('\n')
      });

      const $tooltip = this.findOrCreateTooltip(options.tooltipStyles);

      let xPositionProperty = 'left';
      let yPositionProperty = 'top';
      let xPosition = position.pageX + options.tooltipOffsetX;
      let yPosition = position.pageY + options.tooltipOffsetY;

      $tooltip.html(tooltipText);
      const tooltipWidth = $tooltip.width();
      const tooltipHeight = $tooltip.height();
      const css = {
        top: 'auto',
        left: 'auto',
        right: 'auto',
        bottom: 'auto'
      };

      const pageWidth = window.innerWidth;
      const pageHeight = window.innerHeight;
      const plotWidth = $('#placeholder').width();

      css.width = plotWidth <= 650 ? 'min-content' : 'auto';

      if (xPosition + tooltipWidth > plotWidth) {
        xPositionProperty = 'right';
        xPosition = pageWidth - position.pageX + options.tooltipOffsetX;
      }
      if (yPosition + tooltipHeight > pageHeight) {
        yPositionProperty = 'bottom';
        yPosition = pageHeight - position.pageY + options.tooltipOffsetY;
      }

      css[xPositionProperty] = xPosition;
      css[yPositionProperty] = yPosition;
      $tooltip.css(css).show();
    }

    _handleMouseOut() {
      if (this._rafId) {
        cancelAnimationFrame(this._rafId);
        this._rafId = null;
        this._pendingData = null;
      }

      $('#flotMultihighlightTip').hide().css({
        top: 'auto',
        left: 'auto',
        right: 'auto',
        bottom: 'auto'
      });
    }
  }

  MultiHighlightDelta.init = function (plot) {
    new MultiHighlightDeltaPlugin(plot).initialize();
  };

  this.jQuery.plot.plugins.push({
    init: MultiHighlightDelta.init,
    options: MultiHighlightDelta.options,
    name: 'multihighlightdelta',
    version: '0.6'
  });

  return {};
});
