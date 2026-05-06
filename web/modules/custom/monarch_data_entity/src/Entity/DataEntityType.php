<?php

namespace Drupal\monarch_data_entity\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the Data type configuration entity.
 *
 * @ConfigEntityType(
 *   id = "data_entity_type",
 *   label = @Translation("Data type"),
 *   label_collection = @Translation("Data types"),
 *   label_singular = @Translation("data type"),
 *   label_plural = @Translation("data types"),
 *   label_count = @PluralTranslation(
 *     singular = "@count data type",
 *     plural = "@count data types",
 *   ),
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\monarch_data_entity\Form\DataEntityTypeForm",
 *       "edit" = "Drupal\monarch_data_entity\Form\DataEntityTypeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *     "list_builder" = "Drupal\monarch_data_entity\DataEntityTypeListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   admin_permission = "administer data types",
 *   bundle_of = "data_entity",
 *   config_prefix = "data_entity_type",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/data_entity_types/add",
 *     "edit-form" = "/admin/structure/data_entity_types/manage/{data_entity_type}",
 *     "delete-form" = "/admin/structure/data_entity_types/manage/{data_entity_type}/delete",
 *     "collection" = "/admin/structure/data_entity_types"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "uuid",
 *     "primary_keys",
 *   }
 * )
 */
class DataEntityType extends ConfigEntityBundleBase {

  /**
   * The machine name of this data type.
   *
   * @var string
   */
  protected $id;

  /**
   * The human-readable name of the data type.
   *
   * @var string
   */
  protected $label;

  /**
   * The list of field names designated as primary keys.
   *
   * @var array
   */
  protected $primary_keys = [];

  /**
   * Gets the primary key fields.
   *
   * @return array
   *   An array of field names that are primary keys.
   */
  public function getPrimaryKeys() {
    return $this->primary_keys ?: [];
  }

  /**
   * Sets the primary key fields.
   *
   * @param array $primary_keys
   *   An array of field names that are primary keys.
   *
   * @return $this
   */
  public function setPrimaryKeys(array $primary_keys) {
    $this->primary_keys = $primary_keys;
    return $this;
  }

}
