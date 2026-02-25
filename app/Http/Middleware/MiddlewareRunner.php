<?php

namespace App\Http\Middleware;

use App\Http\Exception\UnauthorizedException;
use App\Http\Response\Redirector;

final readonly class MiddlewareRunner
{
    public function __construct(private Redirector $redirector) {}

    public function runOrRedirect(
        BaseMiddleware $middleware,
        string $redirectTo = "/cuentas/login.php",
        bool $withRedirectBack = true,
        ?string $currentUri = null,
    ): void {
        try {
            $middleware->execute();
        } catch (UnauthorizedException) {
            $params = [];
            $uri = $currentUri ?? ($_SERVER["REQUEST_URI"] ?? null);

            if ($withRedirectBack && $uri) {
                $params["redirect"] = $uri;
            }

            $this->redirector->to($redirectTo, $params)->send();
        }
    }
}
