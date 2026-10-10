<?php

namespace App\Controller;

use App\Service\OperationResult;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class UserController extends AbstractController
{
    #[Route('/api/users', name: 'api_users_create', methods: ['POST'])]
    public function create(Request $request, UserService $users): JsonResponse
    {
        return $this->response($users->create($request->toArray()));
    }

    #[Route('/api/users', name: 'api_users_index', methods: ['GET'])]
    public function index(UserService $users): JsonResponse
    {
        return $this->response($users->index($this->getUser()));
    }

    #[Route('/api/users/search', name: 'api_users_search', methods: ['GET'])]
    public function search(Request $request, UserService $users): JsonResponse
    {
        return $this->response($users->search($request->query->get('q')));
    }

    #[Route('/api/users/{id}', name: 'api_users_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, UserService $users): JsonResponse
    {
        return $this->response($users->show($id, $this->getUser()));
    }

    #[Route('/api/users/{id}', name: 'api_users_update', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function update(int $id, Request $request, UserService $users): JsonResponse
    {
        return $this->response($users->update($id, $this->getUser(), $request->toArray()));
    }

    #[Route('/api/users/{id}', name: 'api_users_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id, UserService $users): JsonResponse
    {
        return $this->response($users->delete($id, $this->getUser()));
    }

    private function response(OperationResult $result): JsonResponse
    {
        return $this->json($result->data, $result->statusCode);
    }
}
