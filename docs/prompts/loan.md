# Módulo de Préstamos del Sindicato

## Contexto del sistema existente

Estás añadiendo un **módulo nuevo** (`app/Modules/Loans`) a un sistema PHP ya en producción.
Respeta **todas** las convenciones del proyecto sin excepción.

### Stack
| Capa | Tecnología |
|---|---|
| Backend | PHP 8.x vanilla (sin frameworks) |
| Arquitectura | DDD modular — igual que `app/Modules/Auth` |
| Templating | Latte (`.latte`) |
| Frontend | Bootstrap 5 |
| Base de datos | MySQL — PDO via `PdoBaseRepository` |

### Arquitectura del módulo (espejo exacto de `Auth`)

```
app/Modules/Loans/
  LoansModule.php                         ← registra repos, servicios y casos de uso
  Application/
    Service/
      AmortizationCalculator.php          ← cálculos financieros (interés simple alemán / compuesto)
      LoanEventLogger.php                 ← auditoría de movimientos
      PdfGenerator.php                    ← generación de PDFs server-side (mPDF o TCPDF)
      FolioGenerator.php                  ← genera folio SIN-YYYY-NNN
    UseCase/
      SubmitLoanApplicationUseCase.php
      ReviewLoanApplicationUseCase.php
      ValidateSignedDocumentsUseCase.php
      RegisterPaymentUseCase.php
      RegisterExtraordinaryPaymentUseCase.php
      RegisterPicoUseCase.php
      RestructureLoanUseCase.php
      GenerateAccountStatementUseCase.php
  Domain/
    Entity/
      Loan.php
      AmortizationRow.php
      ExtraordinaryPayment.php
    ValueObject/
      Money.php
      InterestRate.php
    Repository/
      LoanRepositoryInterface.php
      AmortizationRepositoryInterface.php
      PaymentConfigRepositoryInterface.php
      LegalDocRepositoryInterface.php
      ReceiptRepositoryInterface.php
    Service/
      InterestCalculatorInterface.php
    Enum/
      LoanStatusEnum.php
      PaymentStatusEnum.php
      InterestMethodEnum.php
      ReceiptTypeEnum.php
    Exception/
      LoanNotFoundException.php
      InvalidLoanStatusException.php
  Infrastructure/
    Persistence/
      PdoLoanRepository.php
      PdoAmortizationRepository.php
      PdoPaymentConfigRepository.php
      PdoLegalDocRepository.php
      PdoReceiptRepository.php
    Pdf/
      MpdfPdfGenerator.php
```

### Roles existentes (de `RoleEnum.php` — NO modificar ese archivo)
```php
// App\Modules\Auth\Domain\Enum\RoleEnum
case Lider       = "lider";
case Admin       = "administrador";
case Agremiado   = "agremiado";
case NoAgremiado = "no_agremiado";
case Finanzas    = "finanzas";
```
- **Solicitan** préstamos: `Agremiado`, `NoAgremiado`, `Lider`, `Admin`, `Finanzas`
- **Revisan / aprueban / validan**: `Admin`, `Finanzas`, `Lider`

---

## Base de datos (tablas ya definidas — NO recrear)

Las tablas siguientes ya existen. Úsalas exactamente como están:

```
cat_income_types            ← catálogo de tipos de ingreso (quincena, aguinaldo, bonos…)
loans                       ← préstamos (ciclo de vida completo)
loan_payment_configuration  ← configuración de pagos por fuente (nómina / prestación)
loan_legal_documents        ← documentos legales generados y firmados
loan_amortization           ← corrida financiera quincenal
loan_extraordinary_payments ← pagos anticipados / liquidaciones
loan_restructurings         ← historial de reestructuraciones
loan_receipts               ← comprobantes de cada movimiento
```

Campos clave a respetar:

