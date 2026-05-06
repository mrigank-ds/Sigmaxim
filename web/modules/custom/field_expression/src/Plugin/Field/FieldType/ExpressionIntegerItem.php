<?php

namespace Drupal\field_expression\Plugin\Field\FieldType;

use Drupal\Core\Field\Plugin\Field\FieldType\IntegerItem;

/**
 * Plugin implementation of the 'field_expression_integer' field type.
 *
 * @FieldType(
 *   id = "expression_integer",
 *   label = @Translation("Expression (integer)"),
 *   description = @Translation("Create a field value calculated by evaluating an expression that can include tokens."),
 *   category = @Translation("Number"),
 *   default_widget = "expression",
 *   default_formatter = "expression_integer",
 *   cardinality = 1
 * )
 */
class ExpressionIntegerItem extends IntegerItem {
  use ExpressionItemTrait;

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    $value = (int)$this->evaluateExpression($this->getSetting('expression'));
    $this->setValue($value);
  }

}
