<?php

namespace Drupal\monarch_data_entity\Plugin\Field\FieldWidget;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Config\Entity\ThirdPartySettingsInterface;
use Drupal\monarch_data_entity\TokenService;
use Drupal\views\Entity\View;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the 'monarch_data_entity_view' field widget.
 *
 * @FieldWidget(
 *   id = "monarch_data_entity_view",
 *   label = @Translation("View"),
 *   multiple_values = TRUE,
 *   field_types = {
 *     "entity_reference",
 *   },
 * )
 */
class ViewWidget extends WidgetBase
{

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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
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
  public static function defaultSettings()
  {
    return parent::defaultSettings() + [
      'view' => NULL,
      'args' => [],
      'weights' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state)
  {
    $options = [];
    $build = parent::settingsForm($form, $form_state) + [
      '#prefix' => '<div id="extra-options-wrapper">',
      '#suffix' => '</div>',
    ];
    $views = View::loadMultiple();
    $target_type = $this->fieldDefinition->getFieldStorageDefinition()->getSetting('target_type') ?? $this->fieldDefinition->getSetting('target_type');
    $storage = !empty($target_type) ? $this->entityTypeManager->getStorage($target_type) : NULL;
    $base_table = NULL;
    if ($storage instanceof \Drupal\Core\Entity\Sql\SqlContentEntityStorage) {
      $base_table = $storage->getBaseTable();
    }

    foreach ($views as $view_id => $view) {
      $view_base_table = $view->get('base_table');
      foreach (($view->get('display') ?? []) as $display_id => $display_options) {
        if ($display_options['display_plugin'] == 'embed' && (empty($base_table) || $view_base_table == $base_table)) {
          $options[$view->get('label')][$view_id . '.' . $display_id] = $display_options['display_title'] . ' (' . $view_id . '.' . $display_id . ')';
        }
      }
    }

    $ajax = [
      'callback' => [$this, 'ajaxSettings'],
      'disable-refocus' => FALSE,
      'event' => 'change',
      'wrapper' => 'extra-options-wrapper',
      'progress' => [
        'type' => 'throbber',
      ],
    ];

    $values = $form_state->getValues()['fields'][$this->fieldDefinition->getName()]['settings_edit_form']['settings'] ?? $this->settings ?? NULL;

    $build['view'] = [
      '#type' => 'select',
      '#title' => $this->t('View'),
      '#options' => $options,
      '#empty_option' => $this->t('-- None --'),
      '#empty_value' => '',
      '#default_value' => $this->settings['view'] ?? NULL,
      '#ajax' => $ajax,
    ];

    if ($values['view'] ?? NULL) {
      [$selected_view_id, $selected_display_id] = explode('.', $values['view']);

      if ($selected_view = $views[$selected_view_id] ?? NULL) {
        $args = $this->getArgs($selected_view, $selected_display_id);

        if (!empty($args)) {
          $build['args']['#tree'] = TRUE;
          foreach ($args as $arg_name => $arg_settings) {
            $build['args'][$arg_name]['value'] = [
              '#type' => 'textfield',
              '#title' => $this->t('@arg_name value', ['@arg_name' => $arg_name]),
              '#default_value' => $values['args'][$arg_name]['value'] ?? NULL,
            ];

            $build['args'][$arg_name]['transformer'] = [
              '#type' => 'select',
              '#title' => $this->t('@arg_name type', ['@arg_name' => $arg_name]),
              '#options' => $this->transformTypeOptions(),
              '#empty_value' => NULL,
              '#empty_option' => $this->t('Literal'),
              '#default_value' => $values['args'][$arg_name]['transformer'] ?? NULL,
            ];
          }
        }
      }
    }

    $build['view_arguments'] = [];
    $build['weights'] = [
      '#type' => 'table',
      '#tree' => TRUE,
      '#header' => [
        $this->t('Column'),
        $this->t('Weight'),
        $this->t('Filter Token'),
        $this->t('Display'),
      ],
      '#attributes' => [
        'id' => 'view-widget-field-order',
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'table-sort-weight',
        ],
      ],
    ];

    if (empty($target_type)) {
      $build['weights']['#empty'] = $this->t('Target type not found.');
      return $build;
    }

    // We use third party settings from monarch_data_entity now.
    if ($this->fieldDefinition instanceof ThirdPartySettingsInterface) {
      $weights = $this->fieldDefinition->getThirdPartySetting('monarch_data_entity', 'weights');
    }
    if (empty($weights)) {
      $weights = $this->getSetting('weights') ?? [];
    }
    $rows = [];

    $target_type = $this->fieldDefinition->getFieldStorageDefinition()->getSetting('target_type');
    $handler_settings = $this->fieldDefinition->getSetting('handler_settings');
    $target_bundles = $handler_settings['target_bundles'] ?? [];
    $target_bundle = !empty($target_bundles) ? reset($target_bundles) : NULL;

    // If no specific bundle is selected, try to get all fields for the entity type.
    if ($target_bundle) {
      $target_field_definitions = $this->entityFieldManager->getFieldDefinitions($target_type, $target_bundle);
    } else {
      $target_field_definitions = $this->entityFieldManager->getBaseFieldDefinitions($target_type);
    }
    $entity_definition = $this->entityTypeManager->getDefinition($target_type);
    $primary_keys = $entity_definition ? $entity_definition->getKey('id') : NULL;

    foreach ($target_field_definitions as $field_name => $target_field_definition) {
      $label = $target_field_definition->getLabel();
      if ($field_name == $primary_keys) {
        $label .= ' (primary key)';
      }
      $rows[$field_name] = [
        $label,
        $weights[$field_name]['weight'] ?? 0,
        $weights[$field_name]['token'] ?? '',
        $weights[$field_name]['enabled'] ?? !($target_field_definition instanceof BaseFieldDefinition),
      ];
    }

    uasort($rows, function ($a, $b) {
      $a_weight = isset($a[1]) ? (int) $a[1] : 0;
      $b_weight = isset($b[1]) ? (int) $b[1] : 0;
      return $a_weight - $b_weight;
    });

    foreach ($rows as $name => [$title, $weight, $token, $enabled]) {
      $build['weights'][$name] = [
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
      ];
    }

    return $build;
  }

