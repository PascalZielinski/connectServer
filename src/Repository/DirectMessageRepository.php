<?php

namespace App\Repository;

use App\Entity\DirectMessage;
use App\Entity\DmConversation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<DirectMessage>
 */
class DirectMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DirectMessage::class);
    }
    

    public function findByUuidAndConversation(string $uuid, DmConversation $conversation): ?DirectMessage
    {
        if (!Uuid::isValid($uuid)) {
            return null;
        }

        return $this->findOneBy(['uuid' => Uuid::fromString($uuid), 'conversation' => $conversation]);
    }

    /** @return DirectMessage[] */
    public function findHistory(DmConversation $conversation, int $limit, ?DirectMessage $before): array
    {
        $qb = $this->createQueryBuilder('m')
            ->where('m.conversation = :c')
            ->setParameter('c', $conversation)
            ->orderBy('m.id', 'DESC')
            ->setMaxResults($limit);

        if ($before !== null) {
            $qb->andWhere('m.id < :before')->setParameter('before', $before->getId());
        }

        return array_reverse($qb->getQuery()->getResult());
    }

    /** @return DirectMessage[] */
    public function findAfterId(DmConversation $conversation, int $id): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.conversation = :c')
            ->andWhere('m.id > :id')
            ->setParameter('c', $conversation)
            ->setParameter('id', $id)
            ->orderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

//    /**
//     * @return DirectMessage[] Returns an array of DirectMessage objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('d')
//            ->andWhere('d.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('d.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?DirectMessage
//    {
//        return $this->createQueryBuilder('d')
//            ->andWhere('d.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
