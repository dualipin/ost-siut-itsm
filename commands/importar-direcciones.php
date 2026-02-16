<?php
// Configuración
define('BATCH_SIZE', 1000);
define('TEMP_DIR', sys_get_temp_dir() . '/cp_import_' . uniqid() . '/');

use App\Configuracion\MysqlConexion;
require_once __DIR__ . '/../src/configuracion.php';

$getOps = getopt('', ['ruta:']);
if (!isset($getOps['ruta']) || !file_exists($getOps['ruta'])) {
    die("Uso: php importar-direcciones.php --ruta=\"C:\\ruta\\CodigoPostal.zip\"\n");
}

$pdo = null;
try {
    // 1. Crear temp
    if (!mkdir(TEMP_DIR, 0755, true) && !is_dir(TEMP_DIR)) {
        throw new Exception("No se pudo crear directorio temporal");
    }

    // 2. Copiar ZIP
    $zipFile = TEMP_DIR . 'CP.zip';
    if (!copy($getOps['ruta'], $zipFile)) {
        throw new Exception("No se pudo copiar el ZIP");
    }

    // 3. Extraer
    $zip = new ZipArchive();
    if ($zip->open($zipFile) !== true) {
        throw new Exception("No se pudo abrir el ZIP");
    }
    $zip->extractTo(TEMP_DIR);
    $zip->close();

    $txtFile = TEMP_DIR . 'CPdescarga.txt';
    if (!file_exists($txtFile)) {
        throw new Exception("No se encontró CPdescarga.txt");
    }

    // 4. Conexión + UTF8MB4
    $pdo = MysqlConexion::conexion();

    // FORZAR UTF8MB4 EN LA CONEXIÓN
    $pdo->exec("SET NAMES utf8mb4");
    $pdo->exec("SET CHARACTER SET utf8mb4");
    $pdo->exec("SET COLLATION_CONNECTION = utf8mb4_unicode_ci");

    // 5. Crear tabla con UTF8MB4
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cp (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cp VARCHAR(10) NOT NULL,
            colonia VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci,
            localidad VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci,
            ciudad VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci,
            estado VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci,
            INDEX idx_cp (cp)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 6. TRUNCATE
    $pdo->exec("TRUNCATE TABLE cp");

    // 7. Leer archivo con codificación correcta
    $handle = fopen($txtFile, 'r');
    if (!$handle) throw new Exception("No se pudo abrir el archivo TXT");

    // Detectar y saltar BOM
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") {
        rewind($handle);
    }

    $batch = [];
    $total = 0;
    $linea = 0;

    echo "Importando... (puede tardar ~30 segundos)\n";

    while (($line = fgets($handle)) !== false) {
        $linea++;
        if ($linea <= 2) continue;

        $campos = explode('|', $line);
        if (count($campos) < 13) continue;

        // FORZAR UTF-8 y limpiar
        $row = [
                limpiar(utf8_encode(trim($campos[0]))),
                limpiar(utf8_encode($campos[1] ?: 'NO REGISTRADO(A)')),
                limpiar(utf8_encode($campos[3] ?: 'NO REGISTRADO(A)')),
                limpiar(utf8_encode($campos[5] ?: 'NO REGISTRADO(A)')),
                limpiar(utf8_encode($campos[4] ?: 'NO REGISTRADO(A)'))
        ];

        $batch[] = $row;

        if (count($batch) >= BATCH_SIZE) {
            insertarLote($pdo, $batch);
            $total += count($batch);
            $batch = [];
            echo ".";
        }
    }

    if (!empty($batch)) {
        insertarLote($pdo, $batch);
        $total += count($batch);
        echo ".";
    }

    fclose($handle);
    echo "\nÉXITO: $total registros importados correctamente.\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
} finally {
    // LIMPIEZA ROBUSTA
    if (is_dir(TEMP_DIR)) {
        $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(TEMP_DIR, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }
        rmdir(TEMP_DIR);
    }
}

// === INSERTAR LOTE ===
function insertarLote(PDO $pdo, array $lote): void
{
    $placeholders = str_repeat('(?,?,?,?,?),', count($lote) - 1) . '(?,?,?,?,?)';
    $valores = [];
    foreach ($lote as $fila) {
        $valores = array_merge($valores, $fila);
    }

    $sql = "INSERT INTO cp (cp, colonia, localidad, ciudad, estado) VALUES $placeholders";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($valores);
}

// === LIMPIAR TEXTO (con UTF-8) ===
function limpiar(string $texto): string
{
    $texto = trim($texto);
    if ($texto === '') return 'NO REGISTRADO(A)';

    // Reemplazar caracteres problemáticos
    $from = ['á','é','í','ó','ú','ñ','Á','É','Í','Ó','Ú','Ñ','ü','Ü','"',"'",','];
    $to   = ['A','E','I','O','U','N','A','E','I','O','U','N','U','U','','',''];

    return strtoupper(str_replace($from, $to, $texto));
}