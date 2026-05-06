<?php

namespace Drupal\sigmaxim_data_entity\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\monarch_data_entity\DataEntityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Defines the 'sigmaxim_simple_data_reference' field widget.
 *
 * @FieldWidget(
 *   id = "sigmaxim_simple_data_reference",
 *   label = @Translation("Data Reference"),
 *   field_types = {
 *     "entity_reference",
 *   },
 * )
 */
class SimpleDataReferenceWidget extends WidgetBase {

  /**
   * The entity_type.manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity_field.manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The token service.
   *
   * @var \Drupal\monarch_data_entity\TokenService
   */
  protected $tokenService;

  /**
   * The query cache.
   *
   * @var array
   */
  static protected $cache = [];

  /**
   * Cache of rendered option lists by target field and stage ids.
   *
   * @var array
   */
  static protected $stageOptionsCache = [];

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $interface = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $interface->entityTypeManager = $container->get('entity_type.manager');
    $interface->entityFieldManager = $container->get('entity_field.manager');
    $interface->renderer = $container->get('renderer');
    $interface->tokenService = $container->get('monarch_data_entity.token_service');

    return $interface;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return parent::defaultSettings() + [
      'weights' => [
        'label' => [
          'weight' => -2,
        ],
        'id' => [
          'weight' => -1,
        ],
      ],
      'height' => 1,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element['height'] = [
      '#type' => 'number',
      '#title' => $this->t('Height'),
      '#description' => $this->t('Height of the select widgets. A height of 1 will use dropdown widgets instead.'),
      '#min' => 1,
      '#default_value' => max(1, $this->getSetting('height') ?? 0 ?: 0),
    ];

    $element['weights'] = [
      '#type' => 'table',
      '#tree' => TRUE,
      '#header' => [
        $this->t('Column'),
        $this->t('Weight'),
        $this->t('Filter Token'),
        $this->t('Display'),
        $this->t('Visibility'),
      ],
      '#attributes' => [
        'id' => 'data-drill-down-field-order',
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'table-sort-weight',
        ],
      ],
    ];

    $weights = $this->fieldDefinition->getThirdPartySetting('monarch_data_entity', 'weights');
    if (empty($weights)) {
        $weights = $this->getSetting('weights') ?? [];
    }
    $rows = [];

    $target_type = $this->fieldDefinition->getSetting('target_type');
    $handler_settings = $this->fieldDefinition->getSetting('handler_settings');
    $target_bundles = $handler_settings['target_bundles'] ?? [];
    $target_bundle = !empty($target_bundles) ? current($target_bundles) : NULL;

    if ($target_bundle) {
      $target_field_definitions = $this->entityFieldManager->getFieldDefinitions($target_type, $target_bundle);
    } elseif ($target_type) {
      $target_field_definitions = $this->entityFieldManager->getBaseFieldDefinitions($target_type);
    } else {
      $target_field_definitions = [];
    }

    $primary_keys = [];
    if ($target_bundle) {
      $bundle_entity = $this->entityTypeManager->getStorage('data_entity_type')->load($target_bundle);
      if ($bundle_entity && method_exists($bundle_entity, 'getPrimaryKeys')) {
        $primary_keys = $bundle_entity->getPrimaryKeys();
      }
    }

    foreach ($target_field_definitions as $field_name => $target_field_definition) {
      $rows[$field_name] = [
        $target_field_definition->getLabel(),
        $weights[$field_name]['weight'] ?? 0,
        $weights[$field_name]['token'] ?? '',
        $weights[$field_name]['enabled'] ?? !($target_field_definition instanceof BaseFieldDefinition),
        $weights[$field_name]['visibility'] ?? in_array($field_name, $primary_keys),
      ];
    }

    uasort($rows, function ($a, $b) {
      $a_weight = isset($a[1]) ? (int) $a[1] : 0;
      $b_weight = isset($b[1]) ? (int) $b[1] : 0;
      return $a_weight - $b_weight;
    });

