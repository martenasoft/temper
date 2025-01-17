<?php

namespace App\Controller;

use App\Entity\Dir;
use App\Entity\Project;
use App\Form\ProjectType;
use App\Helper\StringHelper;
use App\Repository\ResourceRepository;
use App\Service\BuildService;
use App\Service\ProjectService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/project')]
final class ProjectController extends AbstractController
{

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProjectService $projectService,
        private BuildService $buildService
    ) {

    }

    #[Route('/', name: 'app_project_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->render('project/index.html.twig');
    }

    #[Route('/new', name: 'app_project_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $project = new Project();
        $form = $this->createForm(ProjectType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $project->setSlug(StringHelper::slug($project->getName()));
            $this->entityManager->persist($project);
            $this->entityManager->flush();
            $this->addFlash('success', 'Project created.');
            return $this->redirectToRoute(
                'app_project_edit', [
                    'projectUuid' => $project->getUuid()
                ],
                Response::HTTP_SEE_OTHER
            );
        }

        return $this->render('project/new.html.twig', [
            'project' => $project,
            'form' => $form,
        ]);
    }

    #[Route('/show/{projectUuid}/{resourceUuid}', defaults: ['resourceUuid' => null], name: 'app_project_show', methods: ['GET', 'POST'])]
    public function show(Request $request, string $projectUuid): Response
    {
        $templates = $this->projectService->collectTemplates($projectUuid);
        $form = $this->projectService->initForm($this->createFormBuilder(), $templates);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $result = $this->buildService->build($projectUUid, $templates, $data);
            if (!empty($result[0]) && file_exists($result[0])) {
                return new StreamedResponse(function () use ($result) {
                    $outputStream = fopen('php://output', 'wb');
                    $fileStream = fopen($result[0], 'rb');
                    stream_copy_to_stream($fileStream, $outputStream);
                    fclose($fileStream);
                    fclose($outputStream);

                    // Удаляем временный архив после передачи
                    unlink($result[0]);
                }, 200, [
                    'Content-Type' => 'application/zip',
                    'Content-Disposition' => 'attachment; filename="archive.zip"',
                    'Content-Length' => filesize($result[0]),
                ]);
            }

        }

        return $this->render('project/show.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route(
        '/edit/{projectUuid}/{resourceUuid}',
        name: 'app_project_edit',
        defaults: ['resourceUuid' => null],
        methods: ['GET', 'POST'])
    ]
    public function edit(
        Request $request,
        string $projectUuid,
        ?string $resourceUuid = null,
    ): Response {

        $obj = $this->projectService->getProjectResourceByUuid($projectUuid, $resourceUuid);
        $project = $obj['project'] ?? null;
        $resource = $obj['resource'] ?? null;


        if (!$project) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(ProjectType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $project->setSlug(StringHelper::slug($project->getName()));
            $this->entityManager->flush();
            $this->addFlash('success', 'Project updated.');
            return $this->redirectToRoute(
                'app_project_edit', [
                'projectUuid' => $project->getUuid()
            ],
                Response::HTTP_SEE_OTHER
            );
        }

        return $this->render('project/edit.html.twig', [
            'project' => $project,
            'form' => $form,
            'resourceItem' => $resource,
            'resources' => $this->projectService->getResources($project, $resource)
        ]);
    }

    #[Route('/delete/{projectUuid}', name: 'app_project_delete', methods: ['GET'])]
    public function delete(string $projectUuid): Response
    {
        $obj = $this->projectService->getProjectResourceByUuid($projectUuid);
        $project = $obj['project'] ?? null;

        if (!$project) {
            throw $this->createNotFoundException();
        }
        $this->entityManager->remove($project);
        $this->entityManager->flush();
        $this->addFlash('success', 'Project has been deleted.');
        return $this->redirectToRoute('app_project_index', [], Response::HTTP_SEE_OTHER);

    }

    public function navItems()
    {
        $items = $this->projectService->getNavItems();
        return $this->render('project/_nav_items.html.twig', [
            'items' => $items
        ]);
    }
}
