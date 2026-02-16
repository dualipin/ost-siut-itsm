<?php

declare(strict_types=1);

use App\Manejadores\Sesion;
use App\Servicios\ServicioLatte;

require_once __DIR__ . '/../../src/configuracion.php';

\App\Manejadores\SesionProtegida::proteger(['administrador', 'finanzas']);
$pdo = \App\Configuracion\MysqlConexion::conexion();

/* Guardar movimiento manual */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tipo'], $_POST['concepto'], $_POST['monto'])) {
    $tipo = $_POST['tipo'];
    $concepto = trim($_POST['concepto']);
    $monto = (float)$_POST['monto'];
    $fecha = $_POST['fecha'] ?: date('Y-m-d');
    $obs = trim($_POST['observacion'] ?? '');
    $ins = $pdo->prepare("INSERT INTO movimientos_finanzas (tipo,concepto,monto,fecha,observacion) VALUES (?,?,?,?,?)");
    $ins->execute([$tipo, $concepto, $monto, $fecha, $obs]);
    header("Location: finanzas.php");
    exit;
}

/* ===== CÁLCULOS EN CÓDIGO ===== */

/* 1. Ingresos por pagos "pagados" del mes */
$ingresoPagos = $pdo->query(
        "SELECT COALESCE(SUM(monto_pago),0)
                             FROM pagos_prestamos
                             WHERE estado = 'pagado'
                               AND MONTH(fecha_pago) = MONTH(CURDATE())
                               AND YEAR(fecha_pago)  = YEAR(CURDATE())"
)->fetchColumn();

/* 2. Egresos por préstamos "activos" del mes */
$egresoPrestamos = $pdo->query(
        "SELECT COALESCE(SUM(monto_aprobado),0)
                                FROM solicitudes_prestamos
                                WHERE estado = 'activo'
                                  AND MONTH(fecha_respuesta) = MONTH(CURDATE())
                                  AND YEAR(fecha_respuesta)  = YEAR(CURDATE())"
)->fetchColumn();

/* 3. Movimientos manuales del mes */
$manualIngreso = $pdo->query(
        "SELECT COALESCE(SUM(monto),0) FROM movimientos_finanzas WHERE tipo='ingreso' AND MONTH(fecha)=MONTH(CURDATE())"
)->fetchColumn();
$manualEgreso = $pdo->query(
        "SELECT COALESCE(SUM(monto),0) FROM movimientos_finanzas WHERE tipo='egreso'  AND MONTH(fecha)=MONTH(CURDATE())"
)->fetchColumn();

/* 4. Totales del mes */
$ingresoMes = (float)$ingresoPagos + (float)$manualIngreso;
$egresoMes = (float)$egresoPrestamos + (float)$manualEgreso;
$saldoMes = $ingresoMes - $egresoMes;

/* 5. Pendiente y pagado (sin cambios) */
$pendienteCobro = $pdo->query(
        "SELECT COALESCE(SUM(monto_pago),0) FROM pagos_prestamos WHERE estado='pendiente'"
)->fetchColumn();
$pagadoMes = $pdo->query(
        "SELECT COALESCE(SUM(monto_pago),0) FROM pagos_prestamos WHERE estado='pagado' AND MONTH(fecha_pago)=MONTH(CURDATE())"
)->fetchColumn();

/* 6. Movimientos manuales para tabla */
$movimientos = $pdo->query(
        "SELECT id,tipo,concepto,monto,fecha,observacion 
                            FROM movimientos_finanzas 
                            WHERE MONTH(fecha)=MONTH(CURDATE()) 
                            ORDER BY fecha DESC, id DESC"
)->fetchAll();

ServicioLatte::renderizar(__DIR__ . '/finanzas.latte', [
        'ingresoMes' => $ingresoMes,
        'egresoMes' => $egresoMes,
        'saldoMes' => $saldoMes,
        'pendienteCobro' => $pendienteCobro,
        'pagadoMes' => $pagadoMes,
        'movimientos' => $movimientos,
        'ingresoPagos' => $ingresoPagos,
        'egresoPrestamos' => $egresoPrestamos,
]);
