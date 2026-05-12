/**
 * @file
 * Custom JS to render field group image.
 */

(function ($, drupalSettings) {
  Drupal.behaviors.listImageBehaviours = {
    attach: function (context, settings) {
      if (context !== document) {
        return;
      }
      // Show image for the selected option on page load.
      $('.list_image__field_wrapper .list_image__field_input').each(function () {
        var optionName = this.value;
        if (optionName !== '_none') {
          var parentWrapper = $(this).closest('.list_image__field_wrapper');
          parentWrapper.find('.list_image__wrapper img').hide();
          parentWrapper.find('.list_image__' + optionName).show();
        }
      });

      // Show image for the selected option on change action.
      $('.list_image__field_wrapper .list_image__field_input').on('change', function () {
        var parentWrapper = $(this).closest('.list_image__field_wrapper');
        var optionName = this.value;
        parentWrapper.find('.list_image__wrapper img').hide();
        parentWrapper.find('.list_image__' + optionName).show();
      });
    }
  }
})(jQuery, drupalSettings);
