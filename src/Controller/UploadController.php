<?php

namespace App\Controller;

use App\Entity\Resource;
use App\Form\FileUploadType;
use App\Service\ProjectService;
use App\Service\ResourceService;
use App\Service\UploadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class UploadController extends AbstractController
{
    #[Route('/upload/{projectUuid}/{resourceUuid}', name: 'app_upload', defaults: ['resourceUuid'=>null])]
    public function index(
        Request $request,
        UploadService $service,
        ProjectService $projectService,
        ResourceService $resourceService,
        string $projectUuid,
        ?string $resourceUuid = null
    ): Response {
        $form = $this->createForm(FileUploadType::class);
        $form->handleRequest($request);
        $obj = $projectService->getProjectResourceByUUid($this->getUser(), $projectUuid, $resourceUuid);

        if (empty($obj['project'])) {
            throw $this->createNotFoundException();
        }

        $path = $service->getUploadDir() . DIRECTORY_SEPARATOR . $obj['project']->getSlug();
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $service->upload($form, $obj);

                $projectService->loadToDbFromFs(
                    $path,
                    $this->getUser(),
                    $obj['project']
                );


                $resourceService->updatePath($obj['project']);
                $fileSystem = new Filesystem();
                $fileSystem->remove($path);

                $this->addFlash('success', 'Файл успешно загружен: ' . $service->getUploadedFile()?->getClientOriginalName());


            } catch (\Throwable $e) {
                $this->addFlash('danger', $e->getMessage());
                return $this->redirectToRoute('app_upload', ['projectUuid' => $projectUuid, 'resourceUuid' => $resourceUuid]);
            }

            return $this->redirectToRoute('app_project_item', ['projectUuid' => $projectUuid, 'resourceUuid' => $resourceUuid]);
        }

        return $this->render('upload/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
