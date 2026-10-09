<?php

namespace App\Controller;

use App\Entity\Channel;
use App\Entity\Message;
use App\Entity\User;
use App\Enum\ChannelType;
use App\Repository\ChannelRepository;
use App\Repository\MemberRepository;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/channels/{channelUuid}/messages')]
final class MessageController extends AbstractController
{
    #[Route('', name: 'api_channel_messages_history', methods: ['GET'])]
    public function history(
        string $channelUuid,
        Request $request,
        ChannelRepository $channels,
        MemberRepository $members,
        MessageRepository $messages,
    ): JsonResponse {
        $channel = $this->findTextChannel($channelUuid, $channels);
        $this->requireMembership($channel, $members);

        $limit = $request->query->getInt('limit', 50);
        if ($limit < 1 || $limit > 100) {
            return $this->json(['error' => 'limit must be between 1 and 100.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $before = null;
        $beforeUuid = $request->query->get('before');
        if ($beforeUuid !== null) {
            if (!is_string($beforeUuid)) {
                return $this->json(['error' => 'before must be a message UUID.'], JsonResponse::HTTP_BAD_REQUEST);
            }
            $before = $messages->findByUuidAndChannel($beforeUuid, $channel);
            if ($before === null) {
                return $this->json(['error' => 'The before message was not found in this channel.'], JsonResponse::HTTP_NOT_FOUND);
            }
        }

        return $this->json(array_map(
            fn (Message $message): array => $this->messageData($message),
            $messages->findHistory($channel, $limit, $before),
        ));
    }

    #[Route('/after/{id}', name: 'api_channel_messages_after_id', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function afterId(
        string $channelUuid,
        int $id,
        ChannelRepository $channels,
        MemberRepository $members,
        MessageRepository $messages,
    ): JsonResponse {
        $channel = $this->findTextChannel($channelUuid, $channels);
        $this->requireMembership($channel, $members);

        return $this->json(array_map(
            fn (Message $message): array => $this->messageData($message),
            $messages->findAfterId($channel, $id),
        ));
    }

    #[Route('', name: 'api_channel_messages_create', methods: ['POST'])]
    public function create(
        string $channelUuid,
        Request $request,
        ChannelRepository $channels,
        MemberRepository $members,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
    ): JsonResponse {
        $channel = $this->findTextChannel($channelUuid, $channels);
        $user = $this->requireMembership($channel, $members);
        $data = $request->toArray();
        if (!isset($data['content']) || !is_string($data['content'])) {
            return $this->json(['error' => 'content is required and must be a string.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $message = new Message($channel, $user, $data['content']);
        $violations = $validator->validate($message);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()][] = $violation->getMessage();
            }

            return $this->json(['errors' => $errors], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $entityManager->persist($message);
        $entityManager->flush();

        return $this->json($this->messageData($message), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{messageUuid}', name: 'api_channel_messages_update', methods: ['PATCH'])]
    public function update(
        string $channelUuid,
        string $messageUuid,
        Request $request,
        ChannelRepository $channels,
        MemberRepository $members,
        MessageRepository $messages,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
    ): JsonResponse {
        $channel = $this->findTextChannel($channelUuid, $channels);
        $user = $this->requireMembership($channel, $members);
        $message = $messages->findByUuidAndChannel($messageUuid, $channel);
        if ($message === null) {
            throw $this->createNotFoundException('Message not found.');
        }
        if ($message->getAuthor()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('You can only edit your own messages.');
        }

        $data = $request->toArray();
        if (!isset($data['content']) || !is_string($data['content'])) {
            return $this->json(['error' => 'content is required and must be a string.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $message->setContent($data['content']);
        $violations = $validator->validate($message);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()][] = $violation->getMessage();
            }

            return $this->json(['errors' => $errors], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $entityManager->flush();

        return $this->json($this->messageData($message));
    }

    #[Route('/{messageUuid}', name: 'api_channel_messages_delete', methods: ['DELETE'])]
    public function delete(
        string $channelUuid,
        string $messageUuid,
        ChannelRepository $channels,
        MemberRepository $members,
        MessageRepository $messages,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $channel = $this->findTextChannel($channelUuid, $channels);
        $user = $this->requireMembership($channel, $members);
        $message = $messages->findByUuidAndChannel($messageUuid, $channel);
        if ($message === null) {
            throw $this->createNotFoundException('Message not found.');
        }
        if ($message->getAuthor()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('You can only delete your own messages.');
        }

        $entityManager->remove($message);
        $entityManager->flush();

        return $this->json(null, JsonResponse::HTTP_NO_CONTENT);
    }

    private function findTextChannel(string $uuid, ChannelRepository $channels): Channel
    {
        $channel = $channels->findOneBy(['uuid' => $uuid]);
        if (!$channel instanceof Channel) {
            throw $this->createNotFoundException('Channel not found.');
        }
        if ($channel->getType() !== ChannelType::Text) {
            throw $this->createNotFoundException('Messages are only available in text channels.');
        }

        return $channel;
    }

    private function requireMembership(Channel $channel, MemberRepository $members): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $server = $channel->getServerSettings()?->getServer();
        if ($server === null || $members->findMembership($server, $user) === null) {
            throw $this->createAccessDeniedException('You must be a member of this server to access its messages.');
        }

        return $user;
    }

    private function messageData(Message $message): array
    {
        $author = $message->getAuthor();

        return [
            'id' => $message->getId(),
            'uuid' => $message->getUuid(),
            'content' => $message->getContent(),
            'created_at' => $message->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'author' => [
                'uuid' => $author?->getUuid(),
                'name' => $author?->getProfile()?->getDisplayName(),
            ],
        ];
    }
}
