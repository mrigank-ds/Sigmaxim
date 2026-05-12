<?php

namespace Drupal\resetfield\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Plugin implementation of the 'reset_button_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "reset_button_formatter",
 *   label = @Translation("Reset Button Formatter"),
 *   field_types = {
 *     "reset_button_field"
 *   }
 * )
 */
class ResetButtonFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'display_mode' => 'button_only',
      'show_fields_list' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    $elements['display_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Display Mode'),
      '#options' => [
        'button_only' => $this->t('Button only'),
        'button_with_info' => $this->t('Button with information'),
        'hidden' => $this->t('Hidden (no display)'),
      ],
      '#default_value' => $this->getSetting('display_mode'),
      '#description' => $this->t('Choose how to display the reset button field.'),
    ];

    $elements['show_fields_list'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show list of fields'),
      '#default_value' => $this->getSetting('show_fields_list'),
      '#description' => $this->t('Display a list of fields that will be reset.'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    
    $display_mode = $this->getSetting('display_mode');
    $summary[] = $this->t('Display: @mode', [
      '@mode' => $display_mode,
    ]);

    if ($this->getSetting('show_fields_list')) {
      $summary[] = $this->t('Showing fields list');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $display_mode = $this->getSetting('display_mode');

    // If hidden, return empty
    if ($display_mode === 'hidden') {
      return $elements;
    }

    $field_definition = $items->getFieldDefinition();
    $field_settings = $field_definition->getSettings();
    
    $button_text = $field_settings['button_label'] ?? 'Reset';
    $fields_to_reset = $field_settings['fields_list'] ?? '';
    
    $fields_array = array_filter(array_map('trim', explode(',', $fields_to_reset)));

    if (!empty($items->getValue())) {
      $elements[0] = [
        '#theme' => 'resetfield_button',
        '#button_text' => $button_text,
        '#button_class' => 'reset-button-display',
        '#field_id' => 'reset-button-display',
        '#markup' => '<div class="reset-button-display-wrapper">
          <div class="reset-button-info">
            <strong>' . $this->t('Reset Button') . ':</strong> ' . $button_text . '
          </div>',
      ];

      if ($display_mode === 'button_with_info' && !empty($fields_array)) {
        $elements[0]['#markup'] .= '<div class="reset-fields-list">
          <strong>' . $this->t('Fields to reset') . ':</strong>
          <ul>';
        
        foreach ($fields_array as $field_name) {
          $elements[0]['#markup'] .= '<li>' . htmlspecialchars($field_name) . '</li>';
        }
        
        $elements[0]['#markup'] .= '</ul></div>';
      }

      $elements[0]['#markup'] .= '</div>';
    }

    return $elements;
  }

}