<?php

namespace Drupal\field_expression\Plugin\Field\FieldType;

use Drupal\Core\Field\Plugin\Field\FieldType\FloatItem;

/**
 * Plugin implementation of the 'field_expression_float' field type.
 *
 * @FieldType(
 *   id = "expression_float",
 *   label = @Translation("Expression (float)"),
 *   description = @Translation("Create a field value calculated by evaluating an expression that can include tokens."),
 *   category = @Translation("Number"),
 *   default_widget = "expression",
 *   default_formatter = "expression_decimal",
 *   cardinality = 1
 * )
 */
class ExpressionFloatItem extends FloatItem {
  use ExpressionItemTrait;
}
