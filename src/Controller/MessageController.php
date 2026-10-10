<?php

namespace App\Controller;

use App\Service\MessageService;
use App\Service\OperationResult;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/channels/{channelUuid}/messages')]
final class MessageController extends AbstractController
{
    #[Route('', name: 'api_channel_messages_history', methods: ['GET'])]
    public function history(string $channelUuid, Request $request, MessageService $messages): JsonResponse
    {
        return $this->response($messages->history(
            $channelUuid,
            $this->getUser(),
            $request->query->getInt('limit', 50),
            $request->query->get('before'),
        ));
    }

    #[Route('/after/{id}', name: 'api_channel_messages_after_id', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function afterId(string $channelUuid, int $id, MessageService $messages): JsonResponse
    {
        return $this->response($messages->afterId($channelUuid, $id, $this->getUser()));
    }

    #[Route('', name: 'api_channel_messages_create', methods: ['POST'])]
    public function create(string $channelUuid, Request $request, MessageService $messages): JsonResponse
    {
        $file = $request->files->get('file');
        $content = $file === null
            ? ($request->toArray()['content'] ?? null)
            : ($request->request->all()['content'] ?? null);

        return $this->response($messages->create(
            $channelUuid,
            $this->getUser(),
            $content,
            $file,
        ));
    }

    #[Route('/{messageUuid}', name: 'api_channel_messages_update', methods: ['PATCH'])]
    public function update(
        string $channelUuid,
        string $messageUuid,
        Request $request,
        MessageService $messages,
    ): JsonResponse {
        $data = $request->toArray();

        return $this->response($messages->update(
            $channelUuid,
            $messageUuid,
            $this->getUser(),
            $data['content'] ?? null,
        ));
    }

    #[Route('/{messageUuid}', name: 'api_channel_messages_delete', methods: ['DELETE'])]
    public function delete(string $channelUuid, string $messageUuid, MessageService $messages): JsonResponse
    {
        return $this->response($messages->delete(
            $channelUuid,
            $messageUuid,
            $this->getUser(),
        ));
    }

    #[Route('/attachments/{attachmentUuid}', name: 'api_channel_message_attachment_download', methods: ['GET'])]
    public function downloadAttachment(
        string $channelUuid,
        string $attachmentUuid,
        MessageService $messages,
    ): Response {
        return $messages->downloadAttachment($channelUuid, $attachmentUuid, $this->getUser());
    }

    private function response(OperationResult $result): JsonResponse
    {
        return $this->json($result->data, $result->statusCode);
    }
}
