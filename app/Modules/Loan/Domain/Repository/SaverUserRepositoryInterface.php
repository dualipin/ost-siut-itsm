<?php

namespace App\Modules\Loan\Domain\Repository;

interface SaverUserRepositoryInterface
{
    public function isSaver(int $userId): bool;

    public function addSaver(int $userId): void;

    public function removeSaver(int $userId): void;

    public function findAllActiveSavers(): array;
}
