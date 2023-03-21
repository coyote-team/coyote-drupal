<?php

namespace Drupal\coyote_img_desc\Hook;

use Coyote\Model\WebhookUpdateModel;
use Drupal\coyote_img_desc\DB;
use Drupal\coyote_img_desc\Constants;

class RestApiUpdatePostHook {
  public static function hook(?WebhookUpdateModel $update): bool {
    if (is_null($update)) {
      return false;
    }

    $representation = $update->getTopRepresentationByMetum(Constants::METUM);

    if (is_null($representation)) {
      \Drupal::logger(Constants::MODULE_NAME)->warning('Update @update contained no relevant data.', ['@update' => $update]);
      return false;
    }

    return DB::updateResourceCoyoteDescription($update->getId(), $representation->getText());
  }
}
