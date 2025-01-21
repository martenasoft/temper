<?php

namespace App\Repository;

use App\Entity\Project;
use App\Entity\User;
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

    public function getOneByUuidQueryBuilder(User $user, string $projectUuid): ?QueryBuilder
    {

        $queryBuilder = $this->createQueryBuilder(self::ALIAS);
        $queryBuilder
            ->leftJoin(self::ALIAS . '.resources', ResourceRepository::ALIAS)
            ->addSelect(ResourceRepository::ALIAS)
            ->andWhere(self::ALIAS . '.uuid=:uuid')
            ->andWhere(self::ALIAS . '.owner=:user')
            ->setParameter('uuid', $projectUuid)
            ->setParameter('user', $user)
        ;


        return $queryBuilder;
    }
}
