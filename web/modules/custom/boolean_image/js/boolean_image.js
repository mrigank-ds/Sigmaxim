
(function ($, Drupal, drupalSettings, once) {
  Drupal.behaviors.booleanImageBehaviours = {
    attach: function (context, settings) {
      $(once('booleanImageInit', '.field--type-boolean', context)).each(function () {
        var $wrapper = $(this);

        function showSelectedImage() {
          var $inputs = $wrapper.find('input[type="checkbox"], input[type="radio"]');

          if (!$inputs.length) {
            console.warn('No checkbox/radio found in', $wrapper);
            return;
          }

          // hide all images first
          $wrapper.find('.boolean_image__wrapper img').hide();

          // checkboxes:
          $inputs.filter('input[type="checkbox"]').each(function () {
            if ($(this).prop('checked')) {
              $wrapper.find('.boolean_image__on').show();
            } else {
              $wrapper.find('.boolean_image__off').show();
            }
          });

          // radios:
          var $selectedRadio = $inputs.filter('input[type="radio"]:checked');
          if ($selectedRadio.length) {
            var val = $selectedRadio.val(); // e.g. "1", "0", "_none"

            if (val === '1' || val === 'on') {
              $wrapper.find('.boolean_image__on').show();
            } else if (val === '_none') {
              // show the N/A image for value="_none"
              $wrapper.find('.boolean_image__na').show();
            } else {
              $wrapper.find('.boolean_image__off').show();
            }
          }
        }

        // run on load
        showSelectedImage();

        // bind change event
        $wrapper.find('input[type="checkbox"], input[type="radio"]').on('change', showSelectedImage);
      });
    }
  };
})(jQuery, Drupal, drupalSettings, once);
