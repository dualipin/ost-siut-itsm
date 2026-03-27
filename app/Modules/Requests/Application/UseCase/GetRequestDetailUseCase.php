<?php

declare(strict_types=1);

namespace App\Modules\Requests\Application\UseCase;

use App\Modules\Requests\Domain\Repository\RequestRepositoryInterface;
use App\Modules\Requests\Domain\Repository\RequestAttachmentRepositoryInterface;
use App\Modules\Requests\Domain\Exception\RequestNotFoundException;

final readonly class GetRequestDetailUseCase
{
    public function __construct(
        private RequestRepositoryInterface $requestRepository,
        private RequestAttachmentRepositoryInterface $attachmentRepository,
    ) {
    }

    /**
     * @throws RequestNotFoundException
     */
    public function execute(int $requestId): array
    {
        $request     = $this->requestRepository->findById($requestId);
        $attachments = $this->attachmentRepository->findByRequestId($requestId);

        return [
            'request'     => $request,
            'attachments' => $attachments,
        ];
    }
}
