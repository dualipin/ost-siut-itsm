<?php

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\App;

return function (App $app) {
    $app->get('/', function (RequestInterface $request, ResponseInterface $response) {
        $response->getBody()->write(json_encode(['status' => 'ok', 'timestamp' => time(), 'timezone' => date('P')]));
        return $response->withHeader('Content-Type', 'application/json');
    });
};
