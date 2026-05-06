<?php

namespace Drupal\field_expression\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'expression_widget' widget.
 *
 * @FieldWidget(
 *   id = "expression",
 *   label = @Translation("Field Expression"),
 *   field_types = {
 *     "expression_integer",
 *     "expression_decimal",
 *     "expression_float"
 *   }
 * )
 */
class ExpressionWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = [];

    $element['value'] = $element + [
        '#title' => $this->fieldDefinition->getName(),
        '#type' => 'value',
        '#value' => 0,
//        '#disabled' => TRUE,
        '#description' => $this->t('Normally this field should not be shown!'),
    ];

    return $element;
  }

  /**
   * Define how the widget is constructed.
   */
  public function getDefaultValue() {
    return 0;
  }

}
