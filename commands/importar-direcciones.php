<?php

declare(strict_types=1);

use App\Bootstrap;

require_once __DIR__ . "/../bootstrap.php";

// 1. Constantes y Configuración Dinámica
const BATCH_SIZE = 5000; // Incrementado para mejor rendimiento
$tempDir =
    rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) .
    DIRECTORY_SEPARATOR .
    "cp_import_" .
    uniqid() .
    DIRECTORY_SEPARATOR;

// Transliterator global (creado una sola vez)
$transliterator = Transliterator::create("Any-Latin; Latin-ASCII; Upper()");

// Función de limpieza reutilizable
$cleanUp = function () use ($tempDir): void {
    if (!is_dir($tempDir)) {
        return;
    }
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($tempDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($files as $fileinfo) {
        $path = $fileinfo->getRealPath();
        if ($path === false) {
            $path = $fileinfo->getPathname();
        }
        if ($fileinfo->isDir()) {
            @rmdir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($tempDir);
};

register_shutdown_function($cleanUp);

// 2. Argumentos de Consola (PHP 8+)
$getOps = getopt("", ["path:"]);
$path = $getOps["path"] ?? null;

if (!$path || !file_exists($path) || !is_readable($path)) {
    die("Uso: php commands/importar-direcciones.php --path=/ruta/al/archivo/CP.zip\n");
}

try {
    if (!mkdir($tempDir, 0755, true) && !is_dir($tempDir)) {
        throw new RuntimeException("No se pudo crear directorio temporal");
    }

    // 3. Manejo de ZIP (Optimizado)
    $zipFile = $tempDir . "CP.zip";
    copy($path, $zipFile);

    $zip = new ZipArchive();
    if ($zip->open($zipFile) === true) {
        $zip->extractTo($tempDir);
        $zip->close();
    }

    $txtFile = $tempDir . "CPdescarga.txt";
    if (!file_exists($txtFile)) {
        throw new Exception("Archivo no encontrado.");
    }

    // 4. Base de Datos (PDO Moderno)
    $container = Bootstrap::buildContainer();
    $pdo = $container->get(PDO::class);

    // Configuración recomendada para PHP 8.3
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

    // Optimizaciones para importación masiva
    $pdo->exec("SET autocommit=0");
    $pdo->exec("SET unique_checks=0");
    $pdo->exec("SET foreign_key_checks=0");

    $pdo->exec("CREATE TABLE IF NOT EXISTS localidades (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cp VARCHAR(10) NOT NULL,
        colonia VARCHAR(255) NOT NULL,
        localidad VARCHAR(255) NOT NULL,
        ciudad VARCHAR(255) NOT NULL,
        estado VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("TRUNCATE TABLE localidades");
    
    // Eliminar índices temporalmente para inserción rápida
    try {
        $pdo->exec("ALTER TABLE localidades DROP INDEX idx_cp");
    } catch (Exception $e) {
        // Índice no existe, continuar
    }

    // 5. Lectura y Procesamiento Optimizado (CSV + LOAD DATA INFILE)
    $file = new SplFileObject($txtFile, "r");
    $file->setFlags(
        SplFileObject::READ_AHEAD |
            SplFileObject::SKIP_EMPTY |
            SplFileObject::DROP_NEW_LINE,
    );

    // Crear archivo CSV temporal para carga rápida
    $csvFile = $tempDir . "datos.csv";
    $csvHandler = fopen($csvFile, "w");
    
    $total = 0;

    echo "Procesando archivo de datos...\n";

    foreach ($file as $index => $line) {
        if ($index < 2) {
            continue;
        } // Saltar encabezados

        $campos = explode("|", (string) $line);
        if (count($campos) < 13) {
            continue;
        }

        // Escribir directamente al CSV
        fputcsv(
            $csvHandler,
            [
                limpiar($campos[0], $transliterator), // CP
                limpiar($campos[1] ?: "N/A", $transliterator), // Colonia
                limpiar($campos[3] ?: "N/A", $transliterator), // Localidad
                limpiar($campos[5] ?: "N/A", $transliterator), // Ciudad
                limpiar($campos[4] ?: "N/A", $transliterator), // Estado
            ],
            ",",
            '"',
            "\\"
        );
        
        $total++;
        
        if ($total % 10000 === 0) {
            echo ".";
        }
    }

    fclose($csvHandler);
    echo "\n$total registros procesados.\n";

    // Importar usando LOAD DATA INFILE (mucho más rápido)
    echo "Importando a base de datos...\n";
    
    $csvFileEscaped = str_replace("\\", "/", $csvFile);
    
    try {
        // Intentar LOAD DATA LOCAL INFILE primero (más rápido)
        $sql = "LOAD DATA LOCAL INFILE '$csvFileEscaped'
                INTO TABLE localidades
                FIELDS TERMINATED BY ',' 
                ENCLOSED BY '\"' 
                ESCAPED BY '\\\\'
                LINES TERMINATED BY '\\n'
                (cp, colonia, localidad, ciudad, estado)";
        $pdo->exec($sql);
        echo "Importación completada con LOAD DATA LOCAL INFILE.\n";
    } catch (Exception $e) {
        // Fallback a inserción por lotes si LOAD DATA no está disponible
        echo "LOAD DATA no disponible, usando inserción por lotes...\n";
        cargarDesdeCsv($pdo, $csvFile);
    }

    // Recrear índices
    echo "Creando índices...\n";
    $pdo->exec("ALTER TABLE localidades ADD INDEX idx_cp (cp)");
    
    // Restaurar configuración
    $pdo->exec("SET unique_checks=1");
    $pdo->exec("SET foreign_key_checks=1");
    $pdo->exec("SET autocommit=1");

    echo "\nÉXITO: $total registros importados.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    if (isset($pdo)) {
        // Restaurar configuración en caso de error
        try {
            $pdo->exec("SET unique_checks=1");
            $pdo->exec("SET foreign_key_checks=1");
            $pdo->exec("SET autocommit=1");
        } catch (Exception $ignored) {}
    }
} finally {
    $cleanUp();
}

/**
 * Carga datos desde CSV usando inserción por lotes (fallback)
 */
function cargarDesdeCsv(PDO $pdo, string $csvFile): void
{
    $handle = fopen($csvFile, "r");
    $batch = [];
    $batchSize = BATCH_SIZE;
    
    $pdo->beginTransaction();
    
    while (($data = fgetcsv($handle, 0, ",", '"', "\\")) !== false) {
        $batch[] = $data;
        
        if (count($batch) >= $batchSize) {
            insertarLote($pdo, $batch);
            $batch = [];
        }
    }
    
    if (!empty($batch)) {
        insertarLote($pdo, $batch);
    }
    
    $pdo->commit();
    fclose($handle);
}

/**
 * Inserta lotes usando Prepared Statements eficientes
 */
function insertarLote(PDO $pdo, array $lote): void
{
    $cols = count($lote[0]);
    $placeholders =
        str_repeat(
            "(" . str_repeat("?,", $cols - 1) . "?),",
            count($lote) - 1,
        ) .
        "(" .
        str_repeat("?,", $cols - 1) .
        "?)";

    $sql = "INSERT INTO localidades (cp, colonia, localidad, ciudad, estado) VALUES $placeholders";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge(...$lote));
}

/**
 * Limpia y normaliza texto para PHP 8.3 (optimizado)
 */
function limpiar(?string $texto, Transliterator $transliterator): string
{
    if (!$texto || trim($texto) === "") {
        return "NO REGISTRADO(A)";
    }

    // Convertir de ISO-8859-1 a UTF-8 si es necesario
    $texto = mb_convert_encoding(trim($texto), "UTF-8", "ISO-8859-1");

    // Normalización usando transliterator compartido
    $limpio = $transliterator->transliterate($texto);

    return str_replace(['"', "'", ","], "", $limpio);
}
