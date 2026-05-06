<?php

namespace Drupal\sigmaxim_workflow\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for deleting a Products revision.
 *
 * @ingroup sigmaxim_workflow
 */
class ProductsRevisionDeleteForm extends ConfirmFormBase {

  /**
   * The Products revision.
   *
   * @var \Drupal\sigmaxim_workflow\Entity\ProductsInterface
   */
  protected $revision;

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
    return 'sigmaxim_workflow_order_revision_delete_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the revision from %revision-date?', [
      '%revision-date' => \Drupal::service('date.formatter')->format($this->revision->getRevisionCreationTime()),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.sigmaxim_workflow_order.version_history', ['sigmaxim_workflow_order' => $this->revision->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $sigmaxim_workflow_order_revision = NULL) {
    $this->revision = $this->productsStorage->loadRevision($sigmaxim_workflow_order_revision);
    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->productsStorage->deleteRevision($this->revision->getRevisionId());

    $this->logger('content')->notice('Products: deleted %title revision %revision.', ['%title' => $this->revision->label(), '%revision' => $this->revision->getRevisionId()]);
    $this->messenger()->addMessage(t('Revision from %revision-date of Products %title has been deleted.', ['%revision-date' => \Drupal::service('date.formatter')->format($this->revision->getRevisionCreationTime()), '%title' => $this->revision->label()]));
    $form_state->setRedirect(
      'entity.sigmaxim_workflow_order.canonical',
       ['sigmaxim_workflow_order' => $this->revision->id()]
    );
    if ($this->connection->query('SELECT COUNT(DISTINCT vid) FROM {sigmaxim_workflow_order_field_revision} WHERE id = :id', [':id' => $this->revision->id()])->fetchField() > 1) {
      $form_state->setRedirect(
        'entity.sigmaxim_workflow_order.version_history',
         ['sigmaxim_workflow_order' => $this->revision->id()]
      );
    }
  }

}
