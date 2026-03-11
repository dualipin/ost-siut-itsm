# Módulo Auth — Espejo completo (actualizado 2026-03-08)

Este documento contiene un espejo fiel de todos los archivos bajo `app/Modules/Auth` tal como existen en el proyecto.

## Árbol de archivos

- app/Modules/Auth/
  - AuthModule.php
  - Application/
    - Service/
      - AuthEventLogger.php
    - UseCase/
      - ChangePasswordWithTokenUseCase.php
      - LoginUseCase.php
      - LogoutUseCase.php
      - PasswordResetUseCase.php
      - RecoverPasswordWithMagicLinkUseCase.php
  - Domain/
    - Service/
      - CredentialVerifier.php
      - LoginAttemptPolicy.php
      - MagicLinkTokenPolicy.php
      - PasswordRecoveryNotifierInterface.php
    - Repository/
      - AuthLogRepositoryInterface.php
      - CredentialRepositoryInterface.php
      - PasswordRecoveryInterface.php
    - Entity/
      - AuthLog.php
      - UserCredential.php
    - Enum/
      - RoleEnum.php
      - AuthLogActionEnum.php
    - Exception/
      - TooManyAttemptsException.php
  - Infrastructure/
    - Mail/
      - PasswordRecoveryMailer.php
    - Persistence/
      - PdoAuthLogRepository.php
      - PdoCredentialRepository.php
      - PdoPasswordRecoveryRepository.php

## Contenido de archivos

---

### app/Modules/Auth/AuthModule.php

```php
<?php

namespace App\Modules\Auth;

use App\Modules\AbstractModule;
use App\Modules\Auth\Application\Service\AuthEventLogger;
use App\Modules\Auth\Application\UseCase\ChangePasswordWithTokenUseCase;
use App\Modules\Auth\Application\UseCase\LoginUseCase;
use App\Modules\Auth\Application\UseCase\LogoutUseCase;
use App\Modules\Auth\Application\UseCase\PasswordResetUseCase;
use App\Modules\Auth\Application\UseCase\RecoverPasswordWithMagicLinkUseCase;
use App\Modules\Auth\Domain\Repository\AuthLogRepositoryInterface;
use App\Modules\Auth\Domain\Repository\CredentialRepositoryInterface;
use App\Modules\Auth\Domain\Repository\PasswordRecoveryInterface;
use App\Modules\Auth\Domain\Service\CredentialVerifier;
use App\Modules\Auth\Domain\Service\LoginAttemptPolicy;
use App\Modules\Auth\Domain\Service\MagicLinkTokenPolicy;
use App\Modules\Auth\Domain\Service\PasswordRecoveryNotifierInterface;
use App\Modules\Auth\Infrastructure\Persistence\PdoAuthLogRepository;
use App\Modules\Auth\Infrastructure\Persistence\PdoCredentialRepository;
use App\Modules\Auth\Infrastructure\Persistence\PdoPasswordRecoveryRepository;

final class AuthModule extends AbstractModule
{
    protected const array REPOSITORIES = [
        AuthLogRepositoryInterface::class => PdoAuthLogRepository::class,
        CredentialRepositoryInterface::class => PdoCredentialRepository::class,
        PasswordRecoveryInterface::class => PdoPasswordRecoveryRepository::class,
    ];

    protected const array SERVICES = [
        AuthEventLogger::class,
        CredentialVerifier::class,
        LoginAttemptPolicy::class,
        MagicLinkTokenPolicy::class,
    ];

    protected const array USE_CASES = [
        ChangePasswordWithTokenUseCase::class,
        LoginUseCase::class,
        LogoutUseCase::class,
        PasswordResetUseCase::class,
        RecoverPasswordWithMagicLinkUseCase::class,
    ];
}

```

---

### app/Modules/Auth/Infrastructure/Persistence/PdoAuthLogRepository.php

