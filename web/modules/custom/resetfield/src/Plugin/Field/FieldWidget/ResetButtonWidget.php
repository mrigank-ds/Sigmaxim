<?php

namespace Drupal\resetfield\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\Token;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * @FieldWidget(
 *   id = "reset_button_widget",
 *   label = @Translation("Reset Button Widget"),
 *   field_types = {
 *     "reset_button_field"
 *   }
 * )
 */
class ResetButtonWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  protected $token;

  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, Token $token) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->token = $token;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('token')
    );
  }

  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $field_settings = $this->getFieldSettings();
    $widget_settings = $this->getSettings();
    
    $button_text = !empty($widget_settings['button_text']) 
      ? $widget_settings['button_text'] 
      : ($field_settings['button_label'] ?? 'Reset');
    
    $fields_to_reset = !empty($widget_settings['fields_to_reset'])
      ? $widget_settings['fields_to_reset']
      : ($field_settings['fields_list'] ?? '');

    $fields_array = $this->parseFieldsList($fields_to_reset);
    
    $entity = $items->getEntity();
    $entity_type_id = $entity->getEntityTypeId();
    
    $processed_fields = [];
    $default_values = [];
    
    foreach ($fields_array as $field_pattern) {
      if (preg_match('/\[([^:\]]+):([^\]]+)\]/', $field_pattern, $matches)) {
        // Format: [entity_type:field_name]
        $token_entity_type = $matches[1];
        $field_name = $matches[2];
        
        if (strpos($field_name, ':') !== FALSE) {
          $field_parts = explode(':', $field_name);
          $field_name = $field_parts[0];
        }
        
        $field_name = trim($field_name);
        
        if ($token_entity_type === $entity_type_id || $token_entity_type === 'entity') {
          $processed_fields[] = $field_name;
          $this->extractDefaultValue($entity, $field_name, $default_values);
        }
      }
      elseif (preg_match('/^\[([^\]]+)\]$/', trim($field_pattern), $matches)) {
        // Format: [field_name] — strip brackets, treat as plain field name
        $field_name = trim($matches[1]);
        $processed_fields[] = $field_name;
        $this->extractDefaultValue($entity, $field_name, $default_values);
      }
      else {
        // Format: field_name — plain field name without brackets
        $field_name = trim($field_pattern);
        $processed_fields[] = $field_name;
        $this->extractDefaultValue($entity, $field_name, $default_values);
      }
    }

    $processed_fields = array_unique(array_filter($processed_fields));

    $button_style = $field_settings['button_style'] ?? 'rectangular';
    $reset_confirmation = $field_settings['reset_confirmation'] ?? FALSE;
    $unique_id = 'reset-btn-' . uniqid();

    $element['reset_button'] = [
      '#type' => 'html_tag',
      '#tag' => 'button',
      '#value' => $button_text,
      '#attributes' => [
        'type' => 'button',
        'class' => ['reset-button', 'reset-button-' . $button_style],
        'id' => $unique_id,
        'data-fields-to-reset' => implode(',', $processed_fields),
        'data-confirmation' => $reset_confirmation ? 'true' : 'false',
        'data-default-values' => json_encode($default_values),
        'data-entity-type' => $entity_type_id,
      ],
    ];

    $element['#attached']['library'][] = 'resetfield/reset_button';
    $element['#attached']['drupalSettings']['resetfield'][$items->getName()] = [
      'fields' => $processed_fields,
      'confirmation' => $reset_confirmation,
      'defaultValues' => $default_values,
    ];

    return $element;
  }

  /**
   * Extracts default value from entity field and populates $default_values array.
   */
  protected function extractDefaultValue($entity, $field_name, array &$default_values) {
    if ($entity->hasField($field_name)) {
      try {
        $field_item_list = $entity->get($field_name);
        if (!$field_item_list->isEmpty()) {
          $field_value = $field_item_list->getValue();
          if (isset($field_value[0]['value'])) {
            $default_values[$field_name] = $field_value[0]['value'];
          }
          elseif (isset($field_value[0]['target_id'])) {
            $default_values[$field_name] = $field_value[0]['target_id'];
          }
        }
      }
      catch (\Exception $e) {
        \Drupal::logger('resetfield')->warning('Error: @msg', ['@msg' => $e->getMessage()]);
      }
    }
  }

  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    return [];
  }

  protected function parseFieldsList($fields_string) {
    if (empty($fields_string)) {
      return [];
    }
    // Split on commas or newlines, but preserve tokens like [type:field]
    // Use a regex-aware split to avoid splitting inside brackets
    $fields = preg_split('/[\r\n]+|,(?![^\[]*\])/', $fields_string);
    $fields = array_map('trim', $fields);
    return array_filter($fields);
  }

  public static function defaultSettings() {
    return [
      'button_text' => '',
      'fields_to_reset' => '',
    ] + parent::defaultSettings();
  }

  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);
    $elements['button_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Button Text'),
      '#default_value' => $this->getSetting('button_text'),
    ];
    $elements['fields_to_reset'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Fields to Reset'),
      '#default_value' => $this->getSetting('fields_to_reset'),
      '#rows' => 4,
    ];
    return $elements;
  }

  public function settingsSummary() {
    $summary = [];
    $button_text = $this->getSetting('button_text');
    if (!empty($button_text)) {
      $summary[] = $this->t('Button: @text', ['@text' => $button_text]);
    }
    return $summary;
  }
}