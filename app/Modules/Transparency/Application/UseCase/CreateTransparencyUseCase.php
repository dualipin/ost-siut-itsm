<?php

declare(strict_types=1);

namespace App\Modules\Transparency\Application\UseCase;

use App\Infrastructure\Persistence\TransactionManager;
use App\Modules\Transparency\Domain\Entity\Transparency;
use App\Modules\Transparency\Domain\Entity\TransparencyAttachment;
use App\Modules\Transparency\Domain\Enum\AttachmentType;
use App\Modules\Transparency\Domain\Enum\TransparencyType;
use App\Modules\Transparency\Domain\Repository\FileStorageInterface;
use App\Modules\Transparency\Domain\Repository\TransparencyRepositoryInterface;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

final readonly class CreateTransparencyUseCase
{
    public function __construct(
        private TransparencyRepositoryInterface $repository,
        private FileStorageInterface $fileStorage,
        private TransactionManager $transactionManager
    ) {
    }

    public function execute(
        int $authorId,
        string $title,
        ?string $summary,
        string $typeValue,
        string $datePublished,
        bool $isPrivate,
        array $files = [],
        array $links = []
    ): Transparency {
        if (trim($title) === '') {
            throw new InvalidArgumentException('El título no puede estar vacío.');
        }

        $type = TransparencyType::tryFrom($typeValue);
        if ($type === null) {
            throw new InvalidArgumentException("Tipo de transparencia inválido: {$typeValue}");
        }

        $publishedAt = DateTimeImmutable::createFromFormat('Y-m-d', $datePublished);
        if (!$publishedAt || $publishedAt->format('Y-m-d') !== $datePublished) {
            throw new InvalidArgumentException('Formato de fecha de publicación inválido. Use YYYY-MM-DD.');
        }

        $transparency = new Transparency(
            id: null,
            authorId: $authorId,
            title: trim($title),
            summary: $summary ? trim($summary) : null,
            type: $type,
            createdAt: new DateTimeImmutable(),
            datePublished: $publishedAt,
            isPrivate: $isPrivate
        );

        return $this->transactionManager->transactional(function () use ($transparency, $files, $links) {
            $savedTransparency = $this->repository->save($transparency);

            foreach ($files as $file) {
                if ($file['error'] !== UPLOAD_ERR_OK) continue;

                $savedPath = $this->fileStorage->store($file['tmp_name'], $file['name'], $transparency->isPrivate);
                
                $attachment = new TransparencyAttachment(
                    id: null,
                    transparencyId: $savedTransparency->id,
                    filePath: $savedPath,
                    mimeType: $file['type'] ?: 'application/octet-stream',
                    attachmentType: AttachmentType::tryFrom($file['attachment_type'] ?? '') ?? AttachmentType::OTRO,
                    description: $file['description'] ?? null
                );
                
                $this->repository->saveAttachment($attachment);
            }

            foreach ($links as $link) {
                if (empty($link['url']) || !filter_var($link['url'], FILTER_VALIDATE_URL)) continue;

                $attachment = new TransparencyAttachment(
                    id: null,
                    transparencyId: $savedTransparency->id,
                    filePath: $link['url'],
                    mimeType: 'text/uri-list',
                    attachmentType: AttachmentType::ENLACE,
                    description: $link['description'] ?? null
                );
                
                $this->repository->saveAttachment($attachment);
            }

            return $savedTransparency;
        });
    }
}
