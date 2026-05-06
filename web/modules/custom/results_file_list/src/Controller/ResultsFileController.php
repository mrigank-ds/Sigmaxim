<?php

namespace Drupal\results_file_list\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * Returns a page listing files in a specific folder (by folder_id).
 */
class ResultsFileController extends ControllerBase {

  /**
   * List files from a folder in private://filedepot/{folder_id}.
   *
   * @param string $folder_id
   *   The folder ID.
   *
   * @return array
   *   Renderable array containing file info.
   */
  public function listFiles($folder_id) {
    $directory = 'private://filedepot/' . $folder_id;
    $file_system = \Drupal::service('file_system');
    $real_path = $file_system->realpath($directory);
    $build = [];

    if (!is_dir($real_path)) {
      $build['error'] = [
        '#markup' => $this->t('Folder not found: @id', ['@id' => $folder_id]),
      ];
      return $build;
    }

    $files = array_diff(scandir($real_path), ['.', '..']);

    if (empty($files)) {
      $build['no_files'] = [
        '#markup' => $this->t('No files found in this folder.'),
      ];
      return $build;
    }

    $rows = [];
    foreach ($files as $filename) {
      $file_uri = $directory . '/' . $filename;
      $public_url = \Drupal::service('file_url_generator')->generateAbsoluteString($file_uri);

      $rows[] = [
        'filename' => $filename,
        'modified' => date('Y-m-d H:i', filemtime($real_path . '/' . $filename)),
        'download' => [
          'data' => [
            '#type' => 'link',
            '#title' => $this->t('Download'),
            '#url' => Url::fromUri($public_url, [
              'attributes' => ['target' => '_blank', 'rel' => 'noopener'],
            ]),
          ],
        ],
      ];
    }

    $build['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('File Name'),
        $this->t('Last Modified'),
        $this->t('Download'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No files available.'),
    ];

    return $build;
  }

}