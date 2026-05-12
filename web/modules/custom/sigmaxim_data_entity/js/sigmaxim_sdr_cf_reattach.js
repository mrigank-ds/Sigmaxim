(function ($, Drupal, once) {
  'use strict';

  /**
   * Re-attaches Drupal States after widget AJAX replaces the dependee select.
   *
   * SimpleDataReferenceWidget replaces its inner wrapper div via AJAX whenever
   * a drill-down select changes. Drupal's states.js binds change handlers
   * directly to dependee elements at initialization time; once those elements
   * are swapped out of the DOM the bindings are gone and conditional visibility
   * rules stop working.
   *
   * This behavior listens for jQuery ajaxComplete on any form that contains
   * the widget and, after each AJAX response, removes the once() markers from
   * dependent fields and re-runs Drupal.behaviors.states.attach() so that
   * fresh bindings are created against the newly inserted select elements.
   */
  let formCounter = 0;

  Drupal.behaviors.sigmaximSdrCfReattach = {
    attach(context) {
      once('sigmaxim-sdr-cf-reattach', 'form', context).forEach(function (form) {
        // Only set up the listener when the widget is actually present.
        if (!form.querySelector('.field--widget-sigmaxim-simple-data-reference')) {
          return;
        }

        // Use a unique namespace per form so multiple forms on a page do not
        // inadvertently remove each other's listeners.
        const ns = '.sigmaximSdrCf' + (++formCounter);

        $(document).on('ajaxComplete' + ns, function () {
          // If the form has been removed from the DOM, clean up and stop.
          if (!form.isConnected) {
            $(document).off('ajaxComplete' + ns);
            return;
          }

          const stateEls = form.querySelectorAll('[data-drupal-states]');
          if (!stateEls.length) {
            return;
          }

          // Remove the once() markers so Drupal.behaviors.states.attach()
          // re-processes these elements and re-binds to the new selects that
          // were inserted by the widget AJAX.
          once.remove('states', stateEls);
          Drupal.behaviors.states.attach(form, drupalSettings);
        });
      });
    },
  };

})(jQuery, Drupal, once);
