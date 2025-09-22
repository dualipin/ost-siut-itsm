<?php

namespace App\Configuracion;

final class VariablesEntorno
{
    private function __construct()
    {
        $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();
    }

    public static function init(): void
    {
        new self();
    }
}