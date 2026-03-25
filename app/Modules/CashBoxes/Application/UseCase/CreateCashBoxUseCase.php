<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Application\UseCase;

use App\Modules\CashBoxes\Domain\Entity\CashBox;
use App\Modules\CashBoxes\Domain\Enum\BoxStatusEnum;
use App\Modules\CashBoxes\Domain\Repository\CashBoxRepositoryInterface;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class CreateCashBoxUseCase
{
    public function __construct(
        private CashBoxRepositoryInterface $cashBoxRepository
    ) {
    }

    public function execute(
        int $createdBy,
        string $name,
        ?string $description = null,
        string $currency = 'MXN',
        float $initialBalance = 0.0
    ): void {
        if ($initialBalance < 0) {
            throw new InvalidArgumentException("Initial balance cannot be negative.");
        }

        if ($currency !== 'MXN') {
            throw new InvalidArgumentException("Only MXN currency is allowed.");
        }

        $now = new DateTimeImmutable();

        $box = new CashBox(
            boxId: $this->cashBoxRepository->nextId(),
            createdBy: $createdBy,
            name: $name,
            description: $description,
            currency: $currency,
            initialBalance: $initialBalance,
            currentBalance: $initialBalance,
            status: BoxStatusEnum::OPEN,
            createdAt: $now
        );

        $this->cashBoxRepository->save($box);
    }
}