```php
<?php

namespace App\Modules\Auth\Infrastructure\Persistence;

use App\Infrastructure\Persistence\Repository\PdoBaseRepository;
use App\Modules\Auth\Domain\Entity\AuthLog;
use App\Modules\Auth\Domain\Repository\AuthLogRepositoryInterface;

final class PdoAuthLogRepository extends PdoBaseRepository implements
    AuthLogRepositoryInterface
{
    public function saveAuthLog(AuthLog $log): void
    {
        $stmt = $this->pdo->prepare("insert into auth_logs 
            (user_id, email, action, ip_address, user_agent, error_message, success) 
            VALUES (:uid, :email, :action, :ip, :ua, :error, :success)");
        $stmt->execute([
            "uid" => $log->userId,
            "email" => $log->email,
            "action" => $log->action->value,
            "ip" => $log->ipAddress,
            "ua" => $log->userAgent,
            "error" => $log->errorMessage,
            "success" => (int) $log->success,
        ]);
    }
    public function updateLastLogin(int $userId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE users SET last_entry = NOW() WHERE user_id = :id",
        );
        $stmt->bindParam(":id", $userId);
        $stmt->execute();
    }

    public function getCountLastFailedAttempts(
        string $email,
        int $minutes = 15,
    ): int {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM auth_logs 
              WHERE email = :email AND success = false AND created_at > (NOW() - INTERVAL :interval MINUTE)",
        );
        $stmt->execute([
            "email" => $email,
            "interval" => $minutes,
        ]);

        return (int) $stmt->fetchColumn();
    }
}

```

---

### app/Modules/Auth/Infrastructure/Mail/PasswordRecoveryMailer.php

```php
<?php

namespace App\Modules\Auth\Infrastructure\Mail;

use App\Infrastructure\Mail\MailerInterface;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Auth\Domain\Service\PasswordRecoveryNotifierInterface;

final readonly class PasswordRecoveryMailer implements
    PasswordRecoveryNotifierInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private RendererInterface $renderer,
        private string $templateBasePath,
    ) {}

    public function sendMagicLink(string $email, string $magicLink): void
    {
        $subject = "Recuperación de contraseña";
        $templatePath = $this->templateBasePath . "/templates/emails/reset-password.latte";
        $body = $this->renderer->renderToString(
            $templatePath,
            [
                "link" => $magicLink,
                "year" => date('Y'),
            ],
        );
        $altBody = "Abre este enlace para recuperar tu contraseña: {$magicLink}";

        $this->mailer->send([$email], $subject, $body, $altBody);
    }
}

```

---

### app/Modules/Auth/Infrastructure/Persistence/PdoPasswordRecoveryRepository.php

```php
<?php

namespace App\Modules\Auth\Infrastructure\Persistence;

use App\Infrastructure\Persistence\Repository\PdoBaseRepository;
use App\Modules\Auth\Domain\Repository\PasswordRecoveryInterface;

final class PdoPasswordRecoveryRepository extends PdoBaseRepository implements
    PasswordRecoveryInterface
{
    public function storeMagicLink(string $email, string $token): void
    {
        $deleteStmt = $this->pdo->prepare(
            "DELETE FROM password_resets WHERE email = :email",
        );
        $deleteStmt->execute(["email" => $email]);

        $insertStmt = $this->pdo->prepare(
            "INSERT INTO password_resets (email, token, created_at) VALUES (:email, UNHEX(:token), NOW())",
        );
        $insertStmt->execute([
            "email" => $email,
            "token" => $token,
        ]);
    }

    public function findEmailByValidToken(
        string $token,
        int $ttlMinutes,
    ): ?string {
        $stmt = $this->pdo->prepare(
            "SELECT email FROM password_resets WHERE token = UNHEX(:token) AND TIMESTAMPDIFF(MINUTE, created_at, NOW()) <= :ttl LIMIT 1",
        );
        $stmt->bindValue(":token", $token);
        $stmt->bindValue(":ttl", $ttlMinutes, \PDO::PARAM_INT);
        $stmt->execute();

        $email = $stmt->fetchColumn();

        return is_string($email) ? $email : null;
    }

    public function consumeToken(string $token): void
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM password_resets WHERE token = UNHEX(:token)",
        );
        $stmt->execute(["token" => $token]);
    }
}

```

---

### app/Modules/Auth/Infrastructure/Persistence/PdoCredentialRepository.php

```php
<?php

namespace App\Modules\Auth\Infrastructure\Persistence;

use App\Infrastructure\Persistence\Repository\PdoBaseRepository;use App\Modules\Auth\Domain\Entity\UserCredential;use App\Modules\Auth\Domain\Repository\CredentialRepositoryInterface;use App\Shared\Domain\Enum\RoleEnum;

class PdoCredentialRepository extends PdoBaseRepository implements
    CredentialRepositoryInterface
{
    public function findByEmail(string $email): ?UserCredential
    {
        $stmt = $this->pdo->prepare("
        SELECT user_id, email, password_hash, active, role FROM users WHERE email = :email
        ");

        $stmt->execute(["email" => $email]);

        $result = $stmt->fetch();

        if (!$result) {
            return null;
        }

        return new UserCredential(
            id: $result["user_id"],
            email: $result["email"],
            passwordHash: $result["password_hash"],
            role: RoleEnum::tryFrom($result["role"]) ?? RoleEnum::NoAgremiado,
            isActive: (bool) $result["active"],
        );
    }

    public function updatePassword(int $userId, string $passwordHash): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE users SET password_hash = :password WHERE user_id = :id",
        );
        $stmt->execute([
            ":password" => $passwordHash,
            ":id" => $userId,
        ]);
    }
}

```

