<?php

namespace Drupal\monarch_data_entity;

use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\SortArray;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Service to help implementation of custom form tokens.
 */
class TokenService
{

  /**
   * Returns whether the widget handles multiple values.
   *
   * @param object $widget
   *   The widget to check.
   *
   * @return bool
   *   TRUE if a single copy of formElement() can handle multiple field values,
   *   FALSE if multiple values require separate copies of formElement().
   */
  public function handlesMultipleValues($widget): bool
  {
    $definition = $widget->getPluginDefinition();
    return $definition['multiple_values'];

  }

  /**
   * Get the value of a field on the form using the original widget.
   *
   * @param string $field_name
   *   The name of the field to get.
   * @param array $form
   *   The form to get the field from.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The state of the form.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface
   *   The item list for the field.
   */
  public function getField(string $field_name, array &$form, FormStateInterface $form_state): ?FieldItemListInterface
  {
    /** @var \Drupal\Core\Entity\ContentEntityFormInterface $form_object */
    if (($form_object = $form_state->getFormObject()) && $form_object instanceof \Drupal\Core\Entity\ContentEntityFormInterface) {
      $form_display = $form_object->getFormDisplay($form_state);
      /** @var \Drupal\Core\Entity\ContentEntityInterface $form_entity */
      $form_entity = $form_object->getEntity();

      if ($form_entity && $form_entity->hasField($field_name)) {
        $items = $form_entity->get($field_name);

        // If the field is empty on the entity (e.g. new entity), try to get the default value from its definition.
        if ($items->isEmpty()) {
          $default_value = $items->getFieldDefinition()->getDefaultValue($form_entity);
          if (!empty($default_value)) {
            $items->setValue($default_value);
          }
        }

        // Create a separate instance to avoid modifying the entity's own field list.
        /** @var \Drupal\Core\Field\FieldItemListInterface $items */
        $items = $items::createInstance($items->getDataDefinition(), $items->getName());
        $items->setValue($form_entity->get($field_name)->getValue() ?: $items->getValue());

        // Extract the values from $form_state->getValues().
        $path = array_merge($form['#parents'], [$field_name]);
        $key_exists = NULL;

        $values = $form_state->getValues();
        $values = NestedArray::getValue($values, $path, $key_exists);

        if ($key_exists) {
          $widget = $form_display->getRenderer($field_name);

          if ($widget) {
            // Account for drag-and-drop reordering if needed.
            if (!$this->handlesMultipleValues($widget)) {
              // Remove the 'value' of the 'add more' button.
              unset($values['add_more']);

              // The original delta, before drag-and-drop reordering, is needed to
              // route errors to the correct form element.
              foreach ($values as $delta => &$value) {
                $value['_original_delta'] = $delta;
              }

              usort($values, function ($a, $b) {
                return SortArray::sortByKeyInt($a, $b, '_weight');
              });
            }

            // Let the widget massage the submitted values.
            $is_drilldown = ($widget->getPluginId() === 'monarch_data_drill_down_widget');
            if ($widget instanceof \Drupal\Core\Field\WidgetInterface && !$is_drilldown) {
              $values = $widget->massageFormValues($values, $form, $form_state);
            }
          }

          // Assign the values and remove the empty ones.
          $items->setValue($values);
          if (!empty($values) && !$is_drilldown) {
            $items->filterEmptyItems();
          }
        }

        return $items;
      }
    }

    return NULL;
  }

  /**
   * Check if array is sequential.
   *
   * @param array $arr
   *   The array to check.
   *
   * @return bool
   *   Returns TRUE if the array is sequential or empty.
   */
  protected function isSequential(array $arr)
  {
    if (empty($arr)) {
      return TRUE;
    }

    if (!array_key_exists(0, $arr)) {
      return FALSE;
    }

    return array_keys($arr) === range(0, count($arr) - 1);
  }

  /**
   * Unwrap.
   */
  public function normalizeValues(array $arr)
  {
    if (!$this->isSequential($arr)) {
      return [$arr];
    }

    $affected = TRUE;

    $values = $arr;

    while ($affected) {
      $affected = FALSE;

      $old = $values;
      $values = [];

      foreach ($old as $value) {
        if ($value instanceof FieldItemListInterface) {
          $affected = TRUE;

          foreach ($value as $subvalue) {
            $values[] = $subvalue;
          }
        } else {
          $values[] = $value;
        }
      }

      $old = $values;
      $values = [];

      foreach ($old as $subvalue) {
        if ($subvalue instanceof EntityReferenceItem) {
          $affected = TRUE;
          if (!is_null($tmp = $subvalue->entity ?? NULL)) {
            $values[] = $tmp;
          }
        } elseif ($subvalue instanceof FieldItemInterface) {
          $affected = TRUE;
          if (!is_null($tmp = $subvalue->getValue())) {
            $values[] = $tmp;
          }
        } else {
          if (!is_null($subvalue)) {
            $values[] = $subvalue;
          }
        }
      }
    }

    for ($a = 0; $a < count($values); $a++) {
      if (is_null($values[$a])) {
        continue;
      }

      for ($b = $a + 1; $b < count($values); $b++) {
        if ($values[$a] === $values[$b]) {
          unset($values[$b]);
        }
      }
    }

    return array_filter($values);
  }

  /**
   * Helper to decode JSON or Serialized values from widgets.
   */
  protected function decodeValue($v)
  {
    if (!is_string($v)) {
      return $v;
    }

    // Try JSON
    if (str_starts_with($v, '{') || str_starts_with($v, '[')) {
      $decoded = json_decode($v, TRUE);
      if (json_last_error() === JSON_ERROR_NONE) {
        return is_array($decoded) ? ($decoded['value'] ?? $decoded['target_id'] ?? $v) : $decoded;
      }
    }

    if (preg_match('/^[aOs]:\d+:/', $v)) {
      $decoded = @unserialize($v, ['allowed_classes' => FALSE]);
      if ($decoded !== FALSE && is_array($decoded)) {
        return $decoded['value'] ?? $decoded['target_id'] ?? $v;
      }
    }

    return $v;
  }

