(function ($, Drupal, drupalSettings, once) {
  'use strict';

  Drupal.behaviors.resetButtonField = {
    attach: function (context, settings) {
      once('reset-button-init', '.reset-button', context).forEach(function (button) {
        var $button = $(button);
        var fieldsToReset = $button.attr('data-fields-to-reset');
        var confirmation = $button.attr('data-confirmation') === 'true';
        var defaultValuesJson = $button.attr('data-default-values');
        
        var defaultValues = {};
        try {
          if (defaultValuesJson) {
            defaultValues = JSON.parse(defaultValuesJson);
          }
        } catch (e) {
          console.warn('Failed to parse default values');
        }

        if (!fieldsToReset) {
          return;
        }

        var fieldsArray = fieldsToReset.split(',').map(function(f) {
          return f.trim();
        }).filter(function(f) {
          return f.length > 0;
        });

        $button.on('click', function (e) {
          e.preventDefault();
          e.stopPropagation();

          if (confirmation && !confirm('Are you sure you want to reset the selected fields?')) {
            return false;
          }

          fieldsArray.forEach(function (fieldName) {
            resetField(fieldName, defaultValues[fieldName]);
          });

          return false;
        });
      });
    }
  };

  function resetField(fieldName, defaultValue) {
    var selectors = [
      '[name*="[' + fieldName + ']"]',
      '[data-field-name="' + fieldName + '"]',
      '[id*="edit-' + fieldName.replace(/_/g, '-') + '"]',
      '.field--name-' + fieldName.replace(/_/g, '-') + ' input',
      '.field--name-' + fieldName.replace(/_/g, '-') + ' textarea',
      '.field--name-' + fieldName.replace(/_/g, '-') + ' select'
    ];

    var found = false;
    
    selectors.forEach(function(selector) {
      var $elements = $(selector);
      if ($elements.length > 0) {
        found = true;
        $elements.each(function() {
          var $el = $(this);
          var tagName = $el.prop('tagName');
          var type = $el.attr('type');

          if (!tagName) return;
          tagName = tagName.toLowerCase();

          if (tagName === 'input' && (type === 'text' || type === 'number' || type === 'email' || type === 'tel')) {
            $el.val(defaultValue || '').trigger('change');
          }
          else if (tagName === 'textarea') {
            $el.val(defaultValue || '').trigger('change');
          }
          else if (tagName === 'input' && (type === 'checkbox' || type === 'radio')) {
            $el.prop('checked', false).trigger('change');
          }
          else if (tagName === 'select') {
            if (defaultValue !== undefined) {
              $el.val(defaultValue).trigger('change');
            } else {
              $el.prop('selectedIndex', 0).trigger('change');
            }
          }
          else if (tagName === 'input' && type === 'hidden') {
            $el.val(defaultValue || '').trigger('change');
          }
        });
      }
    });

    if (!found) {
      console.warn('Field not found: ' + fieldName);
    }
  }

})(jQuery, Drupal, drupalSettings, once);