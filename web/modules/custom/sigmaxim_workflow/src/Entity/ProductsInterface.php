<?php

namespace Drupal\sigmaxim_workflow\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Products entities.
 *
 * @ingroup sigmaxim_workflow
 */
interface ProductsInterface extends ContentEntityInterface, RevisionLogInterface, EntityChangedInterface, EntityPublishedInterface, EntityOwnerInterface {

  /**
   * Add get/set methods for your configuration properties here.
   */

  /**
   * Gets the Products name.
   *
   * @return string
   *   Name of the Products.
   */
  public function getName();

  /**
   * Sets the Products name.
   *
   * @param string $name
   *   The Products name.
   *
   * @return \Drupal\sigmaxim_workflow\Entity\ProductsInterface
   *   The called Products entity.
   */
  public function setName($name);

  /**
   * Gets the Products creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Products.
   */
  public function getCreatedTime();

  /**
   * Sets the Products creation timestamp.
   *
   * @param int $timestamp
   *   The Products creation timestamp.
   *
   * @return \Drupal\sigmaxim_workflow\Entity\ProductsInterface
   *   The called Products entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Gets the Products revision creation timestamp.
   *
   * @return int
   *   The UNIX timestamp of when this revision was created.
   */
  public function getRevisionCreationTime();

  /**
   * Sets the Products revision creation timestamp.
   *
   * @param int $timestamp
   *   The UNIX timestamp of when this revision was created.
   *
   * @return \Drupal\sigmaxim_workflow\Entity\ProductsInterface
   *   The called Products entity.
   */
  public function setRevisionCreationTime($timestamp);

  /**
   * Gets the Products revision author.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity for the revision author.
   */
  public function getRevisionUser();

  /**
   * Sets the Products revision author.
   *
   * @param int $uid
   *   The user ID of the revision author.
   *
   * @return \Drupal\sigmaxim_workflow\Entity\ProductsInterface
   *   The called Products entity.
   */
  public function setRevisionUserId($uid);

}
