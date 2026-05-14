# Análisis: Generación de PDF para Simulador de Préstamos

## 1. CONTROLADOR PRINCIPAL - POST Request

### Archivo: [public/simulador.php](public/simulador.php)
- **Línea 15**: Detecta si la salida debe ser PDF: `$salidaPdf = $form->input('output', 'html') === 'pdf';`
- **Línea 45 (POST block)**: Procesa el formulario POST del simulador
- **Líneas 510-530**: Genera el PDF (código principal)

```php
// Línea 15: Detecta solicitud de PDF
$salidaPdf = $form->input('output', 'html') === 'pdf';

// Línea 45: Inicia procesamiento POST
if ($form->method() == "POST") {
    // ... lógica de cálculo ...
    
    // Líneas 510-530: Renderiza y genera PDF
    if ($salidaPdf) {
        $resumen = [
            'montoTotal' => $montoPrestamo,
            'interesTotal' => $interesTotalGlobal,
            'pagoTotal' => $pagoTotalGlobal,
        ];

        $html = $renderer->renderToString('./portal/prestamos/pdf-simulados.latte', [
            'prestamistaNombre' => $prestamistaNombre,
            'montoPrestamo' => $montoPrestamo,
            'mesesPagar' => $mesesPagar,
            'diasAdicionales' => $diasAdicionales,
            'tasaInteresMensual' => $tasaInteresMensual,
            'fechaOtorgamiento' => $fechaOtorgamiento,
            'formasPago' => $formasPago,
            'resumenAnual' => $resumenAnual,
            'corridaPrestaciones' => $corridaPrestaciones,
            'corridasPorTipo' => $corridasPorTipo,
            'resumen' => $resumen,
            'fecha_simulacion' => (new DateTimeImmutable())->format('d/m/Y H:i'),
        ]);

        $pdf = new Dompdf();
        $pdf->loadHtml($html);
        $pdf->setPaper('Letter');
        $options = $pdf->getOptions();
        $options->setIsRemoteEnabled(true);
        $options->setIsHtml5ParserEnabled(true);
        $pdf->setOptions($options);
        $pdf->render();

        $pdf->stream('simulacion-prestamos.pdf', ['Attachment' => true]);
        exit;
    }
}
```

### Datos recibidos del formulario (líneas 230-242):
```php
$montoPrestamo = max(0.0, (float) $form->input("monto_prestamo", 0));
$fechaOtorgamiento = $form->input("fecha_otorgamiento", date('Y-m-d'));
$mesesPagar = max(0, (int) $form->input("meses_pagar", 0));
$diasAdicionales = max(0, (int) $form->input("dias_adicionales", 0));
$tasaInteresMensual = (float) $form->input("tasa_interes", 0);
$descuentos = json_decode($form->input("descuentos_json"), true) ?? [];
$prestamistaNombre = $form->input("prestamista_nombre", $prestamistaNombre);
```

---

## 2. SERVICIOS Y CLASES DE GENERACIÓN DE PDF

### Clase Principal: DompdfLoanPdfGenerator
**Archivo**: [app/Modules/Loan/Infrastructure/Service/DompdfLoanPdfGenerator.php](app/Modules/Loan/Infrastructure/Service/DompdfLoanPdfGenerator.php)

Esta clase genera PDFs para préstamos reales, pero NO se usa para el simulador. Está disponible para referencias futuras:

```php
namespace App\Modules\Loan\Infrastructure\Service;

use App\Infrastructure\Templating\RendererInterface;
use Dompdf\Dompdf;

final readonly class DompdfLoanPdfGenerator implements PdfGeneratorInterface
{
    public function generatePromissoryNote(Loan $loan, array $userData): string { ... }
    public function generateConsentForm(Loan $loan, array $userData): string { ... }
    public function generateApplicationForm(Loan $loan, array $userData, array $paymentConfigs): string { ... }
    public function generateAmortizationSchedule(Loan $loan, array $userData, array $amortizationRows): string { ... }
}
```

### Configuración Dompdf
**Archivo**: [config/definitions.php](config/definitions.php) - Líneas 114-122

