<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Dotenv\Dotenv;
use Latte\Engine;
use PHPMailer\PHPMailer\PHPMailer;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

require __DIR__ . '/../vendor/autoload.php';

// 1. Carga de entorno
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

$dotenv->required(['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'MAIL_HOST', 'MAIL_USER', 'MAIL_PASSWORD']);

$isDev = ($_ENV['APP_ENV'] ?? 'prod') === 'dev';


$builder = new ContainerBuilder();


if (!$isDev) {
    $builder->enableCompilation(__DIR__ . '/../temp/di_cache');
    $builder->writeProxiesToFile(true, __DIR__ . '/../temp/di_proxies');
}

$builder->addDefinitions([
    // --- DATABASE ---
    PDO::class => function (): PDO {
        $host = $_ENV['DB_HOST'];
        $db = $_ENV['DB_NAME'];
        $user = $_ENV['DB_USER'];
        $pass = $_ENV['DB_PASS'];
        $port = $_ENV['DB_PORT'] ?? 3306;
        $charset = 'utf8mb4';

        $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";

        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false, // Importante para seguridad real en MySQL
        ]);
    },

        // --- TEMPLATE ENGINE ---
    Engine::class => function () use ($isDev): Engine {
        $cacheDir = __DIR__ . '/../temp/latte';

        // Crear directorio si no existe (útil en despliegues frescos)
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0775, true);
        }

        $latte = new Engine();
        $latte->setTempDirectory($cacheDir);
        $latte->setAutoRefresh($isDev); // Solo regenerar plantillas si cambian en modo DEV

        return $latte;
    },

        // twig
    FilesystemLoader::class => function () {
        return new FilesystemLoader(__DIR__ . '/../templates');
    },

    Environment::class => fn(FilesystemLoader $loader) =>
        new Environment($loader, [
            'debug' => $isDev,
            'cache' => __DIR__ . '/../temp/twig_cache',
        ]),

        // --- MAILER ---
    PHPMailer::class => function (): PHPMailer {
        $mail = new PHPMailer(true);

        // Configuración del servidor
        $mail->isSMTP();
        $mail->Host = $_ENV['MAIL_HOST'];
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['MAIL_USER'];
        $mail->Password = $_ENV['MAIL_PASSWORD'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = (int) ($_ENV['MAIL_PORT'] ?? 465);
        $mail->CharSet = 'UTF-8'; // Importante para caracteres latinos

        // Configuraciones por defecto (opcional pero útil)
        $mail->setFrom($_ENV['MAIL_FROM_ADDRESS'] ?? $_ENV['MAIL_USER'], $_ENV['MAIL_FROM_NAME'] ?? 'Sistema');
        $mail->Timeout = 10;

        return $mail;
    }
]);

try {
    $container = $builder->build();
} catch (Exception $e) {
    // Si el contenedor falla al construirse, es un error fatal.
    // En producción podrías loguear esto y mostrar una página de error 500 estática.
    die('Error crítico al iniciar la aplicación: ' . $e->getMessage());
}