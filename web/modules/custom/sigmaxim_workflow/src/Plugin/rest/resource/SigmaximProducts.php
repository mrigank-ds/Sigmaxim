<?php

namespace Drupal\sigmaxim_workflow\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\image\Entity\ImageStyle;

/**
 * Provide rest resource for the products.
 *
 * @RestResource(
 *   id = "all_products",
 *   label = @Translation("All products"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/all-products"
 *   }
 * )
 */
class SigmaximProducts extends ResourceBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The current HTTP request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Constructs a new SigmaximProducts object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   A current user instance.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current HTTP request.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountProxyInterface $current_user,
    Request $request
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

    $this->currentUser = $current_user;
    $this->request = $request;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('sigmaxim_workflow'),
      $container->get('current_user'),
      $container->get('request_stack')->getCurrentRequest()
    );
  }

  /**
   * Responds to entity GET requests.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing the list of products.
   */
  public function get() {
    $current_user = $this->currentUser;
    $current_user_roles = $current_user->getRoles();
    $current_user_role = [];
    foreach ($current_user_roles as $value) {
      $current_user_role[] = $value . '_role';
    }

    $query = \Drupal::entityQuery('taxonomy_term');
    $query->condition('vid', "sigmaxim_workflow_product_info");
    $query->accessCheck(FALSE);
    $tids = $query->execute();
    $terms = \Drupal\taxonomy\Entity\Term::loadMultiple($tids);
    $tags = [];
    $cnt = 0;
    foreach ($terms as $term) {
      $imageUri = '';
      if ($term->field_product_image->target_id) {
        $imageUri = ImageStyle::load('thumbnail2')->buildUrl($term->field_product_image->entity->getFileUri());
      }
      $role_permission = [];
      $product_url = '';
      $product_entity = '';
      $product_arr = \Drupal::entityQuery('sigmaxim_workflow_order_type')
        ->condition('product_category', $term->id())
        ->execute();
      if ($product_arr) {
        $product = reset($product_arr);
        $product_entity = \Drupal::entityTypeManager()->getStorage('sigmaxim_workflow_order_type')->load($product);
        if ($product_entity) {
          $product_permission = $product_entity->getPermission();
          if ($product_permission) {
            $role_permission = array_intersect($current_user_role, $product_permission);
          }
        }
        if ($product) {
          $product_url = '/order/add/' . $product;
        }
      }
      if (count($role_permission) > 0 || !$product_entity) {
        $tags[$cnt]['name'] = $term->label();
        $tags[$cnt]['tid'] = $term->id();
        $tags[$cnt]['pid'] = $term->parent->target_id;
        $tags[$cnt]['img'] = $imageUri;
        $tags[$cnt]['url'] = $product_url;
        $cnt++;
      }
    }

    $error = 200;
    $response = new ModifiedResourceResponse($tags, $error);

    return $response;
  }

}
