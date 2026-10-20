<?php

namespace App\Controller;

use App\Service\DirectMessageService;
use App\Service\OperationResult;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class DirectMessageController extends AbstractController
{
    private const MESSAGES = '/api/direct/{conversationUuid}/message';

    #[Route('/api/direct/{participantUuid}', name: 'api_direct_add', methods: ['POST'])]
    public function add(string $participantUuid, DirectMessageService $directMessageService): JsonResponse
    {
        return $this->response($directMessageService->add($participantUuid, $this->getUser()));
    }

    #[Route(self::MESSAGES, name: 'api_direct_message_history', methods: ['GET'])]
    public function history(string $conversationUuid, Request $request, DirectMessageService $directMessageService): JsonResponse
    {
        return $this->response($directMessageService->history(
            $conversationUuid,
            $this->getUser(),
            $request->query->getInt('limit', 50),
            $request->query->get('before'),
        ));
    }

    #[Route(self::MESSAGES . '/after/{id}', name: 'api_direct_message_after_id', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function afterId(string $conversationUuid, int $id, DirectMessageService $directMessageService): JsonResponse
    {
        return $this->response($directMessageService->afterId($conversationUuid, $id, $this->getUser()));
    }

    #[Route(self::MESSAGES, name: 'api_direct_message_create', methods: ['POST'])]
    public function create(string $conversationUuid, Request $request, DirectMessageService $directMessageService): JsonResponse
    {
        return $this->response($directMessageService->create(
            $conversationUuid,
            $this->getUser(),
            $request->getPayload()->all()['content'] ?? null,
        ));
    }

    #[Route(self::MESSAGES . '/{messageUuid}', name: 'api_direct_message_update', methods: ['PATCH'])]
    public function update(
        string $conversationUuid,
        string $messageUuid,
        Request $request,
        DirectMessageService $directMessageService,
    ): JsonResponse {
        $data = $request->toArray();

        return $this->response($directMessageService->update(
            $conversationUuid,
            $messageUuid,
            $this->getUser(),
            $data['content'] ?? null,
        ));
    }

    #[Route(self::MESSAGES . '/{messageUuid}', name: 'api_direct_message_delete', methods: ['DELETE'])]
    public function delete(string $conversationUuid, string $messageUuid, DirectMessageService $directMessageService): JsonResponse
    {
        return $this->response($directMessageService->delete(
            $conversationUuid,
            $messageUuid,
            $this->getUser(),
        ));
    }

    private function response(OperationResult $result): JsonResponse
    {
        return $this->json($result->data, $result->statusCode);
    }
}