CREATE TABLE IF NOT EXISTS users
(
    user_id         INT AUTO_INCREMENT PRIMARY KEY,

    -- auth
    email           varchar(100) NOT NULL UNIQUE,
    password_hash   varchar(255) NOT NULL,
    role            VARCHAR(20)  NOT NULL DEFAULT 'no_agremiado',
    active          BOOLEAN               DEFAULT TRUE,

    -- info personal
    curp            VARCHAR(20),
    name            VARCHAR(100) NOT NULL,
    surnames        VARCHAR(255) NOT NULL,
    birthdate       DATE                  DEFAULT NULL,
    address         VARCHAR(255),
    phone           VARCHAR(50),
    photo           VARCHAR(255),

    -- datos bancarios
    bank_name       VARCHAR(100),
    interbank_code  VARCHAR(18),
    bank_account    VARCHAR(20),

    -- laboral
    category        VARCHAR(100),
    department      VARCHAR(100),
    nss             VARCHAR(15),
    salary          DECIMAL(12, 2)        DEFAULT 0,
    work_start_date DATE                  DEFAULT NULL,

    -- sesion
    last_entry      DATETIME              DEFAULT NULL,
    created_at      DATETIME              DEFAULT CURRENT_TIMESTAMP,
    update_at       DATETIME              DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    delete_at       DATETIME              DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS user_documents
(
    document_id   INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT          NOT NULL,
    document_type VARCHAR(100) NOT NULL, -- 'afiliacion', 'ine', 'comprobante_domicilio', etc.
    file_path     VARCHAR(255) NOT NULL,
    status        varchar(30)  NOT NULL DEFAULT 'pendiente',
    observation   TEXT,
    created_at    DATETIME              DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME              DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    validated_by  INT,                   -- usuario_id de quien validó

    CONSTRAINT fk_document_user
        FOREIGN KEY (user_id)
            REFERENCES users (user_id)
            ON DELETE CASCADE,

    INDEX idx_user_document_type (user_id, document_type),
    INDEX idx_status (status)
);


CREATE TABLE IF NOT EXISTS auth_logs
(
    auth_id       INT AUTO_INCREMENT PRIMARY KEY,
    user_id       int,
    email         VARCHAR(255),
    action        VARCHAR(50) NOT NULL,
    ip_address    VARCHAR(45),
    user_agent    VARCHAR(255),
    success       BOOLEAN     NOT NULL DEFAULT FALSE,
    error_message TEXT,
    created_at    TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE SET NULL,
    INDEX idx_usuario_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
);

CREATE TABLE if NOT EXISTS password_resets
(
    email      VARCHAR(255) NOT NULL,
    token      binary(16)   NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (email),
    INDEX (token)
);

CREATE TABLE if NOT EXISTS mail_queue
(
    id         INT AUTO_INCREMENT PRIMARY KEY,
    recipient  TEXT NOT NULL, -- JSON array de correos
    subject    VARCHAR(255),
    body       TEXT,
    alt_body   TEXT,
    attempts   INT                                DEFAULT 0,
    status     ENUM ('pending', 'sent', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP                          DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE if not exists cat_income_types
(
    income_type_id          INT AUTO_INCREMENT PRIMARY KEY,
    name                    VARCHAR(100) NOT NULL, -- "Aguinaldo", "Quincena", "Bono"
    description             TEXT,
    is_periodic             BOOLEAN DEFAULT FALSE, -- TRUE para Quincena, FALSE para bonos anuales
    frequency_days          INT,                   -- 15 para quincenas, NULL para anuales
    tentative_payment_month INT,                   -- Para prestaciones: 12 para Diciembre
    tentative_payment_day   INT,                   -- 15 o 20 típicamente
    active                  BOOLEAN DEFAULT TRUE
);


CREATE TABLE if not exists loans
(
    loan_id                     INT AUTO_INCREMENT PRIMARY KEY,
    user_id                     int            NOT NULL,

    -- Identificación
    folio                       VARCHAR(50) UNIQUE,      -- Generado automáticamente: SIN-2025-001

    -- Montos
    requested_amount            DECIMAL(10, 2) NOT NULL,
    approved_amount             DECIMAL(10, 2),
    applied_interest_rate       DECIMAL(5, 2)  NOT NULL, -- % exacto usado (puede ser personalizado)
    daily_default_rate          DECIMAL(5, 4),           -- Para calcular picos por retraso
    estimated_total_to_pay      DECIMAL(10, 2),
    outstanding_balance         DECIMAL(10, 2),          -- Se actualiza con cada pago

    -- Plazos
    term_months                 INT,
    term_fortnights             INT,
    first_payment_date          DATE,
    last_scheduled_payment_date DATE,

    -- Fechas del Workflow
    application_date            DATETIME                DEFAULT CURRENT_TIMESTAMP,
    document_review_date        DATETIME,
    approval_date               DATETIME,
    document_generation_date    DATETIME,                -- Cuando se creó pagaré
    signature_validation_date   DATETIME,
    disbursement_date           DATETIME,                -- Inicio de devengo de intereses
    total_liquidation_date      DATETIME,

    -- Estado del Flujo
    status                      varchar(30)    NOT NULL DEFAULT 'borrador',
    -- status                       ENUM (
    --     'borrador',                                       -- Usuario llenando solicitud
    --     'revision_documental',                            -- Admin revisando estados de cuenta
    --     'correccion_requerida',                           -- Docs rechazados, usuario corrige
    --     'aprobado_pendiente_firma',-- Pagarés generados, esperando firma
    --     'validacion_firmas',                              -- Firmas subidas, finanzas valida
    --     'active',                                         -- Dinero entregado, corriendo
    --     'pagado',                                         -- Deuda saldada
    --     'vencido',                                        -- Tiene pagos atrasados
    --     'reestructurado',                                 -- Se generó nuevo préstamo para cubrir
    --     'cancelado'                                       -- Cancelado antes de desembolso
    --     )                                 DEFAULT 'borrador',

    -- Referencias
    original_loan_id            INT,                     -- Si es reestructuración, apunta al original
    rejection_reason            TEXT,
    admin_observations          TEXT,
    internal_observations       TEXT,                    -- Notas privadas del comité

    -- Firmas digitales de documentos generados
    finance_signatory           VARCHAR(255),            -- Nombre del secretario de finanzas
    lender_signatory            VARCHAR(255),            -- Confirmación del usuario

    -- Control
    requires_restructuring      BOOLEAN                 DEFAULT FALSE,
    created_by                  int,                     -- Admin que procesó
    deletion_date               DATETIME                default NULL,

    CONSTRAINT fk_loan_user
        FOREIGN KEY (user_id)
            REFERENCES users (user_id)
            ON DELETE RESTRICT,
    CONSTRAINT fk_loan_origin
        FOREIGN KEY (original_loan_id)
            REFERENCES loans (loan_id)
            ON DELETE SET NULL,

    INDEX idx_folio (folio),
    INDEX idx_user_status (user_id, status),
    INDEX idx_status_date (status, application_date),
    INDEX idx_origin (original_loan_id)
);

-- Configuración de pagos del préstamo (mix nómina + prestaciones)
CREATE TABLE if not exists loan_payment_configuration
(
    payment_config_id        INT AUTO_INCREMENT PRIMARY KEY,
    loan_id                  INT            NOT NULL,
    income_type_id           INT            NOT NULL,

    -- Configuración
    total_amount_to_deduct   DECIMAL(10, 2) NOT NULL,           -- Total de esta fuente
    number_of_installments   INT                     DEFAULT 1, -- Quincenas: 24, Aguinaldo: 1
    amount_per_installment   DECIMAL(10, 2),                    -- Para quincenas

    -- Método de cálculo de interés
    interest_method          varchar(20)    NOT NULL DEFAULT 'simple_aleman',
    -- interest_method             ENUM ('simple_aleman', 'compuesto')         DEFAULT 'simple_aleman',
    -- Simple alemán: para quincenas (cuota fija de capital + interés variable)
    -- Compuesto: para prestaciones (un solo pago)

    -- Documento probatorio
    supporting_document_path VARCHAR(255),
    -- Estado de cuenta de esa prestación
    document_status          varchar(30)    NOT NULL DEFAULT 'pendiente',
    -- document_status           ENUM ('pendiente', 'validado', 'rechazado') DEFAULT 'pendiente',
    document_observations    TEXT,
    document_validation_date DATETIME,

    CONSTRAINT fk_config_loan
        FOREIGN KEY (loan_id)
            REFERENCES loans (loan_id)
            ON DELETE CASCADE,
    CONSTRAINT fk_config_income_type
        FOREIGN KEY (income_type_id)
            REFERENCES cat_income_types (income_type_id)
            ON DELETE RESTRICT,

    INDEX idx_loan (loan_id),
    INDEX idx_income_type (income_type_id)
);

-- Documentos legales generados del préstamo
CREATE TABLE if not exists loan_legal_documents
(
    legal_doc_id                INT AUTO_INCREMENT PRIMARY KEY,
    loan_id                     INT          NOT NULL,
    document_type               VARCHAR(50)  NOT NULL, -- 'pagare', 'anuencia_descuento', etc.
    -- document_type               ENUM (
    --     'pagare',
    --     'anuencia_descuento',
    --     'corrida_financiera',
    --     'comprobante_transferencia',
    --     'contrato_prestamo',
    --     'carta_reestructuracion'
    --     )                                     NOT NULL,

    file_path                   VARCHAR(255) NOT NULL,
    version                     INT      DEFAULT 1,    -- Si se regenera por reestructuración

    -- Control de firmas
    requires_user_signature     BOOLEAN  DEFAULT FALSE,
    user_signature_url          VARCHAR(255),          -- Archivo firmado subido
    user_signature_date         DATETIME,

    requires_finance_validation BOOLEAN  DEFAULT FALSE,
    validated_by_finance        BOOLEAN  DEFAULT FALSE,
    validated_by                int,                   -- user_id
    validation_date             DATETIME,
    validation_observations     TEXT,

    generation_date             DATETIME DEFAULT CURRENT_TIMESTAMP,
    generated_by                int,                   -- user_id

    CONSTRAINT fk_legal_doc_loan
        FOREIGN KEY (loan_id)
            REFERENCES loans (loan_id)
            ON DELETE CASCADE,

    INDEX idx_loan_type (loan_id, document_type),
    INDEX idx_pending_signature (requires_user_signature, user_signature_date)
);

-- Tabla de amortización (corrida financiera)
CREATE TABLE if not exists loan_amortization
(
    amortization_id            INT AUTO_INCREMENT PRIMARY KEY,
    loan_id                    INT            NOT NULL,

    -- Identificación del pago
    payment_number             INT            NOT NULL,              -- 1, 2, 3... N
    income_type_id             INT            NOT NULL,              -- De qué fuente sale este pago
    scheduled_date             DATE           NOT NULL,              -- 15 o 20 del mes

    -- Desglose Financiero Programado (calculado al generar tabla)
    initial_balance            DECIMAL(10, 2) NOT NULL,
    principal                  DECIMAL(10, 2) NOT NULL,
    ordinary_interest          DECIMAL(10, 2) NOT NULL,
    total_scheduled_payment    DECIMAL(10, 2) NOT NULL,              -- capital + interes
    final_balance              DECIMAL(10, 2) NOT NULL,

    -- Control de Pagos Reales
    payment_status             varchar(30)    NOT NULL DEFAULT 'pendiente',
    -- payment_status                ENUM ('pendiente', 'pagado', 'pagado_parcial', 'vencido') DEFAULT 'pendiente',
    actual_payment_date        DATETIME,
    actual_paid_amount         DECIMAL(10, 2)          DEFAULT 0,

    -- Intereses Moratorios (picos por atraso)
    days_overdue               INT                     DEFAULT 0,
    generated_default_interest DECIMAL(10, 2)          DEFAULT 0,

    -- Trazabilidad
    paid_by                    int,                                  -- user_id que registró el pago
    payment_receipt            VARCHAR(255),                         -- URL del comprobante

    -- Control de regeneración
    table_version              INT                     DEFAULT 1,    -- Incrementa con reestructuraciones
    active                     BOOLEAN                 DEFAULT TRUE, -- FALSE si se regeneró la tabla

    CONSTRAINT fk_amort_loan
        FOREIGN KEY (loan_id)
            REFERENCES loans (loan_id)
            ON DELETE CASCADE,
    CONSTRAINT fk_amort_income_type
        FOREIGN KEY (income_type_id)
            REFERENCES cat_income_types (income_type_id)
            ON DELETE RESTRICT,

    INDEX idx_loan_number (loan_id, payment_number),
    INDEX idx_date_status (scheduled_date, payment_status),
    INDEX idx_version_active (loan_id, table_version, active)
);

-- Pagos extraordinarios (anticipos, abonos adicionales)
CREATE TABLE if not exists loan_extraordinary_payments
(
    extraordinary_payment_id       INT AUTO_INCREMENT PRIMARY KEY,
    loan_id                        INT            NOT NULL,

    payment_type                   varchar(30)    NOT NULL, -- 'anticipo', 'liquidacion_total', 'abono_capital'
    -- payment_type                   ENUM ('anticipo', 'liquidacion_total', 'abono_capital') NOT NULL,
    amount                         DECIMAL(10, 2) NOT NULL,
    payment_date                   DATETIME DEFAULT CURRENT_TIMESTAMP,

    -- Aplicación del pago
    applied_to_principal           DECIMAL(10, 2),
    applied_to_interest            DECIMAL(10, 2),
    applied_to_default             DECIMAL(10, 2),

    -- Efecto
    regenerated_amortization_table BOOLEAN  DEFAULT TRUE,
    generated_table_version        INT,                     -- Nueva versión de amortización creada

    observations                   TEXT,
    payment_receipt                VARCHAR(255),
    registered_by                  int,                     -- user_id

    CONSTRAINT fk_extra_payment_loan
        FOREIGN KEY (loan_id)
            REFERENCES loans (loan_id)
            ON DELETE CASCADE,

    INDEX idx_loan_date (loan_id, payment_date),
    INDEX idx_type (payment_type)
);

-- Historial de reestructuraciones
CREATE TABLE if not exists loan_restructurings
(
    restructuring_id             INT AUTO_INCREMENT PRIMARY KEY,
    original_loan_id             INT            NOT NULL,
    new_loan_id                  INT            NOT NULL,

    reason                       varchar(35)    NOT NULL, -- 'pago_anticipado', 'picos_acumulados', 'solicitud_cliente', 'ajuste_administrativo'
    -- reason                   ENUM (
    --     'pago_anticipado',
    --     'picos_acumulados',
    --     'solicitud_cliente',
    --     'ajuste_administrativo'
    --     )                                   NOT NULL,

    original_outstanding_balance DECIMAL(10, 2) NOT NULL,
    pending_interest             DECIMAL(10, 2) NOT NULL,
    pending_default_interest     DECIMAL(10, 2) NOT NULL,
    new_total_amount             DECIMAL(10, 2) NOT NULL,
    new_interest_rate            DECIMAL(5, 2),
    new_term_fortnights          INT,

    restructuring_date           DATETIME DEFAULT CURRENT_TIMESTAMP,
    authorized_by                int,                     -- user_id
    observations                 TEXT,

    CONSTRAINT fk_restruct_original
        FOREIGN KEY (original_loan_id)
            REFERENCES loans (loan_id)
            ON DELETE RESTRICT,
    CONSTRAINT fk_restruct_new
        FOREIGN KEY (new_loan_id)
            REFERENCES loans (loan_id)
            ON DELETE RESTRICT,

    INDEX idx_original (original_loan_id),
    INDEX idx_new (new_loan_id),
    INDEX idx_date (restructuring_date)
);

-- Comprobantes generados automáticamente
CREATE TABLE if not exists loan_receipts
(
    receipt_id      INT AUTO_INCREMENT PRIMARY KEY,
    loan_id         INT                NOT NULL,
    amortization_id INT,                         -- NULL si es comprobante de desembolso

    receipt_type    varchar(30)        NOT NULL, -- 'desembolso', 'pago_regular', 'pago_extraordinario', 'cargo_moratorio', 'ajuste'
    -- receipt_type  ENUM (
    --     'desembolso',
    --     'pago_regular',
    --     'pago_extraordinario',
    --     'cargo_moratorio',
    --     'ajuste'
    --     )                                NOT NULL,

    receipt_folio   VARCHAR(50) UNIQUE NOT NULL,
    amount          DECIMAL(10, 2)     NOT NULL,
    description     TEXT,

    issue_date      DATETIME DEFAULT CURRENT_TIMESTAMP,
    pdf_path        VARCHAR(255),                -- PDF generado automáticamente

    CONSTRAINT fk_receipt_loan
        FOREIGN KEY (loan_id)
            REFERENCES loans (loan_id)
            ON DELETE CASCADE,
    CONSTRAINT fk_receipt_amortization
        FOREIGN KEY (amortization_id)
            REFERENCES loan_amortization (amortization_id)
            ON DELETE SET NULL,

    INDEX idx_folio (receipt_folio),
    INDEX idx_loan_date (loan_id, issue_date)
);


#
# create table if not exists publicaciones
# (
#     publicacion_id    int auto_increment primary key,
#     titulo            varchar(100) not null,
#     resumen           varchar(255),
#     contenido         text         not null,
#     tipo_publicacion  varchar(20)  not null,
#     -- tipo_publicacion  enum ('noticia', 'gestion', 'aviso') not null,
#     fecha_publicacion datetime     not null,
#     fecha_expiracion  date default null,
#     autor_id          int
# );


# -- Mensajería
#
# create table if not exists mensajes
# (
#     mensaje_id           int auto_increment primary key,
#     usuario_id           int,
#     tipo                 varchar(50)  not null,
#     asunto               varchar(255) not null,
#     prioridad            varchar(20)  not null default 'media',
#     estado               varchar(20)  not null default 'no_leido',
#     -- Datos de contacto externo (si no está logueado)
#     nombre_externo       VARCHAR(100),
#     correo_externo       VARCHAR(100),
#     telefono_externo     VARCHAR(20),
#
#     -- Asignación
#     asignado_a           VARCHAR(36), -- usuario_id del admin/staff
#
#     -- Control
#     fecha_creacion       DATETIME              DEFAULT CURRENT_TIMESTAMP,
#     fecha_ultimo_mensaje DATETIME,
#     fecha_cierre         DATETIME,
#
#     constraint fk_mensaje_usuario
#         foreign key (usuario_id) references usuarios (usuario_id) on delete set null,
#     index idx_tipo_estado (tipo, estado)
# );
#
#
# create table if not exists mensaje_detalles
# (
#     detalle_id         int auto_increment primary key,
#     mensaje_id         int  not null,
#     -- Autor (puede ser usuario logueado o externo)
#     autor_usuario_id   int, -- NULL si es externo
#     es_respuesta_staff BOOLEAN  DEFAULT FALSE,
#
#     mensaje            TEXT NOT NULL,
#     adjunto_url        VARCHAR(255),
#
#     -- Control
#     fecha_envio        DATETIME DEFAULT CURRENT_TIMESTAMP,
#     leido              BOOLEAN  DEFAULT FALSE,
#     fecha_lectura      DATETIME,
#
#     constraint fk_detalle_mensaje
#         foreign key (mensaje_id) references mensajes (mensaje_id) on delete cascade,
#     constraint fk_detalle_autor_usuario
#         foreign key (autor_usuario_id) references usuarios (usuario_id) on delete set null,
#
#     index idx_mensaje_fecha (mensaje_id, fecha_envio),
#     index idx_no_leidos (mensaje_id, es_respuesta_staff)
# );


CREATE TABLE IF NOT EXISTS system_colors
(
    id                int primary key default 1,
    c_primary         varchar(7)      default '#611232',
    c_secondary       varchar(7)      default '#a57f2c',
    c_success         varchar(7)      default '#38b44a',
    c_info            varchar(7)      default '#17a2b8',
    c_warning         varchar(7)      default '#efb73e',
    c_danger          varchar(7)      default '#df382c',
    c_light           varchar(7)      default '#e9ecef',
    c_dark            varchar(7)      default '#002f2a',
    c_white           varchar(7)      default '#ffffff',
    c_body            varchar(7)      default '#212529',
    c_body_background varchar(7)      default '#f8f9fa'
);