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
use function Symfony\Component\String\s;

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

    public function getRecursive(
        ?Project $project = null,
        ?string $type = null,
        ?User $user = null,
        ?Resource $resource = null,
        bool $isDebug = false,
        ?string $projectUuid = null,
        ?string $resourceUuid = null,
    ) {
        $query = 'parent_id IS NULL';

        if (!empty($resource)) {
            $query = 'parent_id = ' . $resource->getId();
        } elseif (!empty($resourceUuid)) {
            $query = " r.uuid = '{$resourceUuid}'";
        }

        if (!empty($project)) {
            $query .= " AND project_id = " . $project->getId();
        } elseif (!empty($projectUuid)) {
            $query .= " AND p.uuid = '{$projectUuid}'";
        }



        if (!empty($type)) {
            $query .= " AND r.type = " . $type;
        }

        if (!empty($user)) {
            $query .= " AND r.owner_id = " .$user->getId();
        }

        $sql = "WITH RECURSIVE resource_hierarchy AS (
                    SELECT
                        r.id,
                        r.uuid,
                        r.name,
                        r.parent_id,
                        p.name as project_name,
                        CAST(r.name AS VARCHAR) AS path,
                        0 AS depth 
                    FROM resource r
                    INNER JOIN public.project p ON r.project_id = p.id
                    WHERE $query
                    UNION ALL
                    SELECT
                        r.id,
                        r.uuid,
                        r.name,
                        r.parent_id,
                        p.name as project_name,
                        CAST(rh.path || '/' || r.name AS VARCHAR(255)) AS path,
                        rh.depth + 1 AS depth
                    FROM resource r
                    INNER JOIN public.project p ON r.project_id = p.id
                    INNER JOIN resource_hierarchy rh
                        ON r.parent_id = rh.id
                )
                SELECT * FROM resource_hierarchy ORDER BY id ASC;";

        if ($isDebug) {
            dd($sql);
        }


        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);

        $res = $stmt->executeQuery();

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

    public function findByWordQueryBuilder(
        string $word,
        ?string $camelCase = null,
        ?string $snackCase = null,
        ?QueryBuilder $queryBuilder = null
    ): QueryBuilder {
        $queryBuilder = $queryBuilder ?? $this->createQueryBuilder(self::ALIAS);

        $orX = $queryBuilder->expr()->orX();
        $orX->add($queryBuilder->expr()->like(self::ALIAS.'.name', ':word'));
        $orX->add($queryBuilder->expr()->like(self::ALIAS.'.content', ':word'));
        $orX->add($queryBuilder->expr()->like(self::ALIAS.'.path', ':word'));

        if (!empty($camelCase)) {
            $orX->add($queryBuilder->expr()->like(self::ALIAS.'.name', ':cc'));
            $orX->add($queryBuilder->expr()->like(self::ALIAS.'.content', ':cc'));
            $orX->add($queryBuilder->expr()->like(self::ALIAS.'.path', ':cc'));
            $queryBuilder->setParameter('cc', '%' . $camelCase . '%');
        }

        if (!empty($snackCase)) {
            $orX->add($queryBuilder->expr()->like(self::ALIAS.'.name', ':sc'));
            $orX->add($queryBuilder->expr()->like(self::ALIAS.'.content', ':sc'));
            $orX->add($queryBuilder->expr()->like(self::ALIAS.'.path', ':sc'));
            $queryBuilder->setParameter('sc', '%' . $snackCase . '%');
        }

        $queryBuilder->setParameter('word', '%'.$word.'%');

        $queryBuilder->andWhere($orX);
        return $queryBuilder;
    }

    public function insert($data)
    {
        dd($data);
        $tableName = $this->getEntityManager()->getClassMetadata(self::ALIAS)->getTableName();
        $conn = $this->getEntityManager()->getConnection();
        $conn->insert($tableName, $data);
    }
}
