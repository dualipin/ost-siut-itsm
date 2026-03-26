<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Http\Request\FormRequest;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Sodexo\Application\DTO\GuardarEncuestaDTO;
use App\Modules\Sodexo\Application\UseCase\GuardarEncuestaUseCase;
use App\Modules\Sodexo\Application\UseCase\ObtenerEncuestaUseCase;
use App\Modules\User\Domain\Repository\UserRepositoryInterface;
use App\Shared\Context\UserContext;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

/** @var UserContext $userContext */
$userContext  = $container->get(UserContext::class);
$authUser     = $userContext->get();

// Verificar sesión activa
if ($authUser === null) {
    header("Location: /cuentas/login.php?redirect=" . urlencode($_SERVER["REQUEST_URI"]));
    exit;
}

$renderer = $container->get(RendererInterface::class);
$request  = new FormRequest();

/** @var UserRepositoryInterface $userRepository */
$userRepository = $container->get(UserRepositoryInterface::class);
$userFull       = $userRepository->findById($authUser->id);

if ($userFull === null) {
    header("Location: /portal/index.php");
    exit;
}

// Determinar tipo de empleado a partir de la categoría registrada
$category     = strtolower(trim((string) ($userFull->workData->category ?? '')));
$tipoEmpleado = str_contains($category, 'docente') ? 'docente' : 'administrativo';

/** @var ObtenerEncuestaUseCase $obtenerUseCase */
$obtenerUseCase   = $container->get(ObtenerEncuestaUseCase::class);
$encuestaExistente = $obtenerUseCase->execute($authUser->id);

$errors  = [];
$success = false;

if ($request->isSubmitted()) {
    [$errors, $success] = procesarFormulario($container, $request, $authUser->id, $tipoEmpleado);
    // Recargar la encuesta actualizada tras guardar
    if ($success) {
        $encuestaExistente = $obtenerUseCase->execute($authUser->id);
    }
}

$renderer->render("./solicitud.latte", [
    'user'              => $userFull,
    'tipoEmpleado'      => $tipoEmpleado,
    'encuesta'          => $encuestaExistente,
    'errors'            => $errors,
    'success'           => $success,
    // Precomputed booleans for the Latte template (avoid {var} scoping issues)
    'admDicSel'         => $encuestaExistente?->mesAdmSeleccionado('dic') ?? false,
    'admEneSel'         => $encuestaExistente?->mesAdmSeleccionado('ene') ?? false,
    'admFebSel'         => $encuestaExistente?->mesAdmSeleccionado('feb') ?? false,
    'admMarSel'         => $encuestaExistente?->mesAdmSeleccionado('mar') ?? false,
]);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function procesarFormulario(
    \Psr\Container\ContainerInterface $container,
    FormRequest $request,
    int $userId,
    string $tipoEmpleado
): array {
    $errors = [];

    $firmaRfc = strtoupper(trim((string) $request->input('firma_rfc', '')));

    if ($firmaRfc === '') {
        $errors[] = "El RFC de firma es obligatorio para dar validez a la declaración.";
    } elseif (!preg_match('/^[A-Z&Ñ]{3,4}\d{6}[A-Z0-9]{3}$/i', $firmaRfc)) {
        $errors[] = "El RFC ingresado no tiene un formato válido (ej. ABCD123456XYZ).";
    }

    if ($errors !== []) {
        return [$errors, false];
    }

    // ── Administrativo ───────────────────────────────────────────────────
    $admDicPuntualidad = null;
    $admDicAsistencia  = null;
    $admEnePuntualidad = null;
    $admEneAsistencia  = null;
    $admFebPuntualidad = null;
    $admFebAsistencia  = null;
    $admMarPuntualidad = null;
    $admMarAsistencia  = null;

    if ($tipoEmpleado === 'administrativo') {
        if ($request->input('dic_seleccionado') !== null) {
            $admDicPuntualidad = clampAmount((string) $request->input('dic_puntualidad', '0'));
            $admDicAsistencia  = clampAmount((string) $request->input('dic_asistencia',  '0'));
        }
        if ($request->input('ene_seleccionado') !== null) {
            $admEnePuntualidad = clampAmount((string) $request->input('ene_puntualidad', '0'));
            $admEneAsistencia  = clampAmount((string) $request->input('ene_asistencia',  '0'));
        }
        if ($request->input('feb_seleccionado') !== null) {
            $admFebPuntualidad = clampAmount((string) $request->input('feb_puntualidad', '0'));
            $admFebAsistencia  = clampAmount((string) $request->input('feb_asistencia',  '0'));
        }
        if ($request->input('mar_seleccionado') !== null) {
            $admMarPuntualidad = clampAmount((string) $request->input('mar_puntualidad', '0'));
            $admMarAsistencia  = clampAmount((string) $request->input('mar_asistencia',  '0'));
        }
    }

    // ── Docente ──────────────────────────────────────────────────────────
    $docDicPagado = $tipoEmpleado === 'docente'
        && $request->input('doc_dic_pagado') !== null;

    $docMarPagado = $tipoEmpleado === 'docente'
        && $request->input('doc_mar_pagado') !== null;

    $dto = new GuardarEncuestaDTO(
        userId:            $userId,
        tipoEmpleado:      $tipoEmpleado,
        admDicPuntualidad: $admDicPuntualidad,
        admDicAsistencia:  $admDicAsistencia,
        admEnePuntualidad: $admEnePuntualidad,
        admEneAsistencia:  $admEneAsistencia,
        admFebPuntualidad: $admFebPuntualidad,
        admFebAsistencia:  $admFebAsistencia,
        admMarPuntualidad: $admMarPuntualidad,
        admMarAsistencia:  $admMarAsistencia,
        docDicPagado:      $docDicPagado,
        docMarPagado:      $docMarPagado,
        firmaRfc:          $firmaRfc,
    );

    try {
        /** @var GuardarEncuestaUseCase $guardarUseCase */
        $guardarUseCase = $container->get(GuardarEncuestaUseCase::class);
        $saved = $guardarUseCase->execute($dto);
    } catch (\Throwable $e) {
        error_log('[Sodexo] Error al guardar encuesta: ' . $e->getMessage());
        return [["No fue posible guardar la información. Intente de nuevo."], false];
    }

    return $saved ? [[], true] : [["No fue posible guardar la información. Intente de nuevo."], false];
}

/**
 * Asegura que el valor quede entre 0.00 y 50.00.
 */
function clampAmount(string $raw): float
{
    $value = (float) $raw;
    return max(0.0, min(50.0, $value));
}
