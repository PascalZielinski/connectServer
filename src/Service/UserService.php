<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\OwnerVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class UserService
{
    public function __construct(
        private UserRepository $users,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface $validator,
        private AuthorizationCheckerInterface $authorization,
        private AuthService $auth,
    ) {
    }

    public function create(array $data): OperationResult
    {
        return $this->auth->registerRequest($data);
    }

    public function index(mixed $currentUser): OperationResult
    {
        $user = $this->requireUser($currentUser);
        $this->requireAuthorization(OwnerVoter::MANAGE, $user);

        return new OperationResult([$this->userData($user)]);
    }

    public function search(mixed $search): OperationResult
    {
        if (!is_string($search) || trim($search) === '') {
            return new OperationResult(['error' => 'A non-empty q search parameter is required.'], 400);
        }

        return new OperationResult(array_map(
            static fn (User $user): array => [
                'uuid' => $user->getUuid(),
                'name' => $user->getProfile()?->getDisplayName(),
            ],
            $this->users->searchByNameOrUuid(trim($search)),
        ));
    }

    public function show(int $id, mixed $currentUser): OperationResult
    {
        return new OperationResult($this->userData($this->findAuthorizedUser($id, $currentUser)));
    }

    public function update(int $id, mixed $currentUser, array $data): OperationResult
    {
        $user = $this->findAuthorizedUser($id, $currentUser);
        if (!isset($data['email']) || !is_string($data['email'])) {
            return new OperationResult(['error' => 'Email is required.'], 400);
        }
        if (array_key_exists('password', $data) && !is_string($data['password'])) {
            return new OperationResult(['error' => 'Password must be a string.'], 400);
        }

        $user->setEmail($data['email']);
        if (array_key_exists('password', $data)) {
            $user->setPassword($data['password']);
        }

        $errors = $this->validationErrors($user);
        if ($errors !== []) {
            return new OperationResult(['errors' => $errors], 422);
        }

        $duplicate = $this->users->findOneBy(['email' => $user->getEmail()]);
        if ($duplicate !== null && $duplicate->getId() !== $user->getId()) {
            return new OperationResult(['error' => 'An account with this email already exists.'], 409);
        }
        if (array_key_exists('password', $data)) {
            $user->setPasswordHash($this->passwordHasher->hashPassword($user, $data['password']));
        }

        $this->entityManager->flush();

        return new OperationResult($this->userData($user));
    }

    public function delete(int $id, mixed $currentUser): OperationResult
    {
        $user = $this->findAuthorizedUser($id, $currentUser);
        $this->entityManager->remove($user);
        $this->entityManager->flush();

        return new OperationResult(null, 204);
    }

    private function findAuthorizedUser(int $id, mixed $currentUser): User
    {
        $user = $this->requireUser($currentUser);
        $target = $this->users->find($id);
        if (!$target instanceof User) {
            throw new NotFoundHttpException();
        }
        $this->requireAuthorization(OwnerVoter::MANAGE, $target);

        return $target;
    }

    private function requireUser(mixed $user): User
    {
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        return $user;
    }

    private function requireAuthorization(string $attribute, object $subject): void
    {
        if (!$this->authorization->isGranted('ROLE_USER') || !$this->authorization->isGranted($attribute, $subject)) {
            throw new AccessDeniedHttpException();
        }
    }

    private function validationErrors(User $user): array
    {
        $errors = [];
        foreach ($this->validator->validate($user) as $violation) {
            $errors[$violation->getPropertyPath()][] = $violation->getMessage();
        }

        return $errors;
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
