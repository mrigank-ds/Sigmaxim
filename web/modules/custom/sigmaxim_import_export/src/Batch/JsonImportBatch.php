<?php

namespace Drupal\sigmaxim_import_export\Batch;

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\NodeType;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;

/**
 * Batch callbacks for JSON content type, field & data import.
 */
class JsonImportBatch {

  /**
   * Computed field types — must never have a default_value forced on them.
   *
   * field_expression module type IDs:
   *   expression_field   → "Expression"
   *   expression_float   → "Expression (float)"
   *   expression_decimal → "Expression (decimal)"
   *   expression_integer → "Expression (integer)"
   */
  const COMPUTED_FIELD_TYPES = [
    // field_expression module (all four variants)
    'expression_field',
    'expression_float',
    'expression_decimal',
    'expression_integer',
    // Drupal core computed field module (if used)
    'computed',
    'computed_string',
    'computed_string_long',
    'computed_integer',
    'computed_decimal',
  ];

  /**
   * Core field types whose settings split between storage and instance.
   *
   * Any type NOT in this list (e.g. expression_field, expression_float,
   * expression_decimal, expression_integer) sends ALL settings to the
   * FieldConfig instance — this is where the expression formula lives.
   */
  const CORE_STORAGE_TYPES = [
    'string', 'string_long', 'text', 'text_long', 'text_with_summary',
    'integer', 'float', 'decimal', 'boolean', 'datetime', 'timestamp',
    'created', 'changed', 'language', 'uuid', 'uri', 'email', 'telephone',
    'entity_reference', 'entity_reference_revisions', 'file', 'image',
    'link', 'list_string', 'list_integer', 'list_float', 'map',
  ];

  /**
   * Settings keys that always belong to FieldStorageConfig for core types.
   */
  const STORAGE_ONLY_KEYS = [
    'max_length', 'is_ascii', 'case_sensitive', 'unsigned', 'size',
    'precision', 'scale', 'allowed_values', 'allowed_values_function',
    'target_type', 'handler', 'handler_settings',
  ];

  /**
   * Fields that must never be set manually during entity creation.
   */
  const SKIP_DATA_FIELDS = [
    'id', 'uuid', 'vid', 'revision_created', 'revision_user',
    'revision_log', 'revision_translation_affected', 'revision_default',
    'changed', 'type',
  ];

  // ---------------------------------------------------------------------------
  // BATCH OPERATION: Create bundle + one field
  // ---------------------------------------------------------------------------

