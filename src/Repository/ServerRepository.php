<?php

namespace App\Repository;

use App\Entity\Server;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Server>
 */
class ServerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Server::class);
    }

    public function countCreatedBy(User $user): int
    {
        return (int) $this->createQueryBuilder('server')
            ->select('COUNT(server.id)')
            ->andWhere('server.createdBy = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<Server> */
    public function findPublicServers(?string $search = null, int $limit = 50): array
    {
        $queryBuilder = $this->createQueryBuilder('server')
            ->join('server.settings', 'settings')
            ->andWhere('settings.publicy = true')
            ->orderBy('server.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($search !== null) {
            $queryBuilder
                ->andWhere('LOWER(settings.name) LIKE :search OR LOWER(server.uuid) LIKE :search')
                ->setParameter('search', '%'.strtolower(trim($search)).'%');
        }

        return $queryBuilder->getQuery()->getResult();
    }
}