| Tabla | Campos relevantes |
|---|---|
| `loans` | `folio` (SIN-2025-001), `status` varchar(30), `applied_interest_rate`, `daily_default_rate`, `disbursement_date` (inicio de devengo de intereses), `finance_signatory`, `lender_signatory` |
| `loan_amortization` | `scheduled_date`, `payment_status`, `days_overdue`, `generated_default_interest`, `table_version`, `active` |
| `loan_payment_configuration` | `interest_method` ('simple_aleman' \| 'compuesto'), `income_type_id` |
| `loan_receipts` | `receipt_type` ('desembolso', 'pago_regular', 'pago_extraordinario', 'cargo_moratorio', 'ajuste') |
| `loan_restructurings` | `original_loan_id` → `new_loan_id` |

---

## Flujos de usuario paso a paso

### Flujo 1 — Solicitud (roles: Agremiado, NoAgremiado, Lider, Admin, Finanzas)

1. El empleado abre el formulario de nueva solicitud.
2. Completa:
   - Monto solicitado.
   - **Forma de pago** (puede ser mixta — nómina + una o varias prestaciones):
     - `Nómina` → número de meses / quincenas; el plazo debe cerrar **antes del 31 de diciembre** del año en curso.
     - `Prestaciones` → selecciona una o varias entradas de `cat_income_types`; la fecha de cobro es la fecha de cada prestación.
     - Sube un documento PDF con su último recibo de nómina por cada forma de pago (validación: solo PDF, máximo 5MB).
3. El simulador se recalcula en tiempo real (AJAX o recarga parcial) y muestra la corrida financiera estimada **antes** de enviar (ver § Simulador).
4. Al enviar: `loans.status` → `'solicitado'`.

---

### Flujo 2 — Revisión documental (roles: Admin, Finanzas, Lider)

1. Lista filtrable de solicitudes con `status = 'solicitado'`.
2. El revisor puede:
   - **Aprobar** → define `approved_amount`, plazo y `applied_interest_rate` (puede diferir de la tasa estándar). `status = 'aprobado'`. El sistema:
     - Rellena `loans.finance_signatory` y `loans.lender_signatory` desde la BD.
     - Genera y almacena en `loan_legal_documents`: **Pagaré PDF** + **Anuencia de descuento PDF** + **Formato solicitud PDF**.
     - Registra `loans.document_generation_date`.
   - **Rechazar** → `status = 'rechazado'`, guarda `rejection_reason`.
   - **Lista de espera** → `status = 'en_espera'`.
3. El solicitante puede ver el estado y, si fue aprobado, descargar los documentos.

---

### Flujo 3 — Firma y validación de documentos (Empleado → Admin/Finanzas)

1. El empleado descarga Pagaré, Anuencia y Formato de solicitud, los firma físicamente y los sube al sistema.
   - Se actualiza `loan_legal_documents.user_signature_url` y `user_signature_date`.
2. El revisor valida cada documento:
   - **Validado** → `validated_by_finance = TRUE`, `validation_date = NOW()`. Cuando todos los documentos requeridos estén validados: `loans.status = 'activo'`, `loans.disbursement_date = NOW()` (fecha desde la que devengan intereses).
   - **Rechazado** → `document_status = 'rechazado'`, `document_observations` con el motivo. El empleado corrige y vuelve a subir.

---

### Flujo 4 — Seguimiento (todos los roles con acceso al préstamo)

1. El empleado ve su corrida financiera activa (`loan_amortization` donde `active = TRUE` y `table_version` = la más reciente).
2. Ve próxima quincena y saldo en `loans.outstanding_balance`.
3. Puede solicitar liquidación anticipada (activa Flujo 6c).
4. Puede ver su **estado de cuenta** (todos sus préstamos: activos, liquidados, reestructurados).

---

### Flujo 5 — Registro de pagos regulares (roles: Admin, Finanzas)

