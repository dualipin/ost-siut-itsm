<?php

namespace App\Shared\Context;

use App\Infrastructure\Session\SessionManager;

final readonly class UserContext implements ContextInterface
{
    // Previously the key was "user" but the application stores the ID under
    // "user_id" in several places (login, AuthHelper, etc.). Keeping them in
    // sync lets the context reliably tell us whether someone is logged in.
    private const string Key = "user_id";
    public function __construct(private SessionManager $manager) {}

    public function get(): ?int
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
