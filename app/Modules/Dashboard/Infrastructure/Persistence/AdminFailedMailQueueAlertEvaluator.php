<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Infrastructure\Persistence;

use App\Modules\Dashboard\Domain\Enum\AlertSeverityEnum;
use App\Modules\Dashboard\Domain\Repository\DashboardAdministradorRepositoryInterface;
use App\Modules\Dashboard\Domain\Service\AlertEvaluatorInterface;
use App\Modules\Dashboard\Domain\VO\Alert;
use App\Shared\Context\UserProviderInterface;

final readonly class AdminFailedMailQueueAlertEvaluator implements AlertEvaluatorInterface
{
    public function __construct(
        private DashboardAdministradorRepositoryInterface $adminRepository,
    ) {}

    public function evaluate(UserProviderInterface $userProvider): array
    {
        $alerts = [];
        $failedMailCount = $this->adminRepository->getFailedMailQueueCount();

        if ($failedMailCount > 0) {
            $alerts[] = new Alert(
                id: 'admin_failed_mail',
                title: 'Correos Fallidos en Cola',
                description: "$failedMailCount correo(s) fallaron en envío. Revise la configuración o reintentos.",
                severity: AlertSeverityEnum::Warning,
                actionLabel: 'Ver Cola de Correos',
                actionUrl: '/portal/admin/mail-queue?status=failed',
                affectedCount: $failedMailCount,
            );
        }

        return $alerts;
    }
}
