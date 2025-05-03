(function ($) {
  // Plugin options placeholder (not used in this case)
  var options = {};

  // Store ResizeObservers for each plot instance using WeakMap
  var observers = new WeakMap();

  function init(plot) {
    /**
     * ResizeObserver callback
     * Triggered when the plot container (placeholder) changes size
     */
    function onResize(entries) {
      for (let entry of entries) {
        const placeholder = $(entry.target);

        // Skip if the element has no size (e.g., hidden)
        if (placeholder.width() === 0 || placeholder.height() === 0) return;

        // Recalculate and redraw the plot
        plot.resize();
        plot.setupGrid();
        plot.draw();
      }
    }

    //Bind resize observer to the plot's placeholder element
    function bindEvents(plot, eventHolder) {
      const placeholder = plot.getPlaceholder()[0];

      if (placeholder) {
        const observer = new ResizeObserver(onResize);
        observer.observe(placeholder);
        observers.set(plot, observer); // Store observer for future cleanup
      }
    }

    //Cleanup ResizeObserver on plot shutdown
    function shutdown(plot, eventHolder) {
      const observer = observers.get(plot);
      if (observer) {
        observer.disconnect(); // Stop observing
        observers.delete(plot); // Remove from WeakMap
      }
    }

    // Register hooks with Flot
    plot.hooks.bindEvents.push(bindEvents);
    plot.hooks.shutdown.push(shutdown);
  }

  // Register plugin with Flot
  $.plot.plugins.push({
    init: init,
    options: options,
    name: "resize-observer",
    version: "2.0"
  });

})(jQuery);