<?php

namespace Drupal\sigmaxim_data_entity\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\monarch_data_entity\Entity\DataEntityType;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Pager\PagerManagerInterface;

/**
 * Provides a Sigmaxim Data Entity form.
 */
class TableViewForm extends FormBase {

  /**
   * The entity_type.manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity_field.manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The pager manager service.
   *
   * @var \Drupal\Core\Pager\PagerManagerInterface
   */
  protected $pagerManager;

  /**
   * Number of items per page.
   */
  const ITEMS_PER_PAGE = 50;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);

    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->entityFieldManager = $container->get('entity_field.manager');
    $instance->renderer = $container->get('renderer');
    $instance->pagerManager = $container->get('pager.manager');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sigmaxim_data_entity_table_view';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, DataEntityType $bundle = NULL) {
    if (empty($bundle)) {
      return [];
    }

    $bundle_id = $bundle->id();

    $form = [];
    $headers = [];
    /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $default_display */
    $default_display = $this->entityTypeManager->getStorage('entity_view_display')->load("data_entity.$bundle_id.default");
    if (!$default_display) {
      return $form;
    }
    $display_settings = $default_display->get('content');

    uasort($display_settings, function ($a, $b) {
      return $a['weight'] - $b['weight'];
    });

    foreach (array_keys($display_settings) as $field_name) {
      $headers[$field_name] = $field_name;
    }

    if (!empty($headers)) {
      $des = $this->entityTypeManager->getStorage('data_entity');

      // Count total for pager.
      $total = $des->getQuery()
        ->accessCheck(FALSE)
        ->condition('bundle', $bundle->id())
        ->count()
        ->execute();

      $pager = $this->pagerManager->createPager($total, self::ITEMS_PER_PAGE);
      $page = $pager->getCurrentPage();

      $ids = $des->getQuery()
        ->accessCheck(FALSE)
        ->condition('bundle', $bundle->id())
        ->range($page * self::ITEMS_PER_PAGE, self::ITEMS_PER_PAGE)
        ->execute();

      $entities = $des->loadMultiple($ids);

      if ($entities) {
        $vb = $this->entityTypeManager->getViewBuilder('data_entity');

        $form['table'] = [
          '#type' => 'table',
          '#header' => [],
        ];

        $form['table']['#header'] = $headers;

        $can_edit = $this->currentUser()->hasPermission('edit data');
        $can_delete = $this->currentUser()->hasPermission('delete data');

        if ($can_edit || $can_delete) {
          $form['table']['#header']['entity_link'] = 'Actions';
        }
      $field_definitions = $this->entityFieldManager->getFieldDefinitions('data_entity', $bundle_id);
        foreach ($entities as $entity_id => $entity) {
          $entity_render = $vb->view($entity);
          $this->renderer->render($entity_render);

          foreach ($headers as $field_name => $label) {
            // $form['table']['#header'][$field_name] = $entity_render[$field_name]['#title'] ?? $label;
            // $form['table'][$entity_id][$field_name] = $entity_render[$field_name];
            // $form['table'][$entity_id][$field_name]['#label_display'] = 'hidden';
            $field_label = $field_definitions[$field_name]->getLabel();
            $form['table']['#header'][$field_name] = $field_label;

            $form['table'][$entity_id][$field_name] = $entity_render[$field_name];
            $form['table'][$entity_id][$field_name]['#label_display'] = 'hidden';
          }

          if ($can_edit) {
            $form['table'][$entity_id]['entity_link']['edit'] = $entity->toLink('Edit', 'edit-form')->toRenderable();
          }

          if ($can_edit && $can_delete) {
            $form['table'][$entity_id]['entity_link']['edit_delete_separator']['#markup'] = ' | ';
          }

          if ($can_delete) {
            $form['table'][$entity_id]['entity_link']['delete'] = $entity->toLink('Delete', 'delete-form')->toRenderable();
          }
        }
      }
    }

    $form['pager'] = [
      '#type' => 'pager',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
  }

}
