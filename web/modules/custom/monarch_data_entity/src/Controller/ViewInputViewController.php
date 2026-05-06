<?php

namespace Drupal\monarch_data_entity\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for Monarch Data Entity routes.
 */
class ViewInputViewController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);

    $instance->entityTypeManager = $container->get('entity_type.manager');

    return $instance;
  }

  /**
   * Builds the response.
   */
  public function build($view_name, $view_display_id = 'default', $view_arguments = NULL) {
    $build = [];
    $build['#attached']['library'][] = 'monarch_data_entity/view_input_view_embed';

    if (is_string($view_name) && is_string($view_display_id)) {
      $args = [];

      if (is_string($view_arguments ?? NULL)) {
        $args = json_decode(base64_decode($view_arguments), TRUE);

        foreach ($args as $i => $v) {
          if (empty($v)) {
            $args[$i] = NULL;
          }
        }
      }

      $view = Views::getView($view_name);

      if (!$view || !$view->access($view_display_id)) {
        return [];
      }

      $build['content'] = $view->preview($view_display_id, $args);
    }

    return $build;
  }

}
