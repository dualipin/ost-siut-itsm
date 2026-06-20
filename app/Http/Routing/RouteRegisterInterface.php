<?php

declare(strict_types=1);

namespace App\Http\Routing;

use Slim\Interfaces\RouteCollectorProxyInterface as Group;

interface RouteRegisterInterface
{
    public function register(Group $group): void;
}
