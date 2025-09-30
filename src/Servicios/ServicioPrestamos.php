<?php

declare(strict_types=1);

namespace App\Servicios;

use App\Entidades\SolicitudPrestamo;
use App\Entidades\PagoPrestamo;
use App\Entidades\Miembro;
use PDO;

class ServicioPrestamos
{
  private PDO $pdo;

  public function __construct(PDO $pdo)
  {
    $this->pdo = $pdo;
  }

  public function crearSolicitud(SolicitudPrestamo $solicitud): int
  {
    $sql = "INSERT INTO solicitudes_prestamos 
                (monto_solicitado, plazo_meses, tipo_descuento, justificacion, 
                 recibo_nomina, fk_miembro) 
                VALUES (?, ?, ?, ?, ?, ?)";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([
      $solicitud->montoSolicitado,
      $solicitud->plazoMeses,
      $solicitud->tipoDescuento,
      $solicitud->justificacion,
      $solicitud->reciboNomina,
      $solicitud->fkMiembro
    ]);

    $solicitudId = (int)$this->pdo->lastInsertId();

    // Registrar en historial
    $this->registrarHistorial(
      $solicitudId,
      'solicitud_creada',
      'Solicitud de préstamo creada por $' . number_format($solicitud->montoSolicitado, 2)
    );

    return $solicitudId;
  }

  public function obtenerSolicitudesPendientes(): array
  {
    $sql = "SELECT s.*, m.nombre, m.apellidos, m.salario_quincenal 
                FROM solicitudes_prestamos s 
                INNER JOIN miembros m ON s.fk_miembro = m.id 
                WHERE s.estado = 'pendiente' 
                ORDER BY s.fecha_solicitud ASC";

    $stmt = $this->pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public function obtenerSolicitudesPorMiembro(int $miembroId): array
  {
    $sql = "SELECT * FROM solicitudes_prestamos 
                WHERE fk_miembro = ? 
                ORDER BY fecha_solicitud DESC";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([$miembroId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public function aprobarSolicitud(int $solicitudId, float $montoAprobado, int $aprobadorId): bool
  {
    $this->pdo->beginTransaction();

    try {
      // Actualizar solicitud
      $sql = "UPDATE solicitudes_prestamos 
                    SET monto_aprobado = ?, estado = 'pagare_pendiente', 
                        fecha_respuesta = NOW(), fk_aprobador = ? 
                    WHERE id = ?";

      $stmt = $this->pdo->prepare($sql);
      $stmt->execute([$montoAprobado, $aprobadorId, $solicitudId]);

      // Generar tabla de pagos
      $this->generarTablaPagos($solicitudId);

      // Registrar en historial
      $this->registrarHistorial(
        $solicitudId,
        'solicitud_aprobada',
        'Solicitud aprobada por $' . number_format($montoAprobado, 2)
      );

      $this->pdo->commit();
      return true;
    } catch (\Exception $e) {
      $this->pdo->rollBack();
      return false;
    }
  }

  public function rechazarSolicitud(int $solicitudId, string $motivo, int $rechazadorId): bool
  {
    $sql = "UPDATE solicitudes_prestamos 
                SET estado = 'rechazado', motivo_rechazo = ?, 
                    fecha_respuesta = NOW(), fk_aprobador = ? 
                WHERE id = ?";

    $stmt = $this->pdo->prepare($sql);
    $resultado = $stmt->execute([$motivo, $rechazadorId, $solicitudId]);

    if ($resultado) {
      $this->registrarHistorial($solicitudId, 'solicitud_rechazada', $motivo);
    }

    return $resultado;
  }

  public function ponerEnListaEspera(int $solicitudId, int $administradorId): bool
  {
    $sql = "UPDATE solicitudes_prestamos 
                SET estado = 'lista_espera', fecha_respuesta = NOW(), fk_aprobador = ? 
                WHERE id = ?";

    $stmt = $this->pdo->prepare($sql);
    $resultado = $stmt->execute([$administradorId, $solicitudId]);

    if ($resultado) {
      $this->registrarHistorial(
        $solicitudId,
        'lista_espera',
        'Solicitud puesta en lista de espera para la siguiente quincena'
      );
    }

    return $resultado;
  }

  public function subirPagareFirmado(int $solicitudId, string $rutaPagare): bool
  {
    $sql = "UPDATE solicitudes_prestamos 
                SET pagare_firmado = ?, fecha_pagare = NOW(), estado = 'activo' 
                WHERE id = ? AND estado = 'pagare_pendiente'";

    $stmt = $this->pdo->prepare($sql);
    $resultado = $stmt->execute([$rutaPagare, $solicitudId]);

    if ($resultado) {
      $this->registrarHistorial(
        $solicitudId,
        'pagare_subido',
        'Pagaré firmado subido al sistema'
      );
    }

    return $resultado;
  }

  public function obtenerPrestamosActivosPorMiembro(int $miembroId): array
  {
    $sql = "SELECT * FROM solicitudes_prestamos 
                WHERE fk_miembro = ? AND estado IN ('activo', 'pagare_pendiente') 
                ORDER BY fecha_solicitud DESC";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([$miembroId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public function validarCapacidadPago(int $miembroId, float $montoSolicitado): array
  {
    // Obtener salario del miembro
    $sql = "SELECT salario_quincenal FROM miembros WHERE id = ?";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([$miembroId]);
    $miembro = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$miembro || !$miembro['salario_quincenal']) {
      return [
        'valido' => false,
        'mensaje' => 'No se ha registrado el salario quincenal del miembro'
      ];
    }

    $salarioQuincenal = (float)$miembro['salario_quincenal'];
    $montoMaximo = $salarioQuincenal * 0.70;

    // Obtener préstamos activos
    $prestamosActivos = $this->obtenerPrestamosActivosPorMiembro($miembroId);
    $montoActivo = array_sum(array_column($prestamosActivos, 'monto_aprobado'));

    $disponible = $montoMaximo - $montoActivo;

    return [
      'valido' => $montoSolicitado <= $disponible,
      'salario_quincenal' => $salarioQuincenal,
      'monto_maximo' => $montoMaximo,
      'monto_activo' => $montoActivo,
      'disponible' => $disponible,
      'mensaje' => $montoSolicitado > $disponible
        ? "El monto solicitado excede la capacidad de pago. Disponible: $" . number_format($disponible, 2)
        : 'Capacidad de pago válida'
    ];
  }

  private function generarTablaPagos(int $solicitudId): void
  {
    // Obtener datos de la solicitud
    $sql = "SELECT * FROM solicitudes_prestamos WHERE id = ?";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([$solicitudId]);
    $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$solicitud) return;

    $solicitudObj = new SolicitudPrestamo(
      id: $solicitud['id'],
      montoAprobado: (float)$solicitud['monto_aprobado'],
      plazoMeses: (int)$solicitud['plazo_meses'],
      tipoDescuento: $solicitud['tipo_descuento'],
      tasaInteres: (float)$solicitud['tasa_interes']
    );

    $corrida = $solicitudObj->generarCorridaFinanciera();

    // Insertar pagos en la tabla
    $sqlInsert = "INSERT INTO pagos_prestamos 
                      (numero_pago, monto_pago, fecha_programada, fk_solicitud) 
                      VALUES (?, ?, ?, ?)";
    $stmtInsert = $this->pdo->prepare($sqlInsert);

    foreach ($corrida as $pago) {
      $stmtInsert->execute([
        $pago['numero'],
        $pago['monto'],
        $pago['fecha'],
        $solicitudId
      ]);
    }
  }

  private function registrarHistorial(int $solicitudId, string $accion, string $descripcion): void
  {
    $sql = "INSERT INTO historial_prestamos (fk_solicitud, accion, descripcion) 
                VALUES (?, ?, ?)";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([$solicitudId, $accion, $descripcion]);
  }

  public function obtenerHistorialSolicitud(int $solicitudId): array
  {
    $sql = "SELECT h.*, u.correo as usuario_correo 
                FROM historial_prestamos h 
                LEFT JOIN usuarios u ON h.fk_usuario = u.id 
                WHERE h.fk_solicitud = ? 
                ORDER BY h.fecha DESC";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([$solicitudId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public function generarPagare(int $solicitudId): array
  {
    $sql = "SELECT s.*, m.nombre, m.apellidos, m.nss, m.curp 
                FROM solicitudes_prestamos s 
                INNER JOIN miembros m ON s.fk_miembro = m.id 
                WHERE s.id = ? AND s.estado = 'pagare_pendiente'";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([$solicitudId]);
    $datos = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$datos) {
      return [];
    }

    // Generar corrida financiera para el pagaré
    $solicitud = new SolicitudPrestamo(
      montoAprobado: (float)$datos['monto_aprobado'],
      plazoMeses: (int)$datos['plazo_meses'],
      tipoDescuento: $datos['tipo_descuento'],
      tasaInteres: (float)$datos['tasa_interes']
    );

    $corrida = $solicitud->generarCorridaFinanciera();

    return [
      'solicitud' => $datos,
      'corrida_financiera' => $corrida,
      'fecha_generacion' => date('Y-m-d H:i:s'),
      'numero_pagare' => 'PAG-' . str_pad((string)$solicitudId, 6, '0', STR_PAD_LEFT)
    ];
  }
}
