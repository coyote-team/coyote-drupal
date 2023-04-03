<?php

namespace Drupal\coyote_img_desc\Controller;

use Coyote\CoyoteApiClient;
use Drupal\Core\Controller\ControllerBase;
use Drupal\coyote_img_desc\Constants;
use Drupal\coyote_img_desc\Hook\RestApiUpdatePostHook;
use Drupal\coyote_img_desc\Util;
use Symfony\Component\HttpFoundation\JsonResponse;

class RestApiController extends ControllerBase {
  public static function status(): JsonResponse
  {
    $version = Constants::VERSION;
    return new JsonResponse(['status' => "Coyote Drupal Plugin v{$version} OK"]);
  }

  public static function callback(): JsonResponse
  {
    $config = \Drupal::config('coyote_img_desc.settings');
    $ignore = $config->get('ignore_coyote_webhook_calls');
    if ($ignore) return new JsonResponse(['status' => 'ignored'], 403);

    $request = \Drupal::request();
    $payload = json_decode($request->getContent());

    $update = CoyoteApiClient::parseWebHookResourceUpdate($payload);

    $result = RestApiUpdatePostHook::hook($update);

    return new JsonResponse(['status' => $result]);
  }

  public static function get_info(): JsonResponse
  {
    $request = \Drupal::request();

    $url = $request->query->get('url');

    if (!is_string($url) || !strlen($url)) {
      return new JsonResponse(['status' => 'Invalid request'], 400);
    }

    $resource = Util::getImageResourceByUrl($url);

    if (is_null($resource)) {
      return new JsonResponse(['status' => 'Not Found'], 404);
    }

    return new JsonResponse([
      'id' => $resource->getCoyoteId(),
      'alt' => $resource->getCoyoteDescription(),
      'link' => Util::getResourceLink($resource)
    ]);
  }
}