---

### app/Modules/Auth/Application/Service/AuthEventLogger.php

```php
<?php

namespace App\Modules\Auth\Application\Service;

use App\Modules\Auth\Domain\Entity\AuthLog;
use App\Modules\Auth\Domain\Enum\AuthLogActionEnum;
use App\Modules\Auth\Domain\Repository\AuthLogRepositoryInterface;

final readonly class AuthEventLogger
{
    public function __construct(
        private AuthLogRepositoryInterface $authLogRepository,
    ) {}

    public function successLoginAttempt(
        string $email,
        string $ipAddress,
        string $userAgent,
        int $userId,
    ): void {
        $this->authLogRepository->saveAuthLog(
            new AuthLog(
                action: AuthLogActionEnum::LoginAttempt,
                success: true,
                userId: $userId,
                email: $email,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            ),
        );

        $this->authLogRepository->updateLastLogin($userId);
    }

    public function failedLoginAttempt(
        string $email,
        ?string $ipAddress,
        ?string $userAgent,
        ?string $errorMessage = null,
    ): void {
        $this->authLogRepository->saveAuthLog(
            new AuthLog(
                action: AuthLogActionEnum::LoginAttempt,
                success: false,
                email: $email,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                errorMessage: $errorMessage,
            ),
        );
    }

    public function logoutSuccess(
        int $userId,
        string $email,
        ?string $ipAddress,
        ?string $userAgent,
    ): void {
        $this->authLogRepository->saveAuthLog(
            new AuthLog(
                action: AuthLogActionEnum::Logout,
                success: true,
                userId: $userId,
                email: $email,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            ),
        );
    }

    public function logoutFailed(?string $ipAddress, ?string $userAgent): void
    {
        $this->authLogRepository->saveAuthLog(
            new AuthLog(
                action: AuthLogActionEnum::Logout,
                success: false,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                errorMessage: "intento fallido de cierre de sesión",
            ),
        );
    }

    public function passwordResetRequested(
        string $email,
        ?string $ipAddress,
        ?string $userAgent,
    ): void {
        $this->authLogRepository->saveAuthLog(
            new AuthLog(
                action: AuthLogActionEnum::PasswordReset,
                success: true,
                email: $email,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            ),
        );
    }

    public function passwordResetSuccess(
        int $userId,
        string $email,
        ?string $ipAddress,
        ?string $userAgent,
    ): void {
        $this->authLogRepository->saveAuthLog(
            new AuthLog(
                action: AuthLogActionEnum::PasswordReset,
                success: true,
                userId: $userId,
                email: $email,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            ),
        );

        $this->authLogRepository->updateLastLogin($userId);
    }

    public function passwordResetFailed(
        string $email,
        ?string $ipAddress,
        ?string $userAgent,
        string $errorMessage,
    ): void {
        $this->authLogRepository->saveAuthLog(
            new AuthLog(
                action: AuthLogActionEnum::PasswordReset,
                success: false,
                email: $email,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                errorMessage: $errorMessage,
            ),
        );
    }
}

```

---

### app/Modules/Auth/Application/UseCase/RecoverPasswordWithMagicLinkUseCase.php

