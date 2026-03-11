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
$defaultMaxAttempts = 3;

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
            "SELECT *
            FROM mail_queue
            WHERE status IN ('pending', 'failed')
              AND scheduled_at <= NOW()
              AND attempts < COALESCE(max_attempts, :defaultMaxAttempts)
            ORDER BY priority ASC, scheduled_at ASC, created_at ASC
            LIMIT 10"
        );
        $stmt->bindValue(':defaultMaxAttempts', $defaultMaxAttempts, PDO::PARAM_INT);
        $stmt->execute();
        $pendingEmails = $stmt->fetchAll();
        
        $total = count($pendingEmails);
        if ($total === 0) {
            writeLog("No hay emails pendientes o reintentables para procesar");
            exit(0);
        }
        
        writeLog("Encontrados $total emails pendientes/reintentables");
    } catch (\Exception $e) {
        writeLog("Error al consultar BD: " . $e->getMessage(), 'ERROR');
        exit(1);
    }

    $processed = 0;
    $failed = 0;

    foreach ($pendingEmails as $email) {
        $emailId = $email["id"];
        $maxAttempts = (int)($email["max_attempts"] ?? $defaultMaxAttempts);
        if ($maxAttempts <= 0) {
            $maxAttempts = $defaultMaxAttempts;
        }

        $currentAttempts = (int)($email["attempts"] ?? 0);
        $remainingAttempts = $maxAttempts - $currentAttempts;
        
        if ($remainingAttempts <= 0) {
            writeLog("Email ID $emailId omitido: alcanzó el máximo de intentos ($maxAttempts)", 'WARN');
            continue;
        }

        // Bloquear registro para el ciclo de envío actual
        try {
            $lockStmt = $pdo->prepare(
                "UPDATE mail_queue SET status = 'sending', locked_at = NOW() WHERE id = :id AND status IN ('pending', 'failed')"
            );
            $lockStmt->execute([":id" => $emailId]);
            if ($lockStmt->rowCount() === 0) {
                writeLog("Email ID $emailId omitido: no se pudo adquirir bloqueo de envío", 'WARN');
                continue;
            }
        } catch (\Exception $e) {
            writeLog("Email ID $emailId no se pudo bloquear: " . $e->getMessage(), 'ERROR');
            continue;
        }

        $sent = false;
        for ($retry = 1; $retry <= $remainingAttempts; $retry++) {
            $attemptNumber = $currentAttempts + $retry;

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
                    "UPDATE mail_queue SET status = 'sent', locked_at = NULL, last_error = NULL WHERE id = :id"
                );
                $updateStmt->execute([":id" => $emailId]);

                writeLog("Email ID $emailId enviado exitosamente en intento $attemptNumber/$maxAttempts");
                $processed++;
                $sent = true;
                break;
            } catch (\Throwable $e) {
                $nextStatus = $attemptNumber >= $maxAttempts ? 'failed' : 'pending';

                writeLog(
                    "Email ID $emailId falló en intento $attemptNumber/$maxAttempts: " . get_class($e) . " - " . $e->getMessage(),
                    'ERROR'
                );

                try {
                    $updateStmt = $pdo->prepare(
                        "UPDATE mail_queue SET status = :status, attempts = attempts + 1, last_error = :lastError, locked_at = NULL WHERE id = :id"
                    );
                    $updateStmt->execute([
                        ":status" => $nextStatus,
                        ":lastError" => $e->getMessage(),
                        ":id" => $emailId,
                    ]);
                } catch (\Exception $dbError) {
                    writeLog("Error al actualizar BD: " . $dbError->getMessage(), 'ERROR');
                }

                if ($nextStatus === 'failed') {
                    break;
                }
            }
        }

        if (!$sent) {
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

