<?php
namespace KimaiPlugin\NeontribeCvsImportBundle\Entity;

class NeontribeCvsImport {

  private $hashes = [];

  /**
   *
   * @return string
   */
  public function getHashes(): array {
    return $this->hashes;
  }

  /**
   *
   * @param string|null $customCss
   * @return NeontribeCvsImport
   */
  public function setHashes(string $hashes = null) {
    if (null === $hashes) {
      $hashes = '';
    }

    $this->hashes = $hashes;
    return $this;
  }
}
