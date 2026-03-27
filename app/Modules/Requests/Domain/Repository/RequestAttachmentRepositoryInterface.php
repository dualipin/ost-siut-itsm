<?php

declare(strict_types=1);

namespace App\Modules\Requests\Domain\Repository;

use App\Modules\Requests\Domain\Entity\RequestAttachment;

interface RequestAttachmentRepositoryInterface
{
    public function save(RequestAttachment $attachment): void;

    /**
     * @return RequestAttachment[]
     */
    public function findByRequestId(int $requestId): array;
}
