<?php

declare(strict_types=1);

namespace App\Modules\Transparency\Application\UseCase;

use App\Infrastructure\Persistence\TransactionManager;
use App\Modules\Transparency\Domain\Entity\Transparency;
use App\Modules\Transparency\Domain\Enum\TransparencyType;
use App\Modules\Transparency\Domain\Repository\TransparencyRepositoryInterface;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class CreateTransparencyUseCase
{
    public function __construct(
        private TransparencyRepositoryInterface $repository,
        private TransactionManager $transactionManager
    ) {
    }

    public function execute(
        int $authorId,
        string $title,
        ?string $summary,
        string $typeValue,
        string $datePublished,
        bool $isPrivate
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

        return $this->transactionManager->transactional(function () use ($transparency) {
            return $this->repository->save($transparency);
        });
    }
}