```php
Dompdf::class => function () {
    $dompdf = new Dompdf();
    $dompdf->setPaper("Letter");
    $options = $dompdf->getOptions();
    $options->setIsRemoteEnabled(true);
    $options->setIsHtml5ParserEnabled(true);
    $dompdf->setOptions($options);
    return $dompdf;
},
```

---

## 3. CÁLCULO DE INTERESES EN EL SIMULADOR

### Archivo: [public/simulador.php](public/simulador.php)

#### A) MÉTODO ALEMÁN SIMPLE (Interés Simple - Prestaciones Periódicas)
**Líneas 140-175** - Función `$buildGermanSimpleSchedule`

```php
$buildGermanSimpleSchedule = static function (DateTimeImmutable $fechaBase, array $prestacion, float $tasaMensual): array {
    $monto = max(0.0, (float)($prestacion['monto'] ?? 0));
    $cantidad = max(1, (int)($prestacion['cantidad'] ?? 1));
    $frecuenciaDias = max(1, (int)($prestacion['frecuenciaDias'] ?? 15));
    
    // FÓRMULA INTERÉS SIMPLE:
    // tasaPeriodoSimple = (tasaMensual / 100) × (frecuenciaDias / 30)
    $tasaPeriodoSimple = ($tasaMensual / 100) * ($frecuenciaDias / 30);
    
    $capitalFijo = $monto / $cantidad;
    $saldo = $monto;
    $rows = [];

    for ($i = 1; $i <= $cantidad; $i++) {
        // CÁLCULO DE INTERÉS:
        // interes = saldo × tasaPeriodoSimple
        $interes = $saldo * $tasaPeriodoSimple;
        
        $capital = min($capitalFijo, $saldo);
        $pago = $capital + $interes;
        $saldo -= $capital;
        if ($saldo < 0) $saldo = 0;

        $rows[] = [
            'periodo' => $i,
            'capital' => $capital,
            'interes' => $interes,
            'pago' => $pago,
            'saldo' => $saldo,
            'fecha' => $fecha,
        ];
    }
    return $rows;
};
```

**Fórmula de Cálculo**:
- Tasa por período: `i_período = (tasa_anual / 100) × (días_período / 30)`
- Interés: `Interés = Saldo_actual × i_período`
- Pago: `Pago = Capital_fijo + Interés`
- Nuevo saldo: `Saldo = Saldo_anterior - Capital`

**Ejemplo**: 
- Monto: $1,000, Tasa: 6% anual, Frecuencia: 15 días, Cantidad: 4 períodos
- Capital fijo: $250/período
- i_período = (6 / 100) × (15 / 30) = 0.06 × 0.5 = 0.03 (3% por período)
- Período 1: Interés = $1,000 × 0.03 = $30, Pago = $250 + $30 = $280

---

#### B) MÉTODO COMPUESTO (Interés Compuesto - Prestaciones No Periódicas)
**Líneas 176-221** - Función `$buildCompoundSchedule`

```php
$buildCompoundSchedule = static function (DateTimeImmutable $fechaBase, array $prestacion, float $tasaMensual): array {
    $monto = max(0.0, (float)($prestacion['monto'] ?? 0));
    $fechaObjetivo = DateTimeImmutable::createFromFormat('Y-m-d', (string)($prestacion['fecha'] ?? '')) ?: $fechaBase;

    $dias = max(0, (int)$fechaBase->diff($fechaObjetivo)->days);
    $numQuincenas = max(1, (int)ceil($dias / 15));
    
    // FÓRMULA INTERÉS COMPUESTO:
    // tasaQuincenalCompuesta = (1 + tasa_anual/100)^0.5 - 1
    // tasa_anual^0.5 porque 0.5 quincenas = 1 mes
    $tasaQuincenalCompuesta = pow(1 + ($tasaMensual / 100), 0.5) - 1;

    $saldo = $monto;
    $rows = [];

    for ($i = 1; $i <= $numQuincenas; $i++) {
        // CÁLCULO DE INTERÉS COMPUESTO:
        // interes = saldo × tasaQuincenalCompuesta
        $interes = $saldo * $tasaQuincenalCompuesta;
        $saldoCapitalizado = $saldo + $interes;

        if ($i === $numQuincenas) {
            // Último período: pago total (capital + interés acumulado)
            $capital = $saldoCapitalizado;
            $pago = $capital;
            $saldo = 0.0;
        } else {
            // Períodos intermedios: sólo se capitaliza el interés
            $saldo = $saldoCapitalizado;
            $capital = 0.0;
            $pago = 0.0;
        }

        $rows[] = [
            'periodo' => $i,
            'capital' => $capital,
            'interes' => $interes,
            'pago' => $pago,
            'saldo' => $saldo,
            'fecha' => $fechaPago,
        ];
    }
    return $rows;
};
```