    foreach ($rows as $name => [$title, $weight, $token, $enabled, $visibility]) {
      $element['weights'][$name] = [
        '#attributes' => [
          'class' => ['draggable'],
        ],
        '#weight' => $weight,
        'name' => [
          '#type' => 'inline_template',
          '#template' => $title . '<div style="color:#747474">ID: <em>' . $name . '</em></div>',
        ],
        'weight' => [
          '#type' => 'weight',
          '#title' => $this->t('Weight for @title', [
            '@title' => $title,
          ]),
          '#title_display' => 'invisible',
          '#default_value' => $weight,
          '#attributes' => [
            'class' => [
              'table-sort-weight',
            ],
          ],
        ],
        'token' => [
          '#type' => 'textfield',
          '#default_value' => $token,
        ],
        'enabled' => [
          '#type' => 'checkbox',
          '#default_value' => $enabled,
        ],
        'visibility' => [
          '#type' => 'checkbox',
          '#default_value' => $visibility,
        ],
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    return [];
  }

  /**
   * Get field list.
   */
  public function getFieldList() {
    $field_weights = $this->fieldDefinition->getThirdPartySetting('monarch_data_entity', 'weights');
    if (empty($field_weights)) {
      $field_weights = $this->getSetting('weights') ?? [];
    }

    foreach ($field_weights as $field_name => $field_config) {
      if (!(($field_config['enabled'] ?? NULL) || ($field_config['visibility'] ?? NULL) || ($field_config['token'] ?? NULL))) {
        unset($field_weights[$field_name]);
      }
    }

    uasort($field_weights, function ($a, $b) {
      return intval($a['weight'] ?? 0) - intval($b['weight'] ?? 0);
    });

    $target_type = $this->fieldDefinition->getSetting('target_type');
    $handler_settings = $this->fieldDefinition->getSetting('handler_settings');
    $target_bundles = $handler_settings['target_bundles'] ?? [];
    $target_bundle = !empty($target_bundles) ? current($target_bundles) : NULL;

    if ($target_bundle) {
        $target_field_definitions = $this->entityFieldManager->getFieldDefinitions($target_type, $target_bundle);
    } else {
        $target_field_definitions = $this->entityFieldManager->getBaseFieldDefinitions($target_type);
    }

    return empty($field_weights) ? array_keys($target_field_definitions) : array_keys($field_weights);
  }

  /**
   * Returns primary keys for a target bundle.
   */
  protected function getPrimaryKeys(?string $target_bundle) : array {
    if (empty($target_bundle)) {
      return [];
    }

    $cache_key = 'primary_keys|' . $target_bundle;
    if (!isset(static::$cache[$cache_key])) {
      $primary_keys = [];
      $bundle_entity = $this->entityTypeManager->getStorage('data_entity_type')->load($target_bundle);
      if ($bundle_entity && method_exists($bundle_entity, 'getPrimaryKeys')) {
        $primary_keys = $bundle_entity->getPrimaryKeys();
      }
      static::$cache[$cache_key] = is_array($primary_keys) ? $primary_keys : [];
    }

    return static::$cache[$cache_key];
  }

  /**
   * Builds the option list for a given target field and entity stage.
   */
  protected function getStageOptions(string $target_type, string $target_field_name, array $stage_ids) : array {
    if (empty($stage_ids)) {
      return [];
    }

    $cache_key = implode('|', [
      'stage_options',
      $target_type,
      $target_field_name,
      md5(json_encode(array_values($stage_ids))),
    ]);

    if (isset(static::$stageOptionsCache[$cache_key])) {
      return static::$stageOptionsCache[$cache_key];
    }

    $options = [];
    $found_values = [];
    $storage = $this->entityTypeManager->getStorage($target_type);
    $stage_entities = $storage->loadMultiple($stage_ids);

    foreach ($stage_entities as $stage_entity) {
      if ($target_field_name === 'id') {
        $id_val = $stage_entity->id();
        $options[$id_val] = $stage_entity->label();
        $found_values[$id_val] = $id_val;
        continue;
      }

      if (
        !($stage_entity instanceof FieldableEntityInterface) ||
        !$stage_entity->hasField($target_field_name) ||
        $stage_entity->get($target_field_name)->isEmpty()
      ) {
        continue;
      }

      foreach ($stage_entity->get($target_field_name) as $field_item) {
        $val = json_encode($field_item->getValue());
        if (isset($found_values[$val])) {
          continue;
        }

        $found_values[$val] = $val;
        $label = $field_item->value;
        if (empty($label) && isset($field_item->entity)) {
          $label = $field_item->entity->label();
        }
        if (empty($label)) {
          $evb = $this->entityTypeManager->getViewBuilder($target_type);
          $r = $evb->viewFieldItem($field_item, ['label_display' => 'none']);
          $label = strip_tags($this->renderer->renderPlain($r));
        }
        $options[$val] = $label;
      }
    }

    static::$stageOptionsCache[$cache_key] = $options;
    return $options;
  }

  /**
   * Flatten with key path.
   */
  protected function flatten(array $value, string $path = NULL, array &$ret = []) {
    foreach ($value as $key => $subvalue) {
      $subpath = empty($path) ? $key : $path . '.' . $key;

      if (is_array($subvalue)) {
        $this->flatten($subvalue, $subpath, $ret);
      }
      else {
        $ret[$subpath] = $subvalue;
      }
    }

    return $ret;
  }

  /**
   * Get entity based on the input values.
   */
  protected function getEntityIdsFromInputs(array $input = [], int &$count = NULL, bool $staged = FALSE) : array {
    $target_fields = $this->getFieldList();
    $target_type = $this->fieldDefinition->getSetting('target_type');
    $es = $this->entityTypeManager->getStorage($target_type);

    $query_values = [];
    $query_values['bundle'] = current($this->fieldDefinition->getSetting('handler_settings')['target_bundles']);

    $all_query_values = $query_values;
    $last_value_field = NULL;

    foreach ($target_fields as $target_field_name) {
      if ($target_field_name === 'id') {
        $input_field_value = $input[$target_field_name][$target_field_name] ?? FALSE ?: FALSE;
      }
      else {
        $input_field_value = json_decode($input[$target_field_name][$target_field_name] ?? '' ?: '', TRUE);
      }

      $all_query_values[$target_field_name] = $input_field_value;

      if (!empty($input_field_value)) {
        $last_value_field = $target_field_name;
      }
    }

    $cache_key = 'getEntityIdFromInputs|' . $target_type . '|' . $staged . '|' . json_encode($all_query_values);

    if (isset(static::$cache[$cache_key])) {
      $res = static::$cache[$cache_key] ?? [];

      if ($staged) {
        $count = count(end($res) ?: []);
      }
      else {
        $count = count($res);
      }
    }
    else {
      $all_query = $es->getQuery()->accessCheck(FALSE);

      $res = [];

      if ($staged) {
        $bundle_id = current($this->fieldDefinition->getSetting('handler_settings')['target_bundles']);
        $res['bundle'] = $es->getQuery()
          ->accessCheck(FALSE)
          ->condition('bundle', $bundle_id)
          ->range(0, 500)
          ->execute() ?: [];

        $found_last_selection = is_null($last_value_field);

        foreach ($target_fields as $tfn) {
          $val = $all_query_values[$tfn] ?? NULL;

          if ($found_last_selection) {
            $res[$tfn] = end($res);
            continue;
          }

          if (!empty($val)) {
            if ($tfn === 'id') {
              $all_query->condition($es->getEntityType()->getKey('id'), $val);
            }
            else {
              foreach ($this->flatten([$tfn => $val]) as $key => $subval) {
                $all_query->condition($key, $subval);
              }
            }
            $res[$tfn] = $all_query->execute() ?: [];
            if ($tfn === $last_value_field) {
              $found_last_selection = TRUE;
            }
          }
          else {
            $res[$tfn] = end($res);
          }
        }

        $count = count(end($res) ?: []);
        reset($res);
      }
      else {
        foreach (array_filter($this->flatten($all_query_values)) as $key => $value) {
          $all_query->condition($key, $value);
        }

        $res = $all_query->execute() ?: [];
        $count = count($res);
      }

      static::$cache[$cache_key] = $res;
    }

    return $res ?? [];
  }

  /**
   * Handles populating and updating the form, so Ajax can work properly.
   *
   * @param bool $isAjax
   *   Is this part of an ajax request?
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   The item that this element represents.
   * @param mixed $delta
   *   The field delta.
   * @param array $element
   *   The element render array.
   * @param array $form
   *   The entire form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $input
   *   The input to be used to populate the form. Leave blank to reset.
   *
   * @return void
   *   No return value.
   */
  public function updateOptions(bool $isAjax, FieldItemInterface $item = NULL, $delta, array &$element, array &$form, FormStateInterface $form_state, array $input = []) {
    if ($isAjax) {
      $form_state->setProcessInput(TRUE);
    }
    $field_name = $this->fieldDefinition->getName();

    $target_fields = $this->getFieldList();
    $target_field_definitions = $this->entityFieldManager->getFieldDefinitions($this->fieldDefinition->getSetting('target_type'), current($this->fieldDefinition->getSetting('handler_settings')['target_bundles']));
    $es = $this->entityTypeManager->getStorage($this->fieldDefinition->getSetting('target_type'));

    $parents = $form['#parents'];

    $id_prefix = implode('--', array_merge($parents, [
      $field_name,
      $delta,
      'wrapper',
    ]));
    $id_prefix = str_replace('_', '-', $id_prefix);
    $id_prefix = preg_replace("/[^0-9A-Za-z_\\-]+/", '-', $id_prefix);

    $element['#attached']['library'][] = 'monarch_data_entity/view_widget_notifier';
    $element['#prefix'] = "<div id=\"$id_prefix\">";
    $element['#suffix'] = '</div>';

    $ajax = [
      'callback' => [$this, 'ajax'],
      'disable-refocus' => FALSE,
      'event' => 'change',
      'wrapper' => $id_prefix,
      'progress' => [
        'type' => 'throbber',
      ],
    ];

    $orig_entity = $item->entity ?? NULL;

    $weights = $this->fieldDefinition->getThirdPartySetting('monarch_data_entity', 'weights');
    if (empty($weights)) {
      $weights = $this->getSetting('weights') ?? [];
    }

    $target_bundle = current($this->fieldDefinition->getSetting('handler_settings')['target_bundles']);
    $primary_keys = $this->getPrimaryKeys($target_bundle);

    if ($isAjax) {
      foreach ($target_fields as $target_field_name) {
        if (isset($element[$target_field_name][$target_field_name])) {
          $form_state->setValue($element[$target_field_name][$target_field_name]['#parents'], $element[$target_field_name][$target_field_name]['#value']);
        }
      }
    }

    foreach ($target_fields as $target_field_name) {
      $token = $weights[$target_field_name]['token'] ?? NULL ?: NULL;
      $enabled = $weights[$target_field_name]['enabled'] ?? FALSE ?: FALSE;

      // Visibility defaults to TRUE only for Primary Keys if not explicitly set.
      $is_primary_key = in_array($target_field_name, $primary_keys);
      $visibility = isset($weights[$target_field_name]['visibility']) ? $weights[$target_field_name]['visibility'] : $is_primary_key;

      $element[$target_field_name]['#type'] = 'html_tag';
      $element[$target_field_name]['#tag'] = 'div';

      $element[$target_field_name][$target_field_name]['#type'] = 'select';
      $element[$target_field_name][$target_field_name]['#title'] = $target_field_definitions[$target_field_name]->getLabel();

      if (is_numeric($this->getSetting('height')) && $this->getSetting('height') > 0) {
        $element[$target_field_name][$target_field_name]['#size'] = $this->getSetting('height');
      }
      $element[$target_field_name][$target_field_name]['#options'] = [];
      // Expose the target entity type on each select so the conditional_fields
      // handler can load the referenced entity to get the JSON option value.
      $element[$target_field_name][$target_field_name]['#target_type'] = $this->fieldDefinition->getSetting('target_type');

      if (!empty($orig_entity) && !empty($orig_entity->{$target_field_name}[$delta] ?? NULL) && !$orig_entity->{$target_field_name}[$delta]->isEmpty()) {
        $element[$target_field_name][$target_field_name]['#default_value'] = json_encode($orig_entity->{$target_field_name}[$delta]->getValue() ?? NULL);

        if (!$isAjax) {
          $input[$target_field_name][$target_field_name] = $element[$target_field_name][$target_field_name]['#default_value'];
        }
      }

      // Visibility controls the selector. Enabled (Display) is for the table.
      if (!$visibility) {
        $element[$target_field_name]['#attributes']['style'] = 'display:none;';
      } else {
        $element[$target_field_name]['#attributes']['style'] = 'display:inline-block;';
      }

      if ($token) {
        $element[$target_field_name][$target_field_name]['#after_build'][] = [
          $this,
          'afterBuildElement',
        ];
        $element[$target_field_name][$target_field_name]['#target_field_name'] = $target_field_name;
        $element[$target_field_name][$target_field_name]['#weights'] = $weights;
        $element[$target_field_name][$target_field_name]['#target_type'] = $this->fieldDefinition->getSetting('target_type');
      }

      $element[$target_field_name][$target_field_name]['#ajax'] = $ajax;

      if (is_null($target_field_definitions[$target_field_name] ?? NULL)) {
        $orig_name = $this->fieldDefinition->getName();
        throw new \Exception("Field widget settings are out of date for \"$orig_name\". Please update the widget settings to make this widget work.");
      }
    }

    $element['target_id'] = [
      '#type' => 'hidden',
      '#default_value' => '',
    ];

    $element['results'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['sigmaxim-fields-data-reference-results'],
        'data-field-name' => $this->fieldDefinition->getName(),
        'data-bundle' => $this->fieldDefinition->getTargetBundle(),
        'data-entity-type' => $this->fieldDefinition->getTargetEntityTypeId(),
      ],
    ];

    $element['#attached']['library'][] = 'monarch_data_entity/monarch_data_entity_widget';

    $element['#after_build'][] = [
      $this,
      'renderResultingEntity',
    ];
  }

