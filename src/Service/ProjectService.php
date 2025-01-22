<?php

namespace App\Service;

use App\Entity\Project;
use App\Entity\Resource;
use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Repository\ResourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;
use App\Entity\Enum\ResourceType as EnumResourceType;
use function Symfony\Component\String\s;

class ProjectService
{
    private const PROJECT_PATH = 'projects';

    private string $projectDir = '';

    public function __construct(
        private KernelInterface        $kernel,
        private ProjectRepository      $projectRepository,
        private ResourceRepository     $resourceRepository,
        private EntityManagerInterface $entityManager,
        private ResourceService        $resourceService,
        private TemplateService         $templateService,
        private BuildService $buildService
    ) {
        $this->projectDir = $this->kernel->getProjectDir() . '/' . self::PROJECT_PATH;
    }

    public function save(Project $project, string $userPath): void
    {
        $dirName = $this->getProjectDirName($project->getName(), $userPath);
        if (!file_exists($dirName)) {
            mkdir($dirName, 0777, true);
        }
    }

    public function loadToDbFromFs(string $path, User $user, Project $project, ?Resource $parentResource = null): void
    {
        if (file_exists($path)) {
            $dirs = scandir($path);
            foreach ($dirs as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }
                $path_ = $path . DIRECTORY_SEPARATOR . $name;
                $type = 1;
                $content = null;
                if (!file_exists($path_)) {
                    return;

                }

                if (is_file($path_)) {
                    $type = 2;
                    $content = file_get_contents($path_);
                }


                $resource = $this
                    ->resourceRepository
                    ->getResourceByProjectUuidAndNameQueryBuilder($user, $project->getUuid(), $name)
                    ->andWhere(ResourceRepository::ALIAS .'.type=:type')
                    ->setParameter('type', $type)
                    ->getQuery()
                    ->getOneOrNullResult();


                if (!$resource) {
                    $resource = new Resource();
                    $resource
                        ->setOwner($user)
                        ->setProject($project)
                        ->setParent($parentResource)
                        ->setType(EnumResourceType::setValue($type))
                        ->setName($name)
                        ->setContent($content)
                    ;


                    $this->entityManager->persist($resource);
                    $this->entityManager->flush();
                }

                if ($type == 1) {
                    $this->loadToDbFromFs($path_, $user, $project, $resource);
                }
            }
        }
    }

    private function getProjectDirName(string $name, string $userPath): string
    {
        $slugger = new AsciiSlugger();
        return $this->projectDir . '/' . $userPath . '/' . $slugger->slug($name);
    }

    public function getProjectResourceByUuid(
        User $user,
        string $projectUuid,
        ?string $resourceUuid = null
    ): array
    {
        $resource = null;

        if ($resourceUuid) {
            $resource = $this
                ->resourceRepository
                ->getOneByUuidQueryBuilder($user, $resourceUuid)
                ->getQuery()
                ->getOneOrNullResult()
            ;
        }


        $project = $this
            ->projectRepository
            ->getOneByUuidQueryBuilder($user, $projectUuid, $resource)
            ->getQuery()
            ->getOneOrNullResult()
        ;

      //  dd($resourceUuid);



        return [
            'project' => $project,
            'resource' => $resource
        ];
    }

    public function getNavItems(): ?array
    {
        return $this->projectRepository->findAll();
    }

    public function getResources(Project $project, ?Resource $resource = null): array
    {
        return $this->resourceRepository->getItemsQueryBuilder($project, $resource)->getQuery()->getResult();
    }

    public function collectTemplates(string $projectUuid, User $user): array
    {
        $projectQueryBuilder = $this->resourceRepository->getAllFiles(
            queryBuilder: $this->projectRepository->getOneByUuidQueryBuilder($user, $projectUuid)
        );

        $result = [];
        $keys = join('|', array_keys(BuildService::REPLACE_FUNCTIONS));

        foreach ($projectQueryBuilder->getQuery()->getResult() as $item) {
            foreach ($item->getResources() as $resource) {
                if (
                    preg_match_all("/__({$keys})__([A-Z_]+)+__/", $resource->getContent(), $matches) &&
                    !empty($matches[0])
                ) {
                    foreach ($matches[0] as $item) {
                        if (!isset($result[$item])) {
                            $result[] = $item;
                        }
                    }

                }
            }
        }

        return array_unique($result);
    }

    public function initForm(FormBuilder $formBuilder, array $templates): Form
    {
        $templates = $this->templateService->clearTemplates($templates);
        foreach ($templates as $template => $val) {
            $formBuilder->add($template);
        }
        return $formBuilder->getForm();
    }

    public function removeDirs(Project $project): void
    {
        $fileSystem = new Filesystem();

        $path1 = $this->buildService->getBuildPath() . DIRECTORY_SEPARATOR . $project->getUuid();
        $path2 =  $this->buildService->getBuildArchivePath() . DIRECTORY_SEPARATOR . $project->getUuid() .'.zip';

        if ($fileSystem->exists($path1)) {
            $fileSystem->remove($path1);
        }

        if ($fileSystem->exists($path2)) {
            $fileSystem->remove($path2);
        }
    }
}
