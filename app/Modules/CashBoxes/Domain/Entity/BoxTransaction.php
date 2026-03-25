<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Domain\Entity;

use App\Modules\CashBoxes\Domain\Enum\TransactionTypeEnum;
use DateTimeImmutable;

final readonly class BoxTransaction
{
    public function __construct(
        public int $transactionId,
        public int $boxId,
        public int $categoryId,
        public ?int $contributorUserId,
        public int $createdBy,
        public TransactionTypeEnum $type,
        public float $amount,
        public float $balanceBefore,
        public float $balanceAfter,
        public DateTimeImmutable $transactionDate,
        public DateTimeImmutable $createdAt,
        public ?string $referenceNumber = null,
        public ?string $description = null,
        public array $attachments = [],
        public ?string $attachment = null,
        public ?DateTimeImmutable $updatedAt = null,
        public ?DateTimeImmutable $deletedAt = null,
    ) {
    }
}
