<?php

namespace Drupal\monarch_data_entity\Plugin\Field\FieldWidget;

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
 * Defines the 'monarch_data_entity_data_drill_down' field widget.
 *
 * @FieldWidget(
 *   id = "monarch_data_entity_data_drill_down",
 *   label = @Translation("Data Drill Down"),
 *   field_types = {
 *     "entity_reference",
 *   },
 * )
 */
class DataDrillDownWidget extends WidgetBase
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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    $interface = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $interface->entityTypeManager = $container->get('entity_type.manager');
    $interface->entityFieldManager = $container->get('entity_field.manager');
    $interface->renderer = $container->get('renderer');

    return $interface;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings()
  {
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
  public function settingsForm(array $form, FormStateInterface $form_state)
  {
    $element['height'] = [
      '#type' => 'number',
      '#title' => $this->t('Height'),
      '#description' => $this->t('Height of the select widgets. A height of 1 will use dropdown widgets instead.'),
      '#min' => 1,
      '#default_value' => max(1, $this->getSetting('height') ?? 0 ?: 0),
    ];

    $element['weights'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Field'),
        $this->t('Weight'),
        $this->t('Token'),
        $this->t('Enabled'),
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

    $weights = $this->getSetting('weights') ?? [];
    $rows = [];

    $target_type = $this->fieldDefinition->getSetting('target_type');
    $handler_settings = $this->fieldDefinition->getSetting('handler_settings');
    $target_bundles = $handler_settings['target_bundles'] ?? [];
    $target_bundle = !empty($target_bundles) ? current($target_bundles) : NULL;

    if ($target_bundle) {
      $target_field_definitions = $this->entityFieldManager->getFieldDefinitions($target_type, $target_bundle);
    } else {
      $target_field_definitions = $this->entityFieldManager->getBaseFieldDefinitions($target_type);
    }

    foreach ($target_field_definitions as $field_name => $target_field_definition) {
      if ($field_name != 'id' && $field_name != 'label' && $target_field_definition instanceof BaseFieldDefinition) {
        continue;
      }

      $rows[$field_name] = [
        $target_field_definition->getLabel(),
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
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary()
  {
    return [];
  }

  /**
   * Get field list.
   */
  public function getFieldList()
  {
    $field_weights = $this->getSetting('weights') ?? [];

    uasort($field_weights, function ($a, $b) {
      return intval($a['weight'] ?? 0) - intval($b['weight'] ?? 0);
    });

    $target_field_definitions = $this->entityFieldManager->getFieldDefinitions($this->fieldDefinition->getSetting('target_type'), current($this->fieldDefinition->getSetting('handler_settings')['target_bundles']));

    return empty($field_weights) ? array_keys($target_field_definitions) : array_keys($field_weights);
  }

  /**
   * Flatten with key path.
   */
  protected function flatten(array $value, string $path = NULL, array &$ret = [])
  {
    foreach ($value as $key => $subvalue) {
      $subpath = empty($path) ? $key : $path . '.' . $key;

      if (is_array($subvalue)) {
        $this->flatten($subvalue, $subpath, $ret);
      } else {
        $ret[$subpath] = $subvalue;
      }
    }

    return $ret;
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
  public function updateOptions(bool $isAjax, FieldItemInterface $item = NULL, $delta, array &$element, array &$form, FormStateInterface $form_state, array $input = [])
  {
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

    $element['target-id-display']['#type'] = 'html_tag';
    $element['target-id-display']['#tag'] = 'div';

    foreach ($target_fields as $target_field_name) {
      $element[$target_field_name]['#type'] = 'html_tag';
      $element[$target_field_name]['#tag'] = 'div';

      $element[$target_field_name][$target_field_name]['#title'] = $target_field_definitions[$target_field_name]->getLabel();
      $element[$target_field_name][$target_field_name]['#type'] = 'select';
      if (is_numeric($this->getSetting('height')) && $this->getSetting('height') > 0) {
        $element[$target_field_name][$target_field_name]['#size'] = $this->getSetting('height');
      }
      $element[$target_field_name][$target_field_name]['#options'] = [];
      $element[$target_field_name][$target_field_name]['#default_value'] = $input[$target_field_name][$target_field_name] ?? NULL;
      $element[$target_field_name][$target_field_name]['#ajax'] = $ajax;
    }

    $element['clear-input']['#type'] = 'html_tag';
    $element['clear-input']['#tag'] = 'div';
    $element['clear-input']['#attributes']['style'] = 'display:inline-block';
    $element['clear-input']['clear-input']['#type'] = 'button';
    $element['clear-input']['clear-input']['#name'] = preg_replace("/[^0-9A-Za-z_]/", '-', 'clear-drilldown-input.' . $this->fieldDefinition->getOriginalId() . '.' . $delta);
    $element['clear-input']['clear-input']['#value'] = $this->t('Clear');
    $element['clear-input']['clear-input']['#attributes'] = [
      'class' => ['button--extrasmall'],
      'style' => 'margin:0 0.25em;vertical-align:bottom',
    ];
    $element['clear-input']['clear-input']['#ajax'] = [
      'callback' => [$this, 'clearInputAjax'],
      'event' => 'click',
      'disable-refocus' => FALSE,
      'wrapper' => $id_prefix,
      'progress' => [
        'type' => 'throbber',
      ],
    ];

    if ($isAjax) {
      foreach ($target_fields as $target_field_name) {
        $element[$target_field_name][$target_field_name]['#value'] = $input[$target_field_name][$target_field_name] ?? NULL;
        $form_state->setValue($element[$target_field_name][$target_field_name]['#parents'], $element[$target_field_name][$target_field_name]['#value']);
      }
    }

    $query_values = [];
    $query_values['bundle'] = current($this->fieldDefinition->getSetting('handler_settings')['target_bundles']);

    if (!$isAjax) {
      $id = $item->target_id ?? NULL;
    } else {
      $id = $input['id']['id'] ?? NULL;
    }

    if (empty($id)) {
      $all_query_values = $query_values;

      foreach ($target_fields as $target_field_name) {
        if ($input[$target_field_name][$target_field_name] ?? NULL) {
          $input_field_value = $target_field_name !== 'id' ? unserialize($input[$target_field_name][$target_field_name]) : $input[$target_field_name][$target_field_name];
          $all_query_values[$target_field_name] = $input_field_value;
        }
      }

      $all_query = $es->getQuery()->accessCheck(FALSE);

      foreach (array_filter($this->flatten($all_query_values)) as $key => $value) {
        $all_query->condition($key, $value);
      }

      $count = $all_query->count()->execute();

      if ($count == 1) {
        $all_query = $es->getQuery()->accessCheck(FALSE);

        foreach (array_filter($this->flatten($all_query_values)) as $key => $value) {
          $all_query->condition($key, $value);
        }

        $id = current($all_query->execute());
      }
    }

    if ($id) {
      if ($display_entity = $es->load($id)) {
        $element['target-id-display']['#attributes']['style'] = 'display:inline-block';
        $element['target-id-display']['text']['#markup'] = $display_entity->toLink()->toString();
      }

      $input['id']['id'] = $id;

      if ($item) {
        $element['id']['id']['#default_value'] = $id;
      } else {
        $element['id']['id']['#default_value'] = $element['id']['id']['#value'] = $id;
        $form_state->setValue($element['id']['id']['#parents'], $element['id']['id']['#value']);
      }
    } else {
      $element['target-id-display']['#attributes']['style'] = 'display:none';
    }

    $hide = !empty($id);

    foreach ($target_fields as $target_field_name) {
      if ($hide) {
        $element[$target_field_name]['#attributes']['style'] = 'display:none';
      } else {
        $element[$target_field_name]['#attributes']['style'] = 'display:inline-block';
      }

      $filtered_query_values = array_filter($this->flatten($query_values));
      $ids = $es->getQuery()
        ->accessCheck(FALSE)
        ->condition('bundle', $query_values['bundle'])
        ->range(0, 500)
        ->execute();
      $entities = $es->loadMultiple($ids);

      if (is_null($target_field_definitions[$target_field_name])) {
        $orig_name = $this->fieldDefinition->getOriginalId();
        throw new \Exception("Field widget settings are out of date for \"$orig_name\". Please update the widget settings to make this widget work.");
      }

      $element[$target_field_name][$target_field_name]['#options'] = [
        '' => $this->t('-- Choose Value for @label --', [
          '@label' => $target_field_definitions[$target_field_name]->getLabel(),
        ]),
      ];

      foreach ($entities as $entity) {
        /** @var \Drupal\monarch_data_entity\DataEntityInterface $entity */
        if ($entity->hasField($target_field_name)) {
          foreach ($entity->get($target_field_name) as $field_item) {
            if ($field_item->isEmpty()) {
              continue;
            }

            $evb = $this->entityTypeManager->getViewBuilder($this->fieldDefinition->getSetting('target_type'));
            $r = $evb->viewFieldItem($field_item, [
              'label_display' => 'none',
            ]);

            if ($target_field_name === 'id') {
              $element[$target_field_name][$target_field_name]['#options'][$field_item->value] = strip_tags($this->renderer->renderPlain($r));
            } else {
              $element[$target_field_name][$target_field_name]['#options'][serialize($field_item->getValue())] = strip_tags($this->renderer->renderPlain($r));
            }
          }
        }
      }

      if ($input[$target_field_name][$target_field_name] ?? NULL) {
        $input_field_value = $target_field_name !== 'id' ? unserialize($input[$target_field_name][$target_field_name]) : $input[$target_field_name][$target_field_name];
        $query_values[$target_field_name] = $input_field_value;
      } else {
        $hide = TRUE;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state)
  {
    $element['#field_item'] = $items[$delta] ?? NULL;
    $element['wrapper'] = [];
    $element['wrapper']['#field_item'] = $items[$delta] ?? NULL;
    $parents = [...$form['#parents'], $this->fieldDefinition->getName()];
    $values = $form_state->getValue($parents)[$delta]['wrapper'] ?? [];

    $this->updateOptions(FALSE, $items[$delta], $delta, $element['wrapper'], $form, $form_state, $values);

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function form(FieldItemListInterface $items, array &$form, FormStateInterface $form_state, $get_delta = NULL)
  {
    $ret = parent::form($items, $form, $form_state, $get_delta);

    return $ret;
  }

  /**
   * Ajax callback.
   */
  public function ajax(array &$form, FormStateInterface $form_state)
  {
    $triggering_element = $form_state->getTriggeringElement();
    $elem = &$form;
    $elem_path = $triggering_element['#array_parents'];

    while (end($elem_path) !== 'wrapper') {
      array_pop($elem_path);
    }

    for ($key = reset($elem_path); $key !== FALSE; $key = next($elem_path)) {
      $elem = &$elem[$key];
    }

    $delta = $elem_path[count($elem_path) - 2];

    $parents = [...$form['#parents'], $this->fieldDefinition->getName()];
    $values = $form_state->getValue($parents)[$delta]['wrapper'] ?? [];

    $this->updateOptions(TRUE, $delta['#field_item'], $delta, $elem, $form, $form_state, $values);

    return $elem;
  }

  /**
   * Ajax callback.
   */
  public function clearInputAjax(array &$form, FormStateInterface $form_state)
  {
    $triggering_element = $form_state->getTriggeringElement();
    $elem = &$form;
    $elem_path = $triggering_element['#array_parents'];

    while (end($elem_path) !== 'wrapper') {
      array_pop($elem_path);
    }

    for ($key = reset($elem_path); $key !== FALSE; $key = next($elem_path)) {
      $elem = &$elem[$key];
    }

    $delta = $elem_path[count($elem_path) - 2];

    $this->updateOptions(TRUE, $delta['#field_item'], $delta, $elem, $form, $form_state);

    return $elem;
  }

  /**
   * {@inheritdoc}
   */
  public function errorElement(array $element, ConstraintViolationInterface $error, array $form, FormStateInterface $form_state)
  {
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state)
  {
    foreach ($values as $delta => &$value) {
      if ($value['wrapper']['id']['id'] ?? NULL) {
        $value['target_id'] = $value['wrapper']['id']['id'] ?? NULL;
        unset($value['wrapper']);
      } else {
        unset($values[$delta]);
      }
    }

    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition)
  {
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
