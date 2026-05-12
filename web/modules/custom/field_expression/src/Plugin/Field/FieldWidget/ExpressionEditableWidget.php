<?php

namespace Drupal\field_expression\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'expression_editable' widget.
 *
 * @FieldWidget(
 *   id = "expression_editable",
 *   label = @Translation("Expression Override"),
 *   field_types = {
 *     "expression_integer",
 *     "expression_decimal",
 *     "expression_float"
 *   }
 * )
 */
class ExpressionEditableWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

// yugasa changes 

       // Get the current value or default to empty string for proper type handling
    $value = isset($items[$delta]->value) ? $items[$delta]->value : '';
    
    $element['value'] = [
      '#type' => 'number',
      '#title' => $this->fieldDefinition->getLabel(),
      '#default_value' => $value,
      '#step' => $this->getFieldSetting('scale') ? pow(0.1, $this->getFieldSetting('scale')) : 'any',
      // yugasa changes
      // '#description' => $this->t('Override the calculated expression value. Leave empty to use the expression calculation.'),
      '#description' => $this->getFieldSetting('expression'),
      '#disabled' => TRUE,
      '#description_display' => 'invisible',
    ];




    // $current_value = $items[$delta]->value ?? $this->getDefaultValue();

    // // Keep a scalar form value; nested arrays here break numeric validation.
    // $element['value'] = [
    //   '#title' => $this->fieldDefinition->getName(),
    //   '#type' => 'number',
    //   '#step' => 'any',
    //   '#default_value' => $current_value,
    //   '#attributes' => [
    //     'readonly' => 'readonly',
    //   ],
    //   '#description' => $this->t('Normally this field should not be shown!'),
    //   '#required' => FALSE,
    // ];

    return $element;
  }

  /**
   * Define default value.
   */
  public function getDefaultValue() {
    return 0;
  }

}