**Fórmula de Cálculo**:
- Tasa quincenal: `i_quincenal = (1 + tasa_anual/100)^0.5 - 1`
- Interés cada período: `Interés = Saldo × i_quincenal`
- En el último período se paga todo: `Pago = Saldo_capitalizado`

**Ejemplo**:
- Monto: $1,000, Tasa: 6% anual, Días hasta pago: 30 (2 quincenas)
- i_quincenal = (1 + 0.06)^0.5 - 1 ≈ 0.02956 (2.956% por quincena)
- Período 1: Interés = $1,000 × 0.02956 = $29.56, Saldo capitalizado = $1,029.56
- Período 2: Interés = $1,029.56 × 0.02956 ≈ $30.44, Pago total = $1,060

---

## 4. TEMPLATES/VISTAS PARA TABLAS DE AMORTIZACIÓN EN PDF

### Template Principal PDF: [public/portal/prestamos/pdf-simulados.latte](public/portal/prestamos/pdf-simulados.latte)

**Estructura**:
1. Encabezado (líneas 1-5)
2. Datos resumidos del préstamo (líneas 9-13)
3. Tabla de formas de descuento (líneas 15-41)
4. Tabla de resumen anual (líneas 43-58)
5. Tabla de corrida de prestaciones (líneas 60-84)
6. **TABLA PRINCIPAL DE AMORTIZACIÓN** (líneas 86-140):

```latte
{foreach $corridasPorTipo as $grupo}
  <div style="font-size: 10px; margin: 6px 0 4px 0;">
    <strong>Prestación:</strong> {$grupo['prestacion']} |
    <strong>Tipo:</strong> {if $grupo['tipo'] === 'periodico'}Periódico{else}No periódico{/if} |
    <strong>Método:</strong> {$grupo['metodo']} |
    <strong>Monto base:</strong> ${number_format($grupo['montoBase'], 2, '.', ',')}
  </div>
  <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 9px;">
    <thead>
    <tr>
      <th style="border: 1px solid #ced4da; padding: 6px; text-align: center;">Periodo</th>
      <th style="border: 1px solid #ced4da; padding: 6px; text-align: right;">Pago Capital</th>
      <th style="border: 1px solid #ced4da; padding: 6px; text-align: right;">Interés</th>
      <th style="border: 1px solid #ced4da; padding: 6px; text-align: right;">Pago</th>
      <th style="border: 1px solid #ced4da; padding: 6px; text-align: right;">Saldo Final</th>
      <th style="border: 1px solid #ced4da; padding: 6px; text-align: center;">Fecha de pago</th>
    </tr>
    </thead>
    <tbody>
    {foreach $grupo['corrida'] as $row}
      <tr>
        <td style="border: 1px solid #ced4da; padding: 6px; text-align: center;">{$row['periodo']}</td>
        <td style="border: 1px solid #ced4da; padding: 6px; text-align: right;">${number_format($row['capital'], 2, '.', ',')}</td>
        <td style="border: 1px solid #ced4da; padding: 6px; text-align: right;">${number_format($row['interes'], 2, '.', ',')}</td>
        <td style="border: 1px solid #ced4da; padding: 6px; text-align: right;">${number_format($row['pago'], 2, '.', ',')}</td>
        <td style="border: 1px solid #ced4da; padding: 6px; text-align: right;">${number_format($row['saldo'], 2, '.', ',')}</td>
        <td style="border: 1px solid #ced4da; padding: 6px; text-align: center;">{$row['fecha']}</td>
      </tr>
    {/foreach}
    </tbody>
    <tfoot>
    <tr>
      <td colspan="2" style="border: 1px solid #ced4da; padding: 6px;"><strong>Totales</strong></td>
      <td style="border: 1px solid #ced4da; padding: 6px; text-align: right;"><strong>${number_format($grupo['resumen']['interesTotal'], 2, '.', ',')}</strong></td>
      <td style="border: 1px solid #ced4da; padding: 6px; text-align: right;"><strong>${number_format($grupo['resumen']['pagoTotal'], 2, '.', ',')}</strong></td>
      <td style="border: 1px solid #ced4da; padding: 6px; text-align: right;"><strong>${number_format($grupo['resumen']['saldoFinal'], 2, '.', ',')}</strong></td>
      <td style="border: 1px solid #ced4da; padding: 6px;"></td>
    </tr>
    </tfoot>
  </table>
{/foreach}
```

