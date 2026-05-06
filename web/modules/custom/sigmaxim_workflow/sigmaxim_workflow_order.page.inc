<?php

/**
 * @file
 * Contains sigmaxim_workflow_order.page.inc.
 *
 * Page callback for Products entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Products templates.
 *
 * Default template: sigmaxim_workflow_order.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_sigmaxim_workflow_order(array &$variables) {
  // Fetch Products Entity Object.
  $sigmaxim_workflow_order = $variables['elements']['#sigmaxim_workflow_order'];

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}
