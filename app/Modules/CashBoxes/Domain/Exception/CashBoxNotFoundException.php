<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Domain\Exception;

use DomainException;

final class CashBoxNotFoundException extends DomainException
{
    public function __construct(int $boxId)
    {
        parent::__construct(sprintf("Cash box with ID %d not found.", $boxId));
    }
}
