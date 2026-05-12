<?php

namespace Drupal\sigmaxim_workflow\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for archive a Products.
 *
 * @ingroup sigmaxim_workflow
 */
class ProductsArchiveForm extends ConfirmFormBase {

  /**
   * The Products storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $productsStorage;

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
    $instance->productsStorage = $container->get('entity_type.manager')->getStorage('sigmaxim_workflow_order');
    $instance->connection = $container->get('database');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sigmaxim_workflow_order_archive_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to archive this Order?');
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
  public function getCancelUrl() {
    return new Url('<front>');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Archive');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $sigmaxim_workflow_order = NULL) {
    $this->productsStorage = $this->productsStorage->load($sigmaxim_workflow_order);
    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity = $this->productsStorage;
    $entity->set('archive', 1);
    $entity->save();
    $this->messenger()->addMessage(t('Order Archived'));
    $form_state->setRedirect('<front>');
  }

}
