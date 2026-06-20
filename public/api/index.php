<?php

use App\Bootstrap;
use Slim\Factory\AppFactory;

require __DIR__ . '/../../bootstrap.php';

$container = Bootstrap::buildContainer();

AppFactory::setContainer($container);

$app = AppFactory::create();


$app->setBasePath('/api');

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

$errorMiddleware = $app->addErrorMiddleware(true, true, true);

$routes = require __DIR__ . '/../../config/api_routes.php';

$routes($app);

$app->run();
