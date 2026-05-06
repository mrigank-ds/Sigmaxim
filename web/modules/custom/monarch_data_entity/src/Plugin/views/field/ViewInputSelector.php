<?php

namespace Drupal\monarch_data_entity\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Show selector for entity id.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("view_input_selector")
 */
class ViewInputSelector extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $id = $values->{$this->realField};

    return [
      '#type' => 'checkbox',
      '#attributes' => [
        'class' => ['view-input-view-selector'],
        'value' => $id,
        'data-entity-type' => $values->_entity->getEntityTypeId(),
      ],
      '#default_value' => FALSE,
    ];
  }

}
