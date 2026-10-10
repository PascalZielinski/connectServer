<?php

namespace App\Repository;

use App\Entity\StoredUpload;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StoredUpload>
 */
class StoredUploadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StoredUpload::class);
    }

    /** @return list<StoredUpload> */
    public function findExpired(\DateTimeImmutable $now): array
    {
        return $this->createQueryBuilder('upload')
            ->andWhere('upload.expiresAt <= :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }
}
