<?php

namespace App\Service;

use App\Entity\DirectMessage;
use App\Entity\DmConversation;
use App\Entity\DmParticipant;
use App\Entity\User;
use App\Repository\DirectMessageRepository;
use App\Repository\DmConversationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final readonly class DirectMessageService
{
    public function __construct(
        private DirectMessageRepository $messages,
        private DmConversationRepository $conversations,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private HubInterface $hub,
    ) {
    }

    public function history(string $conversationUuid, mixed $user, int $limit, mixed $beforeUuid): OperationResult
    {
        $user = $this->requireUser($user);
        $conversation = $this->requireConversation($conversationUuid, $user);

        if ($limit < 1 || $limit > 100) {
            return new OperationResult(['error' => 'limit must be between 1 and 100.'], 400);
        }

        $before = null;
        if ($beforeUuid !== null) {
            if (!is_string($beforeUuid)) {
                return new OperationResult(['error' => 'before must be a message UUID.'], 400);
            }
            $before = $this->messages->findByUuidAndConversation($beforeUuid, $conversation);
            if ($before === null) {
                return new OperationResult(['error' => 'The before message was not found in this conversation.'], 404);
            }
        }

        $messages = $this->messages->findHistory($conversation, $limit, $before);
        $this->markSeen($messages, $user);

        return new OperationResult(array_map($this->messageData(...), $messages));
    }

    public function afterId(string $conversationUuid, int $id, mixed $user): OperationResult
    {
        $user = $this->requireUser($user);
        $conversation = $this->requireConversation($conversationUuid, $user);

        $messages = $this->messages->findAfterId($conversation, $id);
        $this->markSeen($messages, $user);

        return new OperationResult(array_map($this->messageData(...), $messages));
    }

    public function add(string $userUuid, mixed $user): OperationResult
    {
        $user = $this->requireUser($user);

        if ($userUuid === '' || !Uuid::isValid($userUuid)) {
            return new OperationResult(['error' => 'userUuid missing or invalid'], 400);
        }

        $targetUser = $this->entityManager->getRepository(User::class)
            ->findOneBy(['uuid' => Uuid::fromString($userUuid)]);

        if ($targetUser === null) {
            return new OperationResult(['error' => 'user not found'], 404);
        }

        if ($targetUser->getId() === $user->getId()) {
            return new OperationResult(['error' => 'cannot start conversation with yourself'], 400);
        }

        $existing = $this->conversations->findBetweenUsers($user, $targetUser);
        if ($existing !== null) {
            $history = $this->history($existing->getUuid()->toRfc4122(), $user, 50, null);

            return new OperationResult([
                'uuid' => $existing->getUuid()->toRfc4122(),
                'messages' => $history->data,
            ]);
        }

        $conversation = new DmConversation();
        foreach ([$user, $targetUser] as $participantUser) {
            $participant = new DmParticipant();
            $participant->setUser($participantUser);
            $conversation->addParticipant($participant);
        }

        $this->entityManager->persist($conversation);
        $this->entityManager->flush();

        return new OperationResult([
            'uuid' => $conversation->getUuid()->toRfc4122(),
            'messages' => [],
        ], 201);
    }

    public function create(string $conversationUuid, mixed $user, mixed $content): OperationResult
    {
        $user = $this->requireUser($user);
        $conversation = $this->requireConversation($conversationUuid, $user);

        if (!is_string($content)) {
            return new OperationResult(['error' => 'content is required and must be a string.'], 400);
        }

        $message = new DirectMessage();
        $message->setAuthor($user);
        $message->setConversation($conversation);
        $message->setContent($content);

        $errors = $this->validationErrors($message);
        if ($errors !== []) {
            return new OperationResult(['errors' => $errors], 422);
        }

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        $this->publish($conversation, 'created', ['message' => $this->messageData($message)]);

        return new OperationResult($this->messageData($message), 201);
    }

    public function update(string $conversationUuid, string $messageUuid, mixed $user, mixed $content): OperationResult
    {
        $user = $this->requireUser($user);
        $conversation = $this->requireConversation($conversationUuid, $user);
        $message = $this->requireMessage($messageUuid, $conversation);
        $this->assertAuthor($message, $user, 'edit');

        if (!is_string($content)) {
            return new OperationResult(['error' => 'content is required and must be a string.'], 400);
        }

        $message->setContent($content);
        $errors = $this->validationErrors($message);
        if ($errors !== []) {
            return new OperationResult(['errors' => $errors], 422);
        }

        $this->entityManager->flush();

        $this->publish($conversation, 'updated', ['message' => $this->messageData($message)]);

        return new OperationResult($this->messageData($message));
    }

    public function delete(string $conversationUuid, string $messageUuid, mixed $user): OperationResult
    {
        $user = $this->requireUser($user);
        $conversation = $this->requireConversation($conversationUuid, $user);
        $message = $this->requireMessage($messageUuid, $conversation);
        $this->assertAuthor($message, $user, 'delete');

        $uuid = $message->getUuid()->toRfc4122();

        $this->entityManager->remove($message);
        $this->entityManager->flush();

        $this->publish($conversation, 'deleted', ['uuid' => $uuid]);

        return new OperationResult(null, 204);
    }

    private function requireUser(mixed $user): User
    {
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Authentication required.');
        }

        return $user;
    }

    private function requireConversation(string $conversationUuid, User $user): DmConversation
    {
        if (Uuid::isValid($conversationUuid)) {
            $conversation = $this->conversations->findOneBy(['uuid' => Uuid::fromString($conversationUuid)]);

            if ($conversation !== null) {
                foreach ($conversation->getParticipants() as $participant) {
                    if ($participant->getUser()?->getId() === $user->getId()) {
                        return $conversation;
                    }
                }
            }
        }

        throw new NotFoundHttpException('Conversation not found.');
    }

    private function requireMessage(string $messageUuid, DmConversation $conversation): DirectMessage
    {
        $message = $this->messages->findByUuidAndConversation($messageUuid, $conversation);
        if ($message === null) {
            throw new NotFoundHttpException('Message not found.');
        }

        return $message;
    }

    private function assertAuthor(DirectMessage $message, User $user, string $action): void
    {
        if ($message->getAuthor()?->getId() !== $user->getId()) {
            throw new AccessDeniedHttpException(sprintf('You can only %s your own messages.', $action));
        }
    }

    /** @param DirectMessage[] $messages */
    private function markSeen(array $messages, User $user): void
    {
        $changed = false;
        foreach ($messages as $message) {
            if ($message->isHidden() && $message->getAuthor()?->getId() !== $user->getId()) {
                $message->setHidden(false);
                $changed = true;
            }
        }

        if ($changed) {
            $this->entityManager->flush();
        }
    }

    private function validationErrors(DirectMessage $message): array
    {
        $errors = [];
        foreach ($this->validator->validate($message) as $violation) {
            $errors[$violation->getPropertyPath()][] = $violation->getMessage();
        }

        return $errors;
    }

    private function messageData(DirectMessage $message): array
    {
        $author = $message->getAuthor();

        return [
            'id' => $message->getId(),
            'uuid' => $message->getUuid()?->toRfc4122(),
            'content' => $message->getContent(),
            'created_at' => $message->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'author' => [
                'uuid' => $author?->getUuid(),
                'name' => $author?->getProfile()?->getDisplayName(),
            ],
        ];
    }

    private function publish(DmConversation $conversation, string $type, array $payload): void
    {
        $payload = ['type' => $type, 'conversation' => $this->uuidString($conversation->getUuid())] + $payload;

        foreach ($conversation->getParticipants() as $participant) {
            $uuid = $this->uuidString($participant->getUser()?->getUuid());
            if ($uuid === null) {
                continue;
            }

            $this->hub->publish(new Update('dm/' . $uuid, json_encode($payload), private: true));
        }
    }

    private function uuidString(mixed $uuid): ?string
    {
        if ($uuid === null) {
            return null;
        }

        return $uuid instanceof Uuid ? $uuid->toRfc4122() : (string) $uuid;
    }
}