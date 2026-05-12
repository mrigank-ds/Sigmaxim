<?php

namespace Drupal\sigmaxim_import_export\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\node\Entity\NodeType;
use Symfony\Component\HttpFoundation\JsonResponse;
use ZipArchive;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Drupal\field\Entity\FieldConfig;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use Drupal\media\Entity\Media;
use Drupal\Core\Entity\Entity\EntityFormDisplay;

/**
 * Product export controller.
 */
class SigmaximExportController extends ControllerBase {

  /**
   * File system service.
   */
  protected FileSystemInterface $fileSystem;

  /**
   * Entity type manager.
   *
   * Defined in ControllerBase, must NOT be typed.
   */
  protected $entityTypeManager;

  /**
   * Entity field manager.
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * Constructor.
   */
  public function __construct(
    FileSystemInterface $file_system,
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager
  ) {
    $this->fileSystem = $file_system;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('file_system'),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * Export node type definition.
   */
  public function exportNode($node_type) {
    // Load content type config entity.
    $content_type = NodeType::load($node_type);

    if (!$content_type) {
      throw new NotFoundHttpException('Content type not found.');
    }

    // Get all field definitions for this content type.
    $field_definitions = $this->entityFieldManager
      ->getFieldDefinitions('node', $node_type);

    $fields = [];

    foreach ($field_definitions as $field_name => $definition) {
      $fields[$field_name] = [
        'label' => $definition->getLabel(),
        'type' => $definition->getType(),
        'required' => $definition->isRequired(),
        'translatable' => $definition->isTranslatable(),
        'settings' => $definition->getSettings(),
      ];
    }

    return new JsonResponse([
      'content_type' => $node_type,
      'fields' => $fields,
    ]);
  }



/**
 * Export product by order type from URL.
 *
 * @param string $sigmaxim_workflow_order_type
 *   Product machine name.
 */
public function exportPage(string $sigmaxim_workflow_order_type) {

  $entity_type = 'sigmaxim_workflow_order';

  /* -----------------------------
   * Load bundle (product type)
   * ----------------------------- */
  $product_type = $this->entityTypeManager()
    ->getStorage('sigmaxim_workflow_order_type')
    ->load($sigmaxim_workflow_order_type);

  if (!$product_type) {
    throw new NotFoundHttpException('Product type not found.');
  }

  /* -----------------------------
   * Field definitions + default values
   * ----------------------------- */
  $field_definitions = $this->entityFieldManager
    ->getFieldDefinitions($entity_type, $sigmaxim_workflow_order_type);

  $fields = [];
  foreach ($field_definitions as $field_name => $definition) {

    $default_value_literal = $definition->getDefaultValueLiteral();
    $default_value = NULL;

    if (!empty($default_value_literal)) {
      $default_value = count($default_value_literal) === 1
        ? $default_value_literal[0]
        : $default_value_literal;
    }

    $fields[$field_name] = [
      'label'         => (string) $definition->getLabel(),
      'type'          => $definition->getType(),
      'required'      => $definition->isRequired(),
      'translatable'  => $definition->isTranslatable(),
      'settings'      => $definition->getSettings(),
      'default_value' => $default_value,
    ];
  }

  /* -----------------------------
   * Field Groups
   * ----------------------------- */
  $field_groups = [];

  $form_display = EntityFormDisplay::load(
    $entity_type . '.' . $sigmaxim_workflow_order_type . '.default'
  );

  if ($form_display) {
    $groups = $form_display->getThirdPartySettings('field_group') ?? [];

    foreach ($groups as $group_name => $group) {
      $field_groups[$group_name] = [
        'label'       => $group['label']       ?? '',
        'format_type' => $group['format_type'] ?? '',
        'region'      => $group['region']      ?? '',
        'children'    => $group['children']    ?? [],
        'parent'      => $group['parent_name'] ?? NULL,
        'weight'      => $group['weight']      ?? 0,
      ];
    }
  }

  /* -----------------------------
   * Data values: from entity if exists,
   * otherwise from field definition defaults
   * ----------------------------- */
  $data_values = [];

  $storage = $this->entityTypeManager()->getStorage($entity_type);
  $ids = $storage->getQuery()
    ->condition('type', $sigmaxim_workflow_order_type)
    ->accessCheck(TRUE)
    ->range(0, 1)
    ->execute();

  if (!empty($ids)) {
    // Load entity and get actual field values.
    $entity = $storage->load(reset($ids));

    foreach ($entity->getFields() as $field_name => $field) {
      $definition = $field->getFieldDefinition();

      if (!$field->isEmpty()) {
        $values = [];
        foreach ($field->getValue() as $value) {
          $values[] = $value;
        }
        $data_values[$field_name] = count($values) === 1 ? $values[0] : $values;
      }
      else {
        $default = $definition->getDefaultValueLiteral();
        $data_values[$field_name] = !empty($default)
          ? (count($default) === 1 ? $default[0] : $default)
          : NULL;
      }
    }
  }
  else {
    // No entity found: build data from field definition defaults only.
    foreach ($field_definitions as $field_name => $definition) {
      $default = $definition->getDefaultValueLiteral();
      $data_values[$field_name] = !empty($default)
        ? (count($default) === 1 ? $default[0] : $default)
        : NULL;
    }
  }

  /* -----------------------------
   * Final JSON structure
   * ----------------------------- */
  $export = [
    'entity_type'       => $entity_type,
    'bundle'            => [
      'id'             => $product_type->id(),
      'label'          => $product_type->label(),
      'description'    => $product_type->get('description') ?? '',
      'weight'         => $product_type->get('weight'),
      'watched_folder' => $product_type->get('watched_folder'),
    ],
    'fields_definition' => $fields,
    'field_groups'      => $field_groups,
    'data'              => $data_values,
  ];

  /* -----------------------------
   * Safe filename
   * ----------------------------- */
  $safe_name = preg_replace(
    '/[^a-zA-Z0-9_-]/',
    '_',
    strtolower($product_type->id())
  );

  /* -----------------------------
   * Base export directory
   * ----------------------------- */
  $base_dir = 'public://entity_export/' . $entity_type;

  $dir_prepared = $this->fileSystem->prepareDirectory(
    $base_dir,
    FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS
  );

  if (!$dir_prepared) {
    return [
      '#type'   => 'markup',
      '#markup' => '<p>' . $this->t('Export directory could not be created.') . '</p>',
    ];
  }

  /* -----------------------------
   * Write JSON file
   * ----------------------------- */
  $real_path = $this->fileSystem->realpath($base_dir) . '/' . $safe_name . '.json';

  $json_written = file_put_contents(
    $real_path,
    json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
  );

  if ($json_written === FALSE) {
    return [
      '#type'   => 'markup',
      '#markup' => '<p>' . $this->t('Could not write export file to @path.', ['@path' => $real_path]) . '</p>',
    ];
  }

  /* -----------------------------
   * Build public download URL
   * ----------------------------- */
  $json_uri = $base_dir . '/' . $safe_name . '.json';
  $json_url = \Drupal::service('file_url_generator')->generateAbsoluteString($json_uri);

  /* -----------------------------
   * Output
   * ----------------------------- */
  return [
    '#type'   => 'markup',
    '#markup' => '
      <div class="export-results">
        <h2>' . $this->t('Export: @label', ['@label' => $product_type->label()]) . '</h2>
        <p>' . $this->t('Product Type: @type', ['@type' => $sigmaxim_workflow_order_type]) . '</p>
        <p>
          <strong>' . $this->t('Download JSON:') . '</strong>&nbsp;
          <a href="' . $json_url . '" download="' . $safe_name . '.json">' . $safe_name . '.json</a>
        </p>
      </div>
    ',
  ];
}
}