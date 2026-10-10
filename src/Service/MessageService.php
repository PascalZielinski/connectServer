<?php

namespace App\Service;

use App\Entity\Channel;
use App\Entity\Message;
use App\Entity\User;
use App\Enum\ChannelType;
use App\Repository\ChannelRepository;
use App\Repository\MemberRepository;
use App\Repository\MessageRepository;
use App\Repository\StoredUploadRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class MessageService
{
    public function __construct(
        private ChannelRepository $channels,
        private MemberRepository $members,
        private MessageRepository $messages,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private UploadService $uploads,
        private StoredUploadRepository $storedUploads,
    ) {
    }

    public function history(string $channelUuid, mixed $user, int $limit, mixed $beforeUuid): OperationResult
    {
        $channel = $this->findTextChannel($channelUuid);
        $this->requireMembership($channel, $user);

        if ($limit < 1 || $limit > 100) {
            return new OperationResult(['error' => 'limit must be between 1 and 100.'], 400);
        }

        $before = null;
        if ($beforeUuid !== null) {
            if (!is_string($beforeUuid)) {
                return new OperationResult(['error' => 'before must be a message UUID.'], 400);
            }
            $before = $this->messages->findByUuidAndChannel($beforeUuid, $channel);
            if ($before === null) {
                return new OperationResult(['error' => 'The before message was not found in this channel.'], 404);
            }
        }

        return new OperationResult(array_map(
            $this->messageData(...),
            $this->messages->findHistory($channel, $limit, $before),
        ));
    }

    public function afterId(string $channelUuid, int $id, mixed $user): OperationResult
    {
        $channel = $this->findTextChannel($channelUuid);
        $this->requireMembership($channel, $user);

        return new OperationResult(array_map(
            $this->messageData(...),
            $this->messages->findAfterId($channel, $id),
        ));
    }

    public function create(string $channelUuid, mixed $user, mixed $content, mixed $file = null): OperationResult
    {
        $channel = $this->findTextChannel($channelUuid);
        $author = $this->requireMembership($channel, $user);
        if ($file === null && (!isset($content) || !is_string($content))) {
            return new OperationResult(['error' => 'content is required and must be a string.'], 400);
        }
        if ($file !== null && $content !== null && !is_string($content)) {
            return new OperationResult(['error' => 'content must be a string.'], 400);
        }
        if ($file === null && trim($content) === '') {
            return new OperationResult(['errors' => ['content' => ['This value should not be blank.']]], 422);
        }

        $message = new Message($channel, $author, is_string($content) ? $content : null);
        $errors = $this->validationErrors($message);
        if ($errors !== []) {
            return new OperationResult(['errors' => $errors], 422);
        }

        $upload = null;
        if ($file !== null) {
            $upload = $this->uploads->store($file);
            $message->setAttachmentUuid($upload->getUuid());
        }

        try {
            $this->entityManager->persist($message);
            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            if ($upload !== null) {
                try {
                    $this->uploads->discard($upload);
                } catch (\Throwable $cleanupException) {
                    throw new \RuntimeException('Failed to clean up a file after its message could not be saved.', 0, $cleanupException);
                }
            }

            throw $exception;
        }

        return new OperationResult($this->messageData($message), 201);
    }

    public function update(
        string $channelUuid,
        string $messageUuid,
        mixed $user,
        mixed $content,
    ): OperationResult {
        $channel = $this->findTextChannel($channelUuid);
        $author = $this->requireMembership($channel, $user);
        $message = $this->messages->findByUuidAndChannel($messageUuid, $channel);
        if ($message === null) {
            throw new NotFoundHttpException('Message not found.');
        }
        $this->assertAuthor($message, $author, 'edit');
        if (!isset($content) || !is_string($content)) {
            return new OperationResult(['error' => 'content is required and must be a string.'], 400);
        }
        if (trim($content) === '') {
            return new OperationResult(['errors' => ['content' => ['This value should not be blank.']]], 422);
        }

        $message->setContent($content);
        $errors = $this->validationErrors($message);
        if ($errors !== []) {
            return new OperationResult(['errors' => $errors], 422);
        }

        $this->entityManager->flush();

        return new OperationResult($this->messageData($message));
    }

    public function delete(string $channelUuid, string $messageUuid, mixed $user): OperationResult
    {
        $channel = $this->findTextChannel($channelUuid);
        $author = $this->requireMembership($channel, $user);
        $message = $this->messages->findByUuidAndChannel($messageUuid, $channel);
        if ($message === null) {
            throw new NotFoundHttpException('Message not found.');
        }
        $this->assertAuthor($message, $author, 'delete');

        $this->entityManager->remove($message);
        $this->entityManager->flush();

        return new OperationResult(null, 204);
    }

    public function downloadAttachment(string $channelUuid, string $attachmentUuid, mixed $user): Response
    {
        $channel = $this->findTextChannel($channelUuid);
        $this->requireMembership($channel, $user);
        if ($this->messages->findByAttachmentUuidAndChannel($attachmentUuid, $channel) === null) {
            throw new NotFoundHttpException('File not found in this channel.');
        }

        return $this->uploads->download($attachmentUuid);
    }

    private function findTextChannel(string $uuid): Channel
    {
        $channel = $this->channels->findOneBy(['uuid' => $uuid]);
        if (!$channel instanceof Channel) {
            throw new NotFoundHttpException('Channel not found.');
        }
        if ($channel->getType() !== ChannelType::Text) {
            throw new NotFoundHttpException('Messages are only available in text channels.');
        }

        return $channel;
    }

    private function requireMembership(Channel $channel, mixed $user): User
    {
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        $server = $channel->getServerSettings()?->getServer();
        if ($server === null || $this->members->findMembership($server, $user) === null) {
            throw new AccessDeniedHttpException('You must be a member of this server to access its messages.');
        }

        return $user;
    }

    private function assertAuthor(Message $message, User $user, string $action): void
    {
        if ($message->getAuthor()?->getId() !== $user->getId()) {
            throw new AccessDeniedHttpException(sprintf('You can only %s your own messages.', $action));
        }
    }

    private function validationErrors(Message $message): array
    {
        $errors = [];
        foreach ($this->validator->validate($message) as $violation) {
            $errors[$violation->getPropertyPath()][] = $violation->getMessage();
        }

        return $errors;
    }

    private function messageData(Message $message): array
    {
        $author = $message->getAuthor();

        return [
            'id' => $message->getId(),
            'uuid' => $message->getUuid(),
            'content' => $message->getContent(),
            'created_at' => $message->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'attachment' => $this->attachmentData($message),
            'author' => [
                'uuid' => $author?->getUuid(),
                'name' => $author?->getProfile()?->getDisplayName(),
            ],
        ];
    }

    private function attachmentData(Message $message): ?array
    {
        $attachmentUuid = $message->getAttachmentUuid();
        if ($attachmentUuid === null) {
            return null;
        }

        $upload = $this->storedUploads->findOneBy(['uuid' => $attachmentUuid]);
        if ($upload === null) {
            return null;
        }

        return [
            'uuid' => $attachmentUuid,
            'filename' => $upload->getOriginalFilename(),
            'media_type' => $upload->getMediaType(),
            'image' => $upload->isImage(),
            'download_url' => sprintf(
                '/api/channels/%s/messages/attachments/%s',
                $message->getChannel()?->getUuid(),
                $attachmentUuid,
            ),
        ];
    }
}
