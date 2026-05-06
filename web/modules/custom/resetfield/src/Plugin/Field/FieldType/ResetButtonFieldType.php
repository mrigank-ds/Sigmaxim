<?php

namespace Drupal\resetfield\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\Form\FormStateInterface;

/**
 * @FieldType(
 *   id = "reset_button_field",
 *   label = @Translation("Reset Button"),
 *   description = @Translation("A dynamic reset button field"),
 *   default_widget = "reset_button_widget",
 *   default_formatter = "reset_button_formatter",
 *   cardinality = 1
 * )
 */
class ResetButtonFieldType extends FieldItemBase {

  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['value'] = DataDefinition::create('string')
      ->setLabel(t('Value'))
      ->setRequired(FALSE)
      ->setComputed(TRUE);
    return $properties;
  }

  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'value' => [
          'type' => 'varchar',
          'length' => 1,
          'not null' => FALSE,
        ],
      ],
    ];
  }

  public function isEmpty() {
    return TRUE;
  }

  public function preSave() {
    $this->set('value', NULL);
  }

  public static function defaultFieldSettings() {
    return [
      'button_label' => 'Reset',
      'button_style' => 'rectangular',
      'reset_confirmation' => FALSE,
      'fields_list' => '',
    ] + parent::defaultFieldSettings();
  }

  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $element = [];

    $element['button_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Button Label'),
      '#default_value' => $this->getSetting('button_label'),
      '#required' => TRUE,
    ];

    $element['button_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Button Style'),
      '#options' => [
        'circular' => $this->t('Circular'),
        'rectangular' => $this->t('Rectangular'),
      ],
      '#default_value' => $this->getSetting('button_style'),
    ];

    $element['reset_confirmation'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Require confirmation'),
      '#default_value' => $this->getSetting('reset_confirmation'),
    ];

    $element['fields_list'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Fields to Reset'),
      '#default_value' => $this->getSetting('fields_list'),
      '#rows' => 5,
      '#description' => $this->t('Enter field names (one per line or comma-separated).<br>Examples: field_textinput1, field_textinput2<br>Or with tokens: [sigmaxim_workflow_order:field_textinput1]'),
      '#required' => TRUE,
    ];

    if (\Drupal::moduleHandler()->moduleExists('token')) {
      $element['token_help'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => ['node', 'sigmaxim_workflow_order', 'order'],
      ];
    }

    return $element;
  }
}