---

## 5. DATOS PASADOS AL PDF

### En simulador.php (líneas 515-521):

```php
$html = $renderer->renderToString('./portal/prestamos/pdf-simulados.latte', [
    'prestamistaNombre' => $prestamistaNombre,
    'montoPrestamo' => $montoPrestamo,
    'mesesPagar' => $mesesPagar,
    'diasAdicionales' => $diasAdicionales,
    'tasaInteresMensual' => $tasaInteresMensual,
    'fechaOtorgamiento' => $fechaOtorgamiento,
    'formasPago' => $formasPago,                      // Array de formas de pago
    'resumenAnual' => $resumenAnual,                  // Resumen por año
    'corridaPrestaciones' => $corridaPrestaciones,    // Corrida de prestaciones
    'corridasPorTipo' => $corridasPorTipo,           // ARRAY PRINCIPAL: Tablas amortización
    'resumen' => $resumen,                            // { montoTotal, interesTotal, pagoTotal }
    'fecha_simulacion' => (new DateTimeImmutable())->format('d/m/Y H:i'),
]);
```

### Estructura de `$corridasPorTipo` (líneas 498-514):
```php
$corridasPorTipo[] = [
    'prestacion' => (string)($prestacion['nombre'] ?? 'Prestación'),
    'tipo' => (string)($prestacion['tipo'] ?? 'no_periodico'),
    'metodo' => $esPeriodico ? 'Interés simple - Método Alemán' : 'Interés compuesto',
    'montoBase' => (float)($prestacion['monto'] ?? 0.0),
    'corrida' => $corridaTipo,  // Array de filas de amortización
    'resumen' => [
        'interesTotal' => $interesTotal,
        'pagoTotal' => $pagoTotal,
        'saldoFinal' => (float)$corridaTipo[count($corridaTipo) - 1]['saldo'],
    ],
];
```

### Estructura de cada fila en `$corrida` (Tabla de Amortización):
```php
[
    'periodo' => $i,                    // Número de período
    'capital' => $capital,              // Pago de capital
    'interes' => $interes,              // Interés calculado
    'pago' => $pago,                    // Pago total (capital + interés)
    'saldo' => $saldo,                  // Saldo final después del pago
    'fecha' => $fechaPago,              // Fecha de pago (Y-m-d)
]
```

---

## 6. INTEGRACIÓN CON AmortizationCalculator

**Archivo**: [app/Modules/Loan/Application/Service/AmortizationCalculator.php](app/Modules/Loan/Application/Service/AmortizationCalculator.php)

### Relación:
- **NO se usa directamente en el simulador** del public/simulador.php
- Se usa en los préstamos reales (`SubmitLoanApplicationUseCase`, `ValidateSignedDocumentsUseCase`)
- Contiene métodos similares al simulador pero con más robustez

### Métodos principales:
1. `calculateGermanSimple()` - Interés simple alemán para préstamos reales
2. `calculateCompound()` - Interés compuesto para prestaciones
3. `calculateByPaymentConfigurations()` - Construcción de tabla completa

