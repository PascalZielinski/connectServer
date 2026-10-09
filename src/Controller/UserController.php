<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\OwnerVoter;
use App\Service\AuthService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class UserController extends AbstractController
{
    #[Route('/api/users', name: 'api_users_create', methods: ['POST'])]
    public function create(Request $request, AuthService $authService): JsonResponse
    {
        $data = $request->toArray();
        if (
            !isset($data['email'], $data['password'], $data['password_confirm'])
            || !is_string($data['email'])
            || !is_string($data['password'])
            || !is_string($data['password_confirm'])
        ) {
            return $this->json(
                ['error' => 'Email, password, and password_confirm are required as strings.'],
                JsonResponse::HTTP_BAD_REQUEST,
            );
        }

        return $this->json(
            $authService->register($data['email'], $data['password'], $data['password_confirm']),
            JsonResponse::HTTP_CREATED,
        );
    }

    #[Route('/api/users', name: 'api_users_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->currentUser();
        $this->denyAccessUnlessGranted(OwnerVoter::MANAGE, $user);

        return $this->json([$this->userData($user)]);
    }

    #[Route('/api/users/search', name: 'api_users_search', methods: ['GET'])]
    public function search(Request $request, UserRepository $users): JsonResponse
    {
        $search = $request->query->get('q');
        if (!is_string($search) || trim($search) === '') {
            return $this->json(['error' => 'A non-empty q search parameter is required.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        return $this->json(array_map(
            static fn (User $user): array => [
                'uuid' => $user->getUuid(),
                'name' => $user->getProfile()?->getDisplayName(),
            ],
            $users->searchByNameOrUuid(trim($search)),
        ));
    }

    #[Route('/api/users/{id}', name: 'api_users_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, UserRepository $users): JsonResponse
    {
        $user = $this->findAuthorizedUser($id, $users);

        return $this->json($this->userData($user));
    }

    #[Route('/api/users/{id}', name: 'api_users_update', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function update(
        int $id,
        Request $request,
        UserRepository $users,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        ValidatorInterface $validator,
    ): JsonResponse {
        $user = $this->findAuthorizedUser($id, $users);
        $data = $request->toArray();

        if (!isset($data['email']) || !is_string($data['email'])) {
            return $this->json(['error' => 'Email is required.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $user->setEmail($data['email']);
        if (array_key_exists('password', $data)) {
            if (!is_string($data['password'])) {
                return $this->json(['error' => 'Password must be a string.'], JsonResponse::HTTP_BAD_REQUEST);
            }
            $user->setPassword($data['password']);
        }

        $violations = $validator->validate($user);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()][] = $violation->getMessage();
            }

            return $this->json(['errors' => $errors], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $duplicate = $users->findOneBy(['email' => $user->getEmail()]);
        if ($duplicate !== null && $duplicate->getId() !== $user->getId()) {
            return $this->json(['error' => 'An account with this email already exists.'], JsonResponse::HTTP_CONFLICT);
        }

        if (array_key_exists('password', $data)) {
            $user->setPasswordHash($passwordHasher->hashPassword($user, $data['password']));
        }

        $entityManager->flush();

        return $this->json($this->userData($user));
    }

    #[Route('/api/users/{id}', name: 'api_users_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id, UserRepository $users, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->findAuthorizedUser($id, $users);
        $entityManager->remove($user);
        $entityManager->flush();

        return $this->json(null, JsonResponse::HTTP_NO_CONTENT);
    }

    private function findAuthorizedUser(int $id, UserRepository $users): User
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $target = $users->find($id);
        if (!$target instanceof User) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(OwnerVoter::MANAGE, $target);

        return $target;
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function userData(User $user): array
    {
        return [
            'id' => $user->getId(),
            'uuid' => $user->getUuid(),
            'email' => $user->getEmail(),
            'created_at' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
