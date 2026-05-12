<?php

namespace Drupal\concat_field\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Provides a field type for concatenating other field values using tokens.
 *
 * @FieldType(
 *   id = "concat_field",
 *   label = @Translation("Concat field"),
 *   description = @Translation("Store values of other fields concatenated together using token patterns."),
 *   default_widget = "concat_field_default",
 *   default_formatter = "concat_field_default"
 * )
 */
class ConcatFieldType extends FieldItemBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['value'] = DataDefinition::create('string')
      ->setLabel(t('Concatenated value'))
      ->setRequired(FALSE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'value' => [
          'type' => 'text',
          'size' => 'big',
          'not null' => FALSE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $value = $this->get('value')->getValue();
    return !isset($value) || $value === '';
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return [
      'token_pattern' => '',
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $element = [];
    
    // Get the field definition to access entity type and bundle.
    $field_definition = $this->getFieldDefinition();
    $entity_type = $field_definition->getTargetEntityTypeId();
    $bundle = $field_definition->getTargetBundle();
    
    // Get entity type definition for labels.
    $entity_type_definition = \Drupal::entityTypeManager()->getDefinition($entity_type);
    $entity_type_label = $entity_type_definition->getLabel();

    $element['token_pattern'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Token pattern'),
      '#description' => $this->t('Enter the token pattern for concatenation. You can use:<br>
        <strong>Short form:</strong> <code>[field_name]</code> (e.g. <code>[field_textinput1]</code>)<br>
        <strong>Full form:</strong> <code>[@entity_type:field_name]</code> (e.g. <code>[@entity_type:field_textinput1]</code>)<br>
        Both formats work and can be mixed in the same pattern.', [
        '@entity_type' => $entity_type,
      ]),
      '#default_value' => $this->getSetting('token_pattern'),
      '#rows' => 3,
      '#required' => TRUE,
    ];

    // Add token tree if token module is available.
    if (\Drupal::moduleHandler()->moduleExists('token')) {
      $element['token_help'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => [$entity_type],
        '#global_types' => TRUE,
        '#click_insert' => TRUE,
        '#show_restricted' => FALSE,
        '#recursion_limit' => 3,
        '#text' => $this->t('Browse available tokens for @entity_type_label', [
          '@entity_type_label' => $entity_type_label,
        ]),
      ];
    }

    // Add dynamic examples based on entity type.
    $examples = $this->getExampleTokenPatterns($entity_type);
    
    $element['examples'] = [
      '#type' => 'details',
      '#title' => $this->t('Example token patterns for @entity_type_label', [
        '@entity_type_label' => $entity_type_label,
      ]),
      '#description' => '<p>' . $this->t('Here are some example token patterns you can use:') . '</p>',
    ];

    foreach ($examples as $example) {
      $element['examples'][] = [
        '#markup' => '<code>' . $example . '</code><br>',
      ];
    }

    // Add info about available field tokens.
    $element['field_info'] = [
      '#type' => 'details',
      '#title' => $this->t('Available fields'),
      '#description' => $this->getAvailableFieldsInfo($entity_type, $bundle),
    ];

    return $element;
  }

  /**
   * Get example token patterns based on entity type.
   *
   * @param string $entity_type
   *   The entity type ID.
   *
   * @return array
   *   Array of example token patterns.
   */
  protected function getExampleTokenPatterns($entity_type) {
    $examples = [];
    
    switch ($entity_type) {
      case 'node':
        $examples = [
          '[node:title] - [node:field_subtitle]',
          '[node:title] ([node:type]) - [node:author:display-name]',
          '[node:title] | Created: [node:created:short] | Status: [node:status]',
          '[node:field_category] - [node:title] - [node:summary]',
          '[field_textinput1] [field_textinput2]',
          '[title] - [field_subtitle]',
        ];
        break;
        
      case 'user':
        $examples = [
          '[user:display-name] - [user:mail]',
          '[user:field_first_name] [user:field_last_name] ([user:name])',
          'User: [user:display-name] | Roles: [user:roles]',
        ];
        break;
        
      case 'taxonomy_term':
        $examples = [
          '[term:name] - [term:vocabulary:name]',
          '[term:parent:name] > [term:name]',
          '[term:name] ([term:description])',
        ];
        break;
        
      case 'commerce_product':
        $examples = [
          '[commerce_product:title] - [commerce_product:field_brand]',
          '[commerce_product:title] | SKU: [commerce_product:field_sku]',
          '[commerce_product:title] - [commerce_product:price] [commerce_product:field_currency]',
        ];
        break;
        
      default:
        $examples = [
          "[$entity_type:title] - [$entity_type:field_description]",
          "[$entity_type:label] | Created: [$entity_type:created:short]",
          "[$entity_type:field_name] - [$entity_type:field_status]",
          "[$entity_type:id] - [$entity_type:label]",
          '[field_textinput1] [field_textinput2]',
          '[field_name] - [field_status]',
        ];
        break;
    }
    
    return $examples;
  }

  /**
   * Get information about available fields for the entity type and bundle.
   *
   * @param string $entity_type
   *   The entity type ID.
   * @param string $bundle
   *   The bundle name.
   *
   * @return string
   *   HTML describing available fields.
   */
  protected function getAvailableFieldsInfo($entity_type, $bundle) {
    $field_manager = \Drupal::service('entity_field.manager');
    $field_definitions = $field_manager->getFieldDefinitions($entity_type, $bundle);
    
    $fields_info = [];
    foreach ($field_definitions as $field_name => $field_definition) {
      // Skip this field itself.
      if ($field_name === $this->getFieldDefinition()->getName()) {
        continue;
      }
      
      $field_type = $field_definition->getType();
      $field_label = $field_definition->getLabel();
      
      $fields_info[] = "<strong>[$entity_type:$field_name]</strong> - $field_label ($field_type)";
    }
    
    if (empty($fields_info)) {
      return '<p>' . $this->t('No other fields available for this bundle.') . '</p>';
    }
    
    return '<p>' . $this->t('Available field tokens:') . '</p><ul><li>' . 
           implode('</li><li>', $fields_info) . '</li></ul>';
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $values['value'] = 'Sample concatenated value';
    return $values;
  }

}