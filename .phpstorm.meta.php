<?php
// .phpstorm.meta.php

namespace PHPSTORM_META {

    // 1. Esto le dice al IDE: 
    // "Si llamo a $container->get('Clase'), el resultado ES esa 'Clase'"
    override(
        \Psr\Container\ContainerInterface::get(0),
        map([
            '' => '@',
        ])
    );

    // 2. Soporte para el método make() si usas PHP-DI directamente
    override(
        \DI\Container::make(0),
        map([
            '' => '@',
        ])
    );
    
    // 3. Soporte para el método get() si usas PHP-DI directamente
    override(
        \DI\Container::get(0),
        map([
            '' => '@',
        ])
    );
}