<?php

namespace Drupal\coyote_img_desc\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class ResourceImportController extends ControllerBase {
  public static function import(): JsonResponse {
    $request = \Drupal::request();
    $payload = JSON::decode($request->getContent());

    $query = \Drupal::entityTypeManager()->getStorage('node')->getQuery();

    $query
      ->condition('status', TRUE)
      ->range(0, 10)
      ->sort('created', 'DESC');

    $ids = $query->execute();
    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($ids);

    $data = array_map(function (NodeInterface $node) {
      return [
        'id' => $node->id(),
        'title' => $node->getTitle()
      ];
    }, $nodes);

    return new JsonResponse(['status' => 'not yet implemented', 'payload' => $payload, 'data' => $data]);
  }
}