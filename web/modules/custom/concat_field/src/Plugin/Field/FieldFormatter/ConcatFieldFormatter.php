<?php

namespace Drupal\concat_field\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a default formatter for the concat field.
 *
 * @FieldFormatter(
 *   id = "concat_field_default",
 *   label = @Translation("Default"),
 *   field_types = {
 *     "concat_field"
 *   }
 * )
 */
class ConcatFieldFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'wrap_tag' => 'div',
      'show_empty' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    $elements['wrap_tag'] = [
      '#type' => 'select',
      '#title' => $this->t('HTML wrapper tag'),
      '#default_value' => $this->getSetting('wrap_tag'),
      '#options' => [
        'div' => 'div',
        'span' => 'span',
        'p' => 'p',
        'h1' => 'h1',
        'h2' => 'h2',
        'h3' => 'h3',
        'h4' => 'h4',
        'h5' => 'h5',
        'h6' => 'h6',
        '' => $this->t('No wrapper'),
      ],
      '#description' => $this->t('Select the HTML tag to wrap the concatenated value.'),
    ];

    $elements['show_empty'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show empty values'),
      '#default_value' => $this->getSetting('show_empty'),
      '#description' => $this->t('Show the field even when the concatenated value is empty.'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    
    $wrap_tag = $this->getSetting('wrap_tag');
    if ($wrap_tag) {
      $summary[] = $this->t('Wrapper: @tag', ['@tag' => $wrap_tag]);
    } else {
      $summary[] = $this->t('No wrapper');
    }

    if ($this->getSetting('show_empty')) {
      $summary[] = $this->t('Show empty values');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $wrap_tag = $this->getSetting('wrap_tag');
    $show_empty = $this->getSetting('show_empty');

    foreach ($items as $delta => $item) {
      $value = $item->value;
      
      // Skip empty values unless configured to show them.
      if (empty($value) && !$show_empty) {
        continue;
      }

      if ($wrap_tag) {
        $elements[$delta] = [
          '#type' => 'html_tag',
          '#tag' => $wrap_tag,
          '#value' => $value ?: '',
          '#attributes' => [
            'class' => ['concat-field-value'],
          ],
        ];
      } else {
        $elements[$delta] = [
          '#markup' => $value ?: '',
        ];
      }
    }

    return $elements;
  }

}

/**
 * Provides a blank formatter for the concat field (for hidden display).
 *
 * @FieldFormatter(
 *   id = "concat_field_blank",
 *   label = @Translation("No output"),
 *   field_types = {
 *     "concat_field"
 *   }
 * )
 */
class ConcatFieldBlankFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    // Return empty array for no output.
    return [];
  }

}