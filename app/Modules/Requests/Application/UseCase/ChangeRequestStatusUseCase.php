<?php

declare(strict_types=1);

namespace App\Modules\Requests\Application\UseCase;

use App\Modules\Requests\Domain\Entity\Request;
use App\Modules\Requests\Domain\Enum\RequestStatusEnum;
use App\Modules\Requests\Domain\Exception\RequestNotFoundException;
use App\Modules\Requests\Domain\Repository\RequestRepositoryInterface;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class ChangeRequestStatusUseCase
{
    public function __construct(
        private RequestRepositoryInterface $requestRepository,
    ) {
    }

    /**
     * @throws RequestNotFoundException
     * @throws InvalidArgumentException
     */
    public function execute(
        int $requestId,
        string $newStatusValue,
        int $changedBy,
        bool $isPrivilegedRole,
        ?string $adminNotes = null,
    ): void {
        $request   = $this->requestRepository->findById($requestId);
        $newStatus = RequestStatusEnum::from($newStatusValue);

        // Privileged roles can cancel from any non-terminal state, agremiados only from pendiente
        if ($newStatus === RequestStatusEnum::CANCELADA && !$isPrivilegedRole) {
            if (!$request->isPending()) {
                throw new InvalidArgumentException('Solo puedes cancelar una solicitud mientras está pendiente.');
            }
        }

        if (!$request->canTransitionTo($newStatus)) {
            throw new InvalidArgumentException(
                "No se puede cambiar el estado de '{$request->status->label()}' a '{$newStatus->label()}'."
            );
        }

        $now     = new DateTimeImmutable();
        $oldStatus = $request->status->value;

        $resolvedAt = in_array($newStatus, [
            RequestStatusEnum::APROBADA,
            RequestStatusEnum::RECHAZADA,
            RequestStatusEnum::ENTREGADA,
            RequestStatusEnum::CANCELADA,
        ], true) ? $now : null;

        $updated = new Request(
            requestId:     $request->requestId,
            userId:        $request->userId,
            requestTypeId: $request->requestTypeId,
            folio:         $request->folio,
            reason:        $request->reason,
            status:        $newStatus,
            adminNotes:    $adminNotes ?? $request->adminNotes,
            resolvedBy:    $resolvedAt !== null ? $changedBy : $request->resolvedBy,
            resolvedAt:    $resolvedAt ?? $request->resolvedAt,
            createdAt:     $request->createdAt,
            updatedAt:     $now,
        );

        $this->requestRepository->save($updated);

        $this->requestRepository->saveStatusHistory(
            requestId:  $requestId,
            changedBy:  $changedBy,
            statusFrom: $oldStatus,
            statusTo:   $newStatus->value,
            notes:      $adminNotes,
        );
    }
}
