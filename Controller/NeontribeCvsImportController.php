<?php
namespace KimaiPlugin\NeontribeCvsImportBundle\Controller;

use App\Controller\AbstractController;
use KimaiPlugin\NeontribeCvsImportBundle\Form\CsvUploadForm;
use KimaiPlugin\NeontribeCvsImportBundle\Repository\NeontribeCvsImportRepository;
use KimaiPlugin\NeontribeCvsImportBundle\Service\NeontribeCvsImportService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Routing\Generator\UrlGenerator;

/**
 *
 * @Route(path="/admin/neontribe_cvs_import")
 * @Security("is_granted('ROLE_SUPER_ADMIN') or is_granted('edit_custom_css')")
 */
class NeontribeCvsImportController extends AbstractController {

  /**
   *
   * @var NeontribeCvsImportRepository
   */
  protected $repository;

  /**
   *
   * @var NeontribeCvsImportService
   */
  protected $importService;

  /**
   *
   * @param NeontribeCvsImportRepository $repository
   */
  public function __construct(NeontribeCvsImportRepository $repository, NeontribeCvsImportService $importService) {
    $this->repository = $repository;
    $this->importService = $importService;
  }

  /**
   *
   * @Route(path="", name="neontribe_cvs_import_admin")
   *
   * @param Request $request
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function indexAction(Request $request) {
    $form = $this->createForm(CsvUploadForm::class);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
      $data = $form->getData();
      /** @var Symfony\Component\HttpFoundation\File\UploadedFile $file */
      $file = $data['csvfile'];
      $filename = $this->generateUniqueFileName() . '.csv';

      $file->move($this->repository->getCsvDir(), $filename);
      $token = uniqid();
      $this->repository->saveToken($filename, $token);
      $this->repository->clearHistory();

      return $this->redirectToRoute('neontribe_cvs_import_batch', [
        'filename' => $filename,
        'dryrun' => $data["dryrun"] ? 1 : 0,
        'checkhashes' => $data["checkhashes"] ? 1 : 0,
        'checkids' => $data["checkids"] ? 1 : 0,
        'token' => $this->repository->makePublicToken($token),
        'offset' => 0,
        'chunk' => $data['chunk']
      ]);
    }

    return $this->render('@NeontribeCvsImport/index.html.twig', [
      'form' => $form->createView()
    ]);
  }

  /**
   *
   * @Route(path="/batch/{filename}/{token}/{offset}/{chunk}/{dryrun}/{checkhashes}/{checkids}", name="neontribe_cvs_import_batch")
   *
   * @param Request $request
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function batchAction($filename, $token, $offset, $chunk, $dryrun, $checkhashes, $checkids) {
    $privToken = $this->repository->getToken($filename);

    if (! $this->repository->checkPublicToken($token, $privToken)) {
      $this->flashError('The token for the action is not recognised.');
      return $this->redirectToRoute('neontribe_cvs_import_admin');
    }

    // Are we done yet?
    $file = new \SplFileObject($this->repository->getCsvDir() . $filename, 'r');
    $file->seek(PHP_INT_MAX);
    $linecount = $file->key() + 1;
    if ($offset > $linecount) {
      return $this->redirectToRoute('neontribe_cvs_import_finish');
    }

    // Now read chunk timesheets
    $counter = 0;
    $errorCount = 0;
    $file->rewind();
    $file->seek($offset); // go to line 200
    for ($i = 0; $i < $chunk and $file->valid(); $i ++, $file->next()) {
      $line = $file->current();
      try {
        // Check hash and/or id
        $data = str_getcsv($line);
        $id = $data[0];
        $hash = md5($line);
        $lineNumber = $offset + $i;
        if ($checkhashes && $this->repository->checkHash($hash)) {
          $this->flashWarning("Duplicate line line (" . $hash . ") " . $lineNumber . ": " . $line);
          $this->repository->appendHistory('Duplicate (hash): ' . $line);
          continue;
        }
        if ($checkids && $this->repository->checkId($id)) {
          $this->flashWarning("Duplicate line line (" . $id . ") " . $lineNumber . ": " . $line);
          $this->repository->appendHistory('Duplicate (id): ' . $line);
          continue;
        }

        if ($this->importService->importLine($data, $dryrun)) {
          $this->repository->saveHash($id, $hash);
        }
      } catch (\Throwable $error) {
        $this->flashError("Could not parse line " . $lineNumber . ": " . $line . ' - ' . $error->getMessage());
        $this->repository->appendHistory('Error: ' . $line);
        $errorCount ++;
      }
      $counter ++;
    }

    $progressPercent = (($offset + $counter) / $linecount * 100);

    // Create a new token
    $token = uniqid();
    $this->repository->saveToken($filename, $token);

    $nextPhase = $this->generateUrl('neontribe_cvs_import_batch', [
      'filename' => $filename,
      'dryrun' => $dryrun,
      'checkhashes' => $checkhashes,
      'checkids' => $checkids,
      'token' => $this->repository->makePublicToken($token),
      'offset' => $offset + $chunk,
      'chunk' => $chunk
    ]);

    return $this->render('@NeontribeCvsImport/batch.html.twig', [
      'nextPhase' => $nextPhase,
      'dryrun' => $dryrun,
      'offset' => $offset,
      'count' => $counter,
      'total' => $linecount,
      'progress' => round($progressPercent, 2),
      'errorCount' => $errorCount
    ]);
  }

  /**
   *
   * @Route(path="/batch/finished", name="neontribe_cvs_import_finish")
   *
   * @param Request $request
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function batchFinished() {
    $history = $this->repository->getHistory();

    $errors = [];
    $warnings = [];
    $other = [];

    foreach ($history as $line) {
      if (strpos($line, 'Duplicate') === 0) {
        $warnings[] = $line;
      } elseif (strpos($line, 'Error') === 0) {
        $errors[] = $line;
      } else {
        $other[] = $line;
      }
    }

    return $this->render('@NeontribeCvsImport/finished.html.twig', [
      'errors' => $errors,
      'warnings' => $warnings,
      'other' => $other
    ]);
  }

  /**
   *
   * @return string
   */
  private function generateUniqueFileName() {
    return md5(uniqid());
  }
}
