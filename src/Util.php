<?php

namespace Drupal\coyote_img_desc;

use Coyote\ContentHelper\Image;
use Coyote\CoyoteApiClientHelperFunctions;
use Coyote\Model\ResourceModel;
use Coyote\Payload\CreateResourcePayload;
use phpDocumentor\Reflection\Types\Boolean;

class Util {
  private const ENDPOINT_PATTERN = '/^https\:\/\/[a-z]+\.coyote\.pics\/?/';

  /**
   * @param \Coyote\ContentHelper\Image $image
   *
   * @return \Drupal\coyote_img_desc\ImageResource|null
   */
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

  /**
   * Convert an API ResourceModel into an ImageResource and store it in the DB.
   *
   * @param \Coyote\ContentHelper\Image $image
   * @param \Coyote\Model\ResourceModel $resource
   *
   * @return \Drupal\coyote_img_desc\ImageResource
   */
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

  /**
   * Generate a protocol-agnostic image URL, e.g. '//example.org/img.png'.
   *
   * @param string $url
   *
   * @return string
   */
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

  /**
   * @param \Coyote\ContentHelper\Image $image
   * @param string|null $hostUri
   *
   * @return \Drupal\coyote_img_desc\ImageResource|null
   */
  private static function getImageResourceFromAPI(Image $image, ?string $hostUri = null): ?ImageResource
  {
    if (Util::isStandAlone()) {
      return null;
    }

    $config = \Drupal::config('coyote_img_desc.settings');
    $token = $config->get('api_token');
    $endpoint = Util::getSuffixedApiEndpoint();

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

  /**
   * Generate a resource link using the supplied Coyote host (testing or live).
   *
   * @param \Drupal\coyote_img_desc\ImageResource $resource
   *
   * @return string
   */
  public static function getResourceLink(ImageResource $resource): string
  {
    $config = \Drupal::config('coyote_img_desc.settings');
    $endpoint = $config->get('api_endpoint');
    $org_id = $config->get('api_organization');

    if (!Util::isDefined($endpoint) || (preg_match(self::ENDPOINT_PATTERN, $endpoint) !== 1)) {
      return 'Coyote';
    }

    return sprintf('<a href="%s/organizations/%s/resources/%d">Coyote</a>', $endpoint, $org_id, $resource->getCoyoteId());
  }

  /**
   * Retrieve a resource either from the database or through the API.
   *
   * @param \Coyote\ContentHelper\Image $image
   * @param string|null $hostUri
   *
   * @return \Drupal\coyote_img_desc\ImageResource|null
   */
  public static function getImageResource(Image $image, ?string $hostUri = null): ?ImageResource
  {
    return self::getImageResourceFromDB($image) ?? self::getImageResourceFromAPI($image, $hostUri);
  }

  /**
   * @param string $url
   *
   * @return \Drupal\coyote_img_desc\ImageResource|null
   */
  public static function getImageResourceByUrl(string $url): ?ImageResource
  {
    return self::getImageResource(new Image($url, ''));
  }

  /**
   * Generate a link to testing or live Coyote website which is safe to inject.
   *
   * @param string|null $suffix
   *
   * @return string
   */
  public static function getCoyoteLink(?string $suffix = null): string {
    $config = \Drupal::config('coyote_img_desc.settings');
    $endpoint = $config->get('api_endpoint');

    if (!Util::isDefined($endpoint) || (preg_match(self::ENDPOINT_PATTERN, $endpoint) !== 1)) {
      return 'Coyote';
    }

    return sprintf('<a href="%s">Coyote</a>', $endpoint);
  }

  /**
   * @param string|null $endpoint
   *
   * @return string|null
   */
  public static function getSuffixedApiEndpoint(?string $endpoint = null): ?string {
    $config = \Drupal::config('coyote_img_desc.settings');
    $endpoint = $endpoint ?? $config->get('api_endpoint');

    if (!Util::isDefined($endpoint)) {
      return null;
    }

    return sprintf("%s/api/v1", $endpoint);
  }

  /**
   * @param string $var
   *
   * @return bool
   */
  private static function isDefined(string $var): bool
  {
    return strlen($var) > 0;
  }

  /**
   * Generate a Resource Group URI using the Drupal installation hostname.
   *
   * @return string
   */
  public static function getResourceGroupUri(): string
  {
    $host = \Drupal::request()->getSchemeAndHttpHost();
    return sprintf('%s/%s', $host, Constants::RESOURCE_GROUP_ENDPOINT_SUFFIX);
  }

  public static function isStandAlone(): bool
  {
    $config = \Drupal::config('coyote_img_desc.settings');
    return !!$config->get('coyote_standalone_mode');
  }
}

