<?php

namespace Drupal\monarch_data_entity\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\monarch_data_entity\DataEntityInterface;

/**
 * Defines the data entity class.
 *
 * @ContentEntityType(
 *   id = "data_entity",
 *   label = @Translation("Data"),
 *   label_collection = @Translation("Data"),
 *   label_singular = @Translation("data"),
 *   label_plural = @Translation("data"),
 *   label_count = @PluralTranslation(
 *     singular = "@count data",
 *     plural = "@count data",
 *   ),
 *   bundle_label = @Translation("Data type"),
 *   handlers = {
 *     "list_builder" = "Drupal\monarch_data_entity\DataEntityListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\monarch_data_entity\DataEntityAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\monarch_data_entity\Form\DataEntityForm",
 *       "edit" = "Drupal\monarch_data_entity\Form\DataEntityForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "data_entity",
 *   admin_permission = "administer data types",
 *   entity_keys = {
 *     "id" = "id",
 *     "bundle" = "bundle",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "collection" = "/admin/content/data-entity",
 *     "add-form" = "/data/add/{data_entity_type}",
 *     "add-page" = "/data/add",
 *     "canonical" = "/data/{data_entity}",
 *     "edit-form" = "/data/{data_entity}/edit",
 *     "delete-form" = "/data/{data_entity}/delete",
 *   },
 *   bundle_entity_type = "data_entity_type",
 *   field_ui_base_route = "entity.data_entity_type.edit_form",
 * )
 */
class DataEntity extends ContentEntityBase implements DataEntityInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Label'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Status'))
      ->setDefaultValue(TRUE)
      ->setSetting('on_label', 'Enabled')
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => FALSE,
        ],
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the data was created.'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the data was last edited.'));

    return $fields;
  }

}
