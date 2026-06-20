<?php

use App\Bootstrap;
use App\Infrastructure\Config\AppConfig;
use Slim\Factory\AppFactory;

require __DIR__ . '/../../bootstrap.php';

$container = Bootstrap::buildContainer();
$appConfig = $container->get(AppConfig::class);

AppFactory::setContainer($container);

$app = AppFactory::create();


$app->setBasePath('/api');

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

$errorMiddleware = $app->addErrorMiddleware($appConfig->isDev, true, true);

$routes = require __DIR__ . '/../../config/api_routes.php';

$routes($app);

$app->run();
