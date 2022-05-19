<?php

namespace Drupal\coyote_img_desc;

require_once( __DIR__ . '/../vendor/autoload.php');

use \Coyote\ContentHelper;

class ContentParser {

  public static function replaceImageDescriptions(string $content, callable $descriptionLookupFn): string
  {
    $contentHelper = new ContentHelper($content);

    $images = $contentHelper->getImages();

    $map = [];

    foreach($images as $image) {
      $description = $descriptionLookupFn($image);

      if (is_null($description)) {
        continue;
      }

      $map[$image->getSrc()] = $description;
    }

    return $contentHelper->setImageAlts($map);
  }

}