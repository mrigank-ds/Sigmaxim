<?php

namespace Drupal\sigmaxim_data_entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list controller for the data entity type.
 */
class DataEntityListBuilder extends EntityListBuilder {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $instance = new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id())
    );

    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->dateFormatter = $container->get('date.formatter');

    return $instance;
  }

  /**
   * Loads entity IDs using a pager sorted by the entity id.
   *
   * @return array
   *   An array of entity IDs.
   */
  protected function getEntityIds() {
    $bundle_entity_type = $this->entityType->getBundleEntityType();

    $query = $this->entityTypeManager->getStorage($bundle_entity_type)->getQuery()
      ->accessCheck(TRUE)
      ->sort('id');

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit);
    }
    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function load() {
    $bundle_entity_type = $this->entityType->getBundleEntityType();

    $entity_ids = $this->getEntityIds();
    return $this->entityTypeManager->getStorage($bundle_entity_type)->loadMultiple($entity_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Table');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\monarch_data_entity\Entity\DataEntityType $entity */
    $link = Link::createFromRoute($entity->label(), 'sigmaxim_data_entity.table_view', [
      'bundle' => $entity->id(),
    ]);

    $row['label'] = $link->toString();
    return $row + parent::buildRow($entity);
  }

}
