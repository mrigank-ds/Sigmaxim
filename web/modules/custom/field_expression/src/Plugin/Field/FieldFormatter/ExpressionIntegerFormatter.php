<?php

namespace Drupal\field_expression\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Plugin\Field\FieldFormatter\IntegerFormatter;

/**
 * Plugin implementation of the 'field_expression_value_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "expression_integer",
 *   label = @Translation("Default"),
 *   field_types = {
 *     "expression_integer",
 *   }
 * )
 */
class ExpressionIntegerFormatter extends IntegerFormatter {
  use ExpressionFormatterTrait;
}
