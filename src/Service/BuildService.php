<?php

namespace App\Service;

use App\Entity\Enum\ResourceType;
use App\Entity\Enum\ResourceType as ResourceTypeEnum;
use App\Entity\Project;
use App\Entity\Resource;
use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Repository\ResourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;
use Symfony\Component\HttpKernel\KernelInterface;
use function Symfony\Component\String\s;

class BuildService
{
    public const BUILD_PATH = 'var/build';
    public const BUILD_ARCHIVE_PATH = 'var/build_archive';

    public const REPLACE_FUNCTIONS = [
        'TCC' => 'templateCamelCase',
        'TCCF' => 'templateCamelCaseUCFirst',
        'TSC' => 'templateSnackCase',
    ];

    public const TITLES = [
        'TCC' => 'Camel case. Example: __TCC__SOME_NAME__ Result: someName',
        'TCCF' => 'Camel case with capital first. Example: __TCCF__SOME_NAME__ Result: SomeName',
        'TSC' => 'Snake case. Example: __TSC__SOME_NAME__ Result: some_mame',
    ];

    private string $buildPath = '';
    private string $buildArchivePath = '';

    private ?Project $project = null;

    public function __construct(
        private KernelInterface $kernel,
        private ProjectRepository $projectRepository,
        private ResourceRepository $resourceRepository,

        private TemplateService $templateService
    ) {
        $this->buildPath = $this->kernel->getProjectDir() . '/' . self::BUILD_PATH;
        $this->buildArchivePath  = $this->kernel->getProjectDir() . '/' . self::BUILD_ARCHIVE_PATH;
    }
    public function getBuildPath(): string
    {
        return $this->buildPath;
    }

    public function getBuildArchivePath()
    {
        return $this->buildArchivePath;
    }

    public function build(User $user, string $projectUuid, array $templates, array $data, ?Project $project = null): array
    {
        $project = $project ?? $this
            ->projectRepository
            ->getOneByUuidQueryBuilder($user, $projectUuid)
            ->getQuery()
            ->getOneOrNullResult();

        if (empty($project)) {
            throw new \RuntimeException('Project not found');
        }


        $buildPath = $this->buildPath . DIRECTORY_SEPARATOR . $projectUuid;

        if (!file_exists($buildPath)) {
            mkdir($buildPath, 0777, true);
        }

        $cleaned = $this->templateService->clearTemplates($templates);
        $dataS = [];
        foreach ($data as $key => $value) {
            if (isset($cleaned[$key])) {
                $cleaned[$key] = array_flip($cleaned[$key]);

                foreach ($cleaned[$key] as $k => $v) {
                    $dataS[$k] = $value;
                }
            }
        }

        $result = [];

        $resources = $project->getResources();
        $this->saveResources($project, $resources, $dataS);
        $result[] = $this->archive($project);
        return $result;
    }

    private function saveResources(Project $project, PersistentCollection $resources,array $data): void
    {

        /** @var Resource $resource */
        foreach ($resources as $resource) {

            $content = $resource->getContent();
            $name = $resource->getName();
            $path = $resource->getPath();

            foreach ($data as $key => $value) {
                if (
                    preg_match('/__([A-Z]+)__/', $key, $matches) &&
                    isset($matches[1]) &&
                    isset(self::REPLACE_FUNCTIONS[$matches[1]])
                ) {
                    $func = self::REPLACE_FUNCTIONS[$matches[1]];
                    $value = $this->$func($value ?? '');

                }

                $content = str_replace($key, $value, $content);
                $name = str_replace($key, $value, $name);
                $path = str_replace($key, $value, $path);
            }

            $resource->setName($name);
            $resource->setPath($path);
            $resource->setContent($content);

            if ($resource->getType()->value === ResourceTypeEnum::Dir->value) {
                $this->createDir($project, $resource);
            } else {
                $this->saveFile($project, $resource);
            }
        }
    }

    private function saveFile(Project $project, Resource $resource): void
    {
        $path = $resource->getPath();
        if (!empty($path)) {
            $path = substr($path, 0,strrpos($path, '/') + 1);
            $path = preg_replace('/\/+$/', '', $path);
        }

        $path = $this->buildPath . DIRECTORY_SEPARATOR . $project->getUuid() . DIRECTORY_SEPARATOR . $path ;

        if (!empty($path) && !file_exists($path)) {
            mkdir($path, 0777, true);
        }
        $filePath = $this->buildPath . DIRECTORY_SEPARATOR . $project->getUuid(). DIRECTORY_SEPARATOR. $resource->getPath();
        file_put_contents($filePath , $resource->getContent());
    }

    private function createDir(Project $project, Resource $resource): string
    {

        if (empty($resource->getPath())) {
            return '';
        }

        $path = $this->buildPath .
            DIRECTORY_SEPARATOR .
            $project->getUuid() .
            DIRECTORY_SEPARATOR .
            $resource->getPath()
        ;

        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }

        return $path;
    }

    private function archive(Project $project): string
    {
        $directory = $this->buildPath . DIRECTORY_SEPARATOR .  $project->getUuid();
        $archivePath = $this->buildArchivePath . DIRECTORY_SEPARATOR .  $project->getUuid() .'.zip';

        if (!file_exists( $this->buildArchivePath )) {
            mkdir($this->buildArchivePath, 0777, true);
        }

        $zip = new \ZipArchive();
        if ($zip->open($archivePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Error create archive');
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($directory) + 1);
            $zip->addFile($filePath, $relativePath);
        }

        $zip->close();
        return $archivePath;
    }

    public function templateCamelCase(string $value): string
    {
        return s($value)->camel()->toString();
    }
    public function templateCamelCaseUCFirst(string $value): string
    {
        return ucfirst(s($value)->camel()->toString());
    }
    public function templateSnackCase(string $value): string
    {
        return s($value)->snake()->toString();
    }
}