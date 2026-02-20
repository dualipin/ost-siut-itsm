<?php

use App\Module\Auth\Repository\UserRepository;
use App\Module\Auth\Repository\UserRepositoryInterface;
use App\Module\Auth\Repository\RoleRepository;
use App\Module\Auth\Repository\RoleRepositoryInterface;

return function (\DI\ContainerBuilder $container) {
    $container->addDefinitions([
        UserRepositoryInterface::class => \DI\autowire(UserRepository::class),
        RoleRepositoryInterface::class => \DI\autowire(RoleRepository::class),
    ]);
};