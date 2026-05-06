(function ($) {
  const DEBOUNCE_TIME = 50, POLL_INTERVAL = 50;

  let loaded = false;

  function debounce (func, milliseconds = DEBOUNCE_TIME) {
    let timeout = null;

    return function (...params) {
      if (milliseconds) {
        if (timeout !== null) {
          clearTimeout(timeout);
        }

        timeout = setTimeout(func, milliseconds, ...params);
      } else {
        func(...params);
      }
    };
  }

  function djb2(str) {
    var hash = 5381n;

    if (str.length > 0) {
      for (let i = 0; i < str.length; i++) {
        hash = (hash * 33n + BigInt(str.charCodeAt(i)));
      }
    }

    return (hash % 0xFFFFFFFFFFFFFFFFn).toString(16);
  }

  window.addEventListener('load', function () {
    loaded = true;
  });

  function checkFormChange (form) {
    if (!loaded || $.active > 0) {
      return;
    }

    forEachGlobalElement((element, inclusions) => {
      let newHash = getFormDataHash(form, inclusions);

      if (!element.dataset.formDataHash) {
        element.dataset.formDataHash = newHash;
      } else if (element.dataset.formDataHash !== newHash) {
        element.dataset.formDataHash = newHash;

        checkFormChange.debounced[element.id] = checkFormChange.debounced[element.id] || debounce(function () {
          element.dispatchEvent(new Event('change'));
        });

        checkFormChange.debounced[element.id]();
      }
    });
  }

  checkFormChange.debounced = {};

  function forEachGlobalElement(func) {
    if (drupalSettings && drupalSettings.monarch_data_entity && drupalSettings.monarch_data_entity.global_change) {
      Object.keys(drupalSettings.monarch_data_entity.global_change).forEach(id => {
        let element = document.getElementById(id);

        if (element) {
          func(element, drupalSettings.monarch_data_entity.global_change[id]);
        }
      });
    }

    Array.from(document.querySelectorAll('[data-form-change-notify]')).forEach(element => {
      if (element.dataset.formChangeNotify) {
        let inclusions = element.dataset.formChangeNotify.split(',').map(v => v.trim());

        if ([
          'input',
          'select',
          'textarea'
        ].indexOf(element.tagName) >= 0) {
          func(element, inclusions);
        }

        let subelements = Array.from(element.querySelectorAll('input, select, textarea'));

        if (subelements && subelements.length) {
          subelements.forEach(subelement => {
            func(subelement, inclusions);
          });
        }
      }
    });
  }

  function getFormDataHash(form, inclusions = null) {
    let formValues = Array.from(new FormData(form));

    if (inclusions) {
      formValues = formValues.filter(v => {
        let fieldName = v[0];
        if (['form_build_id', 'form_token', 'form_id', '_triggering_element_name', '_triggering_element_value'].indexOf(fieldName) >= 0) {
          return false;
        }

        let parts = fieldName.split(/[^a-z0-9_]+/gi).filter(Boolean);
        return inclusions[parts[0]];
      });
    }

    return djb2(JSON.stringify(formValues));
  }

  Drupal.behaviors.viewWidgetNotifier = Drupal.behaviors.viewWidgetNotifier || {
    attach: function attach (context) {
      $('form').each(function () {
        checkFormChange(this);
      });

      once('view-input-view-embed-form-changes', 'form', context).forEach(function (form) {
        let interval = setInterval(() => {
          if (!form) {
            clearInterval(interval);
          } else {
            checkFormChange(form);
          }
        }, POLL_INTERVAL);
      });
    },
  };

  $(document).on('ajaxComplete', function() {
    $('form', document).each(function() {
      checkFormChange(this);
    });
  });
})(jQuery);
