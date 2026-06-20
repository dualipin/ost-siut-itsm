<?php

use App\Http\Routing\Api\HealthRouteRegister;
use App\Http\Routing\Api\LoanRouteRegister;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;
use Slim\App;

return function (App $app) {
    $app->group('', function (Group $group) {
        $register = [
            new HealthRouteRegister(),
            new LoanRouteRegister()
        ];

        foreach ($register as $route) {
            $route->register($group);
        }
    });
};
