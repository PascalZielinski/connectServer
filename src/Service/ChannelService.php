<?php

namespace App\Service;

use App\Entity\Channel;
use App\Entity\Server;
use App\Entity\User;
use App\Enum\ChannelType;
use App\Repository\ChannelRepository;
use App\Repository\ServerRepository;
use App\Security\ServerVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class ChannelService
{
    public function __construct(
        private ChannelRepository $channels,
        private ServerRepository $servers,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private AuthorizationCheckerInterface $authorization,
    ) {
    }

    public function index(string $serverUuid): OperationResult
    {
        $server = $this->findServer($serverUuid);
        $this->requireAuthorization(ServerVoter::VIEW, $server);
        $channels = $server->getSettings()?->getChannels()->toArray() ?? [];

        return new OperationResult(array_map(
            fn (Channel $channel): array => $this->channelData($channel, true),
            $channels,
        ));
    }

    public function create(string $serverUuid, array $data): OperationResult
    {
        $server = $this->findServer($serverUuid);
        $this->requireAuthorization(ServerVoter::MANAGE_CHANNELS, $server);
        $error = $this->validateServerChannelInput($data, true);
        if ($error !== null) {
            return new OperationResult(['error' => $error], 400);
        }

        $type = ChannelType::tryFrom($data['type']);
        if ($type === null) {
            return new OperationResult(['error' => 'type must be either text or voice.'], 422);
        }
        $settings = $server->getSettings();
        if ($settings === null) {
            throw new \LogicException('Server settings are missing.');
        }

        $channel = new Channel($settings, $data['name'], $type);
        $errors = $this->validationErrors($channel);
        if ($errors !== []) {
            return new OperationResult(['errors' => $errors], 422);
        }

        $this->entityManager->persist($channel);
        $this->entityManager->flush();

        return new OperationResult($this->channelData($channel, true), 201);
    }

    public function updateForServer(string $serverUuid, int $id, array $data): OperationResult
    {
        $server = $this->findServer($serverUuid);
        $this->requireAuthorization(ServerVoter::MANAGE_CHANNELS, $server);
        $channel = $this->findChannelForServer($id, $server);
        $error = $this->validateServerChannelInput($data, false);
        if ($error !== null) {
            return new OperationResult(['error' => $error], 400);
        }
        if (array_key_exists('name', $data)) {
            $channel->setName($data['name']);
        }
        if (array_key_exists('type', $data)) {
            $type = ChannelType::tryFrom($data['type']);
            if ($type === null) {
                return new OperationResult(['error' => 'type must be either text or voice.'], 422);
            }
            $channel->setType($type);
        }

        $errors = $this->validationErrors($channel);
        if ($errors !== []) {
            return new OperationResult(['errors' => $errors], 422);
        }

        $this->entityManager->flush();

        return new OperationResult($this->channelData($channel, true));
    }

    public function deleteForServer(string $serverUuid, int $id): OperationResult
    {
        $server = $this->findServer($serverUuid);
        $this->requireAuthorization(ServerVoter::MANAGE_CHANNELS, $server);
        $this->remove($this->findChannelForServer($id, $server));

        return new OperationResult(null, 204);
    }

    public function updateByUuid(string $channelUuid, array $data): OperationResult
    {
        $channel = $this->findChannel($channelUuid);
        $server = $this->findChannelServer($channel);
        $this->requireAuthorization(ServerVoter::MANAGE_CHANNELS, $server);

        if ($data === []) {
            return new OperationResult(['error' => 'Provide a channel name or type to update.'], 400);
        }
        if (array_key_exists('name', $data)) {
            if (!is_string($data['name'])) {
                return new OperationResult(['error' => 'name must be a string.'], 400);
            }
            $channel->setName($data['name']);
        }
        if (array_key_exists('type', $data)) {
            if (!is_string($data['type'])) {
                return new OperationResult(['error' => 'type must be a string.'], 400);
            }
            $type = ChannelType::tryFrom($data['type']);
            if ($type === null) {
                return new OperationResult(['error' => 'type must be either text or voice.'], 422);
            }
            $channel->setType($type);
        }
        $errors = $this->validationErrors($channel);
        if ($errors !== []) {
            return new OperationResult(['errors' => $errors], 422);
        }

        $this->entityManager->flush();

        return new OperationResult($this->channelData($channel, false));
    }

    public function deleteByUuid(string $channelUuid): OperationResult
    {
        $channel = $this->findChannel($channelUuid);
        $this->requireAuthorization(ServerVoter::MANAGE_CHANNELS, $this->findChannelServer($channel));
        $this->remove($channel);

        return new OperationResult(null, 204);
    }

    private function findServer(string $uuid): Server
    {
        $server = $this->servers->findOneBy(['uuid' => $uuid]);
        if (!$server instanceof Server) {
            throw new NotFoundHttpException('Server not found.');
        }

        return $server;
    }

    private function findChannelForServer(int $id, Server $server): Channel
    {
        $settings = $server->getSettings();
        $channel = $settings === null ? null : $this->channels->findForSettings($id, $settings);
        if (!$channel instanceof Channel) {
            throw new NotFoundHttpException('Channel not found.');
        }

        return $channel;
    }

    private function findChannel(string $uuid): Channel
    {
        $channel = $this->channels->findOneBy(['uuid' => $uuid]);
        if (!$channel instanceof Channel) {
            throw new NotFoundHttpException('Channel not found.');
        }

        return $channel;
    }

    private function findChannelServer(Channel $channel): Server
    {
        $server = $channel->getServerSettings()?->getServer();
        if (!$server instanceof Server || $server->getId() === null || $this->servers->find($server->getId()) === null) {
            throw new NotFoundHttpException('Channel server not found.');
        }

        return $server;
    }

    private function validateServerChannelInput(array $data, bool $creating): ?string
    {
        if ($creating && (!isset($data['name']) || !is_string($data['name']))) {
            return 'name is required and must be a string.';
        }
        if (array_key_exists('name', $data) && !is_string($data['name'])) {
            return 'name must be a string.';
        }
        if ($creating && (!isset($data['type']) || !is_string($data['type']))) {
            return 'type is required and must be a string.';
        }
        if (array_key_exists('type', $data) && !is_string($data['type'])) {
            return 'type must be a string.';
        }

        return null;
    }

    private function validationErrors(Channel $channel): array
    {
        $errors = [];
        foreach ($this->validator->validate($channel) as $violation) {
            $errors[$violation->getPropertyPath()][] = $violation->getMessage();
        }

        return $errors;
    }

    private function requireAuthorization(string $attribute, Server $server): void
    {
        if (!$this->authorization->isGranted($attribute, $server)) {
            throw new AccessDeniedHttpException();
        }
    }

    private function remove(Channel $channel): void
    {
        $this->entityManager->remove($channel);
        $this->entityManager->flush();
    }

    private function channelData(Channel $channel, bool $includeId): array
    {
        $data = [
            'uuid' => $channel->getUuid(),
            'name' => $channel->getName(),
            'type' => $channel->getType()->value,
        ];
        if ($includeId) {
            return ['id' => $channel->getId(), ...$data];
        }

        return $data;
    }
}
