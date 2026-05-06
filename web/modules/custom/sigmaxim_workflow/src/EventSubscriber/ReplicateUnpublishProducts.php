<?php

namespace Drupal\sigmaxim_workflow\EventSubscriber;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\sigmaxim_workflow\Entity\Products;
use Drupal\replicate\Events\ReplicateAlterEvent;
use Drupal\replicate\Events\ReplicatorEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Makes  the product unpublished after it replicated.
 */
class ReplicateUnpublishProducts implements EventSubscriberInterface {

  /**
   * Drupal\Core\Language\LanguageManagerInterface definition.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a \Drupal\sigmaxim_workflow\EventSubscriber\RedirectRequestSubscriber object.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface
   *   The language manager.
   */
  public function __construct(LanguageManagerInterface $language_manager) {
    $this->languageManager = $language_manager;
  }

  /**
   * Sets the status of a replicated product to unpublished.
   *
   * @param \Drupal\replicate\Events\ReplicateAlterEvent $event
   *  The event fired by the replicator.
   *  For more details look at replicate_api doc.
   *
   */
  public function setUnpublished(ReplicateAlterEvent $event) {
    $cloned_entity = $event->getEntity();
    if (!$cloned_entity instanceof Products) {
      return;
    }

    // Get languages enable.
    $languages = $this->languageManager->getLanguages();
    foreach ($languages as $lang_code => $language) {
      // Check if entity has translation by lang_code and unpublished.
      if ($cloned_entity->hasTranslation($lang_code)) {
        $cloned_entity_translation = $cloned_entity->getTranslation($lang_code);
        // Set product translation as unpublished.
        $cloned_entity_translation->set('status', 0);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[ReplicatorEvents::REPLICATE_ALTER][] = 'setUnpublished';
    return $events;
  }

}
