<?php

namespace Drupal\coyote_img_desc;

use Coyote\ContentHelper;

class ContentParser {

  public static function replaceImageDescriptions(string $content, callable $descriptionLookupFn): string
  {
    // extract images
    // invoke descriptionLookupFn with image source
    // set the alt text

    $contentHelper = new ContentHelper($content);

    /** @var \Coyote\ContentHelper\Image[] $images */
    $images = $contentHelper->getImages();

    $map = [];

    foreach($images as $image) {
      $description = $descriptionLookupFn($image->src) ?? $image->alt;
      $map[$image->src] = $description;
    }

    return $contentHelper->setImageAlts($map);
  }

}