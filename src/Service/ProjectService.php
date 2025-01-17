<?php

namespace App\Service;

use App\Entity\Project;
use App\Entity\Resource;
use App\Repository\ProjectRepository;
use App\Repository\ResourceRepository;
use Doctrine\ORM\EntityManagerInterface;
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
        private KernelInterface $kernel,
        private ProjectRepository $projectRepository,
        private ResourceRepository $resourceRepository,
        private EntityManagerInterface $entityManager
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

    public function getProjectFs(string $userPath, bool $isRootDir = false): array
    {
        $dir = $userPath;

        if (!$isRootDir) {
            $dir = $this->projectDir .'/'.$userPath;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        $result = [];
        foreach ($iterator as $fileinfo) {

            $hash = sha1( $fileinfo->getPathname().$fileinfo->getFilename().$iterator->getDepth());
            $result[$hash] = [
                'pathname' => $fileinfo->getPathname(),
                'depath' =>$iterator->getDepth(),
                'name' => $fileinfo->getFilename(),
                'type' => $fileinfo->isDir() ? 'dir' : 'file',
            ];
        }
        return $result;
    }

    private function getProjectDirName(string $name, string $userPath): string
    {
        $slugger = new AsciiSlugger();
        return  $this->projectDir . '/' .$userPath . '/'.$slugger->slug($name);
    }

    public function getProjectResourceByUuid(string $projectUuid, ?string $resourceUuid = null): array
    {
        $project = $this->projectRepository->getOneByUuidQueryBuilder($projectUuid)->getQuery()->getOneOrNullResult();
        $resource = null;

        if ($resourceUuid) {
            $resource = $this->resourceRepository->getOneByUuidQueryBuilder($resourceUuid)->getQuery()->getOneOrNullResult();
        }

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

    public function collectTemplates(string $projectUuid): array
    {
        $projectQueryBuilder = $this->resourceRepository->getAllFiles(
            queryBuilder:  $this->projectRepository->getOneByUuidQueryBuilder($projectUuid)
        );

        $result = [];

        foreach ($projectQueryBuilder->getQuery()->getResult() as $item) {
            foreach ($item->getResources() as $resource) {
                if (
                    preg_match_all('/__TCC__([A-Z_]+)+__/', $resource->getContent(), $matches) &&
                    !empty($matches[0])
                ) {
                    foreach ($matches[0] as $item) {
                        if (!isset($result[$item])) {
                            $result[$item] = $resource->getId() ;
                        }
                    }

                }
            }
        }
        return $result;
    }

    public function initForm(FormBuilder $formBuilder, array $templates): Form
    {

        foreach ($templates as $template => $id) {
            if (preg_match('/__([A-Z]+)__(\w+)__/', $template, $matches) && !empty($matches[2])) {
                $label = s($matches[2])
                    ->replaceMatches('/\W+|_+|\-+/', ' ')
                    ->replaceMatches('/\s{2, }/', ' ')
                    ->lower()
                    ->toString()
                ;
                $formBuilder->add($template, TextType::class, [
                    'label' => ucfirst($label)
                ]);
            }
        }

        return $formBuilder->getForm();
    }


}
