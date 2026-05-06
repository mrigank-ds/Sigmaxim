<?php

namespace Drupal\sigmaxim_workflow\EventSubscriber;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Drupal\Core\Session\AccountInterface;

/**
 * Event subscriber subscribing to KernelEvents::REQUEST.
 */
class RedirectAnonymousSubscriber implements EventSubscriberInterface {

  // public function __construct() {
  //   $this->account = \Drupal::currentUser();
  // }

   

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $account;

  /**
   * Constructs a RedirectAnonymousSubscriber object.
   *
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The current user service. Optional for backward compatibility.
   */
  public function __construct(?AccountInterface $account = null) {
    // Fallback to static call if not injected (for legacy instantiation).
    $this->account = $account ?: \Drupal::currentUser();
  }


  public function checkAuthStatus(RequestEvent  $event) {
    $current_path = \Drupal::service('path.current')->getPath();
    if ($this->account->isAnonymous()
      && $current_path != '/user/login') {
      $response = new RedirectResponse('/user/login', 301);
      $event->setResponse($response);
    }
    elseif (!$this->account->isAnonymous()) {
      $c_path = explode('/', $current_path);
      $user = \Drupal::currentUser();
      $uid = $user->id();
      $user_roles = $user->getRoles();
      if ($c_path && count($c_path) > 2 && $c_path[1] == 'order' && is_numeric($c_path[2]) && !in_array('admin', $user_roles) && !in_array('administrator', $user_roles)) {
        $order_id = $c_path[2];
        $sigmaxim_workflow_order = \Drupal::entityTypeManager()->getStorage('sigmaxim_workflow_order')->load($order_id);
        $owner = $sigmaxim_workflow_order->getOwnerId();
        if ($owner != $uid) {
          \Drupal::service('messenger')->addMessage("You are not authorised to view this page.");
          $response = new RedirectResponse('/', 301);
          $event->setResponse($response);
        }
      }
      elseif ($c_path && count($c_path) > 2 && $c_path[2] == 'add' && !in_array('admin', $user_roles) && !in_array('administrator', $user_roles)) {
        $product = $c_path[3];
        $product_entity = \Drupal::entityTypeManager()->getStorage('sigmaxim_workflow_order_type')->load($product);
        $permissions = $product_entity->getPermission();
        foreach ($permissions as $permission) {
          $product_roles[] = str_replace('_role', '', $permission);
        }

        if ($product_roles == null || ($product_roles && !array_intersect($user_roles, $product_roles))) {
          \Drupal::service('messenger')->addMessage("You are not authorised to view this page.");
          $response = new RedirectResponse('/', 301);
          $event->setResponse($response);          
        }
      }
    }
    return;
  }

  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['checkAuthStatus', 100];
    return $events;
  }

}