<?php

namespace Drupal\monarch_data_entity\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for handling Monarch Data Entity JSON callbacks.
 */
class JsonController extends ControllerBase {

  /**
   * Handle the JSON callback request.
   */
  public function handle(Request $request) {
    $post_data = $request->request->all();
    $callback = $post_data['callback'] ?? '';
    $arguments = $post_data['arguments'] ?? $post_data['values'] ?? [];

    if (empty($callback)) {
      return new JsonResponse(['success' => FALSE, 'message' => 'No callback specified']);
    }

    // Map old sigmaxim_fields callback to new monarch_data_entity callback if needed.
    if ($callback == 'sigmaxim_fields_json_data_reference_get_result_table') {
        $callback = 'monarch_data_entity_json_data_reference_get_result_table';
    }

    if (function_exists($callback)) {
      if ($callback == 'monarch_data_entity_json_data_reference_get_result_table') {
        $field_name = $arguments['field_name'] ?? NULL;
        $bundle = $arguments['bundle'] ?? NULL;
        $entity_type = $arguments['entity_type'] ?? NULL;
        $values = $arguments['values'] ?? [];
        
        $result = monarch_data_entity_json_data_reference_get_result_table($field_name, $bundle, $entity_type, $values);
      } else {
        $result = call_user_func($callback, $arguments);
      }
      
      return new JsonResponse($result);
    }

    return new JsonResponse(['success' => FALSE, 'message' => 'Callback function not found: ' . $callback]);
  }

}
