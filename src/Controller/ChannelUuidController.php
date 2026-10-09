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

#[Route('/api/channels/{channelUuid}')]
final class ChannelUuidController extends AbstractController
{
    #[Route('', name: 'api_channel_update', methods: ['PATCH'])]
    public function update(
        string $channelUuid,
        Request $request,
        ChannelRepository $channels,
        ServerRepository $servers,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
    ): JsonResponse {
        $channel = $this->findChannel($channelUuid, $channels);
        $this->denyAccessUnlessGranted(ServerVoter::MANAGE_CHANNELS, $this->findServer($channel, $servers));

        $data = $request->toArray();
        if ($data === []) {
            return $this->json(['error' => 'Provide a channel name or type to update.'], JsonResponse::HTTP_BAD_REQUEST);
        }
        if (array_key_exists('name', $data)) {
            if (!is_string($data['name'])) {
                return $this->json(['error' => 'name must be a string.'], JsonResponse::HTTP_BAD_REQUEST);
            }
            $channel->setName($data['name']);
        }
        if (array_key_exists('type', $data)) {
            if (!is_string($data['type'])) {
                return $this->json(['error' => 'type must be a string.'], JsonResponse::HTTP_BAD_REQUEST);
            }
            $type = ChannelType::tryFrom($data['type']);
            if ($type === null) {
                return $this->json(['error' => 'type must be either text or voice.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            }
            $channel->setType($type);
        }

        $violations = $validator->validate($channel);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()][] = $violation->getMessage();
            }

            return $this->json(['errors' => $errors], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $entityManager->flush();

        return $this->json($this->channelData($channel));
    }

    #[Route('', name: 'api_channel_delete', methods: ['DELETE'])]
    public function delete(
        string $channelUuid,
        ChannelRepository $channels,
        ServerRepository $servers,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $channel = $this->findChannel($channelUuid, $channels);
        $this->denyAccessUnlessGranted(ServerVoter::MANAGE_CHANNELS, $this->findServer($channel, $servers));
        $entityManager->remove($channel);
        $entityManager->flush();

        return $this->json(null, JsonResponse::HTTP_NO_CONTENT);
    }

    private function findChannel(string $uuid, ChannelRepository $channels): Channel
    {
        $channel = $channels->findOneBy(['uuid' => $uuid]);
        if (!$channel instanceof Channel) {
            throw $this->createNotFoundException('Channel not found.');
        }

        return $channel;
    }

    private function findServer(Channel $channel, ServerRepository $servers): Server
    {
        $server = $channel->getServerSettings()?->getServer();
        if (!$server instanceof Server || $servers->find($server->getId()) === null) {
            throw $this->createNotFoundException('Channel server not found.');
        }

        return $server;
    }

    private function channelData(Channel $channel): array
    {
        return [
            'uuid' => $channel->getUuid(),
            'name' => $channel->getName(),
            'type' => $channel->getType()->value,
        ];
    }
}
