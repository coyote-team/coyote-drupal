<?php

namespace Drupal\coyote_img_desc;

require_once( __DIR__ . '/../vendor/autoload.php');

use \Coyote\ContentHelper;
use Exception;

class ContentParser {

  /**
   * Providing a chunk of HTML ($content), replace its image descriptions by known Coyote descriptions using supplied function $descriptionLookupFn.
   *
   * $descriptionLookupFn = (Image $image) => ?string
   *
   * @param string $content
   * @param callable $descriptionLookupFn
   *
   * @return string
   */
  public static function replaceImageDescriptions(string $content, callable $descriptionLookupFn): string
  {
    try {
      $contentHelper = new ContentHelper($content);
    } catch (Exception $e) {
      \Drupal::logger(Constants::MODULE_NAME)->error('Unable to construct ContentHelper: @error', ['@error' => $e->getMessage()]);
      return $content;
    }

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