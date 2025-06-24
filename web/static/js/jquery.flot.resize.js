(function ($) {
  // Plugin options placeholder
  let options = {};

  // Store ResizeObservers and timers for each plot instance using WeakMap
  let observers = new WeakMap();
  let resizeTimers = new WeakMap();

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

        // Get the plot instance from the placeholder
        const plot = placeholder.data("plot");
        if (!plot) return;

        // Clear previous timer if exists
        const existingTimer = resizeTimers.get(plot);
        if (existingTimer) {
          clearTimeout(existingTimer);
        }

        // Set new timer with delay
        const timer = setTimeout(() => {
          // Recalculate and redraw the plot
          plot.resize();
          plot.setupGrid();
          plot.draw();
        }, 100);

        resizeTimers.set(plot, timer);
      }
    }

    //Bind resize observer to the plot's placeholder element
    function bindEvents(plot, eventHolder) {
      const placeholder = plot.getPlaceholder()[0];

      if (placeholder) {
        // Store plot reference in placeholder for access in onResize
        $(placeholder).data("plot", plot);

        const observer = new ResizeObserver(onResize);
        observer.observe(placeholder);
        observers.set(plot, observer); // Store observer for future cleanup
      }
    }

    // Cleanup ResizeObserver and timer on plot shutdown
    function shutdown(plot, eventHolder) {
      // Cleanup observer
      const observer = observers.get(plot);
      if (observer) {
        observer.disconnect(); // Stop observing
        observers.delete(plot); // Remove from WeakMap
      }

      // Cleanup timer
      const timer = resizeTimers.get(plot);
      if (timer) {
        clearTimeout(timer);
        resizeTimers.delete(plot);
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
    version: "2.1"
  });

})(jQuery);