<?php

declare(strict_types=1);

use App\Fabricas\FabricaConexion;

require_once __DIR__ . '/../src/configuracion.php';

echo "Iniciando migración del sistema de préstamos...\n";

try {
  $pdo = FabricaConexion::crear();

  // Agregar columna salario_quincenal si no existe
  echo "1. Agregando columna salario_quincenal a tabla miembros...\n";
  try {
    $pdo->exec("ALTER TABLE miembros ADD COLUMN salario_quincenal DECIMAL(10, 2) DEFAULT NULL");
    echo "   ✓ Columna salario_quincenal agregada\n";
  } catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
      echo "   ✓ Columna salario_quincenal ya existe\n";
    } else {
      throw $e;
    }
  }

  // Eliminar tablas antiguas de préstamos si existen
  echo "2. Eliminando tablas antiguas de préstamos...\n";
  $pdo->exec("DROP TABLE IF EXISTS prestamos_rechazados");
  $pdo->exec("DROP TABLE IF EXISTS prestamos_aprobados");
  echo "   ✓ Tablas antiguas eliminadas\n";

  // Eliminar tabla solicitudes_prestamos antigua si existe
  echo "3. Eliminando tabla solicitudes_prestamos antigua...\n";
  $pdo->exec("DROP TABLE IF EXISTS solicitudes_prestamos");
  echo "   ✓ Tabla antigua eliminada\n";

  // Crear nuevas tablas
  echo "4. Creando nueva tabla solicitudes_prestamos...\n";
  $pdo->exec("
        CREATE TABLE solicitudes_prestamos (
            id                    INT AUTO_INCREMENT PRIMARY KEY,
            monto_solicitado      DECIMAL(10, 2) NOT NULL,
            monto_aprobado        DECIMAL(10, 2) DEFAULT NULL,
            plazo_meses           INT            NOT NULL,
            tipo_descuento        ENUM('quincenal', 'aguinaldo', 'prima_vacacional') NOT NULL,
            justificacion         TEXT,
            recibo_nomina         VARCHAR(255) NOT NULL,
            estado                ENUM('pendiente', 'aprobado', 'rechazado', 'lista_espera', 'pagare_pendiente', 'activo', 'pagado') DEFAULT 'pendiente',
            fecha_solicitud       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            fecha_respuesta       TIMESTAMP NULL,
            motivo_rechazo        TEXT NULL,
            pagare_firmado        VARCHAR(255) NULL,
            fecha_pagare          TIMESTAMP NULL,
            tasa_interes          DECIMAL(5, 2) DEFAULT 0.00,
            fk_miembro            INT NOT NULL,
            fk_aprobador          INT NULL,
            CONSTRAINT fk_solicitud_miembro
                FOREIGN KEY (fk_miembro)
                    REFERENCES miembros (id)
                    ON DELETE CASCADE,
            CONSTRAINT fk_solicitud_aprobador
                FOREIGN KEY (fk_aprobador)
                    REFERENCES miembros (id)
                    ON DELETE SET NULL,
            INDEX idx_fk_miembro (fk_miembro),
            INDEX idx_estado (estado),
            INDEX idx_fecha_solicitud (fecha_solicitud)
        )
    ");
  echo "   ✓ Tabla solicitudes_prestamos creada\n";

  echo "5. Creando tabla pagos_prestamos...\n";
  $pdo->exec("
        CREATE TABLE pagos_prestamos (
            id                INT AUTO_INCREMENT PRIMARY KEY,
            numero_pago       INT NOT NULL,
            monto_pago        DECIMAL(10, 2) NOT NULL,
            fecha_programada  DATE NOT NULL,
            fecha_pago        DATE NULL,
            estado            ENUM('pendiente', 'pagado', 'vencido') DEFAULT 'pendiente',
            fk_solicitud      INT NOT NULL,
            CONSTRAINT fk_pago_solicitud
                FOREIGN KEY (fk_solicitud)
                    REFERENCES solicitudes_prestamos (id)
                    ON DELETE CASCADE,
            INDEX idx_fk_solicitud (fk_solicitud),
            INDEX idx_fecha_programada (fecha_programada),
            INDEX idx_estado (estado)
        )
    ");
  echo "   ✓ Tabla pagos_prestamos creada\n";

  echo "6. Creando tabla historial_prestamos...\n";
  $pdo->exec("
        CREATE TABLE historial_prestamos (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            accion          VARCHAR(100) NOT NULL,
            descripcion     TEXT,
            fecha           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            fk_solicitud    INT NOT NULL,
            fk_usuario      INT NULL,
            CONSTRAINT fk_historial_solicitud
                FOREIGN KEY (fk_solicitud)
                    REFERENCES solicitudes_prestamos (id)
                    ON DELETE CASCADE,
            CONSTRAINT fk_historial_usuario
                FOREIGN KEY (fk_usuario)
                    REFERENCES usuarios (id)
                    ON DELETE SET NULL,
            INDEX idx_fk_solicitud (fk_solicitud),
            INDEX idx_fecha (fecha)
        )
    ");
  echo "   ✓ Tabla historial_prestamos creada\n";

  // Crear directorios necesarios
  echo "7. Creando directorios necesarios...\n";
  $directorios = [
    __DIR__ . '/../temp/recibos-nomina',
    __DIR__ . '/../temp/pagares-firmados'
  ];

  foreach ($directorios as $directorio) {
    if (!is_dir($directorio)) {
      mkdir($directorio, 0755, true);
      echo "   ✓ Directorio creado: $directorio\n";
    } else {
      echo "   ✓ Directorio ya existe: $directorio\n";
    }
  }

  echo "\n✅ Migración completada exitosamente!\n";
  echo "\nSiguientes pasos:\n";
  echo "1. Actualizar los salarios quincenales de los miembros en: aplicacion/prestamos/actualizar-salario.php\n";
  echo "2. El sistema de préstamos está listo para usar\n";
} catch (Exception $e) {
  echo "\n❌ Error durante la migración: " . $e->getMessage() . "\n";
  exit(1);
}
