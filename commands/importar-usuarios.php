<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Modules\User\Application\DTO\CreateUser;
use App\Modules\User\Application\UseCase\CreateUserUseCase;
use App\Shared\Domain\Enum\RoleEnum;
use League\Csv\Exception as CsvException;
use League\Csv\Reader;

require_once __DIR__ . "/../bootstrap.php";

$options = getopt("", ["path:", "default-password::"]);
$path = $options["path"] ?? null;
$defaultPassword = $options["default-password"] ?? "temporal";

if (!$path || !is_readable($path)) {
    echo "Uso: php commands/importar-usuarios.php --path=/ruta/al/archivo.csv [--default-password=temporal]" .
        PHP_EOL;
    exit(1);
}

try {
    $container = Bootstrap::buildContainer();
    echo "Buscando en $path" . PHP_EOL;

    /** @var CreateUserUseCase $createUserUseCase */
    $createUserUseCase = $container->get(CreateUserUseCase::class);

    $csv = Reader::createFromPath($path);
    $csv->setHeaderOffset(0);
    $records = $csv->getRecords();

    $imported = 0;
    $skipped = 0;
    $errors = 0;

    foreach ($records as $index => $record) {
        $row = is_int($index) ? $index + 1 : 0;

        try {
            $name = normalizeNullable($record["nombre"] ?? null);
            $surnames = normalizeNullable($record["apellidos"] ?? null);

            if ($name === null || $surnames === null) {
                throw new \InvalidArgumentException(
                    "Nombre o apellidos faltantes.",
                );
            }

            $email = normalizeNullable($record["email"] ?? null);
            $safeEmail =
                $email ?? buildFallbackEmail($name, $surnames, (int) $row);

            $user = new CreateUser(
                email: $safeEmail,
                password: $defaultPassword,
                role: parseRole($record["rol"] ?? null),
                curp: normalizeNullable($record["curp"] ?? null),
                name: $name,
                surnames: $surnames,
                birthdate: parseDate($record["fecha_nacimiento"] ?? null),
                address: normalizeNullable($record["direccion"] ?? null),
                phone: normalizeNullable($record["telefono"] ?? null),
                photo: null,
                bankName: null,
                interbankCode: null,
                bankAccount: null,
                category: normalizeNullable($record["categoria"] ?? null),
                department: normalizeNullable($record["departamento"] ?? null),
                nss: normalizeNullable($record["nss"] ?? null),
                salary: 0.0,
                workStartDate: parseDate($record["fecha_ingreso"] ?? null),
            );

            $wasCreated = $createUserUseCase->execute($user);

            if ($wasCreated) {
                $imported++;
                echo "Registrado: {$user->name} {$user->surnames} ({$user->email})" .
                    PHP_EOL;
                continue;
            }

            $skipped++;
            echo "Omitido (email duplicado): {$user->email}" . PHP_EOL;
        } catch (Throwable $exception) {
            $errors++;
            echo "Error en fila {$row}: {$exception->getMessage()}" . PHP_EOL;
        }
    }

    echo PHP_EOL;
    echo "Resumen de importacion" . PHP_EOL;
    echo "- Importados: $imported" . PHP_EOL;
    echo "- Omitidos: $skipped" . PHP_EOL;
    echo "- Errores: $errors" . PHP_EOL;

    exit($errors > 0 ? 1 : 0);
} catch (CsvException $exception) {
    echo "No se pudo leer el archivo CSV: {$exception->getMessage()}" .
        PHP_EOL;
    exit(1);
} catch (Throwable $exception) {
    echo "Error inesperado: {$exception->getMessage()}" . PHP_EOL;
    exit(1);
}

function normalizeNullable(?string $value): ?string
{
    if ($value === null) {
        return null;
    }

    $normalized = trim($value);

    return $normalized === "" ? null : $normalized;
}

function parseDate(?string $value): ?\DateTimeImmutable
{
    $normalized = normalizeNullable($value);
    if ($normalized === null) {
        return null;
    }

    foreach (["d/m/Y H:i", "d/m/Y", "Y-m-d H:i:s", "Y-m-d"] as $format) {
        $date = \DateTimeImmutable::createFromFormat($format, $normalized);

        if ($date !== false) {
            return $date;
        }
    }

    return null;
}

function parseRole(?string $value): RoleEnum
{
    $normalized = normalizeNullable($value);

    if ($normalized === null) {
        return RoleEnum::NoAgremiado;
    }

    return RoleEnum::tryFrom(strtolower($normalized)) ??
        RoleEnum::NoAgremiado;
}

function buildFallbackEmail(string $name, string $surnames, int $row): string
{
    $base = strtolower($name . "." . $surnames);
    $ascii = iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $base);

    if ($ascii === false) {
        $ascii = $base;
    }

    $slug = preg_replace('/[^a-z0-9]+/', ".", $ascii);
    $slug = trim((string) $slug, ".");

    if ($slug === "") {
        $slug = "usuario";
    }

    return $slug . "." . $row . "@import.local";
}
