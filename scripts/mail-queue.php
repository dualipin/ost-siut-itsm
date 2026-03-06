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
use App\Infrastructure\Mail\EmailService;

// Configuración
$logsDir = dirname(__DIR__) . '/logs';
$lockFile = $logsDir . '/mail-queue.lock';
$logFile = $logsDir . '/' . date('Y-m-d') . '-mail-queue.log';

// Crear directorio de logs si no existe
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0775, true);
}

/**
 * Escribir mensaje en log
 */
function writeLog(string $message, string $level = 'INFO'): void {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $line = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND);
}

/**
 * Prevenir ejecuciones simultáneas
 */
function acquireLock(): bool {
    global $lockFile;
    
    // Si existe lock y es reciente (menos de 30 minutos), esperar
    if (file_exists($lockFile)) {
        $lockTime = filemtime($lockFile);
        $elapsed = time() - $lockTime;
        
        if ($elapsed < 1800) { // 30 minutos
            writeLog("Otra instancia está ejecutándose. Abortando.", 'WARN');
            return false;
        }
        
        // Lock expirado, limpiar
        unlink($lockFile);
    }
    
    // Crear nuevo lock
    file_put_contents($lockFile, getmypid());
    register_shutdown_function(function () {
        global $lockFile;
        if (file_exists($lockFile)) {
            unlink($lockFile);
        }
    });
    
    return true;
}

try {
    if (!acquireLock()) {
        exit(0); // Exit silenciosamente si hay lock
    }
    
    writeLog("=== Iniciando procesamiento de cola de emails ===");
    
    require_once __DIR__ . "/../bootstrap.php";

    $container = Bootstrap::buildContainer();
    $pdo = $container->get(PDO::class);

    // Obtener EmailService
    try {
        $emailService = $container->get(EmailService::class);
    } catch (\Exception $e) {
        writeLog("No se puede obtener EmailService: " . $e->getMessage(), 'ERROR');
        exit(1);
    }

    // Obtener emails pendientes
    try {
        $stmt = $pdo->prepare(
            "SELECT * FROM mail_queue WHERE status = 'pending' LIMIT 10"
        );
        $stmt->execute();
        $pendingEmails = $stmt->fetchAll();
        
        $total = count($pendingEmails);
        if ($total === 0) {
            writeLog("No hay emails pendientes para procesar");
            exit(0);
        }
        
        writeLog("Encontrados $total emails pendientes");
    } catch (\Exception $e) {
        writeLog("Error al consultar BD: " . $e->getMessage(), 'ERROR');
        exit(1);
    }

    $processed = 0;
    $failed = 0;

    foreach ($pendingEmails as $email) {
        $emailId = $email["id"];
        
        try {
            $recipients = json_decode($email["recipient"], true);
            if (!is_array($recipients)) {
                throw new \RuntimeException("Formato de recipients inválido");
            }
            
            // Enviar email
            $emailService->send(
                $recipients,
                $email["subject"],
                $email["body"],
            );

            // Actualizar estado a 'sent'
            $updateStmt = $pdo->prepare(
                "UPDATE mail_queue SET status = 'sent', sent_at = NOW() WHERE id = :id"
            );
            $updateStmt->execute([":id" => $emailId]);
            
            writeLog("Email ID $emailId enviado exitosamente");
            $processed++;
            
        } catch (\Throwable $e) {
            writeLog(
                "Email ID $emailId falló: " . get_class($e) . " - " . $e->getMessage(),
                'ERROR'
            );
            
            // Actualizar estado a 'failed' e incrementar intentos
            try {
                $updateStmt = $pdo->prepare(
                    "UPDATE mail_queue SET status = 'failed', attempts = attempts + 1 WHERE id = :id"
                );
                $updateStmt->execute([":id" => $emailId]);
            } catch (\Exception $dbError) {
                writeLog("Error al actualizar BD: " . $dbError->getMessage(), 'ERROR');
            }
            
            $failed++;
        }
    }

    writeLog("=== Procesamiento completado: $processed enviados, $failed fallos ===");
    exit(0);
    
} catch (\Throwable $e) {
    writeLog(
        "FATAL: " . get_class($e) . " - " . $e->getMessage() . 
        " en {$e->getFile()}:{$e->getLine()}",
        'FATAL'
    );
    exit(1);
}