1. Selecciona la quincena en `loan_amortization`.
2. Registra `actual_payment_date`, `actual_paid_amount`, `paid_by`.
3. `payment_status = 'pagado'`. Actualiza `loans.outstanding_balance`.
4. Genera comprobante en `loan_receipts` (`receipt_type = 'pago_regular'`).

---

### Flujo 6 — Casos especiales

#### 6a. Pico (quincena no pagada)
- La quincena pasa a `payment_status = 'pico'`.
- Se calculan `days_overdue` y `generated_default_interest` con `loans.daily_default_rate`.
- `AmortizationCalculator` regenera la tabla desde esa quincena: suma el interés moratorio al saldo insoluto y recalcula las cuotas restantes.
- Incrementa `table_version`; filas anteriores quedan `active = FALSE`.
- Genera comprobante `receipt_type = 'cargo_moratorio'`.

#### 6b. Pago anticipado / abono a capital
- Se registra en `loan_extraordinary_payments` (`payment_type = 'anticipo'` o `'abono_capital'`).
- Aplicación: primero a intereses moratorios pendientes, luego a capital.
- `AmortizationCalculator` regenera la tabla e incrementa `table_version`.
- Genera comprobante `receipt_type = 'pago_extraordinario'`.

#### 6c. Liquidación total
- `payment_type = 'liquidacion_total'` en `loan_extraordinary_payments`.
- Calcula el saldo exacto al día (interés devengado hasta hoy).
- `loans.status = 'liquidado'`, `loans.total_liquidation_date = NOW()`.
- Genera comprobante `receipt_type = 'pago_extraordinario'`.

#### 6d. Reestructuración
- Cierra el préstamo original (`loans.requires_restructuring = TRUE`, `status = 'reestructurado'`).
- Crea nuevo registro en `loans` con `original_loan_id` apuntando al original.
- Registra en `loan_restructurings` (saldo insoluto, interés pendiente, interés moratorio, nuevo monto, nueva tasa, nuevo plazo).
- El nuevo préstamo entra al **Flujo 2** para aprobación.
- Genera comprobante `receipt_type = 'ajuste'`.

---

## Reglas de negocio y cálculos

### Tasas de interés estándar

El tipo de socio se determina por `RoleEnum` + campo `is_saver` (ahorrador) en `users`:

| Tipo | Tasa anual |
|---|---|
| `Agremiado` + ahorrador | 6 % |
| `Agremiado` + no ahorrador | 7.5 % |
| `NoAgremiado` + ahorrador | 8 % |
| `NoAgremiado` + no ahorrador | 9.5 % |

> El revisor puede sobreescribir `applied_interest_rate` libremente al aprobar. El sistema guarda siempre la tasa pactada; no se recalcula automáticamente.

---

### Cálculo: Interés simple — Método alemán (`interest_method = 'simple_aleman'`)

Los intereses devengan desde `loans.disbursement_date`.

**Período inicial (días adicionales)**

Desde `disbursement_date` hasta la primera fecha de quincena (día 15 o último día del mes):

```
interés_días_adicionales = saldo_insoluto × (tasa_anual / 365) × días_adicionales
```

**Quincenas regulares**

```
pago_capital       = monto_aprobado / total_quincenas
interés_quincenal  = saldo_insoluto × (tasa_anual / 24)
pago_quincenal     = pago_capital + interés_quincenal
saldo_final        = saldo_insoluto − pago_capital
```

**Fechas de pago:** siempre **día 15** o **último día del mes**.

---

### Cálculo: Interés compuesto (`interest_method = 'compuesto'`)

El monto se cobra de una sola vez en la fecha de la prestación (`cat_income_types.tentative_payment_month` / `tentative_payment_day`).

```
monto_total = capital × (1 + tasa_anual / 365) ^ días_transcurridos
```

`días_transcurridos` = desde `disbursement_date` hasta la fecha de la prestación.

---

### Interés moratorio (picos)

```
interés_moratorio = saldo_pendiente × daily_default_rate × days_overdue
```

