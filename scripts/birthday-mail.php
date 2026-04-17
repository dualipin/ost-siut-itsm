<?php
/**
 * Birthday Mail Enqueuer - Cronjob Script
 *
 * Encola correos de felicitacion para agremiados que cumplen anios hoy.
 * Puede ejecutarse cada 12 horas sin duplicar envio del mismo dia.
 *
 * Uso:
 *   php scripts/birthday-mail.php
 *   php scripts/birthday-mail.php --dry-run
 */

use App\Bootstrap;
use App\Infrastructure\Mail\MailQueueProcessor;
use App\Infrastructure\Mail\MailerInterface;
use Psr\Log\LoggerInterface;

require_once __DIR__ . "/../bootstrap.php";

$dryRun = in_array("--dry-run", $argv ?? [], true);

$logger = null;

try {
    $container = Bootstrap::buildContainer();
    $logger = $container->get(LoggerInterface::class);

    /** @var \PDO $pdo */
    $pdo = $container->get(\PDO::class);

    /** @var MailerInterface $mailer */
    $mailer = $container->get(MailerInterface::class);

    /** @var MailQueueProcessor $queueProcessor */
    $queueProcessor = $container->get(MailQueueProcessor::class);

    $birthdayMembersStmt = $pdo->query(
        "SELECT
            user_id,
            name,
            surnames,
            email
         FROM users
         WHERE role = 'agremiado'
           AND active = 1
           AND delete_at IS NULL
           AND birthdate IS NOT NULL
           AND MONTH(birthdate) = MONTH(CURDATE())
           AND DAY(birthdate) = DAY(CURDATE())
         ORDER BY surnames, name",
    );

    if ($birthdayMembersStmt === false) {
        throw new RuntimeException("No fue posible consultar agremiados con cumpleanos hoy");
    }

    $alreadyQueuedStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM mail_queue
         WHERE recipient = :recipient
           AND subject = :subject
           AND DATE(created_at) = CURDATE()",
    );

    $subject = "Feliz cumpleanos de parte de OST-SIUT-ITSM";

    $found = 0;
    $queued = 0;
    $skippedInvalidEmail = 0;
    $skippedAlreadyQueued = 0;

    while ($member = $birthdayMembersStmt->fetch(\PDO::FETCH_ASSOC)) {
        $found++;

        $email = trim((string) ($member["email"] ?? ""));
        if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $skippedInvalidEmail++;
            continue;
        }

        $alreadyQueuedStmt->execute([
            ":recipient" => $email,
            ":subject" => $subject,
        ]);
        $alreadyQueuedCount = (int) $alreadyQueuedStmt->fetchColumn();

        if ($alreadyQueuedCount > 0) {
            $skippedAlreadyQueued++;
            continue;
        }

        $fullName = trim(
            ((string) ($member["name"] ?? "")) .
                " " .
                ((string) ($member["surnames"] ?? "")),
        );

        $body = buildBirthdayHtmlBody($fullName);
        $altBody = buildBirthdayTextBody($fullName);

        if (!$dryRun) {
            $mailer->send([$email], $subject, $body, $altBody);
        }

        $queued++;
    }

    $summary = [
        "dry_run" => $dryRun,
        "found" => $found,
        "queued" => $queued,
        "skipped_invalid_email" => $skippedInvalidEmail,
        "skipped_already_queued" => $skippedAlreadyQueued,
    ];

    if (!$dryRun && $queued > 0) {
        $summary["queue_process"] = $queueProcessor->process(
            batchSize: max(10, $queued),
        );
    }

    $logger->info("Procesamiento de felicitaciones de cumpleanos completado", $summary);

    $queueSent = isset($summary["queue_process"]["sent"])
        ? (int) $summary["queue_process"]["sent"]
        : 0;
    $queueFailed = isset($summary["queue_process"]["failed"])
        ? (int) $summary["queue_process"]["failed"]
        : 0;

    echo sprintf(
        "[birthday-mail] dry_run=%s found=%d queued=%d skipped_invalid=%d skipped_queued=%d sent=%d failed=%d\n",
        $dryRun ? "1" : "0",
        $found,
        $queued,
        $skippedInvalidEmail,
        $skippedAlreadyQueued,
        $queueSent,
        $queueFailed,
    );

    exit(0);
} catch (Throwable $e) {
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

    echo "[birthday-mail] error\n";
    exit(1);
}

function buildBirthdayHtmlBody(string $fullName): string
{
    $safeName = htmlspecialchars($fullName, ENT_QUOTES, "UTF-8");
    $year = date("Y");

    return "
<!doctype html>
<html lang=\"es\">
  <head>
    <meta charset=\"utf-8\">
    <meta name=\"viewport\" content=\"width=device-width,initial-scale=1\">
    <title>Feliz cumpleanos</title>
  </head>
  <body style=\"margin:0;padding:24px;background:#f8fafc;font-family:Arial,sans-serif;color:#111827;\">
    <table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"max-width:640px;margin:0 auto;background:#ffffff;border-radius:12px;border:1px solid #e5e7eb;\">
      <tr>
        <td style=\"padding:28px 24px 16px 24px;\">
          <h1 style=\"margin:0 0 12px 0;font-size:24px;line-height:1.2;color:#7f1d1d;\">Feliz cumpleanos, {$safeName}</h1>
          <p style=\"margin:0 0 12px 0;font-size:16px;line-height:1.6;\">Desde OST-SIUT-ITSM te enviamos un cordial saludo y nuestros mejores deseos en este dia especial.</p>
          <p style=\"margin:0 0 12px 0;font-size:16px;line-height:1.6;\">Gracias por formar parte de nuestra comunidad de agremiados.</p>
          <p style=\"margin:20px 0 0 0;font-size:14px;line-height:1.5;color:#6b7280;\">Atentamente,<br>OST-SIUT-ITSM {$year}</p>
        </td>
      </tr>
    </table>
  </body>
</html>";
}

function buildBirthdayTextBody(string $fullName): string
{
    return "Hola {$fullName},\n\n" .
        "Desde OST-SIUT-ITSM te enviamos un cordial saludo y nuestros mejores deseos en tu cumpleanos.\n\n" .
        "Gracias por formar parte de nuestra comunidad de agremiados.\n\n" .
        "Atentamente,\n" .
        "OST-SIUT-ITSM";
}
