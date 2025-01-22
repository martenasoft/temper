<?php

namespace App\Service;

use App\Entity\Project;
use App\Entity\Resource;
use App\Entity\User;
use App\Repository\ResourceRepository;
use Doctrine\ORM\EntityManagerInterface;

class ResourceService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ResourceRepository     $resourceRepository,
    )
    {

    }

    public function updatePath(Project $project): void
    {
        $this->resourceRepository->updatePath($project);
    }

    public function getTree(?Project $project = null, ?string $type = null, ?User $user = null): array
    {
        return $this->resourceRepository->getRecursive($project, $type, $user);
    }

    public function move(Project $project, string $activeResourceUuid, string $selectedResourceUuid): void
    {
        $items = $this
            ->resourceRepository
            ->findByUuidQueryBuilder([$activeResourceUuid, $selectedResourceUuid])
            ->getQuery()
            ->getResult();

        if (isset($items[0]) && isset($items[1])) {

            $items[1]->setParent($items[0]);

            if ($items[0]->getId() === $items[0]?->getParent()?->getId()) {
                throw new \Exception('You can not move to a parent itself');
            }

            $this->entityManager->flush();
            $this->updatePath($project);
        }
    }

    public function copy(Project $project, ?Resource $resource = null, array $uuids = []): void
    {
        $items = $this
            ->resourceRepository
            ->findByUuidQueryBuilder($uuids)
            ->getQuery()
            ->getResult();

        if (empty($items)) {
            return;
        }


        foreach ($items as $item) {

            $newResource = new Resource();
            $newResource->setProject($project);
            $name = $item->getName();

            if ($item->getUUid() === $resource?->getUuid()) {
                $name .= "Copy0";
                if (preg_match('/(\w+)(\d+)$/', $name, $matches) && isset($matches[2])) {
                    $name = $matches[1] . (++$matches[2]);
                }
            }

            $newResource
                ->setName($name)
                ->setType($item->getType())
                ->setContent($item->getContent())
                ->setOwner($item->getOwner());

            if ($resource) {
                $newResource->setParent($resource);
            }
            dump($newResource);
            $this->entityManager->persist($newResource);


        }

        $this->entityManager->flush();
        $this->resourceRepository->updatePath($project);
    }
}
