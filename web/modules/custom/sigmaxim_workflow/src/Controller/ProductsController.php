<?php

namespace Drupal\sigmaxim_workflow\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Url;
use Drupal\sigmaxim_workflow\Entity\ProductsInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Link;

/**
 * Class ProductsController.
 *
 *  Returns responses for Products routes.
 */
class ProductsController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->renderer = $container->get('renderer');
    return $instance;
  }

  /**
   * Displays a Products revision.
   *
   * @param int $sigmaxim_workflow_order_revision
   *   The Products revision ID.
   *
   * @return array
   *   An array suitable for drupal_render().
   */
  public function revisionShow($sigmaxim_workflow_order_revision) {
    $sigmaxim_workflow_order = $this->entityTypeManager()->getStorage('sigmaxim_workflow_order')
      ->loadRevision($sigmaxim_workflow_order_revision);
    $view_builder = $this->entityTypeManager()->getViewBuilder('sigmaxim_workflow_order');

    return $view_builder->view($sigmaxim_workflow_order);
  }

  /**
   * Page title callback for a Products revision.
   *
   * @param int $sigmaxim_workflow_order_revision
   *   The Products revision ID.
   *
   * @return string
   *   The page title.
   */
  public function revisionPageTitle($sigmaxim_workflow_order_revision) {
    $sigmaxim_workflow_order = $this->entityTypeManager()->getStorage('sigmaxim_workflow_order')
      ->loadRevision($sigmaxim_workflow_order_revision);
    return $this->t('Revision of %title from %date', [
      '%title' => $sigmaxim_workflow_order->label(),
      '%date' => $this->dateFormatter->format($sigmaxim_workflow_order->getRevisionCreationTime()),
    ]);
  }

  /**
   * Generates an overview table of older revisions of a Products.
   *
   * @param \Drupal\sigmaxim_workflow\Entity\ProductsInterface $sigmaxim_workflow_order
   *   A Products object.
   *
   * @return array
   *   An array as expected by drupal_render().
   */
  public function revisionOverview(ProductsInterface $sigmaxim_workflow_order) {
    $account = $this->currentUser();
    $sigmaxim_workflow_order_storage = $this->entityTypeManager()->getStorage('sigmaxim_workflow_order');

    $langcode = $sigmaxim_workflow_order->language()->getId();
    $langname = $sigmaxim_workflow_order->language()->getName();
    $languages = $sigmaxim_workflow_order->getTranslationLanguages();
    $has_translations = (count($languages) > 1);
    $build['#title'] = $has_translations ? $this->t('@langname revisions for %title', ['@langname' => $langname, '%title' => $sigmaxim_workflow_order->label()]) : $this->t('Revisions for %title', ['%title' => $sigmaxim_workflow_order->label()]);

    $header = [$this->t('Revision'), $this->t('Operations')];
    $revert_permission = (($account->hasPermission("revert all products revisions") || $account->hasPermission('administer products entities')));
    $delete_permission = (($account->hasPermission("delete all products revisions") || $account->hasPermission('administer products entities')));

    $rows = [];

    $vids = $sigmaxim_workflow_order_storage->revisionIds($sigmaxim_workflow_order);

    $latest_revision = TRUE;

    foreach (array_reverse($vids) as $vid) {
      /** @var \Drupal\sigmaxim_workflow\ProductsInterface $revision */
      $revision = $sigmaxim_workflow_order_storage->loadRevision($vid);
      // Only show revisions that are affected by the language that is being
      // displayed.
      if ($revision->hasTranslation($langcode) && $revision->getTranslation($langcode)->isRevisionTranslationAffected()) {
        $username = [
          '#theme' => 'username',
          '#account' => $revision->getRevisionUser(),
        ];

        // Use revision link to link to revisions that are not active.
        $date = $this->dateFormatter->format($revision->getRevisionCreationTime(), 'short');
        if ($vid != $sigmaxim_workflow_order->getRevisionId()) {
          $link = Link::fromTextAndUrl($date, new Url('entity.sigmaxim_workflow_order.revision', [
            'sigmaxim_workflow_order' => $sigmaxim_workflow_order->id(),
            'sigmaxim_workflow_order_revision' => $vid,
          ]))->toString();
        }
        else {
          $link = $sigmaxim_workflow_order->toLink($date)->toString();
        }

        $row = [];
        $column = [
          'data' => [
            '#type' => 'inline_template',
            '#template' => '{% trans %}{{ date }} by {{ username }}{% endtrans %}{% if message %}<p class="revision-log">{{ message }}</p>{% endif %}',
            '#context' => [
              'date' => $link,
              'username' => $this->renderer->renderPlain($username),
              'message' => [
                '#markup' => $revision->getRevisionLogMessage(),
                '#allowed_tags' => Xss::getHtmlTagList(),
              ],
            ],
          ],
        ];
        $row[] = $column;

        if ($latest_revision) {
          $row[] = [
            'data' => [
              '#prefix' => '<em>',
              '#markup' => $this->t('Current revision'),
              '#suffix' => '</em>',
            ],
          ];
          foreach ($row as &$current) {
            $current['class'] = ['revision-current'];
          }
          $latest_revision = FALSE;
        }
        else {
          $links = [];
          if ($revert_permission) {
            $links['revert'] = [
              'title' => $this->t('Revert'),
              'url' => $has_translations ?
              Url::fromRoute('entity.sigmaxim_workflow_order.translation_revert', [
                'sigmaxim_workflow_order' => $sigmaxim_workflow_order->id(),
                'sigmaxim_workflow_order_revision' => $vid,
                'langcode' => $langcode,
              ]) :
              Url::fromRoute('entity.sigmaxim_workflow_order.revision_revert', [
                'sigmaxim_workflow_order' => $sigmaxim_workflow_order->id(),
                'sigmaxim_workflow_order_revision' => $vid,
              ]),
            ];
          }

          if ($delete_permission) {
            $links['delete'] = [
              'title' => $this->t('Delete'),
              'url' => Url::fromRoute('entity.sigmaxim_workflow_order.revision_delete', [
                'sigmaxim_workflow_order' => $sigmaxim_workflow_order->id(),
                'sigmaxim_workflow_order_revision' => $vid,
              ]),
            ];
          }

          $row[] = [
            'data' => [
              '#type' => 'operations',
              '#links' => $links,
            ],
          ];
        }

        $rows[] = $row;
      }
    }

    $build['sigmaxim_workflow_order_revisions_table'] = [
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
    ];

    return $build;
  }

}