  /**
   * The '#after_build' callback.
   */
  public function afterBuildElement(array $element, FormStateInterface $form_state) {
    if (!isset($element['#target_field_name'])) {
      return $element;
    }
    
    // Skip token resolution on configuration forms or if no entity form object exists.
    if (!($form_state->getFormObject() instanceof \Drupal\Core\Entity\ContentEntityFormInterface)) {
      return $element;
    }

    $target_field_name = $element['#target_field_name'];
    $weights = $element['#weights'] ?? $this->getSetting('weights') ?? [];
    $token = $weights[$target_field_name]['token'] ?? NULL ?: NULL;

    if ($token) {
      $form = $form_state->getCompleteForm();
      // call transformArgumentToken just to ensure the JS notifier attachment happens.
      $this->tokenService->transformArgumentToken($token, $element, NULL, $form, $form_state);
    }

    return $element;
  }

  /**
   * The '#after_build' callback.
   */
  public function renderResultingEntity(array $element, FormStateInterface $form_state) {
    $form = $form_state->getCompleteForm();
    $parents = [...$form['#parents'], $this->fieldDefinition->getName()];
    $delta = $element["#parents"][count($element['#parents']) - 2];
    $values = $form_state->getValue([...$parents, $delta, 'wrapper']) ?? NULL ?: [];
    $weights = $this->fieldDefinition->getThirdPartySetting('monarch_data_entity', 'weights');
    if (empty($weights)) {
      $weights = $this->getSetting('weights') ?? [];
    }

    $target_type = $this->fieldDefinition->getSetting('target_type');
    $target_bundle = current($this->fieldDefinition->getSetting('handler_settings')['target_bundles']);
    $target_field_definitions = $this->entityFieldManager->getFieldDefinitions($target_type, $target_bundle);

    $primary_keys = $this->getPrimaryKeys($target_bundle);

    $visible_values = [];
    foreach ($this->getFieldList() as $vis_tfn) {
      $is_pk = in_array($vis_tfn, $primary_keys, TRUE);
      $vis = isset($weights[$vis_tfn]['visibility']) ? $weights[$vis_tfn]['visibility'] : $is_pk;
      if ($vis && isset($values[$vis_tfn])) {
        $visible_values[$vis_tfn] = $values[$vis_tfn];
      }
    }
    $stages = $this->getEntityIdsFromInputs($visible_values, $count, TRUE);

    $stage_keys = ['bundle', ...$this->getFieldList()];
    $prev_stage = reset($stage_keys);
    $current_stage = next($stage_keys);

    $stop_populating = FALSE;

    while ($current_stage) {
      $target_field_name = current(explode('.', $current_stage));
      $is_primary_key = in_array($target_field_name, $primary_keys, TRUE);
      $visibility = isset($weights[$target_field_name]['visibility']) ? $weights[$target_field_name]['visibility'] : $is_primary_key;
      $token = $weights[$target_field_name]['token'] ?? NULL ?: NULL;

      if ($visibility && !$stop_populating) {
        $stage_ids = $stages[$prev_stage] ?? [];
        $options = [
          '' => $this->t('-- Choose Value for @label --', [
            '@label' => $target_field_definitions[$target_field_name]->getLabel(),
          ]),
        ];

        $options += $this->getStageOptions($target_type, $target_field_name, $stage_ids);

        // Handle Token resolution for auto-selection.
        if ($token) {
          $token_value = $this->tokenService->transformArgumentToken($token, $element, NULL, $form, $form_state);
          
          if ($token_value !== NULL && $token_value !== '') {
            $token_value = trim($token_value);
            
            // Check if this is an internal AJAX rebuild from this widget.
            // If the user manually selected something, we shouldn't force the token.
            $trigger = $form_state->getTriggeringElement();
            $my_parents = $element['#parents'];
            $trigger_parents = $trigger['#parents'] ?? [];
            $is_my_ajax = (array_slice($trigger_parents, 0, count($my_parents)) === $my_parents);

            // Match the token value against available options.
            $matched_val = NULL;
            foreach ($options as $opt_val => $opt_label) {
              if ($opt_val === '') continue;
              
              // Direct match (label or value).
              if (strcasecmp((string)$opt_label, $token_value) === 0 || strcasecmp((string)$opt_val, $token_value) === 0) {
                $matched_val = $opt_val;
                break;
              }
              
              // Key-based JSON match.
              $decoded = json_decode((string)$opt_val, TRUE);
              if (is_array($decoded)) {
                $sub_val = $decoded['value'] ?? $decoded['target_id'] ?? reset($decoded);
                if (is_scalar($sub_val) && strcasecmp((string)$sub_val, $token_value) === 0) {
                  $matched_val = $opt_val;
                  break;
                }
              }
            }

            if ($matched_val !== NULL && !$is_my_ajax) {
              $values[$target_field_name][$target_field_name] = $matched_val;
              // Explicitly set the value in form state so it sticks and cascades.
              $form_state->setValue([...$parents, $delta, 'wrapper', $target_field_name, $target_field_name], $matched_val);
              
              // Recalculate stages if we forced a value.
              $visible_values[$target_field_name] = $values[$target_field_name];
              $stages = $this->getEntityIdsFromInputs($visible_values, $count, TRUE);
            }
          }
        }

        $current_value = $values[$target_field_name][$target_field_name] ?? '';
        $is_selected = !empty($current_value);

        if (count($options) > 1) {
          $field_def = $target_field_definitions[$target_field_name];
          $elem_type = 'select';
          if ($field_def->getType() === 'boolean') {
            $elem_type = 'checkbox';
            unset($options['']); // Remove empty option for checkbox.
          }
          
          $element[$target_field_name][$target_field_name]['#type'] = $elem_type;
          $element[$target_field_name][$target_field_name]['#options'] = $options;
          $element[$target_field_name][$target_field_name]['#value'] = $current_value;
          $element[$target_field_name]['#attributes']['style'] = 'display:inline-block';
        }
        else {
           $element[$target_field_name]['#attributes']['style'] = 'display:none';
        }

        // Stop populating further dropdowns if this one doesn't have a selection yet.
        if (!$is_selected) {
          $stop_populating = TRUE;
        }
      }
      else {
        $element[$target_field_name]['#attributes']['style'] = 'display:none';
      }

      [$prev_stage, $current_stage] = [$current_stage, next($stage_keys)];
    }

    $last_stage = end($stages);
    $first_id = !empty($last_stage) ? current($last_stage) : NULL;
    $element['target_id']['#value'] = (!$stop_populating && $first_id) ? (string) $first_id : '';

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element['#field_item'] = $items[$delta] ?? NULL;
    $element['wrapper'] = [];
    $element['wrapper']['#field_item'] = $items[$delta] ?? NULL;
    // Expose the target entity type so the conditional_fields handler can use it.
    $element['#target_type'] = $this->fieldDefinition->getSetting('target_type');
    // Re-attach Drupal States after each widget AJAX so conditional_fields
    // watchers survive the wrapper DOM replacement.
    $element['#attached']['library'][] = 'sigmaxim_data_entity/sdr_cf_reattach';
    $parents = [...$form['#parents'], $this->fieldDefinition->getName()];
    $values = $form_state->getValue($parents)[$delta]['wrapper'] ?? [];

    $this->updateOptions(FALSE, $items[$delta], $delta, $element['wrapper'], $form, $form_state, $values);

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function form(FieldItemListInterface $items, array &$form, FormStateInterface $form_state, $get_delta = NULL) {
    $ret = parent::form($items, $form, $form_state, $get_delta);

    return $ret;
  }

  /**
   * Ajax callback.
   */
  public function ajax(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $elem = &$form;
    $elem_path = $triggering_element['#array_parents'];

    while (end($elem_path) !== 'wrapper') {
      array_pop($elem_path);
    }

    for ($key = reset($elem_path); $key !== FALSE; $key = next($elem_path)) {
      $tmp = &$elem[$key];
      $elem = &$tmp;
    }

    return $elem;
  }

  /**
   * {@inheritdoc}
   */
  public function errorElement(array $element, ConstraintViolationInterface $error, array $form, FormStateInterface $form_state) {
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $parents = [...$form['#parents'], $this->fieldDefinition->getName()];
    $user_input = $form_state->getUserInput();
    $user_input = NestedArray::getValue($user_input, $parents);

    $fields = $this->getFieldList();
    unset($fields['id']);
    unset($fields['label']);
    $fields = array_fill_keys($fields, TRUE);

    foreach ($values as $delta => &$value) {
      $input = $value['wrapper'];

      foreach (array_keys($input) as $key) {
        if ($fields[$key] ?? NULL) {
          continue;
        }

        unset($input[$key]);
      }

      $ids = $this->getEntityIdsFromInputs($input, $count);
      $values[$delta]['target_id'] = $count === 1 ? current($ids) : NULL;
      // Do not unset wrapper, it is needed for partial token resolution.
      // $values[$delta]['wrapper'] = $input;
    }

    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    $target_type = $field_definition->getSetting('target_type');
    $entity_type = \Drupal::entityTypeManager()->getDefinition($target_type);

    if ($entity_type) {
      $target_bundles = $field_definition->getSetting('handler_settings')['target_bundles'] ?? NULL;

      if (!empty($target_bundles) && is_array($target_bundles) && count($target_bundles) == 1) {
        return $entity_type->entityClassImplements(DataEntityInterface::class);
      }
    }

    return FALSE;
  }

}
