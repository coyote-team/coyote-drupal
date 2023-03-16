<?php

namespace Drupal\coyote_img_desc;

use Coyote\ContentHelper\Image;
use PAC_Vendor\Coyote\CoyoteApiClientHelperFunctions;
use PAC_Vendor\Coyote\Model\ResourceModel;
use PAC_Vendor\Coyote\Payload\CreateResourcePayload;
use PAC_Vendor\Coyote\Payload\ResourceRepresentationPayload;
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
      $entry->coyote_resource_id,
      $entry->original_description,
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
      $image->getSrc(),
      intval($resource->getId()),
      $image->getAlt(),
      $coyoteDescription
    );

    DB::insertResource($imageResource);

    return $imageResource;
  }

  private static function getImageUrl(string $url): string
  {
    // if the image is relative, strip off the first slash
    if (mb_substr($url, 0, 1) === '/') {
      $url = \Drupal::service('file_url_generator')
        ->generateAbsoluteString(mb_substr($url, 1));
    }

    // strip off the scheme
    return preg_replace('/^https?:/', '', $url, 1);
  }

  private static function getImageResourceFromAPI(Image $image, ?string $hostUri = null): ?ImageResource
  {
    $config = \Drupal::config('coyote_img_desc.settings');
    $token = $config->get('api_token');
    $endpoint = $config->get('api_endpoint');

    if(!self::isDefined($token) || !self::isDefined($endpoint)) {
      return null;
    }

    $organizationId = $config->get('api_organization');

    if (is_null($organizationId)) {
      return null;
    }

    $resourceGroupId = $config->get('api_resource_group');

    $url = self::getImageUrl($image->getSrc());
    $name = $image->getFigureCaption() ?? $url;

    $payload = new CreateResourcePayload(
      $name, $url, $resourceGroupId, $hostUri
    );

    $alt = $image->getAlt();

    if (!is_null($alt)) {
      $payload->addRepresentation($alt, Constants::METUM);
    }

    $resource = CoyoteApiClientHelperFunctions::createResource($endpoint, $token, $organizationId, $payload);

    if (is_null($resource)) {
      return null;
    }

    return self::createImageResourceFromCoyoteResource($image, $resource);
  }

  public static function getImageResource(Image $image, ?string $hostUri = null): ?ImageResource
  {
    return self::getImageResourceFromDB($image) ?? self::getImageResourceFromAPI($image, $hostUri);
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