  /**
   * Batch operation: create bundle (once) and a single field.
   *
   * @param string $entity_type
   * @param string $bundle_id
   * @param string $bundle_label
   * @param array  $bundle_extra
   * @param string $field_name
   * @param array  $field
   * @param mixed  $default_value
   *   Raw value from JSON 'data' block. Ignored for computed field types.
   * @param array  $context
   */
  public static function processField(
    string $entity_type,
    string $bundle_id,
    string $bundle_label,
    array  $bundle_extra,
    string $field_name,
    array  $field,
    mixed  $default_value,
    array  &$context
  ): void {

    // ------------------------------------------------------------------
    // 1. Create bundle — ONE TIME only
    // ------------------------------------------------------------------
    if (empty($context['sandbox']['bundle_created'])) {
      self::ensureBundle($entity_type, $bundle_id, $bundle_label, $bundle_extra);
      $context['sandbox']['bundle_created'] = TRUE;
    }

    // ------------------------------------------------------------------
    // 2. Validate field type plugin exists
    // ------------------------------------------------------------------
    $field_type   = $field['type'] ?? '';
    $all_settings = $field['settings'] ?? [];

    /** @var \Drupal\Core\Field\FieldTypePluginManagerInterface $type_manager */
    $type_manager = \Drupal::service('plugin.manager.field.field_type');

    if (empty($field_type) || !$type_manager->hasDefinition($field_type)) {
      $context['results']['skipped'][] =
        $field_name . ' (unsupported type: ' . ($field_type ?: 'empty') . ')';
      return;
    }

    $is_computed  = in_array($field_type, self::COMPUTED_FIELD_TYPES, TRUE);
    $is_core_type = in_array($field_type, self::CORE_STORAGE_TYPES, TRUE);

    // ------------------------------------------------------------------
    // 3. Split settings: storage vs instance
    //    CORE types  → split by STORAGE_ONLY_KEYS
    //    CUSTOM/COMPUTED types → ALL settings go to FieldConfig instance
    //    (this is where expression_float stores "expression", etc.)
    // ------------------------------------------------------------------
    if ($is_core_type) {
      $storage_settings  = [];
      $instance_settings = [];

      foreach ($all_settings as $key => $value) {
        if (in_array($key, self::STORAGE_ONLY_KEYS, TRUE)) {
          $storage_settings[$key] = $value;
        }
        else {
          $instance_settings[$key] = $value;
        }
      }

      if (in_array($field_type, ['list_string', 'list_integer', 'list_float'], TRUE)) {
        $raw = $storage_settings['allowed_values'] ?? [];
        $storage_settings['allowed_values'] = is_array($raw) ? $raw : [];
        $storage_settings['allowed_values_function'] =
          $storage_settings['allowed_values_function'] ?? '';
      }
    }
    else {
      $storage_settings  = [];
      $instance_settings = $all_settings;   // expression, default_zero, etc.
    }

    // ------------------------------------------------------------------
    // 4. Field storage
    // ------------------------------------------------------------------
    if (!FieldStorageConfig::loadByName($entity_type, $field_name)) {
      FieldStorageConfig::create([
        'field_name'   => $field_name,
        'entity_type'  => $entity_type,
        'type'         => $field_type,
        'settings'     => $storage_settings,
        'translatable' => $field['translatable'] ?? FALSE,
        'cardinality'  => 1,
      ])->save();
    }

    // ------------------------------------------------------------------
    //    Computed fields: NO default_value (expression computes at runtime)
    //    Regular fields:  default_value from data block
    // ------------------------------------------------------------------
    $default_value_arr = $is_computed
      ? []
      : self::buildDefaultValue($field_type, $default_value);

    $field_config = FieldConfig::loadByName($entity_type, $bundle_id, $field_name);

    if (!$field_config) {
      FieldConfig::create([
        'field_name'    => $field_name,
        'entity_type'   => $entity_type,
        'bundle'        => $bundle_id,
        'label'         => $field['label'] ?? $field_name,
        'required'      => (bool) ($field['required'] ?? FALSE),
        'settings'      => $instance_settings,
        'default_value' => $default_value_arr,
      ])->save();
    }
    else {
      $needs_save = FALSE;

      if (!empty($instance_settings)) {
        foreach ($instance_settings as $key => $value) {
          $field_config->setSetting($key, $value);
        }
        $needs_save = TRUE;
      }

      if (!$is_computed && !empty($default_value_arr)) {
        $field_config->setDefaultValue($default_value_arr);
        $needs_save = TRUE;
      }

      if ($needs_save) {
        $field_config->save();
      }
    }

    $form_display = EntityFormDisplay::load(
      $entity_type . '.' . $bundle_id . '.default'
    );

    if (!$form_display) {
      $form_display = EntityFormDisplay::create([
        'targetEntityType' => $entity_type,
        'bundle'           => $bundle_id,
        'mode'             => 'default',
        'status'           => TRUE,
      ]);
    }

    $widget_id = self::resolveWidgetId($field_type, $type_manager);
    $form_display->setComponent($field_name, array_filter([
      'type'   => $widget_id,
      'weight' => 0,
    ]));
    $form_display->save();

    // ------------------------------------------------------------------
    // 6b. VIEW DISPLAY — Manage Display
    //     Same runtime resolution for formatter.
    // ------------------------------------------------------------------
    $view_display = EntityViewDisplay::load(
      $entity_type . '.' . $bundle_id . '.default'
    );

    if (!$view_display) {
      $view_display = EntityViewDisplay::create([
        'targetEntityType' => $entity_type,
        'bundle'           => $bundle_id,
        'mode'             => 'default',
        'status'           => TRUE,
      ]);
    }

    /** @var \Drupal\Core\Field\FormatterPluginManager $formatter_manager */
    $formatter_manager = \Drupal::service('plugin.manager.field.formatter');
    $formatter_id      = self::resolveFormatterId($field_type, $formatter_manager);

    $view_display->setComponent($field_name, array_filter([
      'type'     => $formatter_id,
      'label'    => 'above',
      'weight'   => 0,
      'settings' => [],
    ]));
    $view_display->save();

    // ------------------------------------------------------------------
    // 7. Progress message
    // ------------------------------------------------------------------
    $context['message'] = t('Processed field: @field (@type)', [
      '@field' => $field_name,
      '@type'  => $field_type,
    ]);
  }

  // ---------------------------------------------------------------------------
  // BATCH FINISHED
  // ---------------------------------------------------------------------------

  /**
   * Batch finished callback.
   */
  public static function finished(
    bool  $success,
    array $results,
    array $operations
  ): void {

    if ($success) {
      \Drupal::messenger()->addStatus(
        t('Product type and fields imported successfully.')
      );

      if (!empty($results['skipped'])) {
        \Drupal::messenger()->addWarning(
          t('Skipped fields (unsupported types): @fields', [
            '@fields' => implode(', ', $results['skipped']),
          ])
        );
      }

      if (!empty($results['errors'])) {
        foreach ($results['errors'] as $err) {
          \Drupal::messenger()->addError($err);
        }
      }
    }
    else {
      \Drupal::messenger()->addError(
        t('JSON import failed. Check the site logs for details.')
      );
    }
  }

 

