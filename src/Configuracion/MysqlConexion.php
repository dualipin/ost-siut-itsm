<?php

namespace App\Configuracion;

use PDO;
use PDOException;

final class MysqlConexion
{
    // Patrón Singleton: previene la creación de instancias con "new", clonación y deserialización.
    private function __construct()
    {
    }
    /**
     * @var ?PDO La única instancia de la conexión PDO.
     */
    private static ?PDO $instancia = null;

    /**
     * Obtiene la instancia única de la conexión PDO.
     * Si no existe, la crea.
     *
     * @return PDO
     */
    public static function conexion(): PDO
    {
        if (self::$instancia === null) {
            // Lee las credenciales de las variables de entorno con valores por defecto.
            $host = $_ENV['DB_HOST'] ?? 'localhost';
            $usuario = $_ENV['DB_USER'] ?? 'root';
            $contrasena = $_ENV['DB_PASS'] ?? '';
            $baseDatos = $_ENV['DB_NAME'] ?? 'siut';
            $charset = 'utf8mb4';

            // DSN (Data Source Name) para la conexión.
            $dsn = "mysql:host=$host;dbname=$baseDatos;charset=$charset";

            // Opciones recomendadas para PDO.
            $opciones = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Lanza excepciones en errores.
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Devuelve resultados como arrays asociativos.
                    PDO::ATTR_EMULATE_PREPARES => false,                  // Usa preparaciones de sentencias nativas.
            ];

            try {
                // Crea la instancia de PDO y la guarda en la propiedad estática.
                self::$instancia = new PDO($dsn, $usuario, $contrasena, $opciones);
            } catch (PDOException $e) {
                // En caso de error, detiene la ejecución y muestra un mensaje.
                // En producción, aquí deberías registrar el error en un log en lugar de mostrarlo.
                throw new PDOException("Error de conexión a la base de datos: " . $e->getMessage(), (int)$e->getCode());
            }
        }

        return self::$instancia;
    }
}