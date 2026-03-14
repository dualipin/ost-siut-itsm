<?php

declare(strict_types=1);

use App\Shared\Utils\DocumentHelper;
use App\Shared\Domain\Enum\RoleEnum;
use App\Modules\User\Domain\Enum\DocumentTypeEnum;

it('returns only basic documents for no-agremiado', function (): void {
    $list = DocumentHelper::resolveAllowedDocumentTypes(RoleEnum::NoAgremiado);

    expect($list)->toBeArray()
        ->and($list)->toHaveCount(3)
        ->and($list)->toContain(DocumentTypeEnum::Ine)
        ->and($list)->toContain(DocumentTypeEnum::ComprobanteDomicilio)
        ->and($list)->toContain(DocumentTypeEnum::Curp);
});

it('returns full document set for other roles', function (): void {
    $list = DocumentHelper::resolveAllowedDocumentTypes(RoleEnum::Admin);

    expect($list)->toBeArray()
        ->and($list)->toHaveCount(5)
        ->and($list)->toContain(DocumentTypeEnum::Afiliacion)
        ->and($list)->toContain(DocumentTypeEnum::ComprobantePago);
});

it('builds document fields with labels', function (): void {
    $fields = DocumentHelper::resolveDocumentFieldsByRole(RoleEnum::NoAgremiado);

    expect($fields)->toBeArray()
        ->and($fields[0])->toHaveKey('key')
        ->and($fields[0])->toHaveKey('label');

    // ensure label for CURP
    $found = array_filter($fields, fn($f) => $f['key'] === DocumentTypeEnum::Curp->value);
    expect($found)->not->toBeEmpty();
});

it('normalizes upload path (trims and strips prefix)', function (): void {
    expect(DocumentHelper::normalizeUploadPath(null))->toBe('');
    expect(DocumentHelper::normalizeUploadPath(''))->toBe('');
    expect(DocumentHelper::normalizeUploadPath('   file.pdf  '))->toBe('file.pdf');
    expect(DocumentHelper::normalizeUploadPath('/uploads/foo/bar.pdf'))->toBe('foo/bar.pdf');
});

it('allows admins and leaders to view other users', function (): void {
    expect(DocumentHelper::canViewOtherUserDocuments(RoleEnum::Admin))->toBeTrue();
    expect(DocumentHelper::canViewOtherUserDocuments(RoleEnum::Lider))->toBeTrue();
    expect(DocumentHelper::canViewOtherUserDocuments(RoleEnum::Agremiado))->toBeFalse();
    expect(DocumentHelper::canViewOtherUserDocuments(RoleEnum::NoAgremiado))->toBeFalse();
});
