<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Domain\Service;

use App\Modules\Dashboard\Domain\VO\Alert;
use App\Shared\Context\UserProviderInterface;

interface AlertEvaluatorInterface
{
    /**
     * Evaluate this alert rule for the given user.
     *
     * @return Alert[] Array of violations found (empty if no violations)
     */
    public function evaluate(UserProviderInterface $userProvider): array;
}
