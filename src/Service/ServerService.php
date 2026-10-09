<?php

namespace App\Service;

use App\Entity\Member;
use App\Entity\Server;
use App\Entity\ServerSettings;
use App\Entity\User;
use App\Enum\MemberRole;
use App\Repository\ServerRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class ServerService
{
    private const MAX_SERVERS_PER_OWNER = 10;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ServerRepository $servers,
        private ValidatorInterface $validator,
    ) {
    }

    public function create(User $owner, array $data): Server
    {
        return $this->entityManager->wrapInTransaction(function () use ($owner, $data): Server {
            $this->entityManager->lock($owner, LockMode::PESSIMISTIC_WRITE);
            if ($this->servers->countCreatedBy($owner) >= self::MAX_SERVERS_PER_OWNER) {
                throw new ConflictHttpException('A user can create at most 10 servers.');
            }

            $server = (new Server())->setCreatedBy($owner);
            $settings = (new ServerSettings())
                ->setName($data['name'])
                ->setDescription($data['description'] ?? null)
                ->setPublicy($data['publicy'] ?? false)
                ->setProfileImage($data['profile_image'] ?? null);
            $server->setSettings($settings);
            $server->addMember(new Member($server, $owner, MemberRole::Owner));
            $this->validate($settings);

            $this->entityManager->persist($server);
            $this->entityManager->flush();

            return $server;
        });
    }

    public function updateSettings(Server $server, array $data): Server
    {
        $settings = $server->getSettings();
        if ($settings === null) {
            throw new \LogicException('Server settings are missing.');
        }

        if (array_key_exists('name', $data)) {
            $settings->setName($data['name']);
        }
        if (array_key_exists('description', $data)) {
            $settings->setDescription($data['description']);
        }
        if (array_key_exists('publicy', $data)) {
            $settings->setPublicy($data['publicy']);
        }
        if (array_key_exists('profile_image', $data)) {
            $settings->setProfileImage($data['profile_image']);
        }

        $this->validate($settings);
        $this->entityManager->flush();

        return $server;
    }

    public function delete(Server $server): void
    {
        $this->entityManager->remove($server);
        $this->entityManager->flush();
    }

    private function validate(ServerSettings $settings): void
    {
        $violations = $this->validator->validate($settings);
        if (count($violations) === 0) {
            return;
        }

        $errors = [];
        foreach ($violations as $violation) {
            $errors[$violation->getPropertyPath()][] = $violation->getMessage();
        }

        throw new UnprocessableEntityHttpException(json_encode($errors, JSON_THROW_ON_ERROR));
    }
}
