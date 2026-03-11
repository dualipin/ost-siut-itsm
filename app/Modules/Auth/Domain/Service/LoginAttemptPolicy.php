<?php

namespace App\Modules\Auth\Domain\Service;

use App\Modules\Auth\Domain\Exception\TooManyAttemptsException;
use App\Modules\Auth\Domain\Repository\AuthLogRepositoryInterface;

final readonly class LoginAttemptPolicy
{
    public function __construct(
        private AuthLogRepositoryInterface $logRepository,
    ) {}

    /**
     * @throws TooManyAttemptsException
     */
    public function ensureNotLocked(string $email): void
    {
        if ($this->logRepository->getCountLastFailedAttempts($email, 5) >= 5) {
            throw new TooManyAttemptsException();
        }
    }
}
