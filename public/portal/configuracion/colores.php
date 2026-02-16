<?php

use App\Bootstrap;
use App\Http\Response\Redirector;
use App\Infrastructure\Templating\RendererInterface;
use App\Module\Sindicato\Entity\Colores;
use App\Module\Sindicato\Repository\ColoresRepository;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

$repo = $container->get(ColoresRepository::class);
$colores = $repo->getColores();
$path = $_SERVER["REQUEST_URI"];

$redirector = $container->get(Redirector::class);
$renderer = $container->get(RendererInterface::class);

// Manejo de POST: validar CSRF, sanitizar y guardar en la tabla colores_sistema
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $esperados = [
        "primario",
        "secundario",
        "exito",
        "info",
        "advertencia",
        "peligro",
        "claro",
        "oscuro",
        "blanco",
        "cuerpo",
        "fondo_cuerpo",
    ];

    $valores = [];
    foreach ($esperados as $campo) {
        $v = trim((string) ($_POST[$campo] ?? ""));
        if ($v === "") {
            $valores[$campo] = null;
            continue;
        }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $v)) {
            $redirector
                ->to($path, [
                    "error" => "El campo '$campo' debe ser un color hexadecimal válido (ej: #ff0000).",
                ])
                ->send();
        }
        $valores[$campo] = strtolower($v);
    }

    try {
        $repo->actualizarColores(
            new Colores(
                primario: $valores["primario"],
                secundario: $valores["secundario"],
                exito: $valores["exito"],
                info: $valores["info"],
                advertencia: $valores["advertencia"],
                peligro: $valores["peligro"],
                claro: $valores["claro"],
                oscuro: $valores["oscuro"],
                blanco: $valores["blanco"],
                cuerpo: $valores["cuerpo"],
                fondoCuerpo: $valores["fondo_cuerpo"],
            ),
        );
    } catch (Exception $e) {
        $redirector
            ->to($path, [
                "error" => "Error al actualizar colores: " . $e->getMessage(),
            ])
            ->send();
    }

    $redirector
        ->to($path, [
            "mensaje" => "Colores actualizados correctamente.",
        ])
        ->send();
}

$mensaje = $_GET["mensaje"] ?? null;
$error = $_GET["error"] ?? null;

$renderer->render("./colores.latte", [
    "colores" => $colores,
    "mensaje" => $mensaje,
    "error" => $error,
]);
