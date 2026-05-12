<?php

namespace Drupal\sigmaxim_workflow;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Products entities.
 *
 * @ingroup sigmaxim_workflow
 */
class ProductsListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('Products ID');
    $header['name'] = $this->t('Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {

    // print_r($entity);
    /* @var \Drupal\sigmaxim_workflow\Entity\Products $entity */
    $row['id'] = $entity->id();
    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'entity.sigmaxim_workflow_order.edit_form',
      ['sigmaxim_workflow_order' => $entity->id()]
    );
    return $row + parent::buildRow($entity);
  }

}
