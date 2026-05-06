
(function ($, Drupal, drupalSettings, once) {
  'use strict';

  Drupal.behaviors.fieldExpression = {
    attach: function (context, settings) {
      
      once('field-expression', '.field--type-expression-integer, .field--type-expression-float, .field--type-expression-decimal', context).forEach(function(element) {
        var $element = $(element);
        var expressionText = $element.find('.form-item__description').text().trim();
        
        if (!expressionText) {
          expressionText = $element.data('expression') || $element.find('.expression-text').text().trim();
        }
        
        $element.data('stored-expression', expressionText);
        calculateField($element);
      });
      
      once('field-expression-listener', 'input[type="number"], input[type="text"]', context).forEach(function(input) {
        $(input).on('input change', function() {
          $('.field--type-expression-integer, .field--type-expression-float, .field--type-expression-decimal').each(function() {
            calculateField($(this));
          });
        });
      });
    }
  };

  function calculateField($field) {
    var expressionText = $field.data('stored-expression');
    
    if (!expressionText) {
      return;
    }
    
    try {
      var tokenRegex = /\[([^\]]+)\]/g;
      var tokens = [];
      var match;
      
      while ((match = tokenRegex.exec(expressionText)) !== null) {
        tokens.push(match[1]);
      }
      
      var evaluableExpression = expressionText;
      
      tokens.forEach(function(token) {
        // Support both "entity_type:field_name" and bare "field_name" formats
        var parts = token.split(':');
        var fieldName = parts.length >= 2 ? parts[1] : parts[0];
        
        // Remove any default value syntax {value}
        var defaultMatch = fieldName.match(/^([^\{]+)\{([^\}]+)\}$/);
        var defaultValue = 0;
        
        if (defaultMatch) {
          fieldName = defaultMatch[1];
          defaultValue = parseFloat(defaultMatch[2]) || 0;
        }
        
        // Find the corresponding input field
        var $input = $('#edit-' + fieldName.replace(/_/g, '-') + '-0-value');
        
        if (!$input.length) {
          $input = $('#edit-' + fieldName.replace(/_/g, '-'));
        }
        
        var fieldValue = defaultValue;
        
        if ($input.length && $input.val() !== '') {
          fieldValue = parseFloat($input.val()) || 0;
        }
        
        var fullToken = '[' + token + ']';
        evaluableExpression = evaluableExpression.replace(new RegExp(escapeRegExp(fullToken), 'g'), fieldValue);
      });
      
      // Remove any remaining curly braces (default value syntax)
      evaluableExpression = evaluableExpression.replace(/\{[^\}]*\}/g, '');
      evaluableExpression = evaluableExpression.replace(/\s+/g, '');
      
      var result = evaluateExpression(evaluableExpression);
      
      if (result !== null && !isNaN(result)) {
        $field.find('input[type="number"]').val(result);
      }
      
    } catch (error) {
      console.warn('Expression calculation error:', error);
    }
  }

  function evaluateExpression(expression) {
    try {
      expression = expression.replace(/ceil\(/g, 'Math.ceil(');
      expression = expression.replace(/floor\(/g, 'Math.floor(');
      expression = expression.replace(/round\(/g, 'Math.round(');
      expression = expression.replace(/min\(/g, 'Math.min(');
      expression = expression.replace(/max\(/g, 'Math.max(');
      expression = expression.replace(/abs\(/g, 'Math.abs(');
      expression = expression.replace(/sqrt\(/g, 'Math.sqrt(');
      expression = expression.replace(/pow\(/g, 'Math.pow(');
      expression = expression.replace(/exp\(/g, 'Math.exp(');
      expression = expression.replace(/log\(/g, 'Math.log(');
      expression = expression.replace(/ln\(/g, 'Math.log(');
      expression = expression.replace(/sin\(/g, 'Math.sin(');
      expression = expression.replace(/cos\(/g, 'Math.cos(');
      expression = expression.replace(/tan\(/g, 'Math.tan(');
      expression = expression.replace(/asin\(/g, 'Math.asin(');
      expression = expression.replace(/acos\(/g, 'Math.acos(');
      expression = expression.replace(/atan\(/g, 'Math.atan(');

      var func = new Function('return ' + expression);
      return func();
    } catch (error) {
      console.warn('Expression evaluation failed:', expression, error);
      return 0;
    }
  }

  function escapeRegExp(string) {
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  }

})(jQuery, Drupal, drupalSettings, once);

