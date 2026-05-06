/**
 * @file
 * Custom JS to render upload image link on entity display edit form.
 */

(function ($, drupalSettings) {
  Drupal.behaviors.fieldGroupImageDisplayForm = {
    attach: function (context, settings) {
      if (context !== document) {
        return;
      }
      // Do not proceed further if fieldGroupImage is not available
      // in drupalSettings.
      if (drupalSettings.path === undefined
        || drupalSettings.path.currentPath === undefined
        || drupalSettings.fieldUIRowsData === undefined) {
        return;
      }
      var currentPath = drupalSettings.path.currentPath;
      // Iterate over the fields.
      Object.entries(drupalSettings.fieldUIRowsData).forEach(([key, field]) => {
        // Check if field is field group or not.
        if (field.rowHandler !== 'group') {
          return;
        }
        var imageUploadFormUrl = currentPath + '/' + field.name + '/upload-image';
        imageUploadFormUrl = '<a href="/' + imageUploadFormUrl + '">upload image</a>';

        // Add image upload form url in field group edit settings wrapper.
        $('#' + key).find('.field-plugin-settings-edit-wrapper').append(imageUploadFormUrl);
      });
    }
  }
})(jQuery, drupalSettings);
