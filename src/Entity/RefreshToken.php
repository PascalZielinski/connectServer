<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken as BaseRefreshToken;

#[ORM\Entity]
#[ORM\Table(name: 'refresh_tokens')]
class RefreshToken extends BaseRefreshToken
{

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $deviceHash = null;

    public function getDeviceHash(): ?string
    {
        return $this->deviceHash;
    }

    public function setDeviceHash(string $deviceHash): static
    {
        $this->deviceHash = $deviceHash;

        return $this;
    }

}
