<?php
/**
 * Mail Queue Processor - Cronjob Script
 *
 * Este script procesa los emails pendientes en la cola de correo.
 * Puede ser ejecutado mediante cronjob cada 5 minutos.
 *
 * Registrarse como tarea programada en crontab
 * Documentación: https://en.wikipedia.org/wiki/Cron
 *
 * @version 1.0
 */

use App\Bootstrap;
use App\Infrastructure\Mail\MailQueueProcessor;
use Psr\Log\LoggerInterface;

require_once __DIR__ . "/../bootstrap.php";

$logger = null;

try {
    $container = Bootstrap::buildContainer();
    $logger = $container->get(LoggerInterface::class);
    $processor = $container->get(MailQueueProcessor::class);
    $result = $processor->process();

    $logger->info("Procesamiento de cola completado", $result);
    exit(0);
} catch (\Throwable $e) {
    $message =
        "FATAL: " .
        get_class($e) .
        " - " .
        $e->getMessage() .
        " en {$e->getFile()}:{$e->getLine()}";

    if ($logger instanceof LoggerInterface) {
        $logger->error($message);
    } else {
        error_log($message);
    }

    exit(1);
}
