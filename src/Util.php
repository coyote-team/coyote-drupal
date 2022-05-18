<?php

namespace Drupal\coyote_img_desc;

use Coyote\ContentHelper\Image;
use Coyote\CoyoteApiClientHelperFunctions;
use Coyote\Model\ResourceModel;
use Coyote\Payload\CreateResourcePayload;
use Drupal\coyote_img_desc\DB;
use Drupal\coyote_img_desc\ImageResource;

class Util {
  private static function getImageResourceFromDB(Image $image): ?ImageResource
  {
    $sha1 = sha1($image->getSrc());
    $entry = DB::getResourceByHash($sha1);

    if (is_null($entry)) {
      return null;
    }

    return new ImageResource(
      $entry->source_uri_sha1,
      $entry->source_uri,
      $entry->coyote_id,
      $entry->local_description,
      $entry->coyote_description
    );
  }

  private static function createImageResourceFromCoyoteResource(Image $image, ResourceModel $resource): ImageResource
  {
    $sha1 = sha1($image->getSrc());

    $representation = $resource->getTopRepresentationByMetum(Constants::METUM);
    $coyoteDescription = $representation ? $representation->getText() : '';

    $imageResource = new ImageResource(
      $sha1,
      $resource->getSourceUri(),
      intval($resource->getId()),
      $image->getAlt(),
      $coyoteDescription
    );

    DB::insertResource($imageResource);

    return $imageResource;

  }

  private static function getImageResourceFromAPI(Image $image): ?ImageResource
  {

    $config = \Drupal::config('coyote_img_desc.settings');
    $token = $config->get('api_token');
    $endpoint = $config->get('api_endpoint');

    // TODO verify that we _have_ an org ID, otherwise we shouldn't even move forward
    $organizationId = $config->get('api_organization_id');
    $resourceGroupId = $config->get('api_resource_group_id');

    if(!self::isDefined($token) || !self::isDefined($endpoint)) {
      return null;
    }

    // TODO obtain the host_uri from somewhere
    $payload = new CreateResourcePayload(
      $image->getSrc(), $image->getSrc(), $resourceGroupId, null
    );

    $resource = CoyoteApiClientHelperFunctions::createResource($endpoint, $token, $organizationId, $payload);

    if (is_null($resource)) {
      return null;
    }

    return self::createImageResourceFromCoyoteResource($image, $resource);
  }

  public static function getImageResource(Image $image): ?ImageResource
  {
    return self::getImageResourceFromDB($image) ?? self::getImageResourceFromAPI($image);
  }

  private static function isDefined(string $var): bool
  {
    return strlen($var) > 0;
  }

  public static function getResourceGroupUri(): string
  {
    $host = \Drupal::request()->getSchemeAndHttpHost();
    return sprintf('%s/%s', $host, Constants::RESOURCE_GROUP_ENDPOINT_SUFFIX);
  }

}

