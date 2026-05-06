(function ($, Drupal) {
    Drupal.behaviors.infiniteFileSearch = {
      attach: function (context, settings) {
        // Attach an event listener to the search input in both forms.
        $('#edit-filename', context).once('infiniteFileSearch').on('input', function () {
          var $input = $(this); // Get the input element
          var $form;

          // Determine which form this input belongs to
          if ($input.closest('#views-exposed-form-fancy-file-delete-page-1').length) {
            // Form 1
            $form = $('#views-exposed-form-fancy-file-delete-page-1');
          } else if ($input.closest('#views-exposed-form-duplicate-of-fancy-file-delete-page-1').length) {
            // Form 2
            $form = $('#views-exposed-form-duplicate-of-fancy-file-delete-page-1');
          }

          // If the form is identified, trigger the AJAX call
          if ($form) {
            $.ajax({
              type: $form.attr('method'),
              url: $form.attr('action'),
              data: $form.serialize(), // Serialize the form data.
              success: function (response) {
                // Replace the view content with the new filtered content.
                $('.view-content').html($(response).find('.view-content').html());
              }
            });
          }
        });
      }
    };
  })(jQuery, Drupal);