  /**
   * Strategy (in order):
   *   1. Use the field type's declared default_widget from its plugin definition.
   *   2. Fall back to our static map for well-known core types.
   *   3. Return NULL so setComponent() lets Drupal pick its own default.
   *
   * @param string $field_type
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $type_manager
   *
   * @return string|null
   */
  protected static function resolveWidgetId(
    string $field_type,
    $type_manager
  ): ?string {

    // 1. Ask the field type plugin for its own default_widget.
    try {
      $definition = $type_manager->getDefinition($field_type, FALSE);
      if (!empty($definition['default_widget'])) {
        $widget_manager = \Drupal::service('plugin.manager.field.widget');
        if ($widget_manager->hasDefinition($definition['default_widget'])) {
          return $definition['default_widget'];
        }
      }
    }
    catch (\Exception $e) {
      // Fall through to static map.
    }

    // 2. Static fallback for core types.
    return match ($field_type) {
      'string'                                    => 'string_textfield',
      'string_long'                               => 'string_textarea',
      'boolean'                                   => 'boolean_checkbox',
      'integer'                                   => 'number',
      'float', 'decimal'                          => 'number',
      'datetime'                                  => 'datetime_default',
      'list_string', 'list_integer', 'list_float' => 'options_select',
      default                                     => NULL,   // let Drupal decide
    };
  }

  /**
   * Strategy (in order):
   *   1. Use the field type's declared default_formatter.
   *   2. Fall back to our static map for well-known core types.
   *   3. Return NULL so setComponent() lets Drupal pick its own default.
   *
   * @param string $field_type
   * @param \Drupal\Core\Field\FormatterPluginManager $formatter_manager
   *
   * @return string|null
   */
  protected static function resolveFormatterId(
    string $field_type,
    $formatter_manager
  ): ?string {

    // 1. Ask the field type plugin for its own default_formatter.
    try {
      $type_manager = \Drupal::service('plugin.manager.field.field_type');
      $definition   = $type_manager->getDefinition($field_type, FALSE);
      if (!empty($definition['default_formatter'])) {
        if ($formatter_manager->hasDefinition($definition['default_formatter'])) {
          return $definition['default_formatter'];
        }
      }
    }
    catch (\Exception $e) {
      // Fall through to static map.
    }

    // 2. Static fallback for core types.
    return match ($field_type) {
      'string'                                    => 'string',
      'string_long'                               => 'basic_string',
      'boolean'                                   => 'boolean',
      'integer'                                   => 'number_integer',
      'float', 'decimal'                          => 'number_decimal',
      'datetime'                                  => 'datetime_default',
      'list_string', 'list_integer', 'list_float' => 'list_default',
      default                                     => NULL,   // let Drupal decide
    };
  }

  /**
   * Build a Drupal-compatible default_value array from a raw exported value.
   * Never called for computed field types.
   */
  protected static function buildDefaultValue(string $field_type, mixed $raw): array {
    if ($raw === NULL) {
      return [];
    }

    $scalar = (is_array($raw) && array_key_exists('value', $raw)) ? $raw['value'] : $raw;

    if ($scalar === NULL || $scalar === '') {
      return [];
    }

    return match (TRUE) {
      $field_type === 'boolean'                                        => [['value' => (int) $scalar]],
      in_array($field_type, ['integer', 'list_integer'], TRUE)        => [['value' => (int) $scalar]],
      in_array($field_type, ['float', 'decimal'], TRUE)               => [['value' => (float) $scalar]],
      default                                                          => [['value' => (string) $scalar]],
    };
  }

  /**
   * Ensure the bundle exists; create it with all extra properties if not.
   */
  protected static function ensureBundle(
    string $entity_type,
    string $bundle_id,
    string $bundle_label,
    array  $bundle_extra
  ): void {

    try {
      if ($entity_type === 'node') {
        if (!NodeType::load($bundle_id)) {
          NodeType::create([
            'type' => $bundle_id,
            'name' => $bundle_label,
          ])->save();
        }
        return;
      }

      $bundle_storage = \Drupal::entityTypeManager()
        ->getStorage($entity_type . '_type');

      if (!$bundle_storage || $bundle_storage->load($bundle_id)) {
        return;
      }

      $bundle_data = array_merge(
        ['id' => $bundle_id, 'label' => $bundle_label],
        array_filter($bundle_extra, fn($v) => $v !== NULL && $v !== '')
      );

      $bundle_storage->create($bundle_data)->save();
    }
    catch (\Exception $e) {
      \Drupal::logger('sigmaxim_import_export')->error(
        'Bundle creation failed: @msg',
        ['@msg' => $e->getMessage()]
      );
    }
  }

}
