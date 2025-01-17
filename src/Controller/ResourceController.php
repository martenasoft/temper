<?php

namespace App\Controller;

use App\Entity\Enum\ResourceType as ResourceTypeEnum;
use App\Entity\Project;
use App\Entity\Resource;
use App\Form\ResourceType;
use App\Service\ProjectService;
use App\Service\ResourceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[Route('/resource')]
final class ResourceController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProjectService         $projectService,
        private ResourceService        $resourceService

    )
    {

    }

    #[Route(
        '/new/{projectUuid}/{type}/{resourceUuid}',
        name: 'app_resource_new',
        defaults: ['type' => 'dir', 'resourceUuid' => null],
        methods: ['GET', 'POST'])
    ]
    public function new(
        string  $projectUuid,
        Request $request,
        ?string $type = null,
        ?string $resourceUuid = null,

    ): Response {

        $obj = $this->projectService->getProjectResourceByUUid($projectUuid, $resourceUuid);
        $project = $obj['project'] ?? null;
        $resource = $obj['resource'] ?? new Resource();

        if (!$project) {
            throw $this->createNotFoundException();
        }

        return $this->save(
            project: $project,
            request: $request,
            resource: $resource,
            type: $type
        );
    }

    #[Route('/show/{projectUuid}/{resourceUuid}', name: 'app_resource_show', methods: ['GET'])]
    public function show(Resource $resource): Response
    {
        return $this->render('resource/show.html.twig', [
            'resource' => $resource,
        ]);
    }

    #[Route(
        '/edit/{projectUuid}/{resourceUuid}',
        name: 'app_resource_edit',
        defaults: ['parent' => null],
        methods: ['GET', 'POST'])
    ]
    public function edit(string $projectUuid, string $resourceUuid, Request $request): Response
    {
        $obj = $this->projectService->getProjectResourceByUUid($projectUuid, $resourceUuid);
        $project = $obj['project'] ?? null;
        $resource = $obj['resource'] ?? null;

        if (!$project || !$resource) {
            throw $this->createNotFoundException();
        }

        return $this->save(
            project: $project,
            request: $request,
            resource: $resource,
            type: $resource->getType()->value,
            parent: $resource->getParent()
        );
    }

    #[Route(
        '/move/{projectUuid}/{resourceUuid}',
        name: 'app_resource_move',
        defaults: ['parent' => null],
        methods: ['GET', 'POST'])
    ]
    public function move(string $projectUuid, string $resourceUuid, Request $request): Response
    {
        $obj = $this->projectService->getProjectResourceByUUid($projectUuid, $resourceUuid);
        $project = $obj['project'] ?? null;
        $resource = $obj['resource'] ?? null;

        if (!$project || !$resource) {
            throw $this->createNotFoundException();
        }
        $tree = $this->resourceService->getTree($project);

        $formBuilder = $this->createFormBuilder();
        $choices = array_combine(array_column($tree, 'name'), array_column($tree, 'uuid'));

        $formBuilder->add('Dir', ChoiceType::class, [
            'choices' => $choices,
            'expanded' => true, // Отображает радио-кнопки вместо выпадающего списка
            'multiple' => false, // Гарантирует выбор только одного варианта
            'label' => 'Select an catalog',
        ]);


        $form = $formBuilder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $this->resourceService->move($project, $resourceUuid, $data['Dir']);
            $params = ['projectUuid' => $project->getUuid()];
            if ($data['Dir']) {
                $params['resourceUuid'] = $data['Dir'];
            }

            $this->resourceService->updatePath($project);

            return $this->redirectToRoute('app_project_edit', $params, Response::HTTP_SEE_OTHER);
        }

        return $this->render('resource/move.html.twig', [
            'project' => $project,
            'resource' => $resource,
            'tree' => $tree,
            'form' => $form
        ]);
    }

    #[Route('/delete/{resourceUuid}', name: 'app_resource_delete', methods: ['GET'])]
    public function delete(string $projectUuid, string $resourceUuid): Response
    {
        $obj = $this->projectService->getProjectResourceByUuid($projectUuid, $resourceUuid);

        $project = $obj['project'] ?? null;
        $resource = $obj['resource'] ?? null;

        if (!$project || !$resource) {
            throw $this->createNotFoundException();
        }

        $this->entityManager->remove($resource);
        $this->entityManager->flush();
        $this->addFlash(
            'success',
            $this->addFlash('success', self::getResourceTypeAndName($resource) . ' has been deleted.')
        );

        return $this->redirectToRoute(
            'app_project_edit', [
            'projectUuid' => $projectUuid
        ],
            Response::HTTP_SEE_OTHER
        );
    }

    private function save(
        Project   $project,
        Request   $request,
        ?Resource $resource = null,
        ?string   $type = null,
        ?Resource $parent = null
    ): Response
    {

        $resource?->setType(ResourceTypeEnum::setValue($type));
        $resource?->setDeep($parent?->getDeep() + 1 ?? 0);
        $resource?->setParent($parent);
        $resource?->setProject($project);


        $form = $this->createForm(ResourceType::class, $resource);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $slugger = new AsciiSlugger();
            $resource->setSlug($slugger->slug($resource->getName())->lower()->toString());
            $path = '';

            if ($resource->getType()->value === ResourceTypeEnum::Dir->value && $parent) {
                $path = $parent->getPath() . '/' . $resource->getSlug();
            }

            $resource->setPath($path);
            $this->entityManager->persist($resource);
            $this->entityManager->flush();
            $this->addFlash('success', self::getResourceTypeAndName($resource) . ' has been saved.');
            $params = ['projectUuid' => $project->getUuid()];
            if ($parent) {
                $params['resourceUuid'] = $parent->getUuid();
            }

            $this->resourceService->updatePath($project);

            return $this->redirectToRoute('app_project_edit', $params, Response::HTTP_SEE_OTHER);
        }

        return $this->render('resource/new.html.twig', [
            'resource' => $resource,
            'form' => $form,
            'project' => $project
        ]);
    }

    public static function getResourceTypeAndName(Resource $resource): string
    {
        return $resource->getType()->name . " \" {$resource->getName()} \"";
    }
}
