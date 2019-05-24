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

      $file->move($this->repository->getCsvUploadDir(), $filename);
      $token = uniqid();
      $this->repository->saveToken($filename, $token);

      return $this->redirectToRoute('neontribe_cvs_import_batch', [
        'filename' => $filename,
        'dryrun' => $data["dryrun"] ? 1 : 0,
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
   * @Route(path="/batch/{filename}/{token}/{offset}/{chunk}/{dryrun}", name="neontribe_cvs_import_batch")
   *
   * @param Request $request
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function batchAction($filename, $token, $offset, $chunk, $dryrun) {
    $privToken = $this->repository->getToken($filename);

    if (! $this->repository->checkPublicToken($token, $privToken)) {
      $this->flashError('The token for the action is not recognised.');
      return $this->redirectToRoute('neontribe_cvs_import_admin');
    }

    // Are we done yet?
    $file = new \SplFileObject($this->repository->getCsvUploadDir() . $filename, 'r');
    $file->seek(PHP_INT_MAX);
    $linecount = $file->key() + 1;
    if ($offset > $linecount) {
      $this->flashSuccess('Timesheets imported.');
      return $this->redirectToRoute('neontribe_cvs_import_admin');
    }

    // Now read chunk timesheets
    $counter = 0;
    $file->rewind();
    $file->seek($offset); // go to line 200
    for ($i = 0; $i < $chunk and $file->valid(); $i ++, $file->next()) {
      try {
        $this->importService->importLine(str_getcsv($file->current()), $dryrun);
      } catch (\Throwable $error) {
        $lineNumber = $offset + $i;
        $this->flashError("Could not parse line " . $lineNumber . ": " . $file->current() . ' - ' . $error->getMessage());
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
      'progress' => round($progressPercent, 2)
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
