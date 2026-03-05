<?php

namespace App\Infrastructure\Logging;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

final class FileLogger extends AbstractLogger
{
    private const LOG_PATH = __DIR__ . '/../../../logs/application.log';

    public function __construct()
    {
        $this->ensureLogDirectory();
    }

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $levelUpper = strtoupper($level);
        $contextJson = !empty($context) ? ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $logMessage = "[{$timestamp}] [{$levelUpper}] {$message}{$contextJson}" . PHP_EOL;

        file_put_contents(self::LOG_PATH, $logMessage, FILE_APPEND);
    }

    private function ensureLogDirectory(): void
    {
        $logDir = dirname(self::LOG_PATH);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
}
