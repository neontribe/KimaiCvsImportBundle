<?php
namespace KimaiPlugin\NeontribeCvsImportBundle\Repository;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Filesystem\Filesystem;

class NeontribeCvsImportRepository {

  /**
   *
   * @var string
   */
  protected $pluginDirectory = null;

  /**
   *
   * @var string
   */
  protected $dataDirectory = null;

  /**
   *
   * @var string
   */
  protected $appSecret;

  /**
   *
   * @var string
   */
  protected $hashesFile;

  /**
   *
   * @param string $pluginDirectory
   */
  public function __construct(string $pluginDirectory, string $dataDirectory, string $appSecret) {
    $this->pluginDirectory = $pluginDirectory;
    $this->dataDirectory = $dataDirectory;
    $this->appSecret = $appSecret;

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

  public function getCsvUploadDir() {
    $filesystem = new Filesystem();
    $csvDir = $this->dataDirectory . '/csv-import-files/';
    if (! $filesystem->exists($csvDir)) {
      $filesystem->mkdir($csvDir, 0700);
    }
    return $csvDir;
  }

  public function saveToken($filename, $token) {
    \file_put_contents($this->generateTokenFileName($filename), $token);
  }

  public function getToken($filename) {
    return \file_get_contents($this->generateTokenFileName($filename));
  }

  protected function generateTokenFileName($filename) {
    $csvDir = $this->getCsvUploadDir();
    $tokenfile = $csvDir . \basename($filename, 'csv') . 'txt';
    return $tokenfile;
  }

  public function makePublicToken($token) {
    return md5($token . $this->appSecret);
  }

  public function checkPublicToken($pubtoken, $privToken) {
    return $pubtoken === $this->makePublicToken($privToken);
  }
}
