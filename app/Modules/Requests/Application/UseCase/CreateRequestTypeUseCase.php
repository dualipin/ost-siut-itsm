<?php

declare(strict_types=1);

namespace App\Modules\Requests\Application\UseCase;

use App\Modules\Requests\Domain\Entity\RequestType;
use App\Modules\Requests\Domain\Repository\RequestTypeRepositoryInterface;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class CreateRequestTypeUseCase
{
    public function __construct(
        private RequestTypeRepositoryInterface $typeRepository,
    ) {
    }

    public function execute(string $name, ?string $description, bool $active = true): void
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('El nombre del tipo de solicitud no puede estar vacío.');
        }

        $type = new RequestType(
            requestTypeId: 0,
            name:          $name,
            description:   $description !== null ? trim($description) : null,
            active:        $active,
            createdAt:     new DateTimeImmutable(),
        );

        $this->typeRepository->save($type);
    }
}
