<?php

namespace Drupal\coyote_img_desc\Hook;

use Coyote\ContentHelper\Image;
use Drupal\Core\Render\Markup;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\coyote_img_desc\ContentParser;
use Drupal\coyote_img_desc\Util;

class NodeViewAlterPostRenderHook implements TrustedCallbackInterface {

  /**
   * @inheritDoc
   */
  public static function trustedCallbacks(): array {
    return ['hook'];
  }

  public static function hook(Markup $markup, array &$output): string
  {
    $config = \Drupal::config('coyote_img_desc.settings');
    $hostUri = $output['_coyote_node_url'];
    $disableParsing = $config->get('disable_coyote_filtering');
    if ($disableParsing) return $markup;

    return ContentParser::replaceImageDescriptions($markup, function(Image $image) use ($hostUri): ?string {
      $resource = Util::getImageResource($image, $hostUri);
      return is_null($resource) ? null : $resource->getCoyoteDescription();
    });
  }
}
