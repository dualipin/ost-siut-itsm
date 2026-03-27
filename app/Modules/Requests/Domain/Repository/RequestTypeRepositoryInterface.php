<?php

declare(strict_types=1);

namespace App\Modules\Requests\Domain\Repository;

use App\Modules\Requests\Domain\Entity\RequestType;

interface RequestTypeRepositoryInterface
{
    /**
     * @return RequestType[]
     */
    public function findAll(): array;

    /**
     * @return RequestType[]
     */
    public function findActive(): array;

    public function save(RequestType $type): void;

    public function findById(int $id): ?RequestType;
}
