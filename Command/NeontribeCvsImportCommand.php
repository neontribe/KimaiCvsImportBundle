<?php
namespace KimaiPlugin\NeontribeCvsImportBundle\Command;

use App\Configuration\FormConfiguration;
use App\Entity\Customer;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\NoResultException;
use KimaiPlugin\NeontribeCvsImportBundle\Repository\NeontribeCvsImportRepository;
use KimaiPlugin\NeontribeCvsImportBundle\Service\NeontribeCvsImportService;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use App\Entity\Activity;
use App\Entity\Timesheet;

class NeontribeCvsImportCommand extends Command {

  protected static $defaultName = 'neontribe:csv:import';

  /**
   *
   * @var Registry
   */
  protected $doctrine;

  /**
   *
   * @var NeontribeCvsImportRepository
   */
  protected $repository;

  /**
   *
   * @var FormConfiguration
   */
  protected $configuration;

  /**
   *
   * @var NeontribeCvsImportService
   */
  protected $importService;

  /**
   *
   * @param RegistryInterface $registry
   */
  public function __construct(RegistryInterface $registry, NeontribeCvsImportRepository $repository, FormConfiguration $configuration, NeontribeCvsImportService $importService) {
    $this->doctrine = $registry;
    $this->repository = $repository;
    $this->configuration = $configuration;
    $this->importService = $importService;

    parent::__construct(self::$defaultName);
  }

  /**
   *
   * {@inheritdoc}
   */
  protected function configure() {
    $this->setName('kimai:csv:import')
      ->setDescription('Import from CSV')
      ->setHelp('Read timesheets from a CSV file and import records.  Creates Customers, Projects and Activities as needed.')
      ->addArgument('file', InputArgument::REQUIRED, 'Relative path to the CSV file.')
      ->addOption('offset', null, InputArgument::OPTIONAL, 'Number of rows to skip before starting the import', 0)
      ->addOption('count', null, InputArgument::OPTIONAL, 'Number of rows to import', 99999)
      ->addOption('ignore-hashes', null, InputArgument::OPTIONAL, 'Ignore hash clashes and always create a new timesheet', 1);
  }

  /**
   *
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $io = new SymfonyStyle($input, $output);

    $filename = $input->getArgument('file');
    $offset = $input->getOption('offset');
    $count = $input->getOption('count');
    $ignoreHashes = $input->getOption('count');

    $file = new \SplFileObject($filename);
    if ($offset > 0) {
      $file->seek($offset);
    }

    $hashData = $this->repository->getHashData();
    $hashes = array_keys($hashData);

    for ($i = 0; $i < $count and $file->valid(); $i ++, $file->next()) {
      $line = str_getcsv($file->current());
      if (count($line) != 9) {
        $io->error("Wrong count (" . count($line) . ") in line " . ($offset + $i) . ": " . $file->current());
        continue;
      }
      $hash = md5($file->current());
      if (! $ignoreHashes && in_array($hash, $hashes)) {
        $io->comment("Duplicate timesheet, skipping line " . ($offset + $i) . ", ID " . $line[0]);
        continue;
      }

      $this->importService->importLine($line);
      $hashData[$hash] = $line[0];
    }

    $this->doctrine->getManager()->flush();
    $this->repository->saveHashData($hashData);
  }
}
