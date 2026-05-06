<?php

namespace Drupal\sigmaxim_workflow\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for restore a Products.
 *
 * @ingroup sigmaxim_workflow
 */
class ProductsRestoreForm extends ConfirmFormBase {

  /**
   * The Products storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $productEntityStorage;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->productEntityStorage = $container->get('entity_type.manager')->getStorage('sigmaxim_workflow_order');
    $instance->connection = $container->get('database');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'product_restore_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to restore this Order?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('<front>');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $current_path = explode('/', \Drupal::service('path.current')->getPath());
    $order_id = $current_path[count($current_path)-2];
    $message = "You are about to archive order " . '<em>' .$order_id . '<em>' . '.';
    return $this->t($message);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Restore');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $sigmaxim_workflow_order = NULL) {
    $this->productEntityStorage = $this->productEntityStorage->load($sigmaxim_workflow_order);
    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity = $this->productEntityStorage;
    $entity->set('archive', 0);
    $entity->save();
    $this->messenger()->addMessage(t('Order Restored'));
    $form_state->setRedirect('<front>');
  }

}