  /**
   * Transforms an argument token.
   *
   * @param string|null $tokens
   *   The token to transform into a value.
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
  public function transformArgumentToken(string $tokens, array &$element, ?array $argument_settings, array &$form, FormStateInterface $form_state): ?string
  {
    $argument_settings = $argument_settings ?? [];

    $tokens = trim($tokens);
    $original_token = $tokens;

    // Support D7 style tokens like [field_name] or [field_name:property]
    if (str_starts_with($tokens, '[')) {
      if (str_ends_with($tokens, ']')) {
        $tokens = substr($tokens, 1, -1);
      }
    }
    // Convert colons to dots for D10 token processing
    $tokens = str_replace(':', '.', $tokens);

    if (strpos($tokens, ',') !== FALSE) {
      $ret = [];

      foreach (explode(',', $tokens) as $token) {
        $ret[] = $this->transformArgumentToken(trim($token), $element, $argument_settings, $form, $form_state);
      }

      $ret = array_filter($ret);

      if (empty($ret)) {
        return NULL;
      }

      if (count($ret) > 1 && ($argument_settings['break_phrase'] ?? NULL)) {
        return implode(',', $ret);
      }

      return reset($ret);
    }

    $tokens = explode('.', $tokens);

    $field_name = array_shift($tokens);

    if ($values = $this->getField($field_name, $form, $form_state)) {
      $values = $this->normalizeValues([$values]);
      if (empty($values) && !empty($tokens)) {
        $path = array_merge($form['#parents'], [$field_name]);
        $key_exists_raw = NULL;
        $raw = NestedArray::getValue($form_state->getValues(), $path, $key_exists_raw);
        if (!$key_exists_raw || empty($raw)) {
          $raw = NestedArray::getValue($form_state->getUserInput(), $path, $key_exists_raw);
        }

        if ($key_exists_raw && is_array($raw)) {
          foreach ($raw as $delta => $delta_val) {
            if (!is_numeric($delta) || !is_array($delta_val)) {
              continue;
            }
            $values[] = $delta_val;
          }
        }
      }

      // Ensure the element has an ID for the notifier to target.
      if (!isset($element['#id'])) {
        $element['#id'] = \Drupal\Component\Utility\Html::getId($element['#name'] ?? 'token-refreshed-field');
      }

      $element['#attached']['library'][] = 'monarch_data_entity/view_widget_notifier';
      $element['#attached']['drupalSettings']['monarch_data_entity']['global_change'][$element['#id']][$field_name] = $field_name;

      for ($token = reset($tokens); $token; $token = next($tokens)) {
        $next_values = [];

        foreach ($values as $value) {
          if ($value instanceof FieldableEntityInterface) {
            // Try both the token name and 'field_' prefixed version.
            $field_token = $token;
            if (!$value->hasField($field_token)) {
              $field_token = 'field_' . $token;
            }
            if ($value->hasField($field_token)) {
              $next_values[] = $value->get($field_token);
            }

            continue;
          }

          if (is_array($value) || $value instanceof \ArrayAccess) {
            if (!is_null($val = $value[$token] ?? NULL)) {
              $next_values[] = $this->decodeValue($val);
            }
            // Support partial selections from drill-down widgets.
            elseif (isset($value['wrapper'])) {
              foreach ([$token, 'field_' . $token] as $key) {
                if (isset($value['wrapper'][$key][$key])) {
                  $next_values[] = $this->decodeValue($value['wrapper'][$key][$key]);
                  break;
                }
              }
            }

            continue;
          }

          if (is_object($value)) {
            if (!is_null($val = $value->{$token} ?? NULL)) {
              $next_values[] = $this->decodeValue($val);
            }

            continue;
          }
        }

        $values = $this->normalizeValues($next_values);
      }

      foreach ($values as $index => $value) {
        $value = $values[$index] = $this->decodeValue($value);

        if ($value instanceof RevisionableInterface && !is_null($value->getRevisionId())) {
          $value = $values[$index] = $value->getRevisionId();
        }

        if ($value instanceof EntityInterface) {
          $value = $values[$index] = $value->id();
        }

        if (is_array($value) || $value instanceof \ArrayAccess) {
          if (!is_null($value['value'] ?? NULL)) {
            $value = $values[$index] = $value['value'];
          } elseif (!is_null($value['target_id'] ?? NULL)) {
            $value = $values[$index] = $value['target_id'];
          } else {
            $value = reset($value);

            while (is_array($value) || $value instanceof \ArrayAccess) {
              $value = reset($value);
            }

            $values[$index] = $value;
          }
        }

        if (is_object($value)) {
          if (!is_null($value->value ?? NULL)) {
            $value = $values[$index] = $value->value;
          } elseif (!is_null($value->target_id ?? NULL)) {
            $value = $values[$index] = $value->target_id;
          }
        }

        if (!is_scalar($value)) {
          unset($values[$index]);
        }
      }

      if ($argument_settings['break_phrase'] ?? NULL) {
        // Honestly no clue why it's called break_phase, but
        // this is when the argument accepts multiple values.
        // We're gonna or it together, but maybe provide an
        // option to select this in the future?
        $result = implode('+', $values) ?: NULL;
      } else {
        $result = reset($values) ?: NULL;
      }

      // If result is NULL, the source field may not have a value yet.

      return $result;
    }

    // Field not found in form; return NULL.
    return NULL;
  }

}
