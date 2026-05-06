<?php


namespace Drupal\sigmaxim_import_export\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\node\Entity\NodeType;
use Drupal\sigmaxim_import_export\Batch\JsonImportBatch;

/**
 * Controller: validate the uploaded JSON then kick off the import batch.
 */
class SigmaximImportController extends ControllerBase {

  /**
   * Route callback: parse JSON, build batch operations, start batch.
   *
   * NOTE: The JSON 'data' block is used ONLY to set field default values.
   * No actual product entity is created during import.
   */
  public function process() {

    // ------------------------------------------------------------------
    // 1. Load the uploaded file.
    // ------------------------------------------------------------------
    $fid  = \Drupal::request()->query->get('fid');
    $file = \Drupal::entityTypeManager()->getStorage('file')->load($fid);

    if (!$file) {
      $this->messenger()->addError($this->t('JSON file not found.'));
      return $this->redirect('sigmaxim_import_export.import_form');
    }

    // ------------------------------------------------------------------
    // 2. Decode & validate JSON structure.
    // ------------------------------------------------------------------
    $json = json_decode(file_get_contents($file->getFileUri()), TRUE);

    if (
      empty($json['entity_type']) ||
      empty($json['bundle']['id']) ||
      empty($json['bundle']['label']) ||
      !is_array($json['fields_definition'] ?? NULL)
    ) {
      $this->messenger()->addError($this->t('Invalid JSON structure.'));
      return $this->redirect('sigmaxim_import_export.import_form');
    }

    $entity_type  = $json['entity_type'];
    $bundle_id    = $json['bundle']['id'];
    $bundle_label = $json['bundle']['label'];

    // ------------------------------------------------------------------
    // 3. Extra bundle properties (everything except id / label).
    // ------------------------------------------------------------------
    $bundle_extra = array_diff_key(
      $json['bundle'],
      array_flip(['id', 'label'])
    );

    // ------------------------------------------------------------------
    // 4. Pre-index data values keyed by field name.
    //    Used ONLY for setting field default values — no entity is created.
    // ------------------------------------------------------------------
    $data_map = $json['data'] ?? [];

    // ------------------------------------------------------------------
    // 5. Guard: warn if the bundle already exists.
    //    We still continue — existing fields are skipped gracefully.
    // ------------------------------------------------------------------
    $already_exists = FALSE;

    if ($entity_type === 'node') {
      $already_exists = (bool) NodeType::load($bundle_id);
    }
    else {
      try {
        $bundle_storage = \Drupal::entityTypeManager()
          ->getStorage($entity_type . '_type');
        $already_exists = $bundle_storage && (bool) $bundle_storage->load($bundle_id);
      }
      catch (\Exception $e) {
        // Storage not available — not a blocker.
      }
    }

    if ($already_exists) {
      $this->messenger()->addWarning(
        $this->t(
          'Bundle "@type" already exists. Existing fields will be skipped; new fields will be added.',
          ['@type' => $bundle_label]
        )
      );
    }

    // ------------------------------------------------------------------
    // 6. Build batch operations — one per custom field (field_*).
    //    The matching value from data_map is passed so the batch can set
    //    it as the field's DEFAULT VALUE on the FieldConfig.
    //    No entity record is created.
    // ------------------------------------------------------------------
    $operations = [];

    foreach ($json['fields_definition'] as $field_name => $field) {
      if (!str_starts_with($field_name, 'field_')) {
        continue;
      }

      // Pull the exported value for this field (NULL if absent).
      $default_value = $data_map[$field_name] ?? NULL;

      $operations[] = [
        [JsonImportBatch::class, 'processField'],
        [
          $entity_type,
          $bundle_id,
          $bundle_label,
          $bundle_extra,
          $field_name,
          $field,
          $default_value,
        ],
      ];
    }

    // ------------------------------------------------------------------
    // 7. Run the batch.
    //    NOTE: processData is intentionally NOT added here.
    //    The imported product ID from the source system must not be
    //    re-created on the target system.
    // ------------------------------------------------------------------
    batch_set([
      'title'      => $this->t('Importing Product type and fields…'),
      'operations' => $operations,
      'finished'   => [JsonImportBatch::class, 'finished'],
    ]);

    return batch_process(Url::fromRoute('sigmaxim_import_export.import_form'));
  }

}
