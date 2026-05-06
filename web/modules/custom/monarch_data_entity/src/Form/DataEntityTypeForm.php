<?php

namespace Drupal\monarch_data_entity\Form;

use Drupal\Core\Entity\BundleEntityFormBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for data type forms.
 */
class DataEntityTypeForm extends BundleEntityFormBase {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $entity_type = $this->entity;
    if ($this->operation == 'edit') {
      $form['#title'] = $this->t('Edit %label data type', ['%label' => $entity_type->label()]);
    }

    $form['label'] = [
      '#title' => $this->t('Label'),
      '#type' => 'textfield',
      '#default_value' => $entity_type->label(),
      '#description' => $this->t('The human-readable name of this data type.'),
      '#required' => TRUE,
      '#size' => 30,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $entity_type->id(),
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
      '#machine_name' => [
        'exists' => ['Drupal\monarch_data_entity\Entity\DataEntityType', 'load'],
        'source' => ['label'],
      ],
      '#description' => $this->t('A unique machine-readable name for this data type. It must only contain lowercase letters, numbers, and underscores.'),
    ];

    // Add primary keys selection for existing bundles.
    if ($this->operation == 'edit' && $entity_type->id()) {
      $entity_field_manager = \Drupal::service('entity_field.manager');
      $field_definitions = $entity_field_manager->getFieldDefinitions('data_entity', $entity_type->id());

      $excluded_fields = [
        'id', 'uuid', 'bundle', 'label', 'status', 'created', 'changed',
        'feeds_item', 'default_langcode', 'revision_translation_affected',
      ];

      $field_options = [];
      foreach ($field_definitions as $field_name => $field_definition) {
        if (in_array($field_name, $excluded_fields)) {
          continue;
        }
        $field_options[$field_name] = (string) $field_definition->getLabel();
      }

      if (!empty($field_options)) {
        $form['primary_keys'] = [
          '#type' => 'checkboxes',
          '#title' => $this->t('Primary Keys'),
          '#description' => $this->t('Select the fields that should be treated as primary keys for this data type. Primary key fields will have a "Visibility" option in the Data Reference widget settings.'),
          '#options' => $field_options,
          '#default_value' => $entity_type->getPrimaryKeys(),
        ];
      }
    }

    return $this->protectBundleIdElement($form);
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Save data type');
    $actions['delete']['#value'] = $this->t('Delete data type');
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity_type = $this->entity;

    $entity_type->set('id', trim($entity_type->id()));
    $entity_type->set('label', trim($entity_type->label()));

    // Save primary keys - filter out unchecked values.
    $primary_keys = $form_state->getValue('primary_keys');
    if (is_array($primary_keys)) {
      $primary_keys = array_values(array_filter($primary_keys));
      $entity_type->setPrimaryKeys($primary_keys);
    }

    $status = $entity_type->save();

    $t_args = ['%name' => $entity_type->label()];
    if ($status == SAVED_UPDATED) {
      $message = $this->t('The data type %name has been updated.', $t_args);
    }
    elseif ($status == SAVED_NEW) {
      $message = $this->t('The data type %name has been added.', $t_args);
    }
    $this->messenger()->addStatus($message);

    $form_state->setRedirectUrl($entity_type->toUrl('collection'));
  }

}
