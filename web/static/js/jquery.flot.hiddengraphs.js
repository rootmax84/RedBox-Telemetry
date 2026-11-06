/*
 * Hidden graphs for Flot - streamlined for progressive rendering and stream updates.
 * Based on the original Mozilla Public License 2.0 plug-in.
 * Keeps each plot's state separate; legend clicks never reprocess the dataset.
 */
(function ($) {
    'use strict';
    function init(plot) {
        var hidden = Object.create(null);
        var ranges = typeof WeakMap !== 'undefined' ? new WeakMap() : null;
        var legendHost = null;
        var ownAxisLimits = Object.create(null);
        var initializedDefaults = false;

        function axisNumber(series) {
            return series.yaxis && series.yaxis.n || 1;
        }

        function currentSeries() { return plot.getData(); }

        function isHidden(label) {
            return Object.prototype.hasOwnProperty.call(hidden, String(label));
        }

        function setVisibility(series) {
            var key = String(series.label);
            var previous = hidden[key];
            if (previous) {
                series._hiddenByLegend = true;
                series.lines.show = false;
                series.points.show = false;
                series.bars.show = false;
                series.color = '#fff';
            } else {
                series._hiddenByLegend = false;
            }
        }

        function getRange(series) {
            var dp = series.datapoints;
            if (!dp || !dp.pointsize) return { min: Infinity, max: -Infinity };
            var source = dp.points;
            if (ranges && ranges.has(source)) return ranges.get(source);
            var result = { min: Infinity, max: -Infinity };
            var ps = dp.pointsize;
            for (var j = 0; j < source.length; j += ps) {
                if (source[j] === null) continue;
                var y = source[j + 1];
                if (typeof y !== 'number' || !isFinite(y)) continue;
                if (y < result.min) result.min = y;
                if (y > result.max) result.max = y;
            }
            if (ranges) ranges.set(source, result);
            return result;
        }

        function updateAxesFromVisibleSeries() {
            var byAxis = Object.create(null);
            currentSeries().forEach(function (series) {
                if (series._hiddenByLegend) return;
                var n = axisNumber(series);
                if (!byAxis[n]) byAxis[n] = { min: Infinity, max: -Infinity };
                var r = getRange(series);
                byAxis[n].min = Math.min(byAxis[n].min, r.min);
                byAxis[n].max = Math.max(byAxis[n].max, r.max);
            });
            plot.getYAxes().forEach(function (axis) {
                if (!axis) return;
                var r = byAxis[axis.n];
                // Set explicit bounds only for the click, without setData().
                // A future setData() releases these bounds to Flot autoscaling.
                if (r && isFinite(r.min) && isFinite(r.max)) {
                    var min = Math.min(0, r.min);
                    var max = Math.max(0, r.max);
                    var tolerance = axis.options.smallNegativeThreshold;
                    axis.options.min = (typeof tolerance === 'number' &&
                        min < 0 && min >= -tolerance) ? 0 :
                        (min < 0 ? min * 1.05 : min);
                    axis.options.max = max > 0 ? max * 1.05 : max;
                } else {
                    axis.options.min = null;
                    axis.options.max = null;
                }
                ownAxisLimits[axis.n] = true;
            });
        }

        function toggle(label) {
            var series = currentSeries().filter(function (s) { return s.label === label; });
            if (!series.length) return;
            if (isHidden(label)) {
                delete hidden[String(label)];
                series.forEach(function (s) {
                    var original = s._legendOriginal || {};
                    s.lines.show = original.lines;
                    s.points.show = original.points;
                    s.bars.show = original.bars;
                    s.color = original.color;
                    s._hiddenByLegend = false;
                });
            } else {
                hidden[String(label)] = true;
                series.forEach(function (s) {
                    s._legendOriginal = {
                        lines: s.lines.show, points: s.points.show,
                        bars: s.bars.show, color: s.color
                    };
                    setVisibility(s);
                });
            }
            updateAxesFromVisibleSeries();
            plot.setupGrid();
            plot.draw();
        }

        function resolveLegendHost(opts) {
            var host = opts.legend.container ? $(opts.legend.container) : plot.getPlaceholder();
            if (!host.length) return;
            if (legendHost && legendHost[0] === host[0]) return;
            if (legendHost) legendHost.off('.flotHiddenGraphs');
            legendHost = host;
            host.on('click.flotHiddenGraphs', '.graphlabel', function (event) {
                event.preventDefault();
                // Labels are resolved by the index placed by labelFormatter,
                // not by parent().text() (which can contain HTML or markup).
                var idx = +$(this).attr('data-flot-series-index');
                var series = currentSeries()[idx];
                if (series) toggle(series.label);
            });
            host.on('mouseenter.flotHiddenGraphs', '.graphlabel', function () {
                this.style.cursor = 'pointer';
            });
        }

        plot.hooks.processOptions.push(function (plot, opts) {
            if (!opts.legend.hideable) return;
            if (!initializedDefaults) {
                (opts.legend.hidden || []).forEach(function (label) { hidden[String(label)] = true; });
                initializedDefaults = true;
            }
            var originalFormatter = opts.legend.labelFormatter;
            opts.legend.labelFormatter = function (label, series) {
                var current = currentSeries();
                var idx = current.indexOf(series);
                var display = originalFormatter ? originalFormatter(label, series) : label;
                return '<span class="graphlabel" data-flot-series-index="' + idx + '">' + display + '</span>';
            };
        });

        // Before setData() starts processing a new stream snapshot, release
        // click-owned limits. The native Flot autoscale uses the new data.
        plot.hooks.processRawData.push(function (plot, series) {
            var n = axisNumber(series);
            if (ownAxisLimits[n]) {
                series.yaxis.options.min = null;
                series.yaxis.options.max = null;
                delete ownAxisLimits[n];
            }
            // Preserve the series visibility across stream setData().
            // processRawData is early enough not to invalidate datapoint format.
            if (isHidden(series.label)) {
                series._legendOriginal = {
                    lines: series.lines.show, points: series.points.show,
                    bars: series.bars.show, color: series.color
                };
                // Apply visibility after first-pass point formatting so
                // filled-line datapoints retain their expected stride.
                series._hiddenByLegend = true;
            }
        });
        plot.hooks.processDatapoints.push(function (plot, series) {
            if (isHidden(series.label)) setVisibility(series);
        });

        plot.hooks.bindEvents.push(function () {
            resolveLegendHost(plot.getOptions());
        });
        // The legend may be moved to a new container when doPlot recreates a plot.
        plot.hooks.draw.push(function () {
            resolveLegendHost(plot.getOptions());
        });
        plot.hooks.shutdown.push(function () {
            if (legendHost) legendHost.off('.flotHiddenGraphs');
            legendHost = null;
            ranges = null;
        });
    }
    $.plot.plugins.push({ init: init, options: {}, name: 'hiddenGraphs', version: '1.1' });
})(jQuery);
