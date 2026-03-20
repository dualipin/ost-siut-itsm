<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Infrastructure\Persistence;

use App\Modules\Dashboard\Domain\Enum\AlertSeverityEnum;
use App\Modules\Dashboard\Domain\Repository\DashboardAdministradorRepositoryInterface;
use App\Modules\Dashboard\Domain\Service\AlertEvaluatorInterface;
use App\Modules\Dashboard\Domain\VO\Alert;
use App\Shared\Context\UserProviderInterface;

final readonly class AdminUnattendedMessagesAlertEvaluator implements AlertEvaluatorInterface
{
    public function __construct(
        private DashboardAdministradorRepositoryInterface $adminRepository,
    ) {}

    public function evaluate(UserProviderInterface $userProvider): array
    {
        $alerts = [];
        $openMessages = $this->adminRepository->getOpenMessageThreads();

        if (count($openMessages) > 0) {
            // Critical if any message has been unattended > 24 hours
            $criticalCount = count(array_filter($openMessages, fn($m) => $m['hours_elapsed'] > 24));

            $alerts[] = new Alert(
                id: 'admin_unattended_messages',
                title: 'Mensajes sin Atender',
                description: count($openMessages) . " hilo(s) de mensajes sin atender. $criticalCount llevan más de 24 horas.",
                severity: $criticalCount > 0 ? AlertSeverityEnum::Critical : AlertSeverityEnum::Warning,
                actionLabel: 'Bandeja de Entrada',
                actionUrl: '/portal/admin/mensajes',
                affectedCount: count($openMessages),
            );
        }

        return $alerts;
    }
}
