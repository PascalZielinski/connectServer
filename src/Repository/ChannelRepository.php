<?php

namespace App\Repository;

use App\Entity\Channel;
use App\Entity\ServerSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Channel>
 */
class ChannelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Channel::class);
    }

    public function findForSettings(int $id, ServerSettings $settings): ?Channel
    {
        return $this->findOneBy(['id' => $id, 'serverSettings' => $settings]);
    }
}
