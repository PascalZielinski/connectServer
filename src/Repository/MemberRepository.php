<?php

namespace App\Repository;

use App\Entity\Member;
use App\Entity\Server;
use App\Entity\User;
use App\Enum\MemberRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Member>
 */
class MemberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Member::class);
    }

    public function findMembership(Server $server, User $user): ?Member
    {
        return $this->findOneBy(['server' => $server, 'user' => $user]);
    }

    public function findByUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['joinedAt' => 'DESC']);
    }

    public function findByServer(Server $server): array
    {
        return $this->findBy(['server' => $server], ['joinedAt' => 'ASC']);
    }

    public function countOwners(Server $server): int
    {
        return (int) $this->createQueryBuilder('member')
            ->select('COUNT(member.id)')
            ->andWhere('member.server = :server')
            ->andWhere('member.role = :ownerRole')
            ->setParameter('server', $server)
            ->setParameter('ownerRole', MemberRole::Owner)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
