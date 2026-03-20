<?php

use App\Bootstrap;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Transparency\Application\UseCase\ListTransparenciesUseCase;
use App\Shared\Context\UserContextInterface;
use App\Shared\Domain\Enum\RoleEnum;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

$renderer = $container->get(RendererInterface::class);
$useCase = $container->get(ListTransparenciesUseCase::class);
$userContext = $container->get(UserContextInterface::class);

$authenticatedUser = $userContext->get();
if ($authenticatedUser === null) {
    header('Location: /portal/cuentas/login.php');
    exit;
}

$isPrivileged = in_array($authenticatedUser->role, [RoleEnum::Admin, RoleEnum::Lider], true);
$transparencies = $isPrivileged
    ? $useCase->executeAll()
    : $useCase->executePublicOrPermittedForUser($authenticatedUser->id);

$groupedTransparencies = [];
if (!$isPrivileged) {
    $monthNames = [
        '01' => 'Enero',
        '02' => 'Febrero',
        '03' => 'Marzo',
        '04' => 'Abril',
        '05' => 'Mayo',
        '06' => 'Junio',
        '07' => 'Julio',
        '08' => 'Agosto',
        '09' => 'Septiembre',
        '10' => 'Octubre',
        '11' => 'Noviembre',
        '12' => 'Diciembre',
    ];

    foreach ($transparencies as $doc) {
        $year = $doc->datePublished->format('Y');
        $monthNumber = $doc->datePublished->format('m');

        if (!isset($groupedTransparencies[$year][$monthNumber])) {
            $groupedTransparencies[$year][$monthNumber] = [
                'name' => $monthNames[$monthNumber] ?? $monthNumber,
                'documents' => [],
            ];
        }

        $groupedTransparencies[$year][$monthNumber]['documents'][] = $doc;
    }

    krsort($groupedTransparencies);
    foreach ($groupedTransparencies as &$months) {
        krsort($months);
    }
    unset($months);
}

$renderer->render("./listado.latte", [
    'transparencies' => $transparencies,
    'groupedTransparencies' => $groupedTransparencies,
    'isPrivileged' => $isPrivileged,
]);
