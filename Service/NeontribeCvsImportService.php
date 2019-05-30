<?php
namespace KimaiPlugin\NeontribeCvsImportBundle\Service;

use App\Configuration\FormConfiguration;
use App\Entity\Customer;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\NoResultException;
use KimaiPlugin\NeontribeCvsImportBundle\Repository\NeontribeCvsImportRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use App\Entity\Activity;
use App\Entity\Timesheet;
use Psr\Log\LoggerInterface;

class NeontribeCvsImportService {

  /**
   *
   * @var LoggerInterface
   */
  protected $logger;

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

  protected $dryRun = True;

  /**
   *
   * @param RegistryInterface $registry
   */
  public function __construct(LoggerInterface $logger, RegistryInterface $registry, NeontribeCvsImportRepository $repository, FormConfiguration $configuration) {
    $this->logger = $logger;
    $this->doctrine = $registry;
    $this->repository = $repository;
    $this->configuration = $configuration;
  }

  public function importLine(array $elements, $dryrun = True) {
    $customerName = $elements[1];
    $projectName = $elements[2];
    $activityName = $elements[3];
    $start = $elements[4];
    $duration = $elements[5];
    $description = $elements[6];
    $userName = $elements[7];
    $email = $elements[8];

    $customer = $this->getCompany($customerName);
    $project = $this->getProject($projectName, $customer);
    $activity = $this->getActivity($activityName, $project);
    $user = $this->getUser($userName, $email);

    $begin = new \DateTime($start);
    $end = new \DateTime($start);
    $end->add(new \DateInterval('PT' . $duration . 'S'));

    $timesheet = new Timesheet();
    $timesheet->setBegin($begin)
      ->setEnd($end)
      ->setDuration($duration)
      ->setDescription($description)
      ->setProject($project)
      ->setActivity($activity)
      ->setUser($user);

    if (! $dryrun) {
      $this->doctrine->getManager()->persist($timesheet);
      return True;
    }

    return False;
  }

  public function getCompany($customerName): Customer {
    $customer = $this->doctrine->getRepository(Customer::class)->findOneBy([
      'name' => $customerName
    ]);

    if ($customer) {
      return $customer;
    }

    $_customer = new Customer();
    $_customer->setName($customerName)
      ->setCountry($this->configuration->find('customer.country'))
      ->setCurrency($this->configuration->find('customer.currency'))
      ->setTimezone($this->configuration->find('customer.timezone'));

    $entityManager = $this->doctrine->getManager();
    $entityManager->persist($_customer);
    $entityManager->flush();

    return $_customer;
  }

  public function getProject($projectName, $customer): Project {
    $project = $this->doctrine->getRepository(Project::class)->findOneBy([
      'name' => $projectName
    ]);

    if ($project) {
      return $project;
    }

    $_project = new Project();
    $_project->setName($projectName)->setCustomer($customer);

    $entityManager = $this->doctrine->getManager();
    $entityManager->persist($_project);
    $entityManager->flush();

    return $_project;
  }

  public function getActivity($activityName, $project): Activity {
    $activity = $this->doctrine->getRepository(Activity::class)->findOneBy([
      'name' => $activityName,
      'project' => $project
    ]);

    if ($activity) {
      return $activity;
    }

    $_activity = new Activity();
    $_activity->setName($activityName)->setProject($project);

    $entityManager = $this->doctrine->getManager();
    $entityManager->persist($_activity);
    $entityManager->flush();

    return $_activity;
  }

  public function getUser($userName, $userEmail) {
    try {
      $user = $this->doctrine->getRepository(User::class)->findOneBy([
        'username' => $userName
      ]);

      if ($user) {
        return $user;
      }
    } catch (NoResultException $nre) {
      // Fail silently. The above usersearch if the user doesn't exist.
      // TODO: Fix this.
    }

    $_user = new User();
    $_user->setUsername($userName)
      ->setEmail($userEmail)
      ->setPassword(md5(uniqid()))
      ->setEnabled(true)
      ->setRoles([
      User::DEFAULT_ROLE
    ]);

    $entityManager = $this->doctrine->getManager();
    $entityManager->persist($_user);
    $entityManager->flush();

    return $_user;
  }
}
