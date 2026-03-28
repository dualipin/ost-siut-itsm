<?php

declare(strict_types=1);

namespace App\Modules\Requests\Application\UseCase;

use App\Modules\Requests\Domain\Entity\RequestType;
use App\Modules\Requests\Domain\Exception\RequestTypeNotFoundException;
use App\Modules\Requests\Domain\Repository\RequestTypeRepositoryInterface;
use InvalidArgumentException;

final readonly class UpdateRequestTypeUseCase
{
    public function __construct(
        private RequestTypeRepositoryInterface $typeRepository,
    ) {
    }

    public function execute(int $id, string $name, ?string $description, bool $active): void
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('El nombre del tipo de solicitud no puede estar vacío.');
        }

        $existing = $this->typeRepository->findById($id);
        if ($existing === null) {
            throw new RequestTypeNotFoundException("Tipo de solicitud #{$id} no encontrado.");
        }

        $updated = new RequestType(
            requestTypeId: $existing->requestTypeId,
            name:          $name,
            description:   $description !== null ? trim($description) : null,
            active:        $active,
            createdAt:     $existing->createdAt,
            updatedAt:     $existing->updatedAt,
        );

        $this->typeRepository->save($updated);
    }

    public function toggle(int $id): void
    {
        $existing = $this->typeRepository->findById($id);
        if ($existing === null) {
            throw new RequestTypeNotFoundException("Tipo de solicitud #{$id} no encontrado.");
        }

        $updated = new RequestType(
            requestTypeId: $existing->requestTypeId,
            name:          $existing->name,
            description:   $existing->description,
            active:        !$existing->active,
            createdAt:     $existing->createdAt,
            updatedAt:     $existing->updatedAt,
        );

        $this->typeRepository->save($updated);
    }
}
