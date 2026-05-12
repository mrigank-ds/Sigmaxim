/**
 * @file
 * JS code to integrate with GTM for Product into product list.
 */

(function ($, drupalSettings) {
  Drupal.behaviors.fieldGroupImage = {
    attach: function (context, settings) {
      if (context !== document) {
        return;
      }
      // Do not proceed further if fieldGroupImage is not available
      // in drupalSettings.
      if (drupalSettings.fieldGroupImage === undefined) {
        return;
      }
      var groupElements = $('.field-group-image');
      // Iterate over the field group elements.
      groupElements.each(function () {
        var group = $(this).attr('data-group');
        // If group name is present in the wrapper and drupalSettings.
        if (group !== null
          && drupalSettings.fieldGroupImage[group] !== undefined
          && drupalSettings.fieldGroupImage[group] !== '') {
          // Set the image markup for the group.
          $(this).prepend(drupalSettings.fieldGroupImage[group]);
        }
      });
    }
  }
})(jQuery, drupalSettings);
