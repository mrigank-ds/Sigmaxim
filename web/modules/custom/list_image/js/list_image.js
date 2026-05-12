(function ($, Drupal, drupalSettings, once) {
  'use strict';

  Drupal.behaviors.listImageBehaviours = {
    attach: function (context, settings) {

      $(once('listImageInit', '.list_image__field_wrapper', context)).each(function () {
        var $wrapper = $(this);

        function showSelectedImage() {
          var $inputs = $wrapper.find('.list_image__field_input');
          var selectedValue;

          // Get selected value
          if ($inputs.is('select')) {
            selectedValue = $inputs.val();
          } else {
            selectedValue = $inputs.filter(':checked').val();
          }

          // Hide all images first
          $wrapper.find('.list_image__wrapper img').hide();

          // If value is valid and not "_none", show matching image
          if (selectedValue && selectedValue !== '_none') {
            $wrapper.find('.list_image__' + selectedValue).show();
          }
        }

        // Run on page load (small delay for Drupal render)
        setTimeout(function () {
          showSelectedImage();
        }, 200);

        // Run on change
        $wrapper.find('.list_image__field_input').on('change', function () {
          showSelectedImage();
        });

      });
    }
  };

})(jQuery, Drupal, drupalSettings, once);
