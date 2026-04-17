<?php

use App\Bootstrap;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Publication\Domain\Repository\PublicationRepositoryInterface;
use App\Shared\Utils\DocumentHelper;

require __DIR__ . "/../bootstrap.php";

$container = Bootstrap::buildContainer();

$renderer = $container->get(RendererInterface::class);
$publicationRepository = $container->get(PublicationRepositoryInterface::class);
$pdo = $container->get(PDO::class);

$latestPublications = $publicationRepository->findLatest(5);
$birthdayMembers = [];

$birthdayMembersStmt = $pdo->query(
    "SELECT
        name,
        surnames,
        photo
     FROM users
     WHERE role = 'agremiado'
       AND active = 1
       AND delete_at IS NULL
       AND birthdate IS NOT NULL
       AND MONTH(birthdate) = MONTH(CURDATE())
       AND DAY(birthdate) = DAY(CURDATE())
     ORDER BY surnames, name
     LIMIT 12",
);

if ($birthdayMembersStmt !== false) {
    while ($row = $birthdayMembersStmt->fetch(PDO::FETCH_ASSOC)) {
        $name = trim(((string) ($row["name"] ?? "")) . " " . ((string) ($row["surnames"] ?? "")));

        $birthdayMembers[] = [
            "fullName" => $name,
            "photo" => DocumentHelper::normalizeUploadPath(
                isset($row["photo"]) ? (string) $row["photo"] : null,
            ),
        ];
    }
}

$renderer->render("./index.latte", [
    "latestPublications" => $latestPublications,
    "birthdayMembers" => $birthdayMembers,
]);
