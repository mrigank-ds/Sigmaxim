<?php

namespace Drupal\results_file_list\Plugin\views\field;

use Drupal\views\ResultRow;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\Annotation\ViewsField;

/**
 * Provides a field handler for showing a Results link.
 *
 * @ViewsField("results_file_link_handler")
 */
class ResultsFileLinkHandler extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // No query alteration needed.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    if (!isset($values->_entity)) {
      return '';
    }

    $entity = $values->_entity;
    $id = $entity->id();

    $url = Url::fromRoute('results_file_list.view_entity_files', ['entity_id' => $id]);
    return Link::fromTextAndUrl('Result', $url)->toRenderable();
  }
}
