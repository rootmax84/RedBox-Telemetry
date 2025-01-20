/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this file,
 * You can obtain one at http://mozilla.org/MPL/2.0/. */

/*
 * Plugin to hide series in flot graphs.
 *
 * To activate, set legend.hideable to true in the flot options object.
 * To hide one or more series by default, set legend.hidden to an array of
 * label strings.
 *
 * At the moment, this only works with line and point graphs.
 *
 * Example:
 *
 *     var plotdata = [
 *         {
 *             data: [[1, 1], [2, 1], [3, 3], [4, 2], [5, 5]],
 *             label: "graph 1"
 *         },
 *         {
 *             data: [[1, 0], [2, 1], [3, 0], [4, 4], [5, 3]],
 *             label: "graph 2"
 *         }
 *     ];
 *
 *     plot = $.plot($("#placeholder"), plotdata, {
 *        series: {
 *             points: { show: true },
 *             lines: { show: true }
 *         },
 *         legend: {
 *             hideable: true,
 *             hidden: ["graph 1", "graph 2"]
 *         }
 *     });
 *
 */
(function ($) {
    var options = { }; // Plugin options
    var drawnOnce = false; // Flag to track if the plot has been drawn at least once
    var hiddenSeries = {}; // Object to store the state of hidden series
    var isUpdating = false; // Flag to prevent recursive updates

    function init(plot) {
        // Function to find a series by its label
        function findPlotSeries(label) {
            var plotdata = plot.getData();
            for (var i = 0; i < plotdata.length; i++) {
                if (plotdata[i].label == label) {
                    return plotdata[i];
                }
            }
            return null;
        }

        // Function to calculate the minimum and maximum Y values among visible series
        function calculateYRange(plotData) {
            var minY = Infinity;
            var maxY = -Infinity;

            for (var i = 0; i < plotData.length; i++) {
                if (!hiddenSeries[plotData[i].label]) { // Ignore hidden series
                    var seriesData = plotData[i].data;
                    for (var j = 0; j < seriesData.length; j++) {
                        if (seriesData[j][1] < minY) {
                            minY = seriesData[j][1];
                        }
                        if (seriesData[j][1] > maxY) {
                            maxY = seriesData[j][1];
                        }
                    }
                }
            }

            // Ensure that 0 is always included in the Y-axis range
            if (minY > 0) minY = 0; // If all values are positive, set minY to 0
            if (maxY < 0) maxY = 0; // If all values are negative, set maxY to 0

            return { min: minY, max: maxY };
        }

        // Function to update the Y-axis scale based on visible series
        function updateYAxisScale(plot) {
            if (isUpdating) return; // Prevent recursive updates
            isUpdating = true;

            var plotData = plot.getData();
            var yRange = calculateYRange(plotData);

            // If all series are hidden, keep the default scale
            if (yRange.min === Infinity || yRange.max === -Infinity) {
                yRange.min = null;
                yRange.max = null;
            } else {
                // Add a 5% margin to the minimum and maximum Y values
                yRange.min = yRange.min * 1.05;
                yRange.max = yRange.max * 1.05;
            }

            // Update the Y-axis scale
            var yaxis = plot.getYAxes()[0];
            if (yaxis) {
                yaxis.options.min = yRange.min; // Set the new minimum value with margin
                yaxis.options.max = yRange.max; // Set the new maximum value with margin
                plot.setupGrid(); // Recalculate the grid
                plot.draw(); // Redraw the plot
            }

            isUpdating = false; // Reset the flag
        }

        // Function to handle legend item clicks
        function plotLabelClicked(label) {
            var series = findPlotSeries(label);
            if (!series) {
                return;
            }

            // Get the current Y range before toggling the series
            var plotData = plot.getData();
            var currentYRange = calculateYRange(plotData);

            // Toggle the series visibility
            if (hiddenSeries[label]) {
                // If the series was hidden, restore its original state
                series.points.show = hiddenSeries[label].points;
                series.lines.show = hiddenSeries[label].lines;
                series.color = hiddenSeries[label].color; // Restore the original color
                delete hiddenSeries[label]; // Remove from hidden series
            } else {
                // If the series was visible, hide it and save its original state
                hiddenSeries[label] = {
                    points: series.points.show,
                    lines: series.lines.show,
                    color: series.color
                };
                series.points.show = false;
                series.lines.show = false;
                series.color = "#fff"; // Make the color transparent
            }

            // Update the plot data and redraw
            plot.setData(plot.getData());
            plot.setupGrid();
            plot.draw();

            // Check if the toggled series affects the Y range
            var newYRange = calculateYRange(plot.getData());
            if (newYRange.min !== currentYRange.min || newYRange.max !== currentYRange.max) {
                // If the Y range has changed, recalculate the Y-axis scale
                updateYAxisScale(plot);
            }
        }

        // Function to add click handlers to legend items
        function plotLabelHandlers(plot, options) {
            $(".graphlabel")
                .mouseenter(function() {
                    $(this).css({
                        "cursor": "pointer",
                        "color": "#000"
                    });
                })
                .mouseleave(function() {
                    $(this).css({
                        "cursor": "default",
                        "color": ""
                    });
                })
                .unbind("click")
                .click(function() {
                    plotLabelClicked($(this).parent().text());
                });

            // On first draw, hide series specified in options.legend.hidden
            if (!drawnOnce) {
                drawnOnce = true;
                if (options.legend.hidden) {
                    for (var i = 0; i < options.legend.hidden.length; i++) {
                        plotLabelClicked(options.legend.hidden[i]);
                    }
                }
            }
        }

        // Function to check and process plugin options
        function checkOptions(plot, options) {
            if (!options.legend.hideable) {
                return;
            }

            // Format legend labels to make them clickable
            options.legend.labelFormatter = function(label, series) {
                return '<span class="graphlabel">' + label + '</span>';
            };

            // Restore the state of hidden series on each data processing
            plot.hooks.processDatapoints.push(function(plot, series, datapoints) {
                if (hiddenSeries[series.label]) {
                    series.points.show = false;
                    series.lines.show = false;
                    series.color = "#fff";
                }
            });

            // Initialize legend item click handlers on each draw
            plot.hooks.draw.push(function(plot, ctx) {
                plotLabelHandlers(plot, options);
            });

            // Recalculate Y-axis scale when the X-axis range changes (e.g., zoom or pan)
            plot.hooks.draw.push(function(plot, ctx) {
                var xaxis = plot.getXAxes()[0];
                if (xaxis) {
                    // Store the current X-axis range
                    if (!plot.__lastXRange) {
                        plot.__lastXRange = { min: xaxis.min, max: xaxis.max };
                    } else if (
                        plot.__lastXRange.min !== xaxis.min ||
                        plot.__lastXRange.max !== xaxis.max
                    ) {
                        // If the X-axis range has changed, recalculate the Y-axis scale
                        updateYAxisScale(plot);
                        plot.__lastXRange = { min: xaxis.min, max: xaxis.max };
                    }
                }
            });
        }

        // Add the checkOptions function to the processOptions hook
        plot.hooks.processOptions.push(checkOptions);

        // Function to hide datapoints if both points and lines are hidden
        function hideDatapointsIfNecessary(plot, s, datapoints) {
            if (!plot.getOptions().legend.hideable) {
                return;
            }

            // Hide datapoints if both points and lines are hidden
            if (!s.points.show && !s.lines.show) {
                s.datapoints.format = [null, null];
            }
        }

        // Add the hideDatapointsIfNecessary function to the processDatapoints hook
        plot.hooks.processDatapoints.push(hideDatapointsIfNecessary);
    }

    // Register the plugin
    $.plot.plugins.push({
        init: init,
        options: options,
        name: 'hiddenGraphs',
        version: '1.0'
    });

})(jQuery);
