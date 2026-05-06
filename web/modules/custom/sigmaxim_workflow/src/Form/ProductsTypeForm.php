<?php

namespace Drupal\sigmaxim_workflow\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\File\FileSystem;

/**
 * Class ProductsTypeForm.
 */
class ProductsTypeForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $sigmaxim_workflow_order_type = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Product Name'),
      '#maxlength' => 255,
      '#default_value' => $sigmaxim_workflow_order_type->label(),
      '#description' => $this->t("The human-readable name of this product. This name must be unique."),
      '#required' => TRUE,
    ];

    $form['watched_folder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Watched Folder'),
      '#maxlength' => 255,
      '#default_value' => $sigmaxim_workflow_order_type->getWatchedFolder(),
      '#description' => $this->t("An existing path relative to the private file directory for storing exported xml files."),
    ];

    $form['description'] = [
      '#title' => $this->t('Description'),
      '#type' => 'textarea',
      '#default_value' => $sigmaxim_workflow_order_type->getDescription(),
      '#description' => $this->t("Describe this product."),
    ];

    $form['weight'] = [
      '#type' => 'weight',
      '#title' => $this->t('Weight'),
      '#default_value' => $sigmaxim_workflow_order_type->weight(),
      '#description' => $this->t("When showing products, those with lighter (smaller) weights get listed before products with heavier (larger) weights."),
    ];

    $product_info = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('sigmaxim_workflow_product_info');
    $tags = [];
    $tags['none'] = '-None-';
    foreach ($product_info as $tag_term) {
      $depth = '';
      if ($tag_term->depth > 0) {
        for ($i=0; $i < $tag_term->depth; $i++) { 
          $depth = $depth.'-';
        }
      }
      $tags[$tag_term->tid] = $depth . $tag_term->name;
    }

    $form['product_category'] = array(
      '#type' => 'select',
      '#options' => $tags,
      '#title' => $this->t('Product Category'),
      '#default_value' => $sigmaxim_workflow_order_type->getProductCategory(),
      '#description' => $this->t("Choose a taxonomy from the drop-down to this Product"),
    );

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $sigmaxim_workflow_order_type->id(),
      '#machine_name' => [
        'exists' => '\Drupal\sigmaxim_workflow\Entity\ProductsType::load',
      ],
      '#disabled' => !$sigmaxim_workflow_order_type->isNew(),
    ];

    $form['permission'] = [
      '#title' => $this->t('Permission'),
      '#type' => 'textfield',
      '#default_value' => $sigmaxim_workflow_order_type->getPermission(),
      '#type' => 'hidden',
    ];

    $form['permissionlabel'] = array(
        '#type' => 'item',
        '#markup' => '<br><b> Permissions </b>',
    );

    $saved_roles = $form['permission']['#default_value'];

    $roles = \Drupal::entityTypeManager()->getStorage('user_role')->loadMultiple();
    $role_arr = [];
    $role_arr = ['anonymous', 'authenticated', 'content_editor'];
    foreach ($roles as $role) {
      if (!in_array($role->id(), $role_arr)) {
        $role_id = $role->id().'_role';
        $def_role = FALSE;

        if (is_array($saved_roles) && in_array($role_id, $saved_roles)) {
          $def_role = TRUE;
        }
        $form[$role_id] = [
          '#type' => 'checkbox',
          '#title' => $this->t($role->label()),
          '#default_value' => $def_role,
          '#weight' => 2,
        ];
      }
    }
    /* You will need additional form elements for your custom properties. */

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    unset($actions['delete']);
    $actions['submit']['#value'] = $this->t('Save configuration');
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {

    $form_value = $form_state->getValues();

    foreach ($form_value as $key => $value) {
      if (strpos($key, 'role') && $value) {
        $roles[] = $key;
      }
    }

    $sigmaxim_workflow_order_type = $this->entity;
    $sigmaxim_workflow_order_type->set('permission', $roles);
    $status = $sigmaxim_workflow_order_type->save();
    
    // Creating a folder for the product name to save the orders.
    $watched_folder = $form_state->getValue('watched_folder');
    if ($watched_folder) {
      $new_folder = 'private://'.$watched_folder.'/';
      $new_folder_filedepot = 'private://filedepot/';
      mkdir($new_folder, FileSystem::CHMOD_DIRECTORY, TRUE);
      mkdir($new_folder_filedepot, FileSystem::CHMOD_DIRECTORY, TRUE);
      mkdir('public://filedepot/', FileSystem::CHMOD_DIRECTORY, TRUE);
    }

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Product %label has been created.', [
          '%label' => $sigmaxim_workflow_order_type->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Product %label has been updated.', [
          '%label' => $sigmaxim_workflow_order_type->label(),
        ]));
    }
    $form_state->setRedirectUrl($sigmaxim_workflow_order_type->toUrl('collection'));
  }

}