`daily_default_rate` se define al aprobar y se guarda en `loans.daily_default_rate`.

---

## Simulador de préstamos

Mostrar antes de enviar la solicitud (modal o sección expandible). También disponible como PDF descargable.

| # | Campo obligatorio |
|---|---|
| a | Nombre completo del prestamista (`lender_signatory`) |
| b | Monto solicitado / aprobado |
| c | Tasa de interés aplicable |
| d | Quincenas + días adicionales (ej. "Quincenas: 11 — Días adicionales: 5") |
| e | Forma de pago y fuente de descuento (ej. _"Se descontará con Bono Día del Padre"_ / _"Se pagará con Aguinaldo — total al cierre del año: $X"_) |
| f | Tabla de corrida financiera quincenal (ver abajo) |
| g | **Sumatoria final:** total capital, total intereses, total pagado |
| h | Firmantes dinámicos leídos de BD: **Secretario de Finanzas** + **Prestamista** |

---

## Tabla de corrida financiera (quincenal)

```
| # Quincena | Pago Capital | Interés Quincenal | Pago Quincenal | Saldo Final | Fecha de Pago |
|------------|-------------|-------------------|----------------|-------------|---------------|
| 1          | $XXX.XX     | $XXX.XX           | $XXX.XX        | $XXX.XX     | 15/Ene/2025   |
| ...        |             |                   |                |             |               |
| TOTALES    | $XXX.XX     | $XXX.XX           | $XXX.XX        | —           | —             |
```

- Fechas: **día 15** o **último día del mes**.
- Se regenera ante cualquier evento (pico, pago anticipado, reestructura), incrementando `table_version`; filas anteriores quedan `active = FALSE`.

---

## Documentos PDF

Todos generados server-side con **domPDF**.
Firmantes leídos dinámicamente de `loans.finance_signatory` y `loans.lender_signatory`.

| Documento | Cuándo se genera | `document_type` | Firmantes |
|---|---|---|---|
| Pagaré | Al aprobar | `pagare` | Prestamista |
| Anuencia de descuento | Al aprobar | `anuencia` | Prestamista |
| Corrida financiera | Al validar docs / bajo demanda | `corrida_financiera` | Sec. Finanzas + Prestamista |
| Estado de cuenta | Bajo demanda | generado al vuelo | — |
| Comprobante de movimiento | Por cada pago, pico o ajuste | `receipt_type` en `loan_receipts` | — |

---

## Convenciones de código obligatorias

1. Misma estructura que `app/Modules/Auth`: `Domain → Application → Infrastructure`.
2. Los repositorios extienden `PdoBaseRepository`; las interfaces van en `Domain/Repository/`.
3. Cero lógica de negocio en plantillas Latte; solo presentación.
4. Todos los montos como `DECIMAL(10,2)`; fechas con `DateTimeImmutable`.
5. Cada acción que cambie `loans.status` o modifique saldos genera un registro en `loan_receipts`.
6. Los casos de uso son `final readonly class` con constructor DI, igual que en `Auth`.
7. `LoansModule.php` registra sus propios `REPOSITORIES`, `SERVICES` y `USE_CASES` como constantes de array, igual que `AuthModule.php`.
8. Excepciones de dominio propias: `LoanNotFoundException`, `InvalidLoanStatusException`.

---

## Entregables esperados

1. `app/Modules/Loans/` — árbol completo de clases con la estructura definida arriba
2. Todos los casos de uso implementados (Flujos 1–6)
3. `AmortizationCalculator` — interés simple alemán, compuesto, regeneración de tabla y cálculo de picos
4. `PdfGenerator` — pagaré, anuencia, corrida financiera, estado de cuenta, comprobantes
5. Repositorios PDO para las 8 tablas existentes
6. Plantillas Latte — listado de solicitudes, detalle del préstamo, simulador, estado de cuenta
7. Validaciones server-side en PHP para todos los formularios
8. `LoansModule.php` con registro completo de dependencias