```php
<?php

namespace App\Modules\Auth\Application\UseCase;

use App\Modules\Auth\Application\Service\AuthEventLogger;
use App\Modules\Auth\Domain\Repository\CredentialRepositoryInterface;
use App\Modules\Auth\Domain\Repository\PasswordRecoveryInterface;
use App\Modules\Auth\Domain\Service\MagicLinkTokenPolicy;
use App\Shared\Context\UserContextInterface;
use App\Shared\Security\AuthenticatedUser;

final readonly class RecoverPasswordWithMagicLinkUseCase
{
    private const int MagicLinkTtlMinutes = 15;

    public function __construct(
        private PasswordRecoveryInterface $passwordRecoveryRepository,
        private CredentialRepositoryInterface $credentialRepository,
        private MagicLinkTokenPolicy $magicLinkTokenPolicy,
        private UserContextInterface $userContext,
        private AuthEventLogger $authEventLogger,
    ) {}

    public function execute(
        string $token,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): bool {
        if (!$this->magicLinkTokenPolicy->isValid($token)) {
            return false;
        }

        $email = $this->passwordRecoveryRepository->findEmailByValidToken(
            token: $token,
            ttlMinutes: self::MagicLinkTtlMinutes,
        );

        if (!$email) {
            $this->authEventLogger->passwordResetFailed(
                email: "",
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                errorMessage: "Token inválido o expirado",
            );

            return false;
        }

        $credential = $this->credentialRepository->findByEmail($email);

        if (!$credential) {
            $this->passwordRecoveryRepository->consumeToken($token);

            $this->authEventLogger->passwordResetFailed(
                email: $email,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                errorMessage: "Usuario no encontrado",
            );

            return false;
        }

        $this->passwordRecoveryRepository->consumeToken($token);

        $this->userContext->set(
            new AuthenticatedUser(
                id: $credential->id,
                email: $credential->email,
                role: $credential->role,
            ),
        );

        $this->authEventLogger->passwordResetSuccess(
            userId: $credential->id,
            email: $credential->email,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
        );

        return true;
    }
}

```

---

### app/Modules/Auth/Application/UseCase/PasswordResetUseCase.php

```php
<?php

namespace App\Modules\Auth\Application\UseCase;

use App\Modules\Auth\Application\Service\AuthEventLogger;
use App\Modules\Auth\Domain\Repository\CredentialRepositoryInterface;
use App\Modules\Auth\Domain\Repository\PasswordRecoveryInterface;
use App\Modules\Auth\Domain\Service\MagicLinkTokenPolicy;
use App\Modules\Auth\Domain\Service\PasswordRecoveryNotifierInterface;
use App\Shared\Utils\UrlBuilder;

final readonly class PasswordResetUseCase
{
    public function __construct(
        private CredentialRepositoryInterface $credentialRepository,
        private PasswordRecoveryInterface $passwordRecoveryRepository,
        private PasswordRecoveryNotifierInterface $passwordRecoveryNotifier,
        private MagicLinkTokenPolicy $magicLinkTokenPolicy,
        private UrlBuilder $urlBuilder,
        private AuthEventLogger $authEventLogger,
    ) {}

    public function execute(
        string $email,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): void {
        $credential = $this->credentialRepository->findByEmail($email);

        if (!$credential) {
            return;
        }

        $token = $this->magicLinkTokenPolicy->generate();

        $this->passwordRecoveryRepository->storeMagicLink($email, $token);

        $magicLink = $this->urlBuilder->to("/cuentas/recuperar-contra.php", [
            "token" => $token,
        ]);

        $this->passwordRecoveryNotifier->sendMagicLink($email, $magicLink);

        $this->authEventLogger->passwordResetRequested(
            email: $email,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
        );
    }
}

```

---

### app/Modules/Auth/Application/UseCase/LogoutUseCase.php

```php
<?php

namespace App\Modules\Auth\Application\UseCase;

use App\Infrastructure\Session\SessionInterface;
use App\Modules\Auth\Application\Service\AuthEventLogger;
use App\Shared\Context\UserContextInterface;

final readonly class LogoutUseCase
{
    public function __construct(
        private AuthEventLogger $authEventLogger,
        private UserContextInterface $userContext,
        private SessionInterface $session,
    ) {}

    public function execute(
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): void {
        $user = $this->userContext->get();

        if (!$user) {
            return;
        }

        $this->session->destroy();

        $this->authEventLogger->logoutSuccess(
            userId: $user->id,
            email: $user->email,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
        );
    }
}

```

---

### app/Modules/Auth/Application/UseCase/LoginUseCase.php

