<?php

declare(strict_types=1);

namespace App\Shared\Utils;

use App\Modules\User\Domain\Entity\User;
use App\Shared\Domain\Enum\RoleEnum;

final class CredentialCardHelper
{
    /**
     * @return array<int, string>
     */
    public static function resolveMissingRequirements(User $user): array
    {
        $missing = [];

        if ($user->role === RoleEnum::NoAgremiado) {
            $missing[] = 'Rol no agremiado';
        }

        if (trim($user->personalInfo->name) === '' || trim($user->personalInfo->surnames) === '') {
            $missing[] = 'Nombre completo';
        }

        if (trim((string) $user->workData->category) === '') {
            $missing[] = 'Cargo';
        }

        if (trim((string) $user->workData->nss) === '') {
            $missing[] = 'Numero de IMSS';
        }

        if (trim((string) $user->personalInfo->photo) === '') {
            $missing[] = 'Foto de perfil';
        }

        return $missing;
    }

    public static function canGenerate(User $user): bool
    {
        return self::resolveMissingRequirements($user) === [];
    }

    public static function resolveVigencia(User $user): string
    {
        return ($user->active && $user->role !== RoleEnum::NoAgremiado)
            ? 'VIGENTE'
            : 'NO VIGENTE';
    }

    public static function buildVerificationUrl(string $baseUrl, User $user): string
    {
        $base = rtrim($baseUrl, '/');

        if ($base === '') {
            $base = 'http://localhost';
        }

        return sprintf(
            '%s/portal/perfiles/validar-credencial.php?uid=%d&token=%s',
            $base,
            $user->id,
            rawurlencode(self::buildValidationToken($user)),
        );
    }

    public static function buildValidationToken(User $user): string
    {
        $updatedAt = $user->updatedAt?->format('Y-m-d H:i:s') ?? '';

        $payload = implode('|', [
            (string) $user->id,
            $user->active ? '1' : '0',
            $user->role->value,
            strtoupper(trim((string) $user->personalInfo->curp)),
            $updatedAt,
        ]);

        return hash('sha256', $payload);
    }
}
