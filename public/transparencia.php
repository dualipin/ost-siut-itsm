<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Transparency\Application\UseCase\ListTransparenciesUseCase;
use App\Modules\Transparency\Domain\Repository\TransparencyRepositoryInterface;
use App\Shared\Context\UserContextInterface;
use App\Shared\Domain\Enum\RoleEnum;

require_once __DIR__ . "/../bootstrap.php";

$container = Bootstrap::buildContainer();

$renderer = $container->get(RendererInterface::class);
$listTransparenciesUseCase = $container->get(ListTransparenciesUseCase::class);
$repository = $container->get(TransparencyRepositoryInterface::class);

session_start([
	'read_and_close' => true,
]);

$userContext = $container->get(UserContextInterface::class);
$user = $userContext->get();
$isAuthenticated = $user !== null;
$isAdmin = $user?->role === RoleEnum::Admin;
$userId = $user?->id ?? 0;

$monthNames = [
	1 => 'enero',
	2 => 'febrero',
	3 => 'marzo',
	4 => 'abril',
	5 => 'mayo',
	6 => 'junio',
	7 => 'julio',
	8 => 'agosto',
	9 => 'septiembre',
	10 => 'octubre',
	11 => 'noviembre',
	12 => 'diciembre',
];

$documentsByYear = [];
$documentVisibility = [];

foreach ($listTransparenciesUseCase->executeAll() as $document) {
	$canViewDetails = !$document->isPrivate;

	if ($document->isPrivate && $isAuthenticated) {
		if ($isAdmin) {
			$canViewDetails = true;
		} else {
			$permissions = $repository->findPermissionsByTransparencyId((int) $document->id);

			foreach ($permissions as $permission) {
				if ($permission->userId === $userId) {
					$canViewDetails = true;
					break;
				}
			}
		}
	}

	$documentVisibility[(int) $document->id] = $canViewDetails;

	$year = (int) $document->datePublished->format('Y');
	$month = (int) $document->datePublished->format('n');

	if (!isset($documentsByYear[$year][$month])) {
		$documentsByYear[$year][$month] = [
			'label' => $monthNames[$month],
			'documents' => [],
		];
	}

	$documentsByYear[$year][$month]['documents'][] = $document;
}

krsort($documentsByYear);

foreach ($documentsByYear as &$months) {
	krsort($months);
}

unset($months);

$renderer->render("./transparencia.latte", [
	'documentsByYear' => $documentsByYear,
	'documentVisibility' => $documentVisibility,
	'isAuthenticated' => $isAuthenticated,
]);