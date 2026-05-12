/**
 * @file
 * JavaScript behaviors for Monarch Data Entity widgets.
 */

(function ($, Drupal, drupalSettings) {

  'use strict';

  /**
   * Result table update logic.
   */
  Drupal.behaviors.monarchDataEntityWidgetResultTable = {
    attach: function (context) {
      // Find all results containers.
      var elements = once('monarch-results', '.sigmaxim-fields-data-reference-results', context);
      $(elements).each(function () {
        var $results = $(this);
        var $wrapper = $results.parents('.js-form-item, fieldset').first();
        if ($wrapper.length === 0) {
            $wrapper = $results.parent();
        }
        var fieldName = $results.data('field-name');
        var bundle = $results.data('bundle');
        var entityType = $results.data('entity-type');

        // Listen for change events on the entire wrapper.
        // addEventListener to ensure we catch events dispatched by view-widget-notifier.js
        if ($wrapper.length > 0) {
            $wrapper[0].addEventListener('change', function(e) {
                if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT') {
                    //console.log('Change detected in widget:', e.target.name, e.target.value);
                    monarchDataEntityUpdateResultTable($results, fieldName, bundle, entityType);
                }
            }, true);
        }

        // Initial load.
        monarchDataEntityUpdateResultTable($results, fieldName, bundle, entityType);
      });
    }
  };

  /**
   * Update the result table via AJAX.
   */
  function monarchDataEntityUpdateResultTable($results, fieldName, bundle, entityType) {
    // The main container includes the hidden input and the results div.
    var $wrapper = $results.parents('.js-form-item, fieldset').first();
    if ($wrapper.length === 0) {
        $wrapper = $results.parent();
    }

    var values = {};

    $wrapper.find('input, select').each(function() {
        var name = $(this).attr('name');
        if (name) {
            var matches = name.match(/\[([^\]]+)\]$/);
            var key = matches ? matches[1] : name;
            values[key] = $(this).val();
        }
    });

    //console.log('Values collected for AJAX:', values);

    if (!values['target_id']) {
        $results.empty();
        return;
    }

    $.ajax({
      url: Drupal.url('monarch-data-entity/json-callback'),
      type: 'POST',
      dataType: 'json',
      data: {
        callback: 'monarch_data_entity_json_data_reference_get_result_table',
        values: {
          field_name: fieldName,
          bundle: bundle,
          entity_type: entityType,
          values: values
        }
      },
      success: function(data) {
        if (data.success && data.data) {
          $results.empty();
          if (data.data.length > 0) {
            var $table = $('<table class="sigmaxim-results-table">');
            var $tbody = $('<tbody>');

            $.each(data.data, function(i, item) {
              var $row = $('<tr>');
              $row.append($('<td>').text(item.column));

              var val = item.value;
              var ext = val.toString().split('.').pop().toLowerCase();
              if (['gif', 'png', 'jpg', 'jpeg'].indexOf(ext) !== -1) {
                $row.append($('<td>').html('<img style="max-width: 100px;" src="/sites/default/files/productimages/' + val + '">'));
              } else {
                $row.append($('<td>').text(val));
              }

              $tbody.append($row);
            });

            $table.append($tbody);
            $results.append($table);
          }
        }
      }
    });
  }

})(jQuery, Drupal, drupalSettings);
