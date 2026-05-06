<?php

namespace Drupal\field_expression\Plugin\Field\FieldType;

use Drupal\Core\Field\Plugin\Field\FieldType\DecimalItem;

/**
 * Plugin implementation of the 'field_expression_decimal' field type.
 *
 * @FieldType(
 *   id = "expression_decimal",
 *   label = @Translation("Expression (decimal)"),
 *   description = @Translation("Create a field value calculated by evaluating an expression that can include tokens."),
 *   category = @Translation("Number"),
 *   default_widget = "expression",
 *   default_formatter = "expression_decimal",
 *   cardinality = 1
 * )
 */
class ExpressionDecimalItem extends DecimalItem {
  use ExpressionItemTrait;
}
