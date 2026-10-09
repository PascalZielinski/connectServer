<?php

namespace App\Controller;

use App\Entity\Profile;
use App\Entity\User;
use App\Repository\ProfileRepository;
use App\Security\OwnerVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ProfileController extends AbstractController
{
    #[Route('/api/me/profile', name: 'api_profile_create', methods: ['POST'])]
    public function create(
        Request $request,
        ProfileRepository $profiles,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
    ): JsonResponse {
        $user = $this->authorizedCurrentUser();
        if ($profiles->findOneBy(['user' => $user]) !== null) {
            return $this->json(['error' => 'A profile already exists.'], JsonResponse::HTTP_CONFLICT);
        }

        $profile = new Profile();
        $profile->setUser($user);
        $this->applyData($profile, $request->toArray());
        $errors = $this->validate($profile, $validator);
        if ($errors !== []) {
            return $this->json(['errors' => $errors], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $entityManager->persist($profile);
        $entityManager->flush();

        return $this->json($this->profileData($profile), JsonResponse::HTTP_CREATED);
    }

    #[Route('/api/me/profile', name: 'api_profile_show', methods: ['GET'])]
    public function show(ProfileRepository $profiles): JsonResponse
    {
        $user = $this->authorizedCurrentUser();
        $profile = $profiles->findOneBy(['user' => $user]);
        if (!$profile instanceof Profile) {
            throw $this->createNotFoundException();
        }

        return $this->json($this->profileData($profile));
    }

    #[Route('/api/me/profile', name: 'api_profile_update', methods: ['PUT'])]
    public function update(
        Request $request,
        ProfileRepository $profiles,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
    ): JsonResponse {
        $user = $this->authorizedCurrentUser();
        $profile = $profiles->findOneBy(['user' => $user]);
        if (!$profile instanceof Profile) {
            throw $this->createNotFoundException();
        }

        $this->applyData($profile, $request->toArray());
        $errors = $this->validate($profile, $validator);
        if ($errors !== []) {
            return $this->json(['errors' => $errors], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $entityManager->flush();

        return $this->json($this->profileData($profile));
    }

    #[Route('/api/me/profile', name: 'api_profile_delete', methods: ['DELETE'])]
    public function delete(ProfileRepository $profiles, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->authorizedCurrentUser();
        $profile = $profiles->findOneBy(['user' => $user]);
        if (!$profile instanceof Profile) {
            throw $this->createNotFoundException();
        }

        $entityManager->remove($profile);
        $entityManager->flush();

        return $this->json(null, JsonResponse::HTTP_NO_CONTENT);
    }

    private function authorizedCurrentUser(): User
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $this->denyAccessUnlessGranted(OwnerVoter::MANAGE, $user);

        return $user;
    }

    private function applyData(Profile $profile, array $data): void
    {
        if (array_key_exists('display_name', $data)) {
            if ($data['display_name'] !== null && !is_string($data['display_name'])) {
                throw $this->createBadRequestException('display_name must be a string or null.');
            }
            $profile->setDisplayName($data['display_name']);
        }

        if (array_key_exists('bio', $data)) {
            if ($data['bio'] !== null && !is_string($data['bio'])) {
                throw $this->createBadRequestException('bio must be a string or null.');
            }
            $profile->setBio($data['bio']);
        }
    }

    private function validate(Profile $profile, ValidatorInterface $validator): array
    {
        $errors = [];
        foreach ($validator->validate($profile) as $violation) {
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
