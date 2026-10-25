<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPasswordHash($newHashedPassword);
        $this->getEntityManager()->flush();
    }

    /** @return list<User> */
    public function searchByNameOrUuid(string $search, int $limit = 25): array
    {
        return $this->createQueryBuilder('user')
            ->leftJoin('user.profile', 'profile')
            ->addSelect('profile')
            ->andWhere('LOWER(user.uuid) LIKE :search OR LOWER(profile.displayName) LIKE :search')
            ->setParameter('search', '%'.strtolower(trim($search)).'%')
            ->orderBy('profile.displayName', 'ASC')
            ->addOrderBy('user.uuid', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function deleteExpiredUnverified(\DateTimeImmutable $now): int
    {
        return $this->createQueryBuilder('u')
            ->delete()
            ->where('u.isVerified = false')
            ->andWhere('u.IsVerifiedExpiresAt IS NOT NULL')
            ->andWhere('u.IsVerifiedExpiresAt < :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->execute();
    }
}
