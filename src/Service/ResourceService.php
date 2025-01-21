<?php

namespace App\Service;

use App\Entity\Project;
use App\Entity\Resource;
use App\Repository\ResourceRepository;
use Doctrine\ORM\EntityManagerInterface;

class ResourceService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ResourceRepository $resourceRepository,
    ) {

    }

    public function updatePath(Project $project): void
    {
        $this->resourceRepository->updatePath($project);
    }

    public function getTree(Project $project, ?string $type = null): array
    {
        return $this->resourceRepository->getRecursive($project, $type);
    }

    public function move(Project $project, string $activeResourceUuid, string $selectedResourceUuid): void
    {
        $items = $this
            ->resourceRepository
            ->findByUuidQueryBuilder( [$activeResourceUuid, $selectedResourceUuid])
            ->getQuery()
            ->getResult()
        ;

        if (isset($items[0]) && isset($items[1])) {

            $items[1]->setParent($items[0]);

            if ($items[0]->getId() === $items[0]?->getParent()?->getId()) {
                throw new \Exception('You can not move to a parent itself');
            }

            $this->entityManager->flush();
            $this->updatePath($project);
        }
    }

}
