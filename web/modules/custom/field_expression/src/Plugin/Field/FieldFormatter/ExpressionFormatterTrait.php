<?php

namespace Drupal\field_expression\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

trait ExpressionFormatterTrait {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
        'always_evaluate' => FALSE
    ] + parent::defaultSettings();
  }
  
  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);
    $element['always_evaluate'] = [
        '#type' => 'checkbox',
        '#title' => t('Always Evaluate Expression?'),
        '#description' => t('Check this box to evaluate the expression every time it is rendered. Otherwise, it will only be evaluated with the entity it is attached to is saved. This setting can be useful if you have complex tokens use referenced content that may change without this entity updating. Be careful that large expressions with lots of tokens could cause performance issues if this setting is enabled, however.'),
        '#default_value' => $this->getSetting('always_evaluate'),
        '#weight' => 100,
    ];
    return $element;
  }
  
  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $summary[] = $this->t('Always Evaluate Expression: @always_evaluate', ['@always_evaluate' => $this->getSetting('always_evaluate') ? 'Yes' : 'No']);
    return $summary;
  }
  
  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $output = '';
    $value = 0;
    $settings = $this->getFieldSettings();

    // We're forcing single cardinality for this field type so we just
    // need to check the first item, and then work with that unless empty.
    if ($this->getSetting('always_evaluate')) {
      // If there's no value yet, we append an item so that we can grab
      if (empty($items[0])) {
        $items->appendItem();
      }
      $value = $items[0]->evaluateExpression($items[0]->getFieldDefinition()->getSetting('expression'));
    }
    elseif (!empty($items[0])) {
      $value = $items[0]->value;
    }
    $output = $this->numberFormat($value);

    // Account for prefix and suffix.
    if ($this->getSetting('prefix_suffix')) {
      $prefixes = isset($settings['prefix']) ? array_map(['Drupal\Core\Field\FieldFilteredMarkup', 'create'], explode('|', $settings['prefix'])) : [''];
      $suffixes = isset($settings['suffix']) ? array_map(['Drupal\Core\Field\FieldFilteredMarkup', 'create'], explode('|', $settings['suffix'])) : [''];
      $prefix = (count($prefixes) > 1) ? $this->formatPlural($items[0]->value, $prefixes[0], $prefixes[1]) : $prefixes[0];
      $suffix = (count($suffixes) > 1) ? $this->formatPlural($items[0]->value, $suffixes[0], $suffixes[1]) : $suffixes[0];
      $output = $prefix . $output . $suffix;
    }
    // Output the raw value in a content attribute if the text of the HTML
    // element differs from the raw value (for example when a prefix is used).
    if (isset($items[0]->_attributes) && $items[0]->value != $output) {
      $items[0]->_attributes += ['content' => $items[0]->value];
    }

    $elements = [];
    $elements[] = ['#markup' => $output];
    return $elements;
  }

}
