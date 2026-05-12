<?php

namespace Drupal\sigmaxim_data_entity\Plugin\conditional_fields\handler;

use Drupal\conditional_fields\ConditionalFieldsHandlerBase;
use Drupal\conditional_fields\ConditionalFieldsInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides states handler for the sigmaxim_simple_data_reference widget.
 *
 *
 * @ConditionalFieldsHandler(
 *   id = "states_handler_sigmaxim_simple_data_reference",
 * )
 */
class SimpleDataReference extends ConditionalFieldsHandlerBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs the handler.
   */
  final public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function statesHandler($field, $field_info, $options) {
    if ($options['values_set'] !== ConditionalFieldsInterface::CONDITIONAL_FIELDS_DEPENDENCY_VALUES_WIDGET) {
      return [];
    }

    // carry the raw wrapper structure from an older save.
    $vf = $options['value_form'] ?? [];
    $target_id = $vf[0]['target_id']
      ?? $vf[0]['wrapper']['target_id']
      ?? NULL;
    if (empty($target_id)) {
      return [];
    }

    // #target_type is set on each select element in updateOptions().
    $target_type = $field['#target_type'] ?? NULL;
    if (empty($target_type)) {
      return [];
    }

    $primary_field_name = $this->findPrimaryFieldName(
      $options['value_form_raw'] ?? NULL,
      $field['#parents'] ?? []
    );
    if (empty($primary_field_name)) {
      return [];
    }

    $parents = $field['#parents'];
    if (count($parents) < 3) {
      return [];
    }
    $select_name = $parents[0] . '[' . $parents[1] . '][' . $parents[2] . ']['
      . $primary_field_name . '][' . $primary_field_name . ']';
    $selector = '[name="' . $select_name . '"]';

    // Load the entity and get the JSON-encoded value of the primary field.
    $entity = $this->entityTypeManager->getStorage($target_type)->load($target_id);
    if (empty($entity) || !$entity->hasField($primary_field_name)) {
      return [];
    }

    $field_item = $entity->get($primary_field_name)->first();
    if (empty($field_item) || $field_item->isEmpty()) {
      return [];
    }

    $json_value = json_encode($field_item->getValue());

    return [$options['state'] => [$selector => ['value' => $json_value]]];
  }

  /**
   * Finds the primary field name from value_form_raw or #parents fallback.
   *
   * value_form_raw[0]['wrapper'] has one entry per drill-down field. The field
   * whose inner value is non-empty is the one the user actually selected —
   * that is the primary key we must watch and match.
   *
   * @param mixed $value_form_raw
   *   The raw saved widget value, or NULL.
   * @param array $parents
   *   The #parents of the registered select element.
   *
   * @return string|null
   *   The primary field name, or NULL if it cannot be determined.
   */
  protected function findPrimaryFieldName($value_form_raw, array $parents): ?string {
    $raw_wrapper = $value_form_raw[0]['wrapper'] ?? NULL;

    if (is_array($raw_wrapper)) {
      foreach ($raw_wrapper as $fname => $fval) {
        if ($fname === 'target_id') {
          continue;
        }
        if (is_array($fval) && !empty($fval[$fname])) {
          return $fname;
        }
      }
    }

    // use the last element of #parents 
    return end($parents) ?: NULL;
  }

}
