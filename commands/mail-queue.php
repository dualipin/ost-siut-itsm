<?php

use App\Bootstrap;
use App\Infrastructure\Mail\EmailService;

require_once __DIR__ . "/../bootstrap.php";

$container = Bootstrap::buildContainer();
$pdo = $container->get(PDO::class);
$realMailer = $container->get(EmailService::class);

$stmt = $pdo->prepare(
    "SELECT * FROM mail_queue where status = 'pending' limit 10",
);
$pendingEmails = $stmt->fetchAll();

foreach ($pendingEmails as $email) {
    try {
        $realMailer->send(
            json_decode($email["recipient"], true),
            $email["subject"],
            $email["body"],
        );

        $pdo->prepare(
            "update mail_queue set status = 'sent' where id = :id",
        )->execute([":id" => $email["id"]]);
    } catch (Exception $e) {
        $pdo->prepare(
            "update mail_queue set status = 'failed', attempts = attempts + 1 where id = :id",
        )->execute([":id" => $email["id"]]);
    }
}
