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
        $project->setOwner($this->getUser());
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

    #[Route(
        '/show/{projectUuid}/{resourceUuid}',
        name: 'app_project_show',
        defaults: ['resourceUuid' => null],
        methods: ['GET', 'POST'])
    ]
    public function show(Request $request, string $projectUuid): Response
    {
        $templates = $this->projectService->collectTemplates($projectUuid, $this->getUser());
        $form = $this->projectService->initForm($this->createFormBuilder(), $templates);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $result = $this->buildService->build($this->getUser(), $projectUuid, $templates, $data);
            $this->addFlash('success', 'Project successfully built.');
            return $this->redirectToRoute(
                'app_project_download', [
                'projectUuid' => $projectUuid
            ],
                Response::HTTP_SEE_OTHER
            );
        }

        return $this->render('project/show.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route(
        '/download/{projectUuid}/{get}',
        name: 'app_project_download',
        defaults: ['get' => null],
        methods: ['GET'])
    ]
    public function download(string $projectUuid, ?string $get = null): Response
    {
        $obj = $this->projectService->getProjectResourceByUuid($this->getUser(), $projectUuid);
        $project = $obj['project'] ?? null;

        $path = $this->buildService->getBuildArchivePath() . DIRECTORY_SEPARATOR . $projectUuid . '.zip';

        if ($get !== null) {
            return new StreamedResponse(function () use ($path) {
                $outputStream = fopen('php://output', 'wb');
                $fileStream = fopen($path, 'rb');
                stream_copy_to_stream($fileStream, $outputStream);
                fclose($fileStream);
                fclose($outputStream);

            }, 200, [
                'Content-Type' => 'application/zip',
                'Content-Disposition' => 'attachment; filename="archive.zip"',
                'Content-Length' => filesize($path),
            ]);
        }

        return $this->render('project/download.html.twig', [
            'project' => $project,

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

        $obj = $this->projectService->getProjectResourceByUuid($this->getUser(), $projectUuid, $resourceUuid);
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
            'resources' => $project->getResources(),
        ]);
    }

    #[Route('/delete/{projectUuid}', name: 'app_project_delete', methods: ['GET'])]
    public function delete(string $projectUuid): Response
    {
        $obj = $this->projectService->getProjectResourceByUuid($this->getUser(), $projectUuid);
        $project = $obj['project'] ?? null;

        if (!$project) {
            throw $this->createNotFoundException();
        }

        $this->projectService->removeDirs($project);
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
