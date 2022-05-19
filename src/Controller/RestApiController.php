<?php

namespace Drupal\coyote_img_desc\Controller;

use Coyote\CoyoteApiClient;
use Drupal\Core\Controller\ControllerBase;
use Drupal\coyote_img_desc\Constants;
use Drupal\coyote_img_desc\Hook\RestApiUpdatePostHook;
use Symfony\Component\HttpFoundation\JsonResponse;

class RestApiController extends ControllerBase {
  public static function status(): JsonResponse {
    $version = Constants::VERSION;
    return new JsonResponse(['status' => "Coyote Drupal Plugin v{$version} OK"]);
  }

  public static function callback(): JsonResponse {
    $ddm = \Drupal::service('devel.dumper');
    $request = \Drupal::request();
    $payload = json_decode($request->getContent());

    $update = CoyoteApiClient::parseWebHookResourceUpdate($payload);

    $ddm->debug(['update', $update]);

    $result = RestApiUpdatePostHook::hook($update);

    return new JsonResponse(['status' => $result]);
  }
}