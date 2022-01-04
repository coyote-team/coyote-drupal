<?php

namespace Drupal\coyote_img_desc\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\coyote_img_desc\Constants;
use Symfony\Component\HttpFoundation\JsonResponse;

class RestApiController extends ControllerBase {
  public static function status(): JsonResponse {
    $version = Constants::VERSION;
    return new JsonResponse(['status' => "Coyote Drupal Plugin v{$version} OK"]);
  }

  public static function callback(): JsonResponse {
    $request = \Drupal::request();
    $payload = JSON::decode($request->getContent());
    return new JsonResponse(['status' => 'not yet implemented', 'payload' => $payload]);
  }
}