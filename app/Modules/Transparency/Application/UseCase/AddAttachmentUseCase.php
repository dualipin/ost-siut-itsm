<?php

declare(strict_types=1);

namespace App\Modules\Transparency\Application\UseCase;

use App\Infrastructure\Persistence\TransactionManager;
use App\Modules\Transparency\Domain\Entity\TransparencyAttachment;
use App\Modules\Transparency\Domain\Enum\AttachmentType;
use App\Modules\Transparency\Domain\Exception\TransparencyNotFoundException;
use App\Modules\Transparency\Domain\Repository\FileStorageInterface;
use App\Modules\Transparency\Domain\Repository\TransparencyRepositoryInterface;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

final readonly class AddAttachmentUseCase
{
    public function __construct(
        private TransparencyRepositoryInterface $repository,
        private FileStorageInterface $fileStorage,
        private TransactionManager $transactionManager
    ) {
    }

    public function execute(
        int $transparencyId,
        string $sourcePath,
        string $originalFilename,
        string $mimeType,
        string $attachmentTypeValue,
        ?string $description,
        ?string $dateUpload = null
    ): TransparencyAttachment {
        $transparency = $this->repository->findById($transparencyId);
        if ($transparency === null) {
            throw TransparencyNotFoundException::withId($transparencyId);
        }

        $type = AttachmentType::tryFrom($attachmentTypeValue);
        if ($type === null) {
            throw new InvalidArgumentException("Tipo de adjunto inválido: {$attachmentTypeValue}");
        }

        $resolvedDateUpload = $this->resolveDateUpload($dateUpload);

        // Caso especial para ENLACE (No se guarda archivo)
        if ($type === AttachmentType::ENLACE) {
            $attachment = new TransparencyAttachment(
                id: null,
                transparencyId: $transparencyId,
                filePath: $sourcePath, // En este caso sourcePath es la URL
                mimeType: 'text/uri-list',
                attachmentType: $type,
                description: $description,
                dateUpload: $resolvedDateUpload
            );
            return $this->repository->saveAttachment($attachment);
        }

        return $this->transactionManager->transactional(function () use (
            $transparency,
            $transparencyId,
            $sourcePath,
            $originalFilename,
            $mimeType,
            $type,
            $description,
            $resolvedDateUpload
        ) {
            try {
                $savedPath = $this->fileStorage->store($sourcePath, $originalFilename, $transparency->isPrivate);
                $resolvedDescription = $this->resolveFileDescription($description, $originalFilename);
                
                $attachment = new TransparencyAttachment(
                    id: null,
                    transparencyId: $transparencyId,
                    filePath: $savedPath,
                    mimeType: $mimeType,
                    attachmentType: $type,
                    description: $resolvedDescription,
                    dateUpload: $resolvedDateUpload
                );

                return $this->repository->saveAttachment($attachment);
            } catch (RuntimeException $e) {
                throw new RuntimeException("Error al procesar el archivo adjunto: " . $e->getMessage(), 0, $e);
            }
        });
    }

    private function resolveDateUpload(?string $dateUpload): DateTimeImmutable
    {
        $rawDate = $dateUpload !== null ? trim($dateUpload) : '';
        if ($rawDate === '') {
            return new DateTimeImmutable('today');
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $rawDate);
        if ($parsed === false || $parsed->format('Y-m-d') !== $rawDate) {
            throw new InvalidArgumentException('Formato de fecha de publicación del adjunto inválido. Use YYYY-MM-DD.');
        }

        return $parsed;
    }

    private function resolveFileDescription(?string $description, string $originalFilename): ?string
    {
        $normalized = trim((string) $description);
        if ($normalized !== '') {
            return $normalized;
        }

        $fallback = trim(pathinfo($originalFilename, PATHINFO_FILENAME));

        return $fallback !== '' ? $fallback : null;
    }
}
