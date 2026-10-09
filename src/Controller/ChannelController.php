<?php

namespace App\Controller;

use App\Entity\Channel;
use App\Entity\Server;
use App\Enum\ChannelType;
use App\Repository\ChannelRepository;
use App\Repository\ServerRepository;
use App\Security\ServerVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/servers/{uuid}/channels')]
final class ChannelController extends AbstractController
{
    #[Route('', name: 'api_server_channels_list', methods: ['GET'])]
    public function index(string $uuid, ServerRepository $servers): JsonResponse
    {
        $server = $this->findServer($uuid, $servers);
        $this->denyAccessUnlessGranted(ServerVoter::VIEW, $server);
        $channels = $server->getSettings()?->getChannels()->toArray() ?? [];

        return $this->json(array_map(fn (Channel $channel): array => $this->channelData($channel), $channels));
    }

    #[Route('', name: 'api_server_channels_create', methods: ['POST'])]
    public function create(
        string $uuid,
        Request $request,
        ServerRepository $servers,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
    ): JsonResponse {
        $server = $this->findServer($uuid, $servers);
        $this->denyAccessUnlessGranted(ServerVoter::MANAGE_CHANNELS, $server);
        $data = $request->toArray();
        $error = $this->validateInput($data, true);
        if ($error !== null) {
            return $this->json(['error' => $error], JsonResponse::HTTP_BAD_REQUEST);
        }

        $type = ChannelType::tryFrom($data['type']);
        if ($type === null) {
            return $this->json(['error' => 'type must be either text or voice.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
        $settings = $server->getSettings();
        if ($settings === null) {
            throw new \LogicException('Server settings are missing.');
        }

        $channel = new Channel($settings, $data['name'], $type);
        $violations = $validator->validate($channel);
        if (count($violations) > 0) {
            return $this->json(['errors' => $this->violations($violations)], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $entityManager->persist($channel);
        $entityManager->flush();

        return $this->json($this->channelData($channel), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_server_channels_update', requirements: ['id' => '\d+'], methods: ['PATCH', 'PUT'])]
    public function update(
        string $uuid,
        int $id,
        Request $request,
        ServerRepository $servers,
        ChannelRepository $channels,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
    ): JsonResponse {
        $server = $this->findServer($uuid, $servers);
        $this->denyAccessUnlessGranted(ServerVoter::MANAGE_CHANNELS, $server);
        $channel = $this->findChannel($id, $server, $channels);
        $data = $request->toArray();
        $error = $this->validateInput($data, false);
        if ($error !== null) {
            return $this->json(['error' => $error], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (array_key_exists('name', $data)) {
            $channel->setName($data['name']);
        }
        if (array_key_exists('type', $data)) {
            $type = ChannelType::tryFrom($data['type']);
            if ($type === null) {
                return $this->json(['error' => 'type must be either text or voice.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            }
            $channel->setType($type);
        }

        $violations = $validator->validate($channel);
        if (count($violations) > 0) {
            return $this->json(['errors' => $this->violations($violations)], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $entityManager->flush();

        return $this->json($this->channelData($channel));
    }

    #[Route('/{id}', name: 'api_server_channels_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(
        string $uuid,
        int $id,
        ServerRepository $servers,
        ChannelRepository $channels,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $server = $this->findServer($uuid, $servers);
        $this->denyAccessUnlessGranted(ServerVoter::MANAGE_CHANNELS, $server);
        $channel = $this->findChannel($id, $server, $channels);
        $entityManager->remove($channel);
        $entityManager->flush();

        return $this->json(null, JsonResponse::HTTP_NO_CONTENT);
    }

    private function findServer(string $uuid, ServerRepository $servers): Server
    {
        $server = $servers->findOneBy(['uuid' => $uuid]);
        if (!$server instanceof Server) {
            throw $this->createNotFoundException('Server not found.');
        }

        return $server;
    }

    private function findChannel(int $id, Server $server, ChannelRepository $channels): Channel
    {
        $settings = $server->getSettings();
        $channel = $settings === null ? null : $channels->findForSettings($id, $settings);
        if (!$channel instanceof Channel) {
            throw $this->createNotFoundException('Channel not found.');
        }

        return $channel;
    }

    private function validateInput(array $data, bool $creating): ?string
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

    private function violations(iterable $violations): array
    {
        $errors = [];
        foreach ($violations as $violation) {
            $errors[$violation->getPropertyPath()][] = $violation->getMessage();
        }

        return $errors;
    }

    private function channelData(Channel $channel): array
    {
        return [
            'id' => $channel->getId(),
            'uuid' => $channel->getUuid(),
            'name' => $channel->getName(),
            'type' => $channel->getType()->value,
        ];
    }
}
