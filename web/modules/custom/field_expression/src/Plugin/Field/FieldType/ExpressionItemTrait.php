<?php

namespace Drupal\field_expression\Plugin\Field\FieldType;

use Drupal\Core\Form\FormStateInterface;
use Webit\Util\EvalMath\EvalMath;
use Drupal\field_expression\CustomEvalMath;

/**
 * Common methods for Expression FieldType plugins.
 */
trait ExpressionItemTrait {

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    $value = $this->evaluateExpression($this->getSetting('expression'));
    $this->setValue($value);
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return [
        'expression' => '',
        'default_zero' => TRUE,
        'suppress_errors' => TRUE,
        'debug_mode' => FALSE,
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $element = [];

    $entity_type_id = $this->getEntity()->getEntityTypeId();

    $element['expression'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Expression'),
        '#description' => $this->t(
          '<ul><li>Enter mathematical expressions such as <code>2 + 2</code> or <code>sqrt(5)</code>. You may assign variables and create mathematical functions and evaluate them. Use <code>;</code> to separate these. Example: <code>f(x) = x + 2; f(2)</code>.</li>' .
          '<li>Expressions may include basic operators <code>(+-*/^)</code>, as well as the following functions: <code>sin(), sinh(), arcsin(), asin(), arcsinh(), asinh(), cos(), cosh(), arccos(), acos(), arccosh(), acosh(), tan(), tanh(), arctan(), atan(), arctanh(), atanh(), pow(), exp(), sqrt(), abs(), ln(), log(), time(), ceil(), floor(), min(), max(), round()</code></li>' .
          '<li>Tokens will be automatically replaced upon saving of the entity this field is attached to; default values can be provided by including them after the token, wrapped in curly braces. Example: <code>[node:field_some_number]</code></li>' .
          '<li>Line breaks will be replaced with spaces in the resulting expression.</li></ul>'
        ),
        '#default_value' => $this->getSetting('expression'),
        '#element_validate' => ['token_element_validate'],
        '#token_types' => [$entity_type_id],
        '#required' => TRUE,
    ];

    $element['token_tree_link'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => [$entity_type_id],
    ];

    $element['default_zero'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Evalute empty tokens to zero?'),
        '#description' => $this->t('Select this option to default any unresolved tokens to zero. If unchecked, unresolved tokens will remain in the expression, likely resulting in an invalid expression, and thus a blank value (and an error depending on if you have error suppression on below). It is recommended that you use token-specific default values whenever possible (see above description for how to implement a token default value).'),
        '#default_value' => $this->getSetting('default_zero'),
    ];

    $element['suppress_errors'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Suppress Errors'),
        '#description' => $this->t('Check this box to suppress any errors that occur when evaluating the expression. If an error occurs the evaluated value will just be blank.'),
        '#default_value' => $this->getSetting('suppress_errors'),
    ];

    if (\Drupal::moduleHandler()->moduleExists('devel')) {
      $element['debug_mode'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Debug Mode'),
          '#description' => $this->t('Check this box to enable debug mode. After an expression is evaluated (e.g. after saving the entity this field is attached to), debug messages will be output with some feedback about the expression.'),
          '#default_value' => $this->getSetting('debug_mode'),
      ];
    }

    $element = $element + parent::fieldSettingsForm($form, $form_state);
    unset($element['min']);
    unset($element['max']);
    return($element);
  }

  /**
   * Evaluate the expression for the field value.
   */
  public function evaluateExpression($expression) {
    $entity = $this->getEntity();
    $entity_type = $entity->getEntityTypeId();
    // Replace line breaks
    $expression = str_replace(["\r", "\n"], '', $expression);
    $original_expression = $expression;

    // Replace the tokens
    $token_service = \Drupal::token();
    $expression = $token_service->replace($expression,
        [$entity_type => $entity],
        ['clear' => FALSE]
        );

    // Add back the default values for any tokens still there
    $remaining_tokens = $token_service->scan($expression);
    foreach ($remaining_tokens as $tokens) {
      foreach ($tokens as $token) {
        $matches = [];

        // First process any items with default values
        if (preg_match_all('/' . preg_quote($token) . '\{(.*?)\}/', $expression, $matches)) {
          // Replace any matches with the default value
          foreach ($matches[0] as $index => $match) {
            $expression = preg_replace('/' . preg_quote($match) . '/', $matches[1][$index], $expression);
          }
        }

        // We may also have instances of this token without default values, so
        // we process those as well
        if (preg_match('/' . preg_quote($token) . '/', $expression)) {
          if ($this->getSetting('default_zero')) {
            // We're using the default_zero
            $expression = preg_replace('/' . preg_quote($token) . '/', 0, $expression);
          }
        }

        // Clean up any remaining default value wrappers
        $expression = preg_replace('/\{.*?\}/', '', $expression);
      }
    }

    // Evaluate the final expression
    $result = FALSE;
    $expressions = explode(';', $expression);
    // yugasa changes
    // $math = new EvalMath();
    $math = new CustomEvalMath();
    $math->suppress_errors = $this->getSetting('suppress_errors');
    foreach ($expressions as $expr) {
      if ($expr !== '') {

        // remove blank spaces because 1 000 will evaluate to zero instead of 1000
        $expr = str_replace(' ', '', $expr);

        $result = $math->evaluate($expr);
        if ($result == false) {
          $result = 0;
        }
      }
    }

    // Support debugging expressions with devel module
    if (\Drupal::moduleHandler()->moduleExists('devel') && $this->getSetting('debug_mode')) {
      $debug = [
          'Original Expression:' => $original_expression,
          'Token Replaced Expression:' => $expression,
          'Expression Result:' => $result
      ];
      dpm($debug, 'Field Token Expression Debug Output');
    }

    return $result;
  }

}
