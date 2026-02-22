<?php

namespace App\Shared\Context;

use App\Infrastructure\Session\SessionManager;

final readonly class UserContext implements ContextInterface
{
    private const string Key = "user";
    public function __construct(private SessionManager $manager) {}

    public function get(): int
    {
        return $this->manager->get(self::Key);
    }

    public function set($value): void
    {
        $this->manager->set(self::Key, $value);
    }

    public function clear(): void
    {
        $this->manager->remove(self::Key);
    }
}