  /**
   * Ajax callback.
   */
  public function ajaxSettings(array &$form, FormStateInterface $form_state)
  {
    return $form['fields'][$this->fieldDefinition->getName()]['plugin']['settings_edit_form']['settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary()
  {
    return ['View: ' . ($this->settings['view'] ?? '')];
  }

  /**
   * Get a list of transform types.
   *
   * @return array
   *   A list of transform options.
   */
  public function transformTypeOptions()
  {
    return [
      'token' => $this->t('Query Token'),
    ];
  }

  /**
   * Get the view chosen in the options.
   *
   * @return \Drupal\views\Entity\View
   *   The view chosen in the options.
   */
  public function getView(): ?View
  {
    if ($this->settings['view'] ?? NULL ?: NULL) {
      [$view_id, $display_id] = explode('.', $this->settings['view'], 2);

      if ($view = View::load($view_id)) {
        return $view;
      }
    }

    return NULL;
  }

  /**
   * Get the display ID chosen in the options.
   *
   * @return string
   *   The display ID chosen in the options.
   */
  public function getDisplayId(): ?string
  {
    if ($this->settings['view'] ?? NULL ?: NULL) {
      [$view_id, $display_id] = explode('.', $this->settings['view'], 2);
      return $display_id;
    }

    return NULL;
  }

  /**
   * Get view arguments.
   *
   * @param \Drupal\views\Entity\View|null $view
   *   The view to get arguments for.
   * @param string|null $display_id
   *   The display ID to get arguments for.
   *
   * @return array
   *   The argument list.
   */
  public function getArgs(?View $view = NULL, $display_id = NULL)
  {
    if (!$view) {
      $view = $this->getView();
    }
    if (!$display_id) {
      $display_id = $this->getDisplayId();
    }

    if ($view && $display_id) {
      $default_display = $view->getDisplay('default');
      $display = $view->getDisplay($display_id);
      $args = ($display['display_options']['defaults']['arguments'] ?? TRUE) ? $default_display['display_options']['arguments'] : $display['display_options']['arguments'];
      return $args ?? [];
    }

    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state)
  {
    $ids = [];

    $storage = $this->fieldDefinition->getFieldStorageDefinition();

    foreach ($items as $d => $item) {
      $ids[$d] = $item->target_id ?? '';
    }

    $element['label'] = [];

    $element['target_id'] = [
      '#type' => 'view_input',
      '#cardinality' => $storage->getCardinality(),
      '#default_value' => implode(',', $ids),
      '#attributes' => ['class' => ['view-input-selector']],
    ];

    if ($view = $this->getView()) {
      $element['label'] = [
        '#theme' => 'form_element_label',
        '#title' => $this->fieldDefinition->getLabel(),
        '#title_display' => 'before',
        '#required' => $this->fieldDefinition->isRequired(),
      ];

      $element['target_id']['#view_name'] = $view->id();
      $element['target_id']['#view_display_id'] = $this->getDisplayId();
      $element['target_id']['#view_args'] = [];
      $element['#process'][] = [$this, 'processElement'];
      $element['#after_build'][] = [$this, 'afterBuildElement'];

      // Attach our custom library and results wrapper.
      $element['#attached']['library'][] = 'monarch_data_entity/monarch_data_entity_widget';

      $element['results'] = [
        '#type' => 'container',
        '#weight' => 9999,
        '#attributes' => [
          'id' => str_replace('_', '-', $this->fieldDefinition->getName()) . '-' . $delta . '-results',
          'class' => ['sigmaxim-fields-data-reference-results'],
          'data-field-name' => $this->fieldDefinition->getName(),
          'data-bundle' => $this->fieldDefinition->getTargetBundle(),
          'data-entity-type' => $this->fieldDefinition->getTargetEntityTypeId(),
        ],
      ];
    }

    return $element;
  }

  /**
   * The '#process' callback.
   */
  public function processElement(array $element, FormStateInterface $form_state)
  {
    $form = $form_state->getCompleteForm();
    $this_field_name = $this->fieldDefinition->getName();
    $needs_ajax = FALSE;

    // Check Contextual Filter argument tokens.
    foreach ($this->getArgs() as $arg_id => $arg_config) {
      if (isset($this->settings['args'][$arg_id]['value'])) {
        if (
          ($value_transformer = $this->settings['args'][$arg_id]['transformer'] ?? NULL) &&
          $value_transformer === 'token'
        ) {
          $needs_ajax = TRUE;
        }
      }
    }

    // Check for column level filter tokens.
    $weights = $this->fieldDefinition->getThirdPartySetting('monarch_data_entity', 'weights') ?: ($this->getSetting('weights') ?: []);
    foreach ($weights as $col_name => $info) {
      if (!empty($info['token'])) {
        $needs_ajax = TRUE;
        break;
      }
    }

    // Set up AJAX wrapper and handler if Contextual Filter tokens are present.
    if ($needs_ajax) {
      $form[$this_field_name]['#prefix'] = "<div id=\"$this_field_name-wrapper\">";
      $form[$this_field_name]['#suffix'] = "</div>";

      $element['target_id']['#ajax'] = [
        'callback' => [$this, 'ajax'],
        'disable-refocus' => TRUE,
        'event' => 'change',
        'wrapper' => "$this_field_name-wrapper",
        'element' => $this_field_name,
        'progress' => [
          'type' => 'none',
        ],
      ];
    }

    return $element;
  }

  /**
   * The '#after_build' callback.
   */
  public function afterBuildElement(array $element, FormStateInterface $form_state)
  {
    $form = $form_state->getCompleteForm();
    $this_field_name = $this->fieldDefinition->getName();

    // Ensure the target_id has a stable ID for the JS Notifier.
    if (!isset($element['target_id']['#id'])) {
      $element['target_id']['#id'] = str_replace('_', '-', $this_field_name) . '-view-selector';
    }

    // Resolve Contextual Filter Arguments.
    foreach ($this->getArgs() as $arg_id => $arg_config) {
      if (isset($this->settings['args'][$arg_id]['value'])) {
        if (($value_transformer = $this->settings['args'][$arg_id]['transformer'] ?? NULL) && $value_transformer === 'token') {
          $resolved = $this->transformArgumentValue($this->settings['args'][$arg_id]['value'], $value_transformer, $element['target_id'], $arg_config, $form, $form_state);
          if ($resolved !== NULL) {
            $element['target_id']['#view_args'][] = $resolved;
          }
        } else {
          $element['target_id']['#view_args'][] = $this->settings['args'][$arg_id]['value'];
        }
      }
    }

    // Resolve column level filter tokens for server side View filtering.
    $weights = $this->fieldDefinition->getThirdPartySetting('monarch_data_entity', 'weights') ?: ($this->getSetting('weights') ?: []);
    $filter_tokens = [];
    foreach ($weights as $col_name => $info) {
      if (!empty($info['token'])) {
        $resolved = $this->tokenService->transformArgumentToken(
          $info['token'],
          $element['target_id'],
          NULL,
          $form,
          $form_state
        );
        if ($resolved !== NULL && $resolved !== '') {
          $filter_tokens[$col_name] = $resolved;
        }
      }
    }
    if (!empty($filter_tokens)) {
      $element['target_id']['#view_filter_tokens'] = $filter_tokens;
    }

    return $this->renderResultingEntity($element, $form_state);
  }

  /**
   * Renders the resulting entity as a table of enabled fields.
   */
  public function renderResultingEntity(array $element, FormStateInterface $form_state)
  {
    // We use third party settings from monarch_data_entity now.
    $weights = [];
    if ($this->fieldDefinition instanceof ThirdPartySettingsInterface) {
      $weights = $this->fieldDefinition->getThirdPartySetting('monarch_data_entity', 'weights');
    }
    if (empty($weights)) {
      $weights = $this->getSetting('weights');
    }

    // Get the selected IDs from the view_input.
    $parents = $element['#parents'];
    $form_values = $form_state->getValue($parents);
    $ids = !empty($form_values['target_id']) ? explode(',', $form_values['target_id']) : [];
    $id = reset($ids);

    if (!empty($id)) {
      $target_type = $this->fieldDefinition->getSetting('target_type');
      $entity = $this->entityTypeManager->getStorage($target_type)->load($id);

      if ($entity instanceof FieldableEntityInterface) {
        $header = [];
        $row = [];

        // If weights are empty or none of the enabled weights exist on the current entity, 
        // we treat them as stale and regenerate them dynamically.
        $has_valid_field = FALSE;
        if (!empty($weights)) {
          foreach ($weights as $fw => $fi) {
            if (!empty($fi['enabled']) && $entity->hasField($fw)) {
              $has_valid_field = TRUE;
              break;
            }
          }
        }

        if (empty($weights) || !$has_valid_field) {
          $excluded_fields = [
            'id',
            'uuid',
            'bundle',
            'label',
            'status',
            'created',
            'changed',
            'feeds_item',
            'default_langcode',
            'revision_translation_affected'
          ];
          $new_weights = [];
          $weight_counter = 0;
          foreach ($entity->getFields() as $tfn => $field_item) {
            if (!in_array($tfn, $excluded_fields)) {
              $new_weights[$tfn] = [
                'enabled' => TRUE,
                'title' => (string) $field_item->getFieldDefinition()->getLabel(),
                'weight' => $weight_counter++,
              ];
            }
          }
          $weights = $new_weights;
        }

        uasort($weights, 'Drupal\Component\Utility\SortArray::sortByWeightElement');

        foreach ($weights as $field_name => $info) {
          if (!empty($info['enabled'])) {
            $label = $info['title'] ?? $field_name;

            if ($entity->hasField($field_name)) {
              $header[] = $label;
              $row[] = $entity->get($field_name)->view(['label' => 'hidden']);
            } elseif ($field_name == '__pkc') {
              $header[] = $label;
              $row[] = ['#markup' => '']; // Placeholder for __pkc
            }
          }
        }

        if (!empty($header)) {
          $rows = [];
          foreach ($header as $i => $label) {
            $rows[] = [
              ['#markup' => (string) $label],
              $row[$i],
            ];
          }
          $element['results']['table'] = [
            '#type' => 'table',
            '#rows' => $rows,
            '#attributes' => ['class' => ['sigmaxim-fields-results-table', 'vertical-table']],
          ];
        }
      }
    }

    return $element;
  }

  /**
   * Transforms argument values according to the settings.
   *
   * @param string|null $value
   *   The transformation value.
   * @param string|null $transformer
   *   The transformation type.
   * @param array $element
   *   The element to transform the value for.
   * @param string|null $argument_settings
   *   The settings for this argument.
   * @param array $form
   *   The form containing the element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The state of the form.
   *
   * @return string|null
   *   The transformed value.
   */
  public function transformArgumentValue(?string $value, ?string $transformer, array &$element, ?array $argument_settings, array &$form, FormStateInterface $form_state): ?string
  {
    if ($transformer === 'token') {
      $ret = $this->tokenService->transformArgumentToken($value, $element, $argument_settings, $form, $form_state);

      return $ret;
    }

    return empty($transformer) ? ($value ?: NULL) : NULL;
  }

  /**
   * Ajax callback.
   */
  public function ajax(array &$form, FormStateInterface $form_state)
  {
    return $form[$form_state->getTriggeringElement()['#ajax']['element']];
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state)
  {
    $ids = array_filter(explode(',', $values['target_id'] ?? ''));

    unset($values['target_id']);

    foreach ($ids as $d => $id) {
      $values[$d]['target_id'] = $id;
    }

    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition)
  {
    return TRUE;
  }

}
