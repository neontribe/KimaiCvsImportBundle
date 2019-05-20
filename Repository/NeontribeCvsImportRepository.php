<?php
namespace KimaiPlugin\NeontribeCvsImportBundle\Repository;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class NeontribeCvsImportRepository {

  /**
   *
   * @var string
   */
  protected $hashesFile;

  /**
   *
   * @param string $pluginDirectory
   */
  public function __construct(string $pluginDirectory, string $dataDirectory) {
    $this->hashesFile = $dataDirectory . '/hashes.json';
  }

  /**
   *
   * @param array $entity
   * @return bool
   * @throws \Exception
   */
  public function saveHashData(array $hashes) {
    if (file_exists($this->hashesFile) && ! is_writable($this->hashesFile)) {
      throw new \Exception('Hashes file is not writable: ' . $this->hashesFile);
    }

    if (false === file_put_contents($this->hashesFile, json_encode($hashes, JSON_PRETTY_PRINT))) {
      throw new \Exception('Failed saving hashes rules to file: ' . $this->hashesFile);
    }

    return true;
  }

  /**
   *
   * @return array
   */
  public function getHashData(): array {
    if (file_exists($this->hashesFile)) {
      return json_decode(file_get_contents($this->hashesFile), True);
    } else {
      return [];
    }
  }
}
