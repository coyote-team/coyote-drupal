<?php

namespace Drupal\coyote_img_desc\Hook;

use Coyote\ContentHelper\Image;
use Drupal\Core\Render\Markup;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\coyote_img_desc\ContentParser;
use Drupal\coyote_img_desc\Util;

class ViewsViewAlterPostRenderHook implements TrustedCallbackInterface {

  /**
   * @inheritDoc
   */
  public static function trustedCallbacks(): array
  {
    return ['hook'];
  }

  /**
   * @param \Drupal\Core\Render\Markup $markup
   * @param array $output
   *
   * @return string
   */
  public static function hook(Markup $markup, array $output): string
  {
    $config = \Drupal::config('coyote_img_desc.settings');
    $hostUri = $output['#coyote_node_url'];
    unset ($output['#coyote_node_url']);

    if ($config->get('disable_coyote_filtering')) {
      return $markup;
    }
   
    return ContentParser::replaceImageDescriptions($markup, function(Image $image) use ($hostUri): ?string
    {
      $resource = Util::getImageResource($image, $hostUri);
      return is_null($resource) ? null : $resource->getCoyoteDescription();
    });
  }
}
