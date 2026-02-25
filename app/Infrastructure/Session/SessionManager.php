<?php

namespace App\Infrastructure\Session;

final class SessionManager
{
    public function start(): void
    {
        if ($this->isStarted()) {
            return;
        }

        ini_set("session.use_strict_mode", "1");
        ini_set("session.cookie_httponly", "1");
        ini_set("session.cookie_secure", "1");
        ini_set("session.cookie_samesite", "Lax");

        session_start();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->ensureStarted();
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->ensureStarted();
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        $this->ensureStarted();
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        $this->ensureStarted();
        session_regenerate_id(true);
    }

    public function destroy(): void
    {
        $this->ensureStarted();
        $_SESSION = [];
        session_destroy();

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            $oneHourAgo = time() - 3600;
            setcookie(
                session_name(),
                "",
                $oneHourAgo,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"],
            );
        }
    }

    public function isStarted(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    private function ensureStarted(): void
    {
        if (!$this->isStarted()) {
            throw new \RuntimeException("Session has not been started.");
        }
    }
}
