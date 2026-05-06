<?php

namespace Drupal\results_file_list\Plugin\views\field;

use Drupal\views\ResultRow;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\Core\File\FileSystemInterface;

/**
 * Provides a field that links to the entity's result file list.
 *
 * @ViewsField("results_link_field")
 */
class ResultLink extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function usesEntityField() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Do not alter the query.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    if (!isset($values->_entity)) {
      return '';
    }

    $folder_id = $this->getValue($values, 'field_folder_id');
    if (empty($folder_id)) {
      return '';
    }

    $directory = 'private://filedepot/' . $folder_id;
    $file_system = \Drupal::service('file_system');
    $real_path = $file_system->realpath($directory);

    // Check if directory exists and has files
    if (is_dir($real_path)) {
      $files = array_diff(scandir($real_path), ['.', '..']);
      if (!empty($files)) {
        $url = Url::fromRoute('results_file_list.view_entity_files', ['folder_id' => $folder_id]);
        $link = Link::fromTextAndUrl($this->t('Result'), $url);
        return $link->toRenderable();
        
      }
    }

    return ['#markup' => ''];
  }

}