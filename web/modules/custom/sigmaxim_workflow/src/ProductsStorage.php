<?php

namespace Drupal\sigmaxim_workflow;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\sigmaxim_workflow\Entity\ProductsInterface;

/**
 * Defines the storage handler class for Products entities.
 *
 * This extends the base storage class, adding required special handling for
 * Products entities.
 *
 * @ingroup sigmaxim_workflow
 */
class ProductsStorage extends SqlContentEntityStorage implements ProductsStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function revisionIds(ProductsInterface $entity) {
    return $this->database->query(
      'SELECT vid FROM {sigmaxim_workflow_order_revision} WHERE id=:id ORDER BY vid',
      [':id' => $entity->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function userRevisionIds(AccountInterface $account) {
    return $this->database->query(
      'SELECT vid FROM {sigmaxim_workflow_order_field_revision} WHERE uid = :uid ORDER BY vid',
      [':uid' => $account->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function countDefaultLanguageRevisions(ProductsInterface $entity) {
    return $this->database->query('SELECT COUNT(*) FROM {sigmaxim_workflow_order_field_revision} WHERE id = :id AND default_langcode = 1', [':id' => $entity->id()])
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function clearRevisionsLanguage(LanguageInterface $language) {
    return $this->database->update('sigmaxim_workflow_order_revision')
      ->fields(['langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED])
      ->condition('langcode', $language->getId())
      ->execute();
  }

}
