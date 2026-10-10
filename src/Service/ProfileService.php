<?php

namespace App\Service;

use App\Entity\Profile;
use App\Entity\User;
use App\Repository\ProfileRepository;
use App\Security\OwnerVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class ProfileService
{
    public function __construct(
        private ProfileRepository $profiles,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private AuthorizationCheckerInterface $authorization,
    ) {
    }

    public function create(mixed $currentUser, array $data): OperationResult
    {
        $user = $this->authorizedUser($currentUser);
        if ($this->profiles->findOneBy(['user' => $user]) !== null) {
            return new OperationResult(['error' => 'A profile already exists.'], 409);
        }

        $profile = (new Profile())->setUser($user);
        $this->applyData($profile, $data);
        $errors = $this->validationErrors($profile);
        if ($errors !== []) {
            return new OperationResult(['errors' => $errors], 422);
        }

        $this->entityManager->persist($profile);
        $this->entityManager->flush();

        return new OperationResult($this->profileData($profile), 201);
    }

    public function show(mixed $currentUser): OperationResult
    {
        $profile = $this->findForUser($this->authorizedUser($currentUser));

        return new OperationResult($this->profileData($profile));
    }

    public function update(mixed $currentUser, array $data): OperationResult
    {
        $profile = $this->findForUser($this->authorizedUser($currentUser));
        $this->applyData($profile, $data);
        $errors = $this->validationErrors($profile);
        if ($errors !== []) {
            return new OperationResult(['errors' => $errors], 422);
        }

        $this->entityManager->flush();

        return new OperationResult($this->profileData($profile));
    }

    public function delete(mixed $currentUser): OperationResult
    {
        $profile = $this->findForUser($this->authorizedUser($currentUser));
        $this->entityManager->remove($profile);
        $this->entityManager->flush();

        return new OperationResult(null, 204);
    }

    private function authorizedUser(mixed $user): User
    {
        if (!$user instanceof User
            || !$this->authorization->isGranted('ROLE_USER')
            || !$this->authorization->isGranted(OwnerVoter::MANAGE, $user)) {
            throw new AccessDeniedHttpException();
        }

        return $user;
    }

    private function findForUser(User $user): Profile
    {
        $profile = $this->profiles->findOneBy(['user' => $user]);
        if (!$profile instanceof Profile) {
            throw new NotFoundHttpException();
        }

        return $profile;
    }

    private function applyData(Profile $profile, array $data): void
    {
        foreach (['display_name', 'bio'] as $field) {
            $key = $field;
            if (array_key_exists($key, $data)) {
                if ($data[$key] !== null && !is_string($data[$key])) {
                    throw new BadRequestHttpException(sprintf('%s must be a string or null.', $field));
                }
                if ($key === 'display_name') {
                    $profile->setDisplayName($data[$key]);
                } else {
                    $profile->setBio($data[$key]);
                }
            }
        }
    }

    private function validationErrors(Profile $profile): array
    {
        $errors = [];
        foreach ($this->validator->validate($profile) as $violation) {
            $errors[$violation->getPropertyPath()][] = $violation->getMessage();
        }

        return $errors;
    }

    private function profileData(Profile $profile): array
    {
        return [
            'id' => $profile->getId(),
            'user_id' => $profile->getUser()?->getId(),
            'display_name' => $profile->getDisplayName(),
            'bio' => $profile->getBio(),
        ];
    }
}
