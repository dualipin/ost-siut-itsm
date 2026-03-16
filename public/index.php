<?php

use App\Bootstrap;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Publication\Domain\Repository\PublicationRepositoryInterface;

require __DIR__ . "/../bootstrap.php";

$container = Bootstrap::buildContainer();

$renderer = $container->get(RendererInterface::class);
$publicationRepository = $container->get(PublicationRepositoryInterface::class);

$latestPublications = $publicationRepository->findLatest(5);

$renderer->render("./index.latte", [
    "latestPublications" => $latestPublications,
]);
