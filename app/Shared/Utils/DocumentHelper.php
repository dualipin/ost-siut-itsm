<?php

declare(strict_types=1);

namespace App\Shared\Utils;

use App\Modules\User\Domain\Enum\DocumentTypeEnum;
use App\Shared\Domain\Enum\RoleEnum;

final class DocumentHelper
{
    /**
     * @return array<int, DocumentTypeEnum>
     */
    public static function resolveAllowedDocumentTypes(RoleEnum $role): array
    {
        if ($role === RoleEnum::NoAgremiado) {
            return [
                DocumentTypeEnum::Ine,
                DocumentTypeEnum::ComprobanteDomicilio,
                DocumentTypeEnum::Curp,
            ];
        }

        return [
            DocumentTypeEnum::Afiliacion,
            DocumentTypeEnum::ComprobanteDomicilio,
            DocumentTypeEnum::Ine,
            DocumentTypeEnum::ComprobantePago,
            DocumentTypeEnum::Curp,
        ];
    }

    /**
     * Determine if a user with the given role may access another user's documents.
     */
    public static function canViewOtherUserDocuments(RoleEnum $viewerRole): bool
    {
        return in_array($viewerRole, [RoleEnum::Admin, RoleEnum::Lider], true);
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    public static function resolveDocumentFieldsByRole(RoleEnum $role): array
    {
        $labels = [
            DocumentTypeEnum::Afiliacion->value => "Afiliacion",
            DocumentTypeEnum::ComprobanteDomicilio->value => "Comprobante de domicilio",
            DocumentTypeEnum::Ine->value => "INE",
            DocumentTypeEnum::ComprobantePago->value => "Comprobante de pago",
            DocumentTypeEnum::Curp->value => "CURP",
        ];

        $fields = [];
        foreach (self::resolveAllowedDocumentTypes($role) as $documentType) {
            $fields[] = [
                'key' => $documentType->value,
                'label' => $labels[$documentType->value] ?? strtoupper($documentType->value),
            ];
        }

        return $fields;
    }

    public static function normalizeUploadPath(?string $value): string
    {
        $path = trim((string) ($value ?? ''));
        $path = ltrim($path, '/\\');

        if ($path === '') {
            return '';
        }

        if (str_starts_with($path, 'uploads/')) {
            return substr($path, 8);
        }

        return $path;
    }
}
