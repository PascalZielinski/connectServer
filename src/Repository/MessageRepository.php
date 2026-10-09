<?php

namespace App\Repository;

use App\Entity\Channel;
use App\Entity\Message;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /** @return list<Message> Newest page, ordered oldest to newest. */
    public function findHistory(Channel $channel, int $limit = 50, ?Message $before = null): array
    {
        $queryBuilder = $this->createQueryBuilder('message')
            ->andWhere('message.channel = :channel')
            ->setParameter('channel', $channel)
            ->orderBy('message.createdAt', 'DESC')
            ->addOrderBy('message.id', 'DESC')
            ->setMaxResults($limit);

        if ($before !== null) {
            $queryBuilder
                ->andWhere('(message.createdAt < :createdAt OR (message.createdAt = :createdAt AND message.id < :id))')
                ->setParameter('createdAt', $before->getCreatedAt()->setTimezone(new \DateTimeZone('UTC')))
                ->setParameter('id', $before->getId());
        }

        return array_reverse($queryBuilder->getQuery()->getResult());
    }

    public function findByUuidAndChannel(string $uuid, Channel $channel): ?Message
    {
        return $this->findOneBy(['uuid' => $uuid, 'channel' => $channel]);
    }

    public function findLatestId(Channel $channel): int
    {
        return (int) $this->createQueryBuilder('message')
            ->select('MAX(message.id)')
            ->andWhere('message.channel = :channel')
            ->setParameter('channel', $channel)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<Message> */
    public function findAfterId(Channel $channel, int $id, int $limit = 100): array
    {
        return $this->createQueryBuilder('message')
            ->andWhere('message.channel = :channel')
            ->andWhere('message.id > :id')
            ->setParameter('channel', $channel)
            ->setParameter('id', $id)
            ->orderBy('message.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
