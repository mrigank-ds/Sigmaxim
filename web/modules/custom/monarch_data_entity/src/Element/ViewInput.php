<?php

namespace Drupal\monarch_data_entity\Element;

use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\FormElement;

/**
 * Provides a form element for an HTML 'view_input' input element.
 *
 * Specify either #default_value or #value but not both.
 *
 * @FormElement("view_input")
 */
class ViewInput extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = static::class;
    return [
      '#input' => TRUE,
      '#process' => [
        [$class, 'processAjaxForm'],
      ],
      '#pre_render' => [
        [$class, 'preRenderViewInput'],
      ],
      '#theme' => 'input__view_widget_iframe',
    ];
  }

  /**
   * Prepares a #type 'view_input' render element for input.html.twig.
   *
   * @param array $element
   *   An associative array containing the properties of the element.
   *   Properties used: #name, #value, #attributes.
   *
   * @return array
   *   The $element with prepared variables ready for input.html.twig.
   */
  public static function preRenderViewInput($element) {
    $element['#attributes']['type'] = 'hidden';
    Element::setAttributes($element, ['id', 'name', 'value']);

    return $element;
  }

}
