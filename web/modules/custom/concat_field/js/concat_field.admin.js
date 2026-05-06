(function ($, Drupal) {
  'use strict';

  // ------------------------------------------------------------------
  // Supported string functions:
  //   strleft(value, n)        → first n characters
  //   strright(value, n)       → last n characters
  //   substr(value, start, len)→ substring (0-based start)
  //   upper(value)             → UPPERCASE
  //   lower(value)             → lowercase
  //   trim(value)              → trim whitespace
  //   strlen(value)            → length of string (returns number)
  //   replace(value,find,repl) → simple string replace
  //
  // Usage in pattern:
  //   strleft([field_textinput1],2) [field_textinput2]
  //   upper([field_textinput1]) - lower([field_textinput2])
  // ------------------------------------------------------------------

  /**
   * Apply a named function to a string value with optional extra args.
   */
  function applyFunction(funcName, value, args) {
    funcName = funcName.toLowerCase().trim();

    switch (funcName) {
      case 'strleft':
        var n = parseInt(args[0], 10);
        return isNaN(n) ? value : value.substring(0, n);

      case 'strright':
        var n = parseInt(args[0], 10);
        return isNaN(n) ? value : value.substring(value.length - n);

      case 'substr':
        var start = parseInt(args[0], 10) || 0;
        if (args[1] !== undefined) {
          var len = parseInt(args[1], 10);
          return isNaN(len) ? value.substring(start) : value.substring(start, start + len);
        }
        return value.substring(start);

      case 'upper':
        return value.toUpperCase();

      case 'lower':
        return value.toLowerCase();

      case 'trim':
        return value.trim();

      case 'strlen':
        return String(value.length);

      case 'replace':
        var find = args[0] !== undefined ? args[0].trim() : '';
        var repl = args[1] !== undefined ? args[1].trim() : '';
        return value.split(find).join(repl);

      default:
        // Unknown function — return value unchanged.
        return value;
    }
  }

  /**
   * After all tokens have been resolved, evaluate any function calls
   * in the string, e.g. strleft(Hello World,2) → "He".
   *
   * Works recursively so nested calls are supported.
   */
  function evaluateFunctions(str) {
    // Match: funcname(content)
    // We iterate until no more function calls remain (handles nesting).
    var maxPasses = 10;
    var pass = 0;
    var changed = true;

    while (changed && pass < maxPasses) {
      changed = false;
      pass++;

      // Match innermost function call (no nested parens inside args).
      str = str.replace(/([a-zA-Z_]+)\(([^()]*)\)/g, function (match, funcName, argsStr) {
        // Split args on commas — first arg is the string value, rest are params.
        var parts = argsStr.split(',');
        var value = parts[0];          // the string the function operates on
        var extraArgs = parts.slice(1); // numeric or other extra args
        changed = true;
        return applyFunction(funcName, value, extraArgs);
      });
    }

    return str;
  }

  Drupal.behaviors.concatFieldAdmin = {
    attach: function (context, settings) {

      var $concatFields = $('[data-concat-pattern]');

      if ($concatFields.length === 0) {
        return;
      }

      $concatFields.each(function () {
        var $concatInput = $(this);

        // Initialise only once per element.
        if ($concatInput.data('concat-init')) {
          return;
        }
        $concatInput.data('concat-init', true);

        var pattern    = $concatInput.attr('data-concat-pattern');
        var entityType = $concatInput.attr('data-entity-type') || '';

        if (!pattern) {
          return;
        }

        // ----------------------------------------------------------------
        // Parse the pattern and collect all field machine names.
        // Supports both short [field_name] and full [entity:field_name].
        // Also handles tokens inside function calls:
        //   strleft([field_textinput1],2)
        // ----------------------------------------------------------------
        var tokenNames = [];

        // Full tokens: [prefix:field_name]
        pattern.replace(/\[([a-zA-Z0-9_]+):([a-zA-Z0-9_.\-]+)\]/g, function (match, prefix, field) {
          var name = field.split(':')[0];
          if (tokenNames.indexOf(name) === -1) tokenNames.push(name);
        });

        // Short tokens: [field_name]
        pattern.replace(/\[([a-zA-Z0-9_]+)\]/g, function (match, name) {
          if (tokenNames.indexOf(name) === -1) tokenNames.push(name);
        });

        // ----------------------------------------------------------------
        // Find the actual DOM input for a Drupal field by name.
        // ----------------------------------------------------------------
        function findFieldInput(fieldName) {
          var selectors = [
            'input[name="'    + fieldName + '[0][value]"]',
            'textarea[name="' + fieldName + '[0][value]"]',
            'input[name="'    + fieldName + '[0][target_id]"]',
            'input[name="'    + fieldName + '[0][uri]"]',
            'input[name="'    + fieldName + '[value]"]',
            'select[name="'   + fieldName + '"]',
            'input[name="'    + fieldName + '"]',
          ];

          for (var i = 0; i < selectors.length; i++) {
            var $el = $(selectors[i]);
            if ($el.length) return $el;
          }

          // Broad fallback — scan all form elements.
          var $fallback = $('input, textarea, select').filter(function () {
            var name = $(this).attr('name') || '';
            return name === fieldName || name.indexOf(fieldName + '[') === 0;
          });

          return $fallback.length ? $fallback.first() : null;
        }

        // ----------------------------------------------------------------
        // Read the plain-text value of a field element.
        // ----------------------------------------------------------------
        function readValue($el) {
          if (!$el || !$el.length) return '';
          var val = $el.val() || '';
          var tmp = document.createElement('div');
          tmp.innerHTML = val;
          return (tmp.textContent || tmp.innerText || val).replace(/\s+/g, ' ').trim();
        }

        // ----------------------------------------------------------------
        // Step 1: Replace all tokens with their live form values.
        // Step 2: Evaluate any function calls in the resulting string.
        // ----------------------------------------------------------------
        function computeValue() {
          var result = pattern;

          // Replace full tokens: [entity_type:field_name]
          result = result.replace(/\[([a-zA-Z0-9_]+):([a-zA-Z0-9_.\-]+)\]/g, function (match, prefix, fieldPath) {
            var fieldName = fieldPath.split(':')[0];
            var $el = findFieldInput(fieldName);
            return $el ? readValue($el) : '';
          });

          // Replace short tokens: [field_name]
          result = result.replace(/\[([a-zA-Z0-9_]+)\]/g, function (match, fieldName) {
            var $el = findFieldInput(fieldName);
            return $el ? readValue($el) : '';
          });

          // Evaluate function calls like strleft(value,2).
          result = evaluateFunctions(result);

          // Normalise whitespace.
          result = result.replace(/\s+/g, ' ').trim();

          $concatInput.val(result);
        }

        // ----------------------------------------------------------------
        // Bind listeners directly to the relevant field elements.
        // ----------------------------------------------------------------
        tokenNames.forEach(function (fieldName) {
          var $el = findFieldInput(fieldName);
          if ($el && $el.length) {
            $el.on('input.concatField keyup.concatField change.concatField', function () {
              computeValue();
            });
          } else {
            // Fallback delegated listener if field not yet in DOM.
            $(document).on(
              'input.concatField keyup.concatField change.concatField',
              'input, textarea, select',
              function () {
                var name = $(this).attr('name') || '';
                if (name === fieldName || name.indexOf(fieldName + '[') === 0) {
                  computeValue();
                }
              }
            );
          }
        });

        // Populate on page load.
        computeValue();
      });
    }
  };

})(jQuery, Drupal);
