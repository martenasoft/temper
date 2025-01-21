<?php

namespace App\Repository;

use App\Entity\Dir;
use App\Entity\Project;
use App\Entity\Resource;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Enum\ResourceType as EnumResourceType;

/**
 * @extends ServiceEntityRepository<Resource>
 */
class ResourceRepository extends ServiceEntityRepository
{
    public const ALIAS = 'r';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Resource::class);
    }

    public function getItemsQueryBuilder(Project $project, ?Resource $resource = null): QueryBuilder
    {

        $queryBuilder = $this->createQueryBuilder(self::ALIAS);
        if ($resource) {
            $queryBuilder
                ->andWhere(self::ALIAS . '.parent=:parent')
                ->setParameter('parent', $resource)
            ;
        } else {
            $queryBuilder
                ->andWhere(self::ALIAS . '.parent IS NULL')
            ;
        }

        return $queryBuilder;
    }

    public function getOneByUuidQueryBuilder(User $user, string $uuid): ?QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder(self::ALIAS);
        $queryBuilder
            ->andWhere(self::ALIAS . '.uuid=:uuid')
            ->andWhere(self::ALIAS . '.owner=:user')
            ->setParameter('uuid', $uuid)
            ->setParameter('user', $user)
        ;

        return $queryBuilder;
    }

    public function getAllFiles(?string $type = null, ?QueryBuilder $queryBuilder = null): QueryBuilder
    {
        $queryBuilder = $queryBuilder ?? $this->createQueryBuilder(self::ALIAS);
        if ($type !== null) {
            $queryBuilder
                ->andWhere(self::ALIAS . '.type=:type')
                ->setParameter('type', $type)
            ;
        }

        return $queryBuilder;
    }

    public function updatePath(Project $project): void
    {
        $sql = "WITH RECURSIVE resource_hierarchy AS (
                    SELECT
                        r.id,
                        r.name,
                        r.parent_id,
                        CAST(r.name AS VARCHAR) AS path,
                        0 AS deep
                    FROM resource r
                             INNER JOIN public.project p on r.project_id = p.id
                    WHERE parent_id IS NULL AND project_id = :project_id
                    UNION ALL
                    SELECT
                        d.id,
                        d.name,
                        d.parent_id,
                        CONCAT(dh.path, '/', d.name) AS path,
                        d.deep + 1 AS deep
                    FROM resource d
                             INNER JOIN resource_hierarchy dh
                                        ON d.parent_id = dh.id
                )
                UPDATE resource
                SET path = dh.path, deep = dh.deep
                FROM resource_hierarchy dh
                WHERE resource.id = dh.id
        ";
        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->executeQuery([
            'project_id' => $project->getId(),
        ]);

    }

    public function getRecursive(Project $project, ?string $type = null)
    {
        $typeQuery = '';

        if (!empty($type)) {
            $typeQuery = " AND r.type = :type ";
        }

        $sql = "WITH RECURSIVE resource_hierarchy AS (
                    SELECT
                        r.id,
                        r.uuid,
                        r.name,
                        r.parent_id,
                        CAST(r.name AS VARCHAR) AS path,
                        0 AS depth 
                    FROM resource r
                    INNER JOIN public.project p ON r.project_id = p.id
                    WHERE parent_id IS NULL AND project_id = :project_id $typeQuery
                    UNION ALL
                    SELECT
                        r.id,
                        r.uuid,
                        r.name,
                        r.parent_id,
                        CAST(rh.path || '/' || r.name AS VARCHAR(255)) AS path,
                        rh.depth + 1 AS depth
                    FROM resource r
                    INNER JOIN resource_hierarchy rh
                        ON r.parent_id = rh.id
                )
                SELECT * FROM resource_hierarchy;";

        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);

        $params = [
            'project_id' => $project->getId(),
        ];

        if (!empty($type)) {
            $params['type'] = $type;
        }

        $res = $stmt->executeQuery($params);

        return $res->fetchAllAssociative();
    }

    public function findByUuidQueryBuilder(array $uuid): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder(self::ALIAS);
        $queryBuilder
            ->andWhere(self::ALIAS.'.uuid IN(:uuid)')
            ->setParameter('uuid', $uuid)
            ->addOrderBy(self::ALIAS.'.id', 'ASC')
        ;
        return $queryBuilder;
    }

    public function getResourceByProjectUuidAndNameQueryBuilder(
        User $user,
        string $projectUuid,
        string $resourceName
    ): ?QueryBuilder {
        return $this
            ->createQueryBuilder(self::ALIAS)
            ->innerJoin(self::ALIAS . '.project', ProjectRepository::ALIAS)
            ->andWhere(self::ALIAS . '.name LIKE :name')
            ->andWhere(self::ALIAS . '.owner = :user')
            ->andWhere(ProjectRepository::ALIAS. '.uuid = :projectUuid')
            ->andWhere(self::ALIAS . '.name LIKE :name')
            ->setParameter('projectUuid', $projectUuid)
            ->setParameter('name', $resourceName)
            ->setParameter('user', $user)
            ;
    }
}
