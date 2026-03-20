<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Application\Service;

use App\Modules\Dashboard\Application\DTO\AlertCollection;
use App\Modules\Dashboard\Domain\Service\AlertEvaluatorInterface;
use App\Shared\Context\UserProviderInterface;

final readonly class AlertEvaluationService
{
    /**
     * @param AlertEvaluatorInterface[] $evaluators
     */
    public function __construct(
        private array $evaluators,
    ) {}

    public function evaluate(UserProviderInterface $userProvider): AlertCollection
    {
        $allAlerts = [];

        foreach ($this->evaluators as $evaluator) {
            $alerts = $evaluator->evaluate($userProvider);
            array_push($allAlerts, ...$alerts);
        }

        return new AlertCollection($allAlerts);
    }
}
