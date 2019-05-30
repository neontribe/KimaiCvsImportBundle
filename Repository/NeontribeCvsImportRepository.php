<?php
namespace KimaiPlugin\NeontribeCvsImportBundle\Repository;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Filesystem\Filesystem;

class NeontribeCvsImportRepository {

  /**
   * Plugin directory supplied from the application.
   *
   * @var string
   */
  protected $pluginDirectory = null;

  /**
   * Data directory supplied from the application.
   *
   * @var string
   */
  protected $dataDirectory = null;

  /**
   * The app secret supplied from the application.
   *
   * @var string
   */
  protected $appSecret;

  /**
   * Hashes of each imported line and the UID of the line are stored in this file.
   *
   * @var string
   */
  protected $hashesFile;

  /**
   * This file lists history items to be displayed after the import id complete.
   *
   * @var string
   */
  protected $historyFile;

  /**
   * The folder where this plugin will store files.
   *
   * @var string
   */
  protected $myDataDir;

  /**
   * The folder where this plugin will store uploaded csv files.
   *
   * @var string
   */
  protected $csvDir;

  /**
   *
   * @param string $pluginDirectory
   * @param string $dataDirectory
   * @param string $appSecret
   */
  public function __construct(string $pluginDirectory, string $dataDirectory, string $appSecret) {
    $this->pluginDirectory = $pluginDirectory;
    $this->dataDirectory = $dataDirectory;
    $this->appSecret = $appSecret;

    $filesystem = new Filesystem();
    $myDataDir = $this->dataDirectory . '/neontribe-csv-import';
    if (! $filesystem->exists($myDataDir)) {
      $filesystem->mkdir($myDataDir, 0700);
    }
    $this->myDataDir = $myDataDir;

    $csvDir = $myDataDir . '/csv/';
    if (! $filesystem->exists($csvDir)) {
      $filesystem->mkdir($csvDir, 0700);
    }
    $this->csvDir = $csvDir;

    $this->hashesFile = $myDataDir . '/hashes.json';
    $this->historyFile = $myDataDir . '/history.txt';
  }

  /**
   * Get the persistent data folder for this plugin.
   *
   * @return string
   */
  public function getDataDir() {
    return $this->myDataDir;
  }

  /**
   * Get the csv folder.
   *
   * @return string
   */
  public function getCsvDir() {
    return $this->csvDir;
  }

  public function saveHash($id, $hash) {
    $data = $this->loadhashes();
    $data[$id] = $hash;
    $this->saveHashes($data);
  }

  public function checkId($id) {
    $data = $this->loadhashes();
    return in_array($id, array_keys($data));
  }

  public function checkHash($hash) {
    $data = $this->loadhashes();
    return in_array($hash, $data);
  }

  protected function loadhashes() {
    if (! file_exists($this->hashesFile)) {
      return [];
    }
    return json_decode(file_get_contents($this->hashesFile), True);
  }

  protected function saveHashes($data) {
    return file_put_contents($this->hashesFile, json_encode($data, JSON_PRETTY_PRINT));
  }

  public function saveToken($filename, $token) {
    \file_put_contents($this->generateTokenFileName($filename), $token);
  }

  public function getToken($filename) {
    return \file_get_contents($this->generateTokenFileName($filename));
  }

  protected function generateTokenFileName($filename) {
    $csvDir = $this->getDataDir();
    $tokenfile = $csvDir . \basename($filename, 'csv') . 'txt';
    return $tokenfile;
  }

  public function makePublicToken($token) {
    return md5($token . $this->appSecret);
  }

  public function checkPublicToken($pubtoken, $privToken) {
    return $pubtoken === $this->makePublicToken($privToken);
  }

  public function clearHistory() {
    file_put_contents($this->historyFile, '');
  }

  public function appendHistory($line) {
    $fh = fopen($this->historyFile, 'a');
    fwrite($fh, $line);
    fclose($fh);
  }

  public function getHistory() {
    return file($this->historyFile);
  }
}
