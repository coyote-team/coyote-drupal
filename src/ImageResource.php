<?php

namespace Drupal\coyote_img_desc;

/**
 * Model class reflecting the Module DB record structure.
 */
class ImageResource {
  private string $sourceUriSha1;
  private string $sourceUri;

  /**
   * @return string
   */
  public function getSourceUriSha1(): string {
    return $this->sourceUriSha1;
  }

  /**
   * @return string
   */
  public function getSourceUri(): string {
    return $this->sourceUri;
  }

  /**
   * @return int
   */
  public function getCoyoteId(): int {
    return $this->coyoteId;
  }

  /**
   * @return string|null
   */
  public function getLocalDescription(): ?string {
    return $this->localDescription;
  }

  /**
   * @return string|null
   */
  public function getCoyoteDescription(): ?string {
    return $this->coyoteDescription;
  }

  private int $coyoteId;
  private ?string $localDescription;
  private ?string $coyoteDescription;

  public function __construct(
    string $sourceUriSha1,
    string $sourceUri,
    int $coyoteId,
    string $localDescription = null,
    string $coyoteDescription = null
  ) {
    $this->sourceUriSha1 = $sourceUriSha1;
    $this->sourceUri = $sourceUri;
    $this->coyoteId = $coyoteId;
    $this->localDescription = $localDescription;
    $this->coyoteDescription = $coyoteDescription;
  }
}