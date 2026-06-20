<?php

namespace App\Http\Routing\Api;

use App\Http\Routing\RouteRegisterInterface;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class HealthRouteRegister implements RouteRegisterInterface
{
    public function register(Group $group): void
    {
        $group->get('/health', function (RequestInterface $_request, ResponseInterface $response) {
            $response->getBody()->write(json_encode(['status' => 'ok', 'timestamp' => time(), 'timezone' => date('P')]));
            return $response->withHeader('Content-Type', 'application/json');
        });
    }
}
