<?php

namespace Drupal\sigmaxim_workflow\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Products type entity.
 *
 * @ConfigEntityType(
 *   id = "sigmaxim_workflow_order_type",
 *   label = @Translation("Product"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\sigmaxim_workflow\ProductsTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\sigmaxim_workflow\Form\ProductsTypeForm",
 *       "edit" = "Drupal\sigmaxim_workflow\Form\ProductsTypeForm",
 *       "delete" = "Drupal\sigmaxim_workflow\Form\ProductsTypeDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\sigmaxim_workflow\ProductsTypeHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "sigmaxim_workflow_order_type",
 *   admin_permission = "administer site configuration",
 *   bundle_of = "sigmaxim_workflow_order",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "permission" = "permission"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "group",
 *     "name",
 *     "value",
 *     "watched_folder",
 *     "permission",
 *     "weight",
 *     "product_category"
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/sigmaxim_workflow_order_type/{sigmaxim_workflow_order_type}",
 *     "add-form" = "/admin/structure/products/add",
 *     "edit-form" = "/admin/structure/products/manage/{sigmaxim_workflow_order_type}",
 *     "delete-form" = "/admin/structure/products/{sigmaxim_workflow_order_type}/delete",
 *     "collection" = "/admin/structure/products"
 *   }
 * )
 */
class ProductsType extends ConfigEntityBundleBase implements ProductsTypeInterface {

  /**
   * The Products type ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Products type label.
   *
   * @var string
   */
  protected $label;

  /**
   * The Products type permission.
   *
   * @var string
   */
  protected $permission;

  /**
   * A brief description of this entity type.
   *
   * @var string
   */
  protected $description;

  /**
   * The Entity's weight.
   *
   * @var int
   */
  protected $weight;

  /**
   * A watched_folder of this entity type.
   *
   * @var string
   */
  protected $watched_folder;

  /**
   * A product_category of this entity type.
   *
   * @var string
   */
  protected $product_category;

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->description;
  }



  /**
   * {@inheritdoc}
   */
  public function setWeight($weight) {
    $this->weight = $weight;
  }

  /**
   * {@inheritdoc}
   */
  public function weight() {
    return $this->weight;
  }

  /**
   * {@inheritdoc}
   */
  public function setWatchedFolder($watched_folder) {
    $this->watched_folder = $watched_folder;
  }

  /**
   * {@inheritdoc}
   */
  public function getWatchedFolder() {
    return $this->watched_folder;
  }

  /**
   * {@inheritdoc}
   */
  public function setPermission($permission) {
    $this->permission = $permission;
  }

  /**
   * {@inheritdoc}
   */
  public function getPermission() {
    return $this->permission;
  }

  /**
   * {@inheritdoc}
   */
  public function setProductCategory($product_category) {
    $this->product_category = $product_category;
  }

  /**
   * {@inheritdoc}
   */
  public function getProductCategory() {
    return $this->product_category;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['permission'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Permission'))
      ->setDescription(t('The name of the Products entity.'))
      ->setRevisionable(TRUE)
      ->setSettings([
        'max_length' => 50,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    return $fields;
  }

}