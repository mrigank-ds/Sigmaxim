<?php

namespace Drupal\field_expression\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Plugin\Field\FieldFormatter\DecimalFormatter;

/**
 * Plugin implementation of the 'field_expression_value_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "expression_decimal",
 *   label = @Translation("Default"),
 *   field_types = {
 *     "expression_decimal",
 *     "expression_float"
 *   }
 * )
 */
class ExpressionDecimalFormatter extends DecimalFormatter {
  use ExpressionFormatterTrait;
}
