<?php

namespace App\Service;

use App\Entity\Project;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

class ProjectService
{
    private const PROJECT_PATH = 'projects';

    private string $projectDir = '';

    public function __construct(private KernelInterface $kernel)
    {
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
}