**Ejemplo de uso en préstamo real** (línea 170 de ValidateSignedDocumentsUseCase):
```php
$generatedRows = $this->amortizationCalculator->calculateByPaymentConfigurations(
    $loan->approvedAmount(),
    new InterestRate($loan->approvedRate()),
    $validationDate,
    $paymentConfigs
);
```

---

## 7. FLUJO COMPLETO DE GENERACIÓN DE PDF

```
1. Usuario accede a public/simulador.php (GET)
   └─ Renderiza simulador.latte con formulario

2. Usuario completa formulario y hace POST a public/simulador.php
   ├─ Form data: descuentos_json, monto_prestamo, fecha_otorgamiento, tasa_interes, etc.
   └─ Variable $salidaPdf = true si output=pdf

3. Control del flujo (simulador.php línea 510):
   ├─ SI $salidaPdf === true:
   │  ├─ Calcula tablas de amortización (buildGermanSimpleSchedule o buildCompoundSchedule)
   │  ├─ Acumula intereses totales en $interesTotalGlobal
   │  ├─ Construye array $corridasPorTipo con todas las filas
   │  ├─ Renderiza HTML desde pdf-simulados.latte
   │  ├─ Crea instancia de Dompdf
   │  ├─ Carga HTML en Dompdf
   │  ├─ Renderiza a PDF
   │  └─ Stream al navegador: "simulacion-prestamos.pdf"
   │
   └─ SI $salidaPdf === false:
      ├─ Renderiza simulador_reporte.latte (versión HTML web)
      └─ Muestra resultado en el navegador

4. Descarga de PDF
   └─ Archivo: "simulacion-prestamos.pdf"
```

---

## 8. REFERENCIAS Y BÚSQUEDA DE SIMULADOR + PDF

### Archivos encontrados:
- [public/simulador.php](public/simulador.php) - Controlador principal ✓
- [public/portal/prestamos/pdf-simulados.latte](public/portal/prestamos/pdf-simulados.latte) - Template PDF ✓
- [public/portal/prestamos/simulador_reporte.latte](public/portal/prestamos/simulador_reporte.latte) - Template HTML web
- [public/simulador.latte](public/simulador.latte) - Formulario del simulador
- [app/Modules/Loan/Infrastructure/Service/DompdfLoanPdfGenerator.php](app/Modules/Loan/Infrastructure/Service/DompdfLoanPdfGenerator.php) - Servicio de PDF (préstamos reales)
- [config/definitions.php](config/definitions.php) - Configuración de Dompdf

### Búsquedas realizadas:
- ✓ "dompdf" - Encontrado en config y simulador.php
- ✓ "simulador.*pdf" - No coincidencias en regex (información explícita en código)
- ✓ "generate|render" con simulador - Encontrado en línea 519 (renderToString)
- ✓ "AmortizationCalculator" - Usado en préstamos reales, no en simulador

---

## RESUMEN FINAL

| Aspecto | Ubicación | Detalles |
|--------|-----------|---------|
| **Controlador POST** | [public/simulador.php:45](public/simulador.php#L45) | Procesa formulario POST |
| **Generación PDF** | [public/simulador.php:510-530](public/simulador.php#L510-L530) | Instancia Dompdf, renderiza, descarga |
| **Template PDF** | [public/portal/prestamos/pdf-simulados.latte](public/portal/prestamos/pdf-simulados.latte) | Tabla de amortización con Bootstrap 5 |
| **Método Alemán** | [public/simulador.php:140-175](public/simulador.php#L140-L175) | `i = Saldo × (tasa/100) × (días/30)` |
| **Método Compuesto** | [public/simulador.php:176-221](public/simulador.php#L176-L221) | `i_q = (1 + tasa/100)^0.5 - 1` |
| **Datos al PDF** | Array $corridasPorTipo | Contiene filas: periodo, capital, interés, pago, saldo, fecha |
| **AmortizationCalculator** | [app/Modules/Loan/Application/Service/AmortizationCalculator.php](app/Modules/Loan/Application/Service/AmortizationCalculator.php) | NO usado en simulador, usado en préstamos reales |
