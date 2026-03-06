<?php

namespace App\Module\Auth\Service;

use App\Http\Exception\TooManyAttemptsException;
use App\Infrastructure\Session\PhpSession;
use App\Module\Auth\DTO\AuthLogDTO;
use App\Module\Auth\DTO\SessionUserDTO;
use App\Module\Auth\Enum\AuthLogActionEnum;
use App\Module\Auth\Repository\AuthenticationRepository;
use App\Module\Usuario\Repository\UsuarioRepository;
use App\Shared\Context\UserContext;

final readonly class AuthenticationService
{
    public function __construct(
        private UsuarioRepository $userRepo,
        private AuthenticationRepository $authRepo,
        private UserContext $context,
        private PhpSession $session,
    ) {}

    /**
     * Auténtica un usuario con email y contraseña
     * @throws TooManyAttemptsException
     */
    public function authenticate(
        string $email,
        string $password,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): bool {
        if ($this->authRepo->isAccountLocked($email)) {
            $this->authRepo->saveAuthLog(
                new AuthLogDTO(
                    action: AuthLogActionEnum::LoginAttempt,
                    success: false,
                    email: $email,
                    ipAddress: $ipAddress,
                    userAgent: $userAgent,
                    errorMessage: "Cuenta bloqueada por demasiados intentos",
                ),
            );
            throw new TooManyAttemptsException();
        }

        $user = $this->userRepo->findAuthByEmail($email);

        if (
            !$user ||
            !$user->active ||
            !password_verify($password, $user->passwordHash)
        ) {
            // Timing attack prevention cuando el usuario no existe
            if (!$user) {
                password_verify(
                    $password,
                    '$2y$10$invalid.hash.to.prevent.timing.attacks',
                );
            }

            $this->authRepo->saveAuthLog(
                new AuthLogDTO(
                    action: AuthLogActionEnum::LoginAttempt,
                    success: false,
                    email: $email,
                    ipAddress: $ipAddress,
                    userAgent: $userAgent,
                    errorMessage: "Credenciales inválidas",
                ),
            );
            return false;
        }

        // Iniciar y preparar sesión
        $this->session->regenerate();

        $this->context->set(
            new SessionUserDTO(
                id: $user->id,
                email: $user->email,
                rol: $user->rol,
            ),
        );

        $this->authRepo->updateLastLogin($user->id);
        $this->authRepo->saveAuthLog(
            new AuthLogDTO(
                action: AuthLogActionEnum::LoginAttempt,
                success: true,
                usuarioId: (int) $user->id,
                email: $email,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            ),
        );

        return true;
    }

    /**
     * Cierra la sesión del usuario actual
     */
    public function logout(
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): void {
        $user = $this->context->get();

        if ($user) {
            $this->authRepo->saveAuthLog(
                new AuthLogDTO(
                    action: AuthLogActionEnum::Logout,
                    success: true,
                    usuarioId: $user->id,
                    email: $user->email,
                    ipAddress: $ipAddress,
                    userAgent: $userAgent,
                ),
            );
        }

        $this->session->destroy();
    }

    /**
     * Obtiene el usuario autenticado actual desde la sesión
     */
    public function getCurrentUser(): ?SessionUserDTO
    {
        return $this->context->get();
    }

    /**
     * Verifica si hay un usuario autenticado
     */
    public function isAuthenticated(): bool
    {
        return $this->context->isAuthenticated();
    }
}
