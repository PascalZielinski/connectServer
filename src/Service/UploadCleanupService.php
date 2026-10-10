<?php

namespace App\Service;

use App\Entity\StoredUpload;
use App\Repository\StoredUploadRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class UploadCleanupService
{
    public function __construct(
        private StoredUploadRepository $uploads,
        private EntityManagerInterface $entityManager,
        private string $storageDirectory,
    ) {
    }

    public function deleteExpired(): int
    {
        $expired = $this->uploads->findExpired(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        foreach ($expired as $upload) {
            $this->deleteStoredFile($upload);
            $this->entityManager->remove($upload);
        }
        $this->entityManager->flush();

        return count($expired);
    }

    private function deleteStoredFile(StoredUpload $upload): void
    {
        $path = $this->storageDirectory . '/' . $upload->getUuid() . '.blob';
        if (!is_file($path)) {
            return;
        }
        if (!unlink($path)) {
            throw new \RuntimeException(sprintf('Unable to delete expired upload "%s".', $upload->getUuid()));
        }
    }
}
