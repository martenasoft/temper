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

    public function copy(Project $project, ?Resource $parentResource = null, string $uuid = '', int $deep = 0): void
    {
        if (empty($uuid)) {
            return;
        }

        $selectedResource = $this
            ->resourceRepository
            ->findByUuidQueryBuilder([$uuid])
            ->getQuery()
            ->getResult()[0] ?? null;

        $items = $selectedResource?->getResources();

        if (empty($items)) {
            return;
        }

        foreach ($items as $i => $item) {

            $newResource = new Resource();
            $newResource->setProject($project);
            $name = $item->getName();

            if ($item->getUUid() === $selectedResource?->getUuid()) {
                $name .= "Copy0";
                if (preg_match('/(\w+)(\d+)$/', $name, $matches) && isset($matches[2])) {
                    $name = $matches[1] . (++$matches[2]);
                }
            }

            $newResource
                ->setName($name)
                ->setType($item->getType())
                ->setContent($item->getContent())
                ->setOwner($item->getOwner())
                ->setParent($parentResource);


            $this->entityManager->persist($newResource);
            $this->entityManager->flush();

            if ($item->getType()->value === 1) {
                $this->copy($project, $newResource, $item->getUuid(), ++$deep);
            }

        }


       // $items_ = $this->resourceRepository->findBy(['parent' => $item->getId()]);
       /* dump([
            'resurses' => $item->getResources()->toArray(),
            'deep' => $deep,
            'resource' => $resource,
            '$items' => $items,
            '$uuids' => $uuids
        ]);*/

     //   $this->copy($project, $newResource, $item->getResources()->toArray(), ++$deep);

    }
}
