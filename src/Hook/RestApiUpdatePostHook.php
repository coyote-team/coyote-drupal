<?php

namespace Drupal\coyote_img_desc\Hook;

use Coyote\Model\ResourceModel;
use Drupal\coyote_img_desc\DB;
use Drupal\coyote_img_desc\Constants;

class RestApiUpdatePostHook {
  public static function hook(?ResourceModel $update): bool {
    if (is_null($update)) {
      return false;
    }

    $representation = $update->getTopRepresentationByMetum(Constants::METUM);

    if (is_null($representation)) {
      // TODO log that this contained no relevant update
      return false;
    }

    return DB::updateResourceCoyoteDescription($update->getId(), $representation->getText());
  }
}