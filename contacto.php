<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

} else {
    http_response_code(405);
    header('Location: /#contact');
    exit();
}