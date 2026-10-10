<?php

namespace App\Service;

use App\Entity\StoredUpload;
use App\Repository\StoredUploadRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final readonly class UploadService
{
    private const MAX_IMAGE_BYTES = 10 * 1024 * 1024;
    private const MAX_IMAGE_PIXELS = 8_000_000;

    public function __construct(
        private StoredUploadRepository $uploads,
        private EntityManagerInterface $entityManager,
        private string $storageDirectory,
    ) {
    }

    public function store(mixed $file): StoredUpload
    {
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            throw new UnprocessableEntityHttpException('A valid file upload is required.');
        }

        $originalFilename = $this->validateOriginalFilename($file->getClientOriginalName());
        if ($file->getClientOriginalPath() !== $originalFilename) {
            throw new UnprocessableEntityHttpException('The filename must not contain a path.');
        }
        $temporaryPath = $file->getPathname();
        $imageKind = $this->imageKind($originalFilename, $temporaryPath);
        if ($imageKind !== null) {
            $size = filesize($temporaryPath);
            if ($size === false) {
                throw new \RuntimeException('Unable to determine the uploaded image size.');
            }
            if ($size > self::MAX_IMAGE_BYTES) {
                throw new UnprocessableEntityHttpException('PNG and JPEG images must be 10 MiB or smaller.');
            }
        }

        $mediaType = $imageKind === 'png' ? 'image/png' : ($imageKind === 'jpeg' ? 'image/jpeg' : 'application/octet-stream');
        $upload = new StoredUpload($originalFilename, $mediaType, $imageKind !== null);
        $this->ensureStorageDirectory();
        $destination = $this->storageDirectory . '/' . $this->storageFilename($upload);
        try {
            if ($imageKind === null) {
                $file->move($this->storageDirectory, $this->storageFilename($upload));
            } else {
                $this->sanitizeImage($temporaryPath, $destination, $imageKind);
            }

            $this->entityManager->persist($upload);
        } catch (\Throwable $exception) {
            if (is_file($destination) && !unlink($destination)) {
                throw new \RuntimeException('Unable to remove an incomplete upload.', 0, $exception);
            }

            throw $exception;
        }

        return $upload;
    }

    public function discard(StoredUpload $upload): void
    {
        $path = $this->storageDirectory . '/' . $this->storageFilename($upload);
        if (is_file($path) && !unlink($path)) {
            throw new \RuntimeException(sprintf('Unable to remove incomplete upload "%s".', $upload->getUuid()));
        }
    }

    public function download(string $uuid): Response
    {
        $upload = $this->uploads->findOneBy(['uuid' => $uuid]);
        if (!$upload instanceof StoredUpload || $upload->getExpiresAt() <= new \DateTimeImmutable('now', new \DateTimeZone('UTC'))) {
            throw new NotFoundHttpException('File not found.');
        }

        $path = $this->storageDirectory . '/' . $this->storageFilename($upload);
        if (!is_file($path)) {
            throw new NotFoundHttpException('File not found.');
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', $upload->getMediaType());
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Content-Security-Policy', "default-src 'none'; sandbox");
        $response->setContentDisposition(
            $upload->isImage() ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $upload->getOriginalFilename(),
        );

        return $response;
    }

    private function validateOriginalFilename(string $filename): string
    {
        if ($filename === ''
            || $filename === '.'
            || $filename === '..'
            || strlen($filename) > 255
            || preg_match('//u', $filename) !== 1
            || preg_match('/[\x00-\x1F\x7F\/\\\\:]/u', $filename) === 1
            || basename($filename) !== $filename
            || trim($filename) !== $filename) {
            throw new UnprocessableEntityHttpException('The filename is invalid.');
        }

        return $filename;
    }

    private function imageKind(string $filename, string $path): ?string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $claimsPng = $extension === 'png';
        $claimsJpeg = in_array($extension, ['jpg', 'jpeg'], true);
        if (!$claimsPng && !$claimsJpeg) {
            return null;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Unable to inspect uploaded file.');
        }

        try {
            $signature = fread($handle, 8);
        } finally {
            fclose($handle);
        }

        $isPng = $signature === "\x89PNG\r\n\x1a\n";
        $isJpeg = str_starts_with($signature, "\xff\xd8\xff");
        if (($claimsPng && !$isPng) || ($claimsJpeg && !$isJpeg)) {
            throw new UnprocessableEntityHttpException('The file contents do not match the PNG or JPEG filename.');
        }

        return $claimsPng ? 'png' : 'jpeg';
    }

    private function sanitizeImage(string $source, string $destination, string $kind): void
    {
        $encoderAvailable = $kind === 'png' ? function_exists('imagepng') : function_exists('imagejpeg');
        if (!function_exists('imagecreatefromstring') || !$encoderAvailable) {
            throw new \RuntimeException('GD with PNG and JPEG support is required to sanitize uploaded images.');
        }

        $imageInfo = getimagesize($source);
        $expectedType = $kind === 'png' ? IMAGETYPE_PNG : IMAGETYPE_JPEG;
        if ($imageInfo === false
            || $imageInfo[2] !== $expectedType
            || $imageInfo[0] < 1
            || $imageInfo[1] < 1
            || $imageInfo[0] * $imageInfo[1] > self::MAX_IMAGE_PIXELS) {
            throw new UnprocessableEntityHttpException('The image is invalid or exceeds 8 megapixels.');
        }

        $contents = file_get_contents($source);
        if ($contents === false) {
            throw new \RuntimeException('Unable to read the uploaded image.');
        }
        $image = imagecreatefromstring($contents);
        unset($contents);
        if ($image === false) {
            throw new UnprocessableEntityHttpException('The image could not be decoded.');
        }

        try {
            if ($kind === 'png') {
                imagealphablending($image, false);
                imagesavealpha($image, true);
                $saved = imagepng($image, $destination, 6);
            } else {
                $saved = imagejpeg($image, $destination, 85);
            }
        } finally {
            imagedestroy($image);
        }

        if (!$saved) {
            throw new \RuntimeException('Unable to save the sanitized image.');
        }
        $sanitizedSize = filesize($destination);
        if ($sanitizedSize === false || $sanitizedSize > self::MAX_IMAGE_BYTES) {
            throw new UnprocessableEntityHttpException('The sanitized image exceeds 10 MiB.');
        }
        if (!chmod($destination, 0600)) {
            throw new \RuntimeException('Unable to restrict permissions on the sanitized image.');
        }
    }

    private function storageFilename(StoredUpload $upload): string
    {
        return $upload->getUuid() . '.blob';
    }

    private function ensureStorageDirectory(): void
    {
        if (is_dir($this->storageDirectory)) {
            return;
        }
        if (!mkdir($this->storageDirectory, 0700, true) && !is_dir($this->storageDirectory)) {
            throw new \RuntimeException(sprintf('Unable to create upload storage directory "%s".', $this->storageDirectory));
        }
    }
}
