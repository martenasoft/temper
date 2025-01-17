<?php

namespace App\Service;

use App\Entity\Enum\ResourceType;
use App\Entity\Enum\ResourceType as ResourceTypeEnum;
use App\Entity\Project;
use App\Entity\Resource;
use App\Repository\ProjectRepository;
use App\Repository\ResourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;
use Symfony\Component\HttpKernel\KernelInterface;

class BuildService
{
    public const BUILD_PATH = 'build';
    public const BUILD_ARCHIVE_PATH = 'build_archive';

    private string $buildPath = '';
    private string $buildArchivePath = '';
    public function __construct(
        private KernelInterface $kernel,
        private ProjectRepository $projectRepository,
        private ResourceRepository $resourceRepository
    ) {
        $this->buildPath = $this->kernel->getProjectDir() . '/' . self::BUILD_PATH;
        $this->buildArchivePath  = $this->kernel->getProjectDir() . '/' . self::BUILD_ARCHIVE_PATH;
    }

    public function build(string $projectSlug, array $templates, array $data): array
    {
        $projects = $this->projectRepository->getOneBySlugQueryBuilder($projectSlug)->getQuery()->getResult();

        if (empty($projects)) {
            throw new \RuntimeException('Project not found');
        }

        $result = [];

        foreach ($projects as $project) {
            $resources = $project->getResources();
            if (!$resources) {
                continue;
            }

            $this->saveResources($project, $resources, $data);
            $result[] = $this->archive($project);
        }

        return $result;
    }

    private function saveResources(Project $project, PersistentCollection $resources,array $data): void
    {

        /** @var Resource $resource */
        foreach ($resources as $resource) {

            $content = $resource->getContent();
            $name = $resource->getName();

            foreach ($data as $key => $value) {
                $content = str_replace($key, $value, $content);
                $name = str_replace($key, $value, $name);
            }

            $resource->setName($name);
            $resource->setContent($content);

            if ($resource->getType()->value == ResourceTypeEnum::Dir->value) {
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
            $path = str_replace([$resource->getSlug(), $resource->getName()], '', $path);
            $path = preg_replace('/\/+$/', '', $path);
        }

        $resource->setPath($path . DIRECTORY_SEPARATOR . $resource->getName());

        if (!empty($path)) {
            $path =  $this->createDir($project, $resource) . DIRECTORY_SEPARATOR ;

        }

        file_put_contents($path . $resource->getName() , $resource->getContent());


    }

    private function createDir(Project $project, Resource $resource): string
    {

        if (empty($resource->getPath())) {
            return '';
        }

        $path = $this->buildPath .
            DIRECTORY_SEPARATOR .
            $project->getSlug() .
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
        $directory = $this->buildPath . DIRECTORY_SEPARATOR .  $project->getSlug(); // Путь к каталогу, который нужно архивировать
        $archivePath = $this->buildArchivePath . DIRECTORY_SEPARATOR .  $project->getSlug() .'.zip'; // Временный путь для архива

        $zip = new \ZipArchive();
        if ($zip->open($archivePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Не удалось создать архив.');
        }

        // Добавляем файлы из каталога в архив
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
}