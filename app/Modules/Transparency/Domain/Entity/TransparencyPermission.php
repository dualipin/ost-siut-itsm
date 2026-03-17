<?php

declare(strict_types=1);

namespace App\Modules\Transparency\Domain\Entity;

final readonly class TransparencyPermission
{
    public function __construct(
        public ?int $id,
        public int $transparencyId,
        public int $userId
    ) {
    }
}