```php
<?php

namespace App\Modules\Auth\Application\UseCase;

use App\Modules\Auth\Application\Service\AuthEventLogger;
use App\Modules\Auth\Domain\Exception\TooManyAttemptsException;
use App\Modules\Auth\Domain\Repository\CredentialRepositoryInterface;
use App\Modules\Auth\Domain\Service\CredentialVerifier;
use App\Modules\Auth\Domain\Service\LoginAttemptPolicy;
use App\Shared\Context\UserContextInterface;
use App\Shared\Security\AuthenticatedUser;

final readonly class LoginUseCase
{
    public function __construct(
        private CredentialRepositoryInterface $credentialRepository,
        private CredentialVerifier $credentialVerifier,
        private LoginAttemptPolicy $loginAttemptPolicy,
        private UserContextInterface $userContext,
        private AuthEventLogger $authEventLogger,
    ) {}

    /**
     * @throws TooManyAttemptsException
     */
    public function execute(
        string $email,
        string $password,
        string $ipAddress,
        string $userAgent,
    ): bool {
        $this->loginAttemptPolicy->ensureNotLocked($email);

        $credential = $this->credentialRepository->findByEmail($email);

        if (!$this->credentialVerifier->verify($credential, $password)) {
            $this->authEventLogger->failedLoginAttempt(
                email: $email,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                errorMessage: "Intento de inicio de sesión fallido",
            );

            return false;
        }

        $this->authEventLogger->successLoginAttempt(
            email: $email,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            userId: $credential->id,
        );

        $this->userContext->set(
            new AuthenticatedUser(
                id: $credential->id,
                email: $credential->email,
                role: $credential->role,
            ),
        );

        return true;
    }
}

```

---

### app/Modules/Auth/Application/UseCase/ChangePasswordWithTokenUseCase.php

```php
<?php

namespace App\Modules\Auth\Application\UseCase;

use App\Infrastructure\Persistence\TransactionManager;
use App\Modules\Auth\Application\Service\AuthEventLogger;
use App\Modules\Auth\Domain\Repository\CredentialRepositoryInterface;
use App\Modules\Auth\Domain\Repository\PasswordRecoveryInterface;
use App\Modules\Auth\Domain\Service\MagicLinkTokenPolicy;
use App\Shared\Context\UserContextInterface;
use App\Shared\Security\AuthenticatedUser;

final readonly class ChangePasswordWithTokenUseCase
{
    private const int MagicLinkTtlMinutes = 15;

    public function __construct(
        private PasswordRecoveryInterface $passwordRecoveryRepository,
        private CredentialRepositoryInterface $credentialRepository,
        private MagicLinkTokenPolicy $magicLinkTokenPolicy,
        private UserContextInterface $userContext,
        private AuthEventLogger $authEventLogger,
        private TransactionManager $transactionManager,
    ) {}

    public function execute(
        string $token,
        string $newPassword,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): bool {
        if (!$this->magicLinkTokenPolicy->isValid($token)) {
            return false;
        }

        $email = $this->passwordRecoveryRepository->findEmailByValidToken(
            token: $token,
            ttlMinutes: self::MagicLinkTtlMinutes,
        );

        if (!$email) {
            $this->authEventLogger->passwordResetFailed(
                email: "",
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                errorMessage: "Token inválido o expirado",
            );

            return false;
        }

        $credential = $this->credentialRepository->findByEmail($email);

        if (!$credential) {
            $this->passwordRecoveryRepository->consumeToken($token);

            $this->authEventLogger->passwordResetFailed(
                email: $email,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                errorMessage: "Usuario no encontrado",
            );

            return false;
        }

        // Hash the new password
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

        // Update password and consume token atomically
        $this->transactionManager->transactional(function () use ($credential, $passwordHash, $token) {
            $this->credentialRepository->updatePassword($credential->id, $passwordHash);
            $this->passwordRecoveryRepository->consumeToken($token);
        });

        // Authenticate the user
        $this->userContext->set(
            new AuthenticatedUser(
                id: $credential->id,
                email: $credential->email,
                role: $credential->role,
            ),
        );

        $this->authEventLogger->passwordResetSuccess(
            userId: $credential->id,
            email: $credential->email,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
        );

        return true;
    }

    public function validateToken(string $token): ?string
    {
        if (!$this->magicLinkTokenPolicy->isValid($token)) {
            return null;
        }

        return $this->passwordRecoveryRepository->findEmailByValidToken(
            token: $token,
            ttlMinutes: self::MagicLinkTtlMinutes,
        );
    }
}

```

---

### app/Modules/Auth/Domain/Service/PasswordRecoveryNotifierInterface.php

```php
<?php

namespace App\Modules\Auth\Domain\Service;

interface PasswordRecoveryNotifierInterface
{
    public function sendMagicLink(string $email, string $magicLink): void;
}

```

---

### app/Modules/Auth/Domain/Repository/AuthLogRepositoryInterface.php

```php
<?php

namespace App\Modules\Auth\Domain\Repository;

use App\Modules\Auth\Domain\Entity\AuthLog;

interface AuthLogRepositoryInterface
{
    public function saveAuthLog(AuthLog $log): void;
    public function updateLastLogin(int $userId): void;
    public function getCountLastFailedAttempts(
        string $email,
        int $minutes = 15,
    ): int;
}

```

