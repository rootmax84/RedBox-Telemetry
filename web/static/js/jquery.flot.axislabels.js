/*
Axis Labels Plugin for flot.
http://github.com/markrcote/flot-axislabels

Original code is Copyright (c) 2010 Xuan Luo.
Original code was released under the GPLv3 license by Xuan Luo, September 2010.
Original code was rereleased under the MIT license by Xuan Luo, April 2012.

Permission is hereby granted, free of charge, to any person obtaining
a copy of this software and associated documentation files (the
"Software"), to deal in the Software without restriction, including
without limitation the rights to use, copy, modify, merge, publish,
distribute, sublicense, and/or sell copies of the Software, and to
permit persons to whom the Software is furnished to do so, subject to
the following conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

(function ($) {
    var options = { };

    function canvasSupported() {
        return !!document.createElement('canvas').getContext;
    }

    function canvasTextSupported() {
        if (!canvasSupported()) {
            return false;
        }
        var dummy_canvas = document.createElement('canvas');
        var context = dummy_canvas.getContext('2d');
        return typeof context.fillText == 'function';
    }

    function css3TransitionSupported() {
        var div = document.createElement('div');
        return typeof div.style.MozTransition != 'undefined'    // Gecko
            || typeof div.style.OTransition != 'undefined'      // Opera
            || typeof div.style.webkitTransition != 'undefined' // WebKit
            || typeof div.style.transition != 'undefined';
    }


    function AxisLabel(axisName, position, padding, plot, opts) {
        this.axisName = axisName;
        this.position = position;
        this.padding = padding;
        this.plot = plot;
        this.opts = opts;
        this.width = 0;
        this.height = 0;
    }

    AxisLabel.prototype.cleanup = function() {
    };


    CanvasAxisLabel.prototype = new AxisLabel();
    CanvasAxisLabel.prototype.constructor = CanvasAxisLabel;
    function CanvasAxisLabel(axisName, position, padding, plot, opts) {
        AxisLabel.prototype.constructor.call(this, axisName, position, padding,
                                             plot, opts);
    }

    CanvasAxisLabel.prototype.calculateSize = function() {
        if (!this.opts.axisLabelFontSizePixels)
            this.opts.axisLabelFontSizePixels = 14;
        if (!this.opts.axisLabelFontFamily)
            this.opts.axisLabelFontFamily = 'sans-serif';

        var textWidth = this.opts.axisLabelFontSizePixels + this.padding;
        var textHeight = this.opts.axisLabelFontSizePixels + this.padding;
        if (this.position == 'left' || this.position == 'right') {
            this.width = this.opts.axisLabelFontSizePixels + this.padding;
            this.height = 0;
        } else {
            this.width = 0;
            this.height = this.opts.axisLabelFontSizePixels + this.padding;
        }
    };

    CanvasAxisLabel.prototype.draw = function(box, context) {
        if (!this.opts.axisLabelColour)
            this.opts.axisLabelColour = 'black';
        var ctx = context || this.plot.getCanvas().getContext('2d');
        ctx.save();
        ctx.font = this.opts.axisLabelFontSizePixels + 'px ' +
            this.opts.axisLabelFontFamily;
        ctx.fillStyle = this.opts.axisLabelColour;
        var width = ctx.measureText(this.opts.axisLabel).width;
        var height = this.opts.axisLabelFontSizePixels;
        var x, y, angle = 0;
        if (this.position == 'top') {
            x = box.left + box.width/2 - width/2;
            y = box.top + height*0.72;
        } else if (this.position == 'bottom') {
            x = box.left + box.width/2 - width/2;
            y = box.top + box.height - height*0.72;
        } else if (this.position == 'left') {
            x = box.left + height*0.72;
            y = box.height/2 + box.top + width/2;
            angle = -Math.PI/2;
        } else if (this.position == 'right') {
            x = box.left + box.width - height*0.72;
            y = box.height/2 + box.top - width/2;
            angle = Math.PI/2;
        }
        ctx.translate(x, y);
        ctx.rotate(angle);
        ctx.fillText(this.opts.axisLabel, 0, 0);
        ctx.restore();
    };


    HtmlAxisLabel.prototype = new AxisLabel();
    HtmlAxisLabel.prototype.constructor = HtmlAxisLabel;
    function HtmlAxisLabel(axisName, position, padding, plot, opts) {
        AxisLabel.prototype.constructor.call(this, axisName, position,
                                             padding, plot, opts);
        this.elem = null;
    }

    HtmlAxisLabel.prototype.calculateSize = function() {
        var elem = $('<div class="axisLabels" style="position:absolute;">' +
                     this.opts.axisLabel + '</div>');
        this.plot.getPlaceholder().append(elem);
        // store height and width of label itself, for use in draw()
        this.labelWidth = elem.outerWidth(true);
        this.labelHeight = elem.outerHeight(true);
        elem.remove();

        this.width = this.height = 0;
        if (this.position == 'left' || this.position == 'right') {
            this.width = this.labelWidth + this.padding;
        } else {
            this.height = this.labelHeight + this.padding;
        }
    };

    HtmlAxisLabel.prototype.cleanup = function() {
        if (this.elem) {
            this.elem.remove();
        }
    };

    HtmlAxisLabel.prototype.draw = function(box) {
        this.plot.getPlaceholder().find('#' + this.axisName + 'Label').remove();
        this.elem = $('<div id="' + this.axisName +
                      'Label" " class="axisLabels" style="position:absolute;">'
                      + this.opts.axisLabel + '</div>');
        this.plot.getPlaceholder().append(this.elem);
        if (this.position == 'top') {
            this.elem.css('left', box.left + box.width/2 - this.labelWidth/2 +
                          'px');
            this.elem.css('top', box.top + 'px');
        } else if (this.position == 'bottom') {
            this.elem.css('left', box.left + box.width/2 - this.labelWidth/2 +
                          'px');
            this.elem.css('top', box.top + box.height - this.labelHeight +
                          'px');
        } else if (this.position == 'left') {
            this.elem.css('top', box.top + box.height/2 - this.labelHeight/2 +
                          'px');
            this.elem.css('left', box.left + 'px');
        } else if (this.position == 'right') {
            this.elem.css('top', box.top + box.height/2 - this.labelHeight/2 +
                          'px');
            this.elem.css('left', box.left + box.width - this.labelWidth +
                          'px');
        }
    };


    CssTransformAxisLabel.prototype = new HtmlAxisLabel();
    CssTransformAxisLabel.prototype.constructor = CssTransformAxisLabel;
    function CssTransformAxisLabel(axisName, position, padding, plot, opts) {
        HtmlAxisLabel.prototype.constructor.call(this, axisName, position,
                                                 padding, plot, opts);
    }

    CssTransformAxisLabel.prototype.calculateSize = function() {
        HtmlAxisLabel.prototype.calculateSize.call(this);
        this.width = this.height = 0;
        if (this.position == 'left' || this.position == 'right') {
            this.width = this.labelHeight + this.padding;
        } else {
            this.height = this.labelHeight + this.padding;
        }
    };

    CssTransformAxisLabel.prototype.transforms = function(degrees, x, y) {
        var stransforms = {
            '-moz-transform': '',
            '-webkit-transform': '',
            '-o-transform': '',
            '-ms-transform': ''
        };
        if (x != 0 || y != 0) {
            var stdTranslate = ' translate(' + x + 'px, ' + y + 'px)';
            stransforms['-moz-transform'] += stdTranslate;
            stransforms['-webkit-transform'] += stdTranslate;
            stransforms['-o-transform'] += stdTranslate;
            stransforms['-ms-transform'] += stdTranslate;
        }
        if (degrees != 0) {
            var rotation = degrees / 90;
            var stdRotate = ' rotate(' + degrees + 'deg)';
            stransforms['-moz-transform'] += stdRotate;
            stransforms['-webkit-transform'] += stdRotate;
            stransforms['-o-transform'] += stdRotate;
            stransforms['-ms-transform'] += stdRotate;
        }
        var s = 'top: 0; left: 0; ';
        for (var prop in stransforms) {
            if (stransforms[prop]) {
                s += prop + ':' + stransforms[prop] + ';';
            }
        }
        s += ';';
        return s;
    };

    CssTransformAxisLabel.prototype.calculateOffsets = function(box) {
        var offsets = { x: 0, y: 0, degrees: 0 };
        if (this.position == 'bottom') {
            offsets.x = box.left + box.width/2 - this.labelWidth/2;
            offsets.y = box.top + box.height - this.labelHeight + 3;
        } else if (this.position == 'top') {
            offsets.x = box.left + box.width/2 - this.labelWidth/2;
            offsets.y = box.top;
        } else if (this.position == 'left') {
            offsets.degrees = -90;
            offsets.x = box.left - this.labelWidth/2 + this.labelHeight/2 - 3;
            offsets.y = box.height/2 + box.top;
        } else if (this.position == 'right') {
            offsets.degrees = 90;
            offsets.x = box.left + box.width - this.labelWidth/2
                        - this.labelHeight/2 + 5;
            offsets.y = box.height/2 + box.top;
        }
        return offsets;
    };

    CssTransformAxisLabel.prototype.draw = function(box) {
        this.plot.getPlaceholder().find("." + this.axisName + "Label").remove();
        var offsets = this.calculateOffsets(box);
        this.elem = $('<div class="axisLabels ' + this.axisName +
                      'Label" style="position:absolute; ' +
                      'color: #000000; ' +
                      this.transforms(offsets.degrees, offsets.x, offsets.y) +
                      '">' + this.opts.axisLabel + '</div>');
        this.plot.getPlaceholder().append(this.elem);
    };


    IeTransformAxisLabel.prototype = new CssTransformAxisLabel();
    IeTransformAxisLabel.prototype.constructor = IeTransformAxisLabel;
    function IeTransformAxisLabel(axisName, position, padding, plot, opts) {
        CssTransformAxisLabel.prototype.constructor.call(this, axisName,
                                                         position, padding,
                                                         plot, opts);
        this.requiresResize = false;
    }

    IeTransformAxisLabel.prototype.transforms = function(degrees, x, y) {
        // I didn't feel like learning the crazy Matrix stuff, so this uses
        // a combination of the rotation transform and CSS positioning.
        var s = '';
        if (degrees != 0) {
            var rotation = degrees/90;
            while (rotation < 0) {
                rotation += 4;
            }
            s += ' filter: progid:DXImageTransform.Microsoft.BasicImage(rotation=' + rotation + '); ';
            // see below
            this.requiresResize = (this.position == 'right');
        }
        if (x != 0) {
            s += 'left: ' + x + 'px; ';
        }
        if (y != 0) {
            s += 'top: ' + y + 'px; ';
        }
        return s;
    };

    IeTransformAxisLabel.prototype.calculateOffsets = function(box) {
        var offsets = CssTransformAxisLabel.prototype.calculateOffsets.call(
                          this, box);
        // adjust some values to take into account differences between
        // CSS and IE rotations.
        if (this.position == 'top') {
            // FIXME: not sure why, but placing this exactly at the top causes
            // the top axis label to flip to the bottom...
            offsets.y = box.top + 1;
        } else if (this.position == 'left') {
            offsets.x = box.left;
            offsets.y = box.height/2 + box.top - this.labelWidth/2;
        } else if (this.position == 'right') {
            offsets.x = box.left + box.width - this.labelHeight;
            offsets.y = box.height/2 + box.top - this.labelWidth/2;
        }
        return offsets;
    };

    IeTransformAxisLabel.prototype.draw = function(box) {
        CssTransformAxisLabel.prototype.draw.call(this, box);
        if (this.requiresResize) {
            this.elem = this.plot.getPlaceholder().find("." + this.axisName +
                                                        "Label");
            // Since we used CSS positioning instead of transforms for
            // translating the element, and since the positioning is done
            // before any rotations, we have to reset the width and height
            // in case the browser wrapped the text (specifically for the
            // y2axis).
            this.elem.css('width', this.labelWidth);
            this.elem.css('height', this.labelHeight);
        }
    };


    function init(plot) {
        var labels = {};
        var defaultPadding = 2;

        // This hook runs once during setupGrid(), after tick labels have been
        // measured and before Flot allocates axis boxes. No recursive draw().
        plot.hooks.processAxisLabels.push(function (plot, axes) {
            var active = {};
            $.each(axes, function (_, axis) {
                var name = axis.direction + (axis.n === 1 ? '' : axis.n) + 'axis';
                var opts = axis.options;
                if (!axis.show || !opts || !opts.axisLabel) return;
                active[name] = true;
                var renderer;
                if (opts.axisLabelUseHtml || (!css3TransitionSupported() && !canvasTextSupported() && !opts.axisLabelUseCanvas)) {
                    renderer = HtmlAxisLabel;
                } else if (opts.axisLabelUseCanvas || !css3TransitionSupported()) {
                    renderer = CanvasAxisLabel;
                } else {
                    renderer = CssTransformAxisLabel;
                }
                var padding = opts.axisLabelPadding === undefined ? defaultPadding : opts.axisLabelPadding;
                var label = labels[name];
                if (!label || label.constructor !== renderer || label.position !== axis.position ||
                    label.padding !== padding || label.opts !== opts) {
                    if (label) label.cleanup();
                    label = new renderer(name, axis.position, padding, plot, opts);
                    labels[name] = label;
                }
                label.calculateSize();
                // axis.labelWidth/Height are freshly computed tick measurements.
                // Do not alter opts.labelWidth/Height, which persist across stream updates.
                axis.labelWidth += label.width;
                axis.labelHeight += label.height;
            });
            $.each(labels, function (name, label) {
                if (!active[name]) {
                    label.cleanup();
                    delete labels[name];
                }
            });
        });

        plot.hooks.draw.push(function (plot, ctx) {
            $.each(plot.getAxes(), function (axisName, axis) {
                var label = labels[axisName];
                if (label && axis.show && axis.box) label.draw(axis.box, ctx);
            });
        });
        plot.hooks.shutdown.push(function () {
            $.each(labels, function (_, label) { label.cleanup(); });
            labels = {};
        });
    }

    $.plot.plugins.push({
        init: init,
        options: options,
        name: 'axisLabels',
        version: '2.0'
    });
})(jQuery);
