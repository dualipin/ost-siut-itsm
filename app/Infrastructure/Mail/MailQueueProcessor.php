<?php

namespace App\Infrastructure\Mail;

use Psr\Log\LoggerInterface;

final readonly class MailQueueProcessor
{
    public function __construct(
        private PdoMailQueueRepository $queueRepository,
        private EmailService $emailService,
        private LoggerInterface $logger,
    ) {}

    /**
     * @return array{released_locks:int,claimed:int,sent:int,failed:int}
     */
    public function process(
        int $batchSize = 10,
        int $defaultMaxAttempts = 3,
        int $lockTimeoutMinutes = 10,
    ): array {
        $processToken = bin2hex(random_bytes(16));

        $releasedLocks = $this->queueRepository->releaseStaleLocks(
            $lockTimeoutMinutes,
        );
        if ($releasedLocks > 0) {
            $this->logger->warning(
                "Se liberaron {$releasedLocks} correos atascados en estado sending",
            );
        }

        $emails = $this->queueRepository->claimBatch(
            $processToken,
            $batchSize,
            $defaultMaxAttempts,
        );

        if ($emails === []) {
            $this->logger->info(
                "No hay correos pendientes para enviar en este ciclo",
            );
            return [
                "released_locks" => $releasedLocks,
                "claimed" => 0,
                "sent" => 0,
                "failed" => 0,
            ];
        }

        $sent = 0;
        $failed = 0;

        foreach ($emails as $email) {
            $emailId = (int) ($email["id"] ?? 0);
            if ($emailId <= 0) {
                continue;
            }

            $maxAttempts = (int) ($email["max_attempts"] ?? $defaultMaxAttempts);
            if ($maxAttempts <= 0) {
                $maxAttempts = $defaultMaxAttempts;
            }

            $attemptNumber = (int) ($email["attempts"] ?? 0) + 1;

            try {
                $recipients = $this->normalizeRecipients(
                    (string) ($email["recipient"] ?? ""),
                );

                $this->emailService->send(
                    $recipients,
                    (string) ($email["subject"] ?? ""),
                    (string) ($email["body"] ?? ""),
                    (string) ($email["alt_body"] ?? ""),
                );

                $updated = $this->queueRepository->markAsSent(
                    $emailId,
                    $processToken,
                );
                if (!$updated) {
                    $this->logger->warning(
                        "Email ID {$emailId} enviado pero no se pudo confirmar estado sent por lock/token",
                    );
                }

                $this->logger->info(
                    "Email ID {$emailId} enviado en intento {$attemptNumber}/{$maxAttempts}",
                );
                $sent++;
            } catch (\Throwable $e) {
                $updated = $this->queueRepository->markAsFailedOrPending(
                    $emailId,
                    $processToken,
                    $e->getMessage(),
                    $defaultMaxAttempts,
                );
                if (!$updated) {
                    $this->logger->warning(
                        "Email ID {$emailId} falló y no se pudo actualizar estado por lock/token",
                    );
                }

                $this->logger->error(
                    "Email ID {$emailId} falló en intento {$attemptNumber}/{$maxAttempts}: " .
                        get_class($e) .
                        " - " .
                        $e->getMessage(),
                );
                $failed++;
            }
        }

        return [
            "released_locks" => $releasedLocks,
            "claimed" => count($emails),
            "sent" => $sent,
            "failed" => $failed,
        ];
    }

    /**
     * @return string[]
     */
    private function normalizeRecipients(string $rawRecipient): array
    {
        $decoded = json_decode($rawRecipient, true);

        if (is_array($decoded)) {
            $recipients = array_values(
                array_filter(
                    $decoded,
                    fn($recipient) =>
                        is_string($recipient) &&
                        filter_var($recipient, FILTER_VALIDATE_EMAIL),
                ),
            );

            if ($recipients !== []) {
                return $recipients;
            }
        }

        if (is_string($decoded) && filter_var($decoded, FILTER_VALIDATE_EMAIL)) {
            return [$decoded];
        }

        $candidate = trim($rawRecipient);
        if ($candidate !== "" && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
            return [$candidate];
        }

        throw new \RuntimeException("Formato de destinatario inválido para la cola");
    }
}