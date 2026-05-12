<?php

namespace Drupal\concat_field\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a widget for the concat field.
 *
 * @FieldWidget(
 *   id = "concat_field_default",
 *   label = @Translation("Concat field"),
 *   field_types = {
 *     "concat_field"
 *   }
 * )
 */
class ConcatFieldWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $token_pattern = $this->getFieldSetting('token_pattern');
    $entity = $items->getEntity();
    
    // Always compute current value for display
    $current_value = '';
    if ($entity && !empty($token_pattern)) {
      // Expand short-form tokens like [field_name] to [entity_type:field_name].
      $entity_type_id = $entity->getEntityTypeId();
      $expanded_pattern = _concat_field_expand_short_tokens($token_pattern, $entity_type_id);

      $token_service = \Drupal::service('token');
      $current_value = $token_service->replace(
        $expanded_pattern,
        [$entity_type_id => $entity],
        [
          'clear' => TRUE,
          'sanitize' => FALSE,
        ]
      );
      $current_value = trim(preg_replace('/\s+/', ' ', strip_tags($current_value)));
    }

    // Get entity type for better labeling.
    $entity_type_id = $entity ? $entity->getEntityTypeId() : 'entity';
    $entity_type_definition = \Drupal::entityTypeManager()->getDefinition($entity_type_id);
    $entity_type_label = $entity_type_definition->getLabel();

    $element['value'] = [
      '#type' => 'textfield',
      '#title' => $this->fieldDefinition->getLabel(),
      '#default_value' => $current_value,
      '#disabled' => TRUE,
      '#rows' => 3,
      '#description' => $this->t('This field is automatically computed for @entity_type using the token pattern: <em>@pattern</em><br><strong>Current computed value:</strong> "@value"', [
        '@entity_type' => strtolower($entity_type_label),
        '@pattern' => $token_pattern ?: $this->t('(No pattern configured)'),
        '@value' => $current_value ?: $this->t('(empty)'),
      ]),
    ];

    // Add styling and data attributes for JavaScript
    // $element['value']['#attributes']['class'][] = 'concat-field-computed';
    // $element['value']['#attributes']['style'] = 'background-color: #f5f5f5; border-style: dashed;';
    
    // Add data attributes for JavaScript to use
    if (!empty($token_pattern)) {
      $element['value']['#attributes']['data-concat-pattern'] = $token_pattern;
      $element['value']['#attributes']['data-entity-type'] = $entity_type_id;
    }

    // Attach the JavaScript library
 $element['#attached']['library'][] = 'concat_field/concat_field.admin';

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getType() === 'concat_field';
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    // Don't extract form values since this field is computed.
    // The value will be set during entity presave.
    return;
  }

}