---

### app/Modules/Auth/Domain/Service/MagicLinkTokenPolicy.php

```php
<?php

namespace App\Modules\Auth\Domain\Service;

use Ramsey\Uuid\Uuid;

use function strlen;
use function str_replace;

final class MagicLinkTokenPolicy
{
    private const int TokenHexLength = 32;

    public function generate(): string
    {
        return str_replace("-", "", Uuid::uuid4()->toString());
    }

    public function isValid(string $token): bool
    {
        return ctype_xdigit($token) && strlen($token) === self::TokenHexLength;
    }
}

```

---

### app/Modules/Auth/Domain/Service/LoginAttemptPolicy.php

```php
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

```

---

### app/Modules/Auth/Domain/Service/CredentialVerifier.php

```php
<?php

namespace App\Modules\Auth\Domain\Service;

use App\Modules\Auth\Domain\Entity\UserCredential;

final readonly class CredentialVerifier
{
    private const string DummyHash = '$2y$10$invalid.hash.to.prevent.timing.attacks';

    public function verify(?UserCredential $user, string $password): bool
    {
        if (!$user) {
            password_verify($password, self::DummyHash);
            return false;
        }

        return $user->verifyPassword($password);
    }
}

```

---

### app/Modules/Auth/Domain/Repository/CredentialRepositoryInterface.php

```php
<?php

namespace App\Modules\Auth\Domain\Repository;

use App\Modules\Auth\Domain\Entity\UserCredential;

interface CredentialRepositoryInterface
{
    public function findByEmail(string $email): ?UserCredential;

    public function updatePassword(int $userId, string $passwordHash): void;
}

```

---

### app/Modules/Auth/Domain/Entity/UserCredential.php

```php
<?php

namespace App\Modules\Auth\Domain\Entity;

use App\Shared\Domain\Enum\RoleEnum;

final readonly class UserCredential
{
    public function __construct(
        public int $id,
        public string $email,
        public string $passwordHash,
        public RoleEnum $role,
        public bool $isActive,
    ) {}

    public function verifyPassword(string $password): bool
    {
        if (!$this->isActive) {
            return false;
        }
        return password_verify($password, $this->passwordHash);
    }
}

```

---

### app/Modules/Auth/Domain/Enum/RoleEnum.php

```php
<?php

namespace App\Modules\Auth\Domain\Enum;

enum RoleEnum: string
{
    case Lider = "lider";
    case Admin = "administrador";
    case Agremiado = "agremiado";
    case NoAgremiado = "no_agremiado";
    case Finanzas = "finanzas";
}

```

---

### app/Modules/Auth/Domain/Repository/PasswordRecoveryInterface.php

```php
<?php

namespace App\Modules\Auth\Domain\Repository;

interface PasswordRecoveryInterface
{
	public function storeMagicLink(string $email, string $token): void;

	public function findEmailByValidToken(
		string $token,
		int $ttlMinutes,
	): ?string;

	public function consumeToken(string $token): void;
}

```

---

### app/Modules/Auth/Domain/Entity/AuthLog.php

```php
<?php

namespace App\Modules\Auth\Domain\Entity;

use App\Modules\Auth\Domain\Enum\AuthLogActionEnum;
use DateTimeImmutable;

final readonly class AuthLog
{
    public function __construct(
        public AuthLogActionEnum $action,
        public bool $success = false,
        public ?int $id = null,
        public ?int $userId = null,
        public ?string $email = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public ?string $errorMessage = null,
        public ?DateTimeImmutable $createdAt = null,
    ) {}
}

```

---

### app/Modules/Auth/Domain/Enum/AuthLogActionEnum.php

```php
<?php

namespace App\Modules\Auth\Domain\Enum;

enum AuthLogActionEnum: string
{
    case LoginAttempt = "login_attempt";
    case Logout = "logout";
    case PasswordReset = "password_reset";
}

```

---

### app/Modules/Auth/Domain/Exception/TooManyAttemptsException.php

```php
<?php

namespace App\Modules\Auth\Domain\Exception;

use Exception;

class TooManyAttemptsException extends Exception
{
    public function __construct()
    {
        parent::__construct("Demasiados intentos fallidos. Intenta más tarde");
    }
}

```

---

### Fin del espejo del módulo Auth

Este archivo fue generado automáticamente como un espejo de `app/Modules/Auth` el 2026-03-08.
