<?php

declare(strict_types=1);

namespace App\Fabricas;

use Exception as ExceptionAlias;
use PDO;
use PDOException;

class FabricaConexion
{
    private static ?PDO $instancia = null;

    /**
     * @throws ExceptionAlias
     */
    public static function crear(): PDO
    {
        if (self::$instancia === null) {
            try {
                $host = $_ENV['DB_HOST'] ?? 'localhost';
                $dbname = $_ENV['DB_NAME'] ?? 'siut_itsm';
                $username = $_ENV['DB_USER'] ?? 'root';
                $password = $_ENV['DB_PASS'] ?? '';
                $charset = 'utf8mb4';

                $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";

                $opciones = [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                ];

                self::$instancia = new PDO($dsn, $username, $password, $opciones);
            } catch (PDOException $e) {
                throw new ExceptionAlias('Error de conexión a la base de datos: ' . $e->getMessage());
            }
        }

        return self::$instancia;
    }

    public static function cerrar(): void
    {
        self::$instancia = null;
    }
}
