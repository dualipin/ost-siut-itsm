<?php

namespace App\Module\Auth\Service;

use App\Http\Exception\TooManyAttemptsException;
use App\Infrastructure\Session\SessionManager;
use App\Module\Auth\DTO\AuthLogDTO;
use App\Module\Auth\DTO\UserAuthDTO;
use App\Module\Auth\Enum\AuthLogActionEnum;
use App\Module\Auth\Repository\AuthenticationRepository;
use App\Module\Usuario\Repository\UsuarioRepository;

/**
 * Servicio de autenticación
 */
final class AuthenticationService
{
    private ?UserAuthDTO $currentUser = null;

    public function __construct(
        private readonly UsuarioRepository $userRepo,
        private readonly AuthenticationRepository $authRepo,
        private readonly SessionManager $sessionManager,
    ) {
        // Cargar usuario desde sesión si existe
        $this->loadUserFromSession();
    }

    /**
     * Carga el usuario actualmente autenticado desde la sesión
     */
    private function loadUserFromSession(): void
    {
        $userId = $this->sessionManager->get("user_id");
        $userEmail = $this->sessionManager->get("user_email");

        if ($userId && $userEmail) {
            $user = $this->userRepo->findAuthByEmail($userEmail);
            if ($user && $user->active) {
                $this->currentUser = $user;
            }
        }
    }

    /**
     * Auténtica un usuario con email y contraseña
     * @throws TooManyAttemptsException
     */
    public function authenticate(
        string $email,
        string $password,
        string $ipAddress = null,
        string $userAgent = null,
    ): bool {
        // Validación de entrada
        $email = trim($email);
        $password = trim($password);

        if (empty($email) || empty($password)) {
            $this->authRepo->saveAuthLog(
                new AuthLogDTO(
                    action: AuthLogActionEnum::LoginAttempt,
                    success: false,
                    email: $email,
                    ipAddress: $ipAddress,
                    userAgent: $userAgent,
                    errorMessage: "Email o contraseña vacíos",
                ),
            );
            return false;
        }

        // Validar formato de email
        if (!$this->isValidEmail($email)) {
            $this->authRepo->saveAuthLog(
                new AuthLogDTO(
                    action: AuthLogActionEnum::LoginAttempt,
                    success: false,
                    email: $email,
                    ipAddress: $ipAddress,
                    userAgent: $userAgent,
                    errorMessage: "Email inválido",
                ),
            );
            return false;
        }

        // Validar longitud de contraseña
        if (strlen($password) < 3 || strlen($password) > 255) {
            $this->authRepo->saveAuthLog(
                new AuthLogDTO(
                    action: AuthLogActionEnum::LoginAttempt,
                    success: false,
                    email: $email,
                    ipAddress: $ipAddress,
                    userAgent: $userAgent,
                    errorMessage: "Contraseña con longitud inválida",
                ),
            );
            return false;
        }

        // Verificar si la cuenta está bloqueada (rate limiting)
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
        $genericError = "Credenciales inválidas"; // Mismo mensaje para ambos casos (protección contra timing attack)

        if (!$user || !$user->active) {
            // Usar password_verify de todas formas para gastar tiempo (constante timing)
            password_verify(
                $password,
                '$2y$10$invalid.hash.to.prevent.timing.attacks',
            );

            $this->authRepo->saveAuthLog(
                new AuthLogDTO(
                    action: AuthLogActionEnum::LoginAttempt,
                    success: false,
                    email: $email,
                    ipAddress: $ipAddress,
                    userAgent: $userAgent,
                    errorMessage: "Usuario no encontrado o inactivo",
                ),
            );
            return false;
        }

        if (!password_verify($password, $user->passwordHash)) {
            $this->authRepo->saveAuthLog(
                new AuthLogDTO(
                    action: AuthLogActionEnum::LoginAttempt,
                    success: false,
                    usuarioId: (int) $user->id,
                    email: $email,
                    ipAddress: $ipAddress,
                    userAgent: $userAgent,
                    errorMessage: "Contraseña incorrecta",
                ),
            );
            return false;
        }

        // Actualizar último login
        $this->authRepo->updateLastLogin($user->id);
        $this->currentUser = $user;
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
     * Registra un nuevo usuario
     */
    public function register(
        string $email,
        string $password,
        string $nombre,
        string $apellidos,
    ): int {
        // Esto está aquí como placeholder - implementar según necesidades
        throw new \Exception("Registro de usuario no implementado aún");
    }

    /**
     * Obtiene el usuario actualmente autenticado
     */
    public function getCurrentUser(): ?UserAuthDTO
    {
        return $this->currentUser;
    }

    /**
     * Verifica si hay un usuario autenticado
     */
    public function isAuthenticated(): bool
    {
        return $this->currentUser !== null;
    }

    /**
     * Cierra la sesión del usuario
     */
    public function logout(): void
    {
        $this->currentUser = null;
    }

    /**
     * Valida que un email sea válido
     */
    public function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Verifica si un email ya está registrado
     */
    public function emailExists(string $email): bool
    {
        return $this->userRepo->findAuthByEmail($email) !== null;
    }
}
