<?php

namespace Drupal\coyote_img_desc\Hook;

use Coyote\ContentHelper\Image;
use Drupal\Core\Render\Markup;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\coyote_img_desc\ContentParser;
use Drupal\coyote_img_desc\Util;

class EntityViewAlterPostRenderHook implements TrustedCallbackInterface {

  /**
   * @inheritDoc
   */
  public static function trustedCallbacks(): array
  {
    return ['hook'];
  }

  /**
   * @param string $markup
   * @param array $output
   *
   * @return string
   */
  public static function hook(string $markup, array $output): string
  {
    $config = \Drupal::config('coyote_img_desc.settings');
    $hostUri = $output['#coyote_node_url'];
    unset ($output['#coyote_node_url']);
    $disableParsing = $config->get('disable_coyote_filtering');

    if (!$hostUri || $disableParsing) {
      return $markup;
    }
   
    $isPublished = true;

    if (array_key_exists('#node', $output) && $output['#node']) {
    	$isPublished = $output['#node']->isPublished();
    }

    if (!$config->get('coyote_process_unpublished_nodes') && !$isPublished) {
      return $markup;
    }

    return ContentParser::replaceImageDescriptions($markup, function(Image $image) use ($hostUri): ?string
    {
      $resource = Util::getImageResource($image, $hostUri);
      return is_null($resource) ? null : $resource->getCoyoteDescription();
    });
  }
}
