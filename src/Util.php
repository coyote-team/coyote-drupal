<?php

namespace Drupal\coyote_img_desc;

use Drupal\coyote_img_desc\DB;
use Drupal\coyote_img_desc\ImageResource;

class Util {
  public static function getImageResourceBySourceUri(string $sourceUri): ?ImageResource
  {
    $sha1 = sha1($sourceUri);
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
}

