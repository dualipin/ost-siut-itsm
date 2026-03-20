<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Infrastructure\Persistence;

use App\Modules\Dashboard\Domain\Enum\AlertSeverityEnum;
use App\Modules\Dashboard\Domain\Repository\MiPrestamoRepositoryInterface;
use App\Modules\Dashboard\Domain\Service\AlertEvaluatorInterface;
use App\Modules\Dashboard\Domain\VO\Alert;
use App\Shared\Context\UserProviderInterface;

final readonly class AgremiadoPendingSignaturesAlertEvaluator implements AlertEvaluatorInterface
{
    public function __construct(
        private MiPrestamoRepositoryInterface $miPrestamoRepository,
    ) {}

    public function evaluate(UserProviderInterface $userProvider): array
    {
        $alerts = [];
        $user = $userProvider->get();

        if (!$user) {
            return [];
        }

        $pendingSigs = $this->miPrestamoRepository->getPendingSignaturesForUser($user->id);

        if (count($pendingSigs) > 0) {
            $alerts[] = new Alert(
                id: 'agremiado_pending_signatures',
                title: 'Documentos Pendientes de Firma',
                description: "Tienes " . count($pendingSigs) . " documento(s) que requieren tu firma para continuar con el proceso del préstamo.",
                severity: AlertSeverityEnum::Critical,
                actionLabel: 'Ir a Firmar',
                actionUrl: '/portal/index#pendingSignatures',
                affectedCount: count($pendingSigs),
            );
        }

        return $alerts;
    }
}
