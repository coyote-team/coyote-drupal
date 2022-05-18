<?php

namespace Drupal\coyote_img_desc;

class DB {
  public static function clearResourceTable(): int {
    $database = \Drupal::database();
    return $database->delete('image_resource')->execute();
  }

  public static function updateResourceDescription(string $id, string $description) {
    $database = \Drupal::database();
    $database->update('image_resource')
      ->fields(['coyote_description' => $description])
      ->condition('coyote_resource_id', $id);
  }

  public static function insertResource(ImageResource $resource) {
    $database = \Drupal::database();
    try {
      $database->insert('image_resource')
        ->fields([
          'source_uri_sha1' => $resource->getSourceUriSha1(),
          'source_uri' => $resource->getSourceUri(),
          'coyote_resource_id' => $resource->getCoyoteId(),
          'original_description' => $resource->getLocalDescription(),
          'coyote_description' => $resource->getCoyoteDescription()
        ])->execute();
    } catch (\Exception $error) {
      // TODO log this exception
    }
  }

  public static function getResourceByCoyoteId(string $id) {
    $database = \Drupal::database();
    $result = $database->select('image_resource', 'r')
      ->fields('r')
      ->condition('coyote_resource_id', $id)
      ->execute();

    $record = $result->fetchObject();

    if ($record === false) {
      return null;
    }

    return $record;
  }

  public static function updateResourceCoyoteDescription(string $id, string $alt): bool
  {
    $database = \Drupal::database();
    $rows = $database->update('image_resource')
      ->fields(['coyote_description' => $alt])
      ->condition('coyote_resource_id', $id)
      ->execute();

    return $rows === 1;
  }

  public static function getResourceByHash(string $hash) {
    $database = \Drupal::database();
    $result = $database->select('image_resource', 'r')
      ->fields('r')
      ->condition('source_uri_sha1', $hash)
      ->execute();

    $record = $result->fetchObject();

    if ($record === false) {
      return null;
    }

    return $record;
  }
}




