<?php

namespace Drupal\monarch_data_entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a data entity type.
 */
interface DataEntityInterface extends ContentEntityInterface, EntityChangedInterface {

}
