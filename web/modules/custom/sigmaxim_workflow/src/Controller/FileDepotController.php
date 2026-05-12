<?php
namespace Drupal\sigmaxim_workflow\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Render\Markup;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Database\Connection;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Drupal\Core\Link;
use Drupal\Component\Utility\Html;
use Drupal\Core\Database\Database;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;


class FileDepotController extends ControllerBase {

public function orderResults(int $id): array {

  $currentUser = \Drupal::currentUser();
  $uid = (int) $currentUser->id();

  // Load the entity (replace 'sigmaxim_workflow_order' with your entity type)
  $order = \Drupal::entityTypeManager()
    ->getStorage('sigmaxim_workflow_order')
    ->load($id);

  // If entity doesn't exist
  if (!$order) {
    return ['#markup' => $this->t('The order was not found.')];
  }

  // Get the creator of the entity
  $creatorId = (int) $order->getOwnerId();

  // Authorization check
  if ($uid != 1 && $uid !== $creatorId) {
    return ['#markup' => $this->t('You are not authorized to access this folder.')];
  }

  // ──────── Folder Path Setup ────────
  $uri = "private://filedepot/$id";
  $fileSystem = \Drupal::service('file_system');
  $realPath = $fileSystem->realpath($uri);

  if (!$realPath || !is_dir($realPath)) {
    return ['#markup' => $this->t('The folder and file are empty.')];
  }


$files = array_filter(glob($realPath . '/*'), 'is_file');
if (empty($files)) {
  return ['#markup' => $this->t('The folder and file are empty.')];
}

$header = [
  $this->t('File Name'),
  $this->t('Action'),
];

$rows = [];

foreach ($files as $filePath) {
  $fileName = basename($filePath);
  $relativePath = "$id/$fileName";

  // Create internal URL with query
  $url = Url::fromUri('internal:/workflow/file/download', [
    'query' => ['filepath' => $relativePath],
  ]);

  // Correct way to add attributes to link
  $link = Link::fromTextAndUrl($this->t('Download'), $url);
  $link->getUrl()->setOption('attributes', ['class' => ['button', 'button--primary', 'button--small']]);



    $rows[] = [
    'data' => [
      ['data' => $fileName], 
      $link,
    ],
  ];
}

// Return table render array
return [
  '#type' => 'table',
  '#header' => $header,
  '#rows' => $rows,
  '#empty' => $this->t('No files found.'),
  '#title' => $this->t('Files available for download'),
];

}



public function download(Request $request) {
  $filepath = $request->query->get('filepath');

  if (empty($filepath)) {
    throw new NotFoundHttpException("No filepath provided.");
  }

  $filepath = urldecode($filepath);

  // Prevent directory traversal
  if (strpos($filepath, '..') !== false || str_starts_with($filepath, '/')) {
    throw new NotFoundHttpException("Invalid file path.");
  }

  // Build full file URI
  $uri = 'private://filedepot/' . $filepath;

  // Resolve real path
  $file_system = \Drupal::service('file_system');
  $real_path = $file_system->realpath($uri);

  // Check existence and validity
  if (!$real_path || !file_exists($real_path) || !is_file($real_path)) {
    throw new NotFoundHttpException("File not found.");
  }


  // Set MIME type automatically
  $mime_type = mime_content_type($real_path);

  // Return file as download
  $response = new BinaryFileResponse($real_path);
  $response->headers->set('Content-Type', $mime_type);
  $response->setContentDisposition(
    ResponseHeaderBag::DISPOSITION_ATTACHMENT,
    basename($real_path)
  );

  return $response;
}

public static function accessResults($id) {
    $user = \Drupal::currentUser();
  
    
    // Allow access to authenticated users only
    if ($user->isAuthenticated()) {
        return AccessResult::allowed();
    }
    
    return AccessResult::forbidden();
}


}