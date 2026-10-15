<?php

namespace App\Controller;

use App\Service\OperationResult;
use App\Service\ProfileService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

final class ProfileController extends AbstractController
{
    #[Route('/api/me/profile', name: 'api_profile_create', methods: ['POST'])]
    public function create(Request $request, ProfileService $profiles): JsonResponse
    {
        return $this->response($profiles->create($this->getUser(), $request->toArray()));
    }

    #[Route('/api/me/profile', name: 'api_profile_show', methods: ['GET'])]
    public function show(ProfileService $profiles): JsonResponse
    {
        return $this->response($profiles->show($this->getUser()));
    }

    #[Route('/api/me/profile', name: 'api_profile_update', methods: ['PUT'])]
    public function update(Request $request, ProfileService $profiles): JsonResponse
    {
        return $this->response($profiles->update($this->getUser(), $request->toArray()));
    }

    #[Route('/api/me/profile', name: 'api_profile_delete', methods: ['DELETE'])]
    public function delete(ProfileService $profiles): JsonResponse
    {
        return $this->response($profiles->delete($this->getUser()));
    }

    #[Route('/api/me/profile/image', name: 'api_profile_image_upload', methods: ['POST'])]
    public function uploadImage(Request $request, ProfileService $profiles): JsonResponse
    {
        return $this->response($profiles->uploadImage($this->getUser(), $request->files->get('image')));
    }

    #[Route('/api/me/profile/image', name: 'api_profile_image_show', methods: ['GET'])]
    public function showImage(ProfileService $profiles): Response
    {
        $path = $profiles->getImagePath($this->getUser());

        if ($path === null) {
            return $this->response(new OperationResult(['error' => 'No profile image.'], 404));
        }

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setPrivate();
        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('must-revalidate');

        return $response;
    }

    private function response(OperationResult $result): JsonResponse
    {
        return $this->json($result->data, $result->statusCode);
    }
}
