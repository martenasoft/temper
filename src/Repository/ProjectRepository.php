<?php

namespace App\Repository;

use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository
{
    public const ALIAS  = 'p';
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    public function getOneByUuidQueryBuilder(string $projectUuid): ?QueryBuilder
    {

        $queryBuilder = $this->createQueryBuilder(self::ALIAS);
        $queryBuilder
            ->leftJoin(self::ALIAS . '.resources', ResourceRepository::ALIAS)
            ->addSelect(ResourceRepository::ALIAS)
            ->andWhere(self::ALIAS . '.uuid=:uuid')
            ->setParameter('uuid', $projectUuid);


        return $queryBuilder;
    }
}
