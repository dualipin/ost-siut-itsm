create table if not exists usuarios
(
    usuario_id            int auto_increment PRIMARY KEY,

    -- auth
    email                 varchar(100) not null unique,
    password_hash         varchar(255) not null,
    rol                   VARCHAR(20)  NOT NULL DEFAULT 'no_agremiado',
    activo                BOOLEAN               DEFAULT TRUE,

    -- info personal
    curp                  VARCHAR(20),
    nombre                VARCHAR(100) NOT NULL,
    apellidos             VARCHAR(255) NOT NULL,
    fecha_nacimiento      DATE                  DEFAULT NULL,
    direccion             VARCHAR(255),
    telefono              VARCHAR(50),
    foto                  VARCHAR(255),

    -- datos bancarios
    banco_nombre          VARCHAR(100),
    clabe_interbancaria   VARCHAR(18),
    cuenta_bancaria       VARCHAR(20),

    -- laboral
    categoria             VARCHAR(100),
    departamento          VARCHAR(100),
    nss                   VARCHAR(15),
    salario_quincenal     DECIMAL(12, 2)        DEFAULT 0,
    fecha_ingreso_laboral DATE                  DEFAULT NULL,

    -- sesion
    ultimo_ingreso        DATETIME              DEFAULT NULL,
    fecha_creacion        DATETIME              DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion   DATETIME              DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fecha_eliminacion     DATETIME              DEFAULT NULL
);

CREATE TABLE if not exists usuario_documentos
(
    documento_id     INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id       int   NOT NULL,
    tipo_documento   VARCHAR(100) NOT NULL, -- 'afiliacion', 'ine', 'comprobante_domicilio', etc.
    ruta_archivo     VARCHAR(255) NOT NULL,
    estado           varchar(30)  NOT NULL DEFAULT 'pendiente',
    observaciones    TEXT,
    fecha_subida     DATETIME              DEFAULT CURRENT_TIMESTAMP,
    fecha_validacion DATETIME,
    validado_por     int,            -- usuario_id de quien validó

    CONSTRAINT fk_documentos_usuario
        FOREIGN KEY (usuario_id)
            REFERENCES usuarios (usuario_id)
            ON DELETE CASCADE,

    INDEX idx_usuario_tipo (usuario_id, tipo_documento),
    INDEX idx_estado (estado)
);


CREATE TABLE IF NOT EXISTS autenticacion_logs
(
    autenticacion_id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id       int,
    email            VARCHAR(255),
    action           VARCHAR(50) NOT NULL,
    ip_address       VARCHAR(45),
    user_agent       VARCHAR(255),
    success          BOOLEAN     NOT NULL DEFAULT FALSE,
    error_message    TEXT,
    created_at       TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios (usuario_id) ON DELETE SET NULL,
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
);


CREATE TABLE if not exists cat_tipos_ingreso
(
    tipo_ingreso_id    INT AUTO_INCREMENT PRIMARY KEY,
    nombre             VARCHAR(100) NOT NULL, -- "Aguinaldo", "Quincena", "Bono"
    descripcion        TEXT,
    es_periodico       BOOLEAN DEFAULT FALSE, -- TRUE para Quincena, FALSE para bonos anuales
    frecuencia_dias    INT,                   -- 15 para quincenas, NULL para anuales
    mes_pago_tentativo INT,                   -- Para prestaciones: 12 para Diciembre
    dia_pago_tentativo INT,                   -- 15 o 20 típicamente
    activo             BOOLEAN DEFAULT TRUE
);


CREATE TABLE if not exists prestamos
(
    prestamo_id                  INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id                   int     NOT NULL,

    -- Identificación
    folio                        VARCHAR(50) UNIQUE,      -- Generado automáticamente: SIN-2025-001

    -- Montos
    monto_solicitado             DECIMAL(10, 2) NOT NULL,
    monto_aprobado               DECIMAL(10, 2),
    tasa_interes_aplicada        DECIMAL(5, 2)  NOT NULL, -- % exacto usado (puede ser personalizado)
    tasa_moratorio_diario        DECIMAL(5, 4),           -- Para calcular picos por retraso
    total_a_pagar_estimado       DECIMAL(10, 2),
    saldo_pendiente              DECIMAL(10, 2),          -- Se actualiza con cada pago

    -- Plazos
    plazo_meses                  INT,
    plazo_quincenas              INT,
    fecha_primer_pago            DATE,
    fecha_ultimo_pago_programado DATE,

    -- Fechas del Workflow
    fecha_solicitud              DATETIME                DEFAULT CURRENT_TIMESTAMP,
    fecha_revision_documental    DATETIME,
    fecha_aprobacion             DATETIME,
    fecha_generacion_documentos  DATETIME,                -- Cuando se creó pagaré
    fecha_validacion_firmas      DATETIME,
    fecha_desembolso             DATETIME,                -- Inicio de devengo de intereses
    fecha_liquidacion_total      DATETIME,

    -- Estado del Flujo
    estado                       varchar(30)    NOT NULL DEFAULT 'borrador',
    -- estado                       ENUM (
    --     'borrador',                                       -- Usuario llenando solicitud
    --     'revision_documental',                            -- Admin revisando estados de cuenta
    --     'correccion_requerida',                           -- Docs rechazados, usuario corrige
    --     'aprobado_pendiente_firma',-- Pagarés generados, esperando firma
    --     'validacion_firmas',                              -- Firmas subidas, finanzas valida
    --     'activo',                                         -- Dinero entregado, corriendo
    --     'pagado',                                         -- Deuda saldada
    --     'vencido',                                        -- Tiene pagos atrasados
    --     'reestructurado',                                 -- Se generó nuevo préstamo para cubrir
    --     'cancelado'                                       -- Cancelado antes de desembolso
    --     )                                 DEFAULT 'borrador',

    -- Referencias
    prestamo_origen_id           INT,                     -- Si es reestructuración, apunta al original
    motivo_rechazo               TEXT,
    observaciones_admin          TEXT,
    observaciones_internas       TEXT,                    -- Notas privadas del comité

    -- Firmas digitales de documentos generados
    firmante_finanzas            VARCHAR(255),            -- Nombre del secretario de finanzas
    firmante_prestamista         VARCHAR(255),            -- Confirmación del usuario

    -- Control
    requiere_reestructuracion    BOOLEAN                 DEFAULT FALSE,
    creado_por                   int,              -- Admin que procesó
    fecha_eliminacion            DATETIME                default NULL,

    CONSTRAINT fk_prestamo_usuario
        FOREIGN KEY (usuario_id)
            REFERENCES usuarios (usuario_id)
            ON DELETE RESTRICT,
    CONSTRAINT fk_prestamo_origen
        FOREIGN KEY (prestamo_origen_id)
            REFERENCES prestamos (prestamo_id)
            ON DELETE SET NULL,

    INDEX idx_folio (folio),
    INDEX idx_usuario_estado (usuario_id, estado),
    INDEX idx_estado_fecha (estado, fecha_solicitud),
    INDEX idx_origen (prestamo_origen_id)
);

-- Configuración de pagos del préstamo (mix nómina + prestaciones)
CREATE TABLE if not exists prestamo_configuracion_pagos
(
    config_pago_id             INT AUTO_INCREMENT PRIMARY KEY,
    prestamo_id                INT            NOT NULL,
    tipo_ingreso_id            INT            NOT NULL,

    -- Configuración
    monto_total_a_descontar    DECIMAL(10, 2) NOT NULL,           -- Total de esta fuente
    numero_cuotas              INT                     DEFAULT 1, -- Quincenas: 24, Aguinaldo: 1
    monto_por_cuota            DECIMAL(10, 2),                    -- Para quincenas

    -- Método de cálculo de interés
    metodo_interes             varchar(20)    NOT NULL DEFAULT 'simple_aleman',
    -- metodo_interes             ENUM ('simple_aleman', 'compuesto')         DEFAULT 'simple_aleman',
    -- Simple alemán: para quincenas (cuota fija de capital + interés variable)
    -- Compuesto: para prestaciones (un solo pago)

    -- Documento probatorio
    ruta_documento_comprobante VARCHAR(255),
    -- Estado de cuenta de esa prestación
    estado_documento           varchar(30)    NOT NULL DEFAULT 'pendiente',
    -- estado_documento           ENUM ('pendiente', 'validado', 'rechazado') DEFAULT 'pendiente',
    observaciones_documento    TEXT,
    fecha_validacion_documento DATETIME,

    CONSTRAINT fk_config_prestamo
        FOREIGN KEY (prestamo_id)
            REFERENCES prestamos (prestamo_id)
            ON DELETE CASCADE,
    CONSTRAINT fk_config_tipo_ingreso
        FOREIGN KEY (tipo_ingreso_id)
            REFERENCES cat_tipos_ingreso (tipo_ingreso_id)
            ON DELETE RESTRICT,

    INDEX idx_prestamo (prestamo_id),
    INDEX idx_tipo_ingreso (tipo_ingreso_id)
);

-- Documentos legales generados del préstamo
CREATE TABLE if not exists prestamo_documentos_legales
(
    doc_legal_id                 INT AUTO_INCREMENT PRIMARY KEY,
    prestamo_id                  INT          NOT NULL,
    tipo_documento               VARCHAR(50)  NOT NULL, -- 'pagare', 'anuencia_descuento', etc.
    -- tipo_documento               ENUM (
    --     'pagare',
    --     'anuencia_descuento',
    --     'corrida_financiera',
    --     'comprobante_transferencia',
    --     'contrato_prestamo',
    --     'carta_reestructuracion'
    --     )                                     NOT NULL,

    ruta_archivo                 VARCHAR(255) NOT NULL,
    version                      INT      DEFAULT 1,    -- Si se regenera por reestructuración

    -- Control de firmas
    requiere_firma_usuario       BOOLEAN  DEFAULT FALSE,
    firma_usuario_url            VARCHAR(255),          -- Archivo firmado subido
    fecha_firma_usuario          DATETIME,

    requiere_validacion_finanzas BOOLEAN  DEFAULT FALSE,
    validado_por_finanzas        BOOLEAN  DEFAULT FALSE,
    validado_por                 int,            -- usuario_id
    fecha_validacion             DATETIME,
    observaciones_validacion     TEXT,

    fecha_generacion             DATETIME DEFAULT CURRENT_TIMESTAMP,
    generado_por                 int,            -- usuario_id

    CONSTRAINT fk_doc_legal_prestamo
        FOREIGN KEY (prestamo_id)
            REFERENCES prestamos (prestamo_id)
            ON DELETE CASCADE,

    INDEX idx_prestamo_tipo (prestamo_id, tipo_documento),
    INDEX idx_pendientes_firma (requiere_firma_usuario, fecha_firma_usuario)
);

-- Tabla de amortización (corrida financiera)
CREATE TABLE if not exists prestamo_amortizacion
(
    amortizacion_id            INT AUTO_INCREMENT PRIMARY KEY,
    prestamo_id                INT            NOT NULL,

    -- Identificación del pago
    numero_pago                INT            NOT NULL,              -- 1, 2, 3... N
    tipo_ingreso_id            INT            NOT NULL,              -- De qué fuente sale este pago
    fecha_programada           DATE           NOT NULL,              -- 15 o 20 del mes

    -- Desglose Financiero Programado (calculado al generar tabla)
    saldo_inicial              DECIMAL(10, 2) NOT NULL,
    capital                    DECIMAL(10, 2) NOT NULL,
    interes_ordinario          DECIMAL(10, 2) NOT NULL,
    pago_total_programado      DECIMAL(10, 2) NOT NULL,              -- capital + interes
    saldo_final                DECIMAL(10, 2) NOT NULL,

    -- Control de Pagos Reales
    estado_pago                varchar(30)    NOT NULL DEFAULT 'pendiente',
    -- estado_pago                ENUM ('pendiente', 'pagado', 'pagado_parcial', 'vencido') DEFAULT 'pendiente',
    fecha_pago_real            DATETIME,
    monto_pagado_real          DECIMAL(10, 2)          DEFAULT 0,

    -- Intereses Moratorios (picos por atraso)
    dias_atraso                INT                     DEFAULT 0,
    interes_moratorio_generado DECIMAL(10, 2)          DEFAULT 0,

    -- Trazabilidad
    pagado_por                 int,                           -- usuario_id que registró el pago
    comprobante_pago           VARCHAR(255),                         -- URL del comprobante

    -- Control de regeneración
    version_tabla              INT                     DEFAULT 1,    -- Incrementa con reestructuraciones
    activa                     BOOLEAN                 DEFAULT TRUE, -- FALSE si se regeneró la tabla

    CONSTRAINT fk_amort_prestamo
        FOREIGN KEY (prestamo_id)
            REFERENCES prestamos (prestamo_id)
            ON DELETE CASCADE,
    CONSTRAINT fk_amort_tipo_ingreso
        FOREIGN KEY (tipo_ingreso_id)
            REFERENCES cat_tipos_ingreso (tipo_ingreso_id)
            ON DELETE RESTRICT,

    INDEX idx_prestamo_numero (prestamo_id, numero_pago),
    INDEX idx_fecha_estado (fecha_programada, estado_pago),
    INDEX idx_version_activa (prestamo_id, version_tabla, activa)
);

-- Pagos extraordinarios (anticipos, abonos adicionales)
CREATE TABLE if not exists prestamo_pagos_extraordinarios
(
    pago_extraordinario_id      INT AUTO_INCREMENT PRIMARY KEY,
    prestamo_id                 INT            NOT NULL,

    tipo_pago                   varchar(30)    NOT NULL, -- 'anticipo', 'liquidacion_total', 'abono_capital'
    -- tipo_pago                   ENUM ('anticipo', 'liquidacion_total', 'abono_capital') NOT NULL,
    monto                       DECIMAL(10, 2) NOT NULL,
    fecha_pago                  DATETIME DEFAULT CURRENT_TIMESTAMP,

    -- Aplicación del pago
    aplicado_a_capital          DECIMAL(10, 2),
    aplicado_a_interes          DECIMAL(10, 2),
    aplicado_a_moratorio        DECIMAL(10, 2),

    -- Efecto
    regenero_tabla_amortizacion BOOLEAN  DEFAULT TRUE,
    version_tabla_generada      INT,                     -- Nueva versión de amortización creada

    observaciones               TEXT,
    comprobante_pago            VARCHAR(255),
    registrado_por              int,              -- usuario_id

    CONSTRAINT fk_pago_extra_prestamo
        FOREIGN KEY (prestamo_id)
            REFERENCES prestamos (prestamo_id)
            ON DELETE CASCADE,

    INDEX idx_prestamo_fecha (prestamo_id, fecha_pago),
    INDEX idx_tipo (tipo_pago)
);

-- Historial de reestructuraciones
CREATE TABLE if not exists prestamo_reestructuraciones
(
    reestructuracion_id      INT AUTO_INCREMENT PRIMARY KEY,
    prestamo_original_id     INT            NOT NULL,
    prestamo_nuevo_id        INT            NOT NULL,

    motivo                   varchar(35)    NOT NULL, -- 'pago_anticipado', 'picos_acumulados', 'solicitud_cliente', 'ajuste_administrativo'
    -- motivo                   ENUM (
    --     'pago_anticipado',
    --     'picos_acumulados',
    --     'solicitud_cliente',
    --     'ajuste_administrativo'
    --     )                                   NOT NULL,

    saldo_pendiente_original DECIMAL(10, 2) NOT NULL,
    intereses_pendientes     DECIMAL(10, 2) NOT NULL,
    moratorios_pendientes    DECIMAL(10, 2) NOT NULL,
    nuevo_monto_total        DECIMAL(10, 2) NOT NULL,
    nueva_tasa_interes       DECIMAL(5, 2),
    nuevo_plazo_quincenas    INT,

    fecha_reestructuracion   DATETIME DEFAULT CURRENT_TIMESTAMP,
    autorizado_por           int,              -- usuario_id
    observaciones            TEXT,

    CONSTRAINT fk_reest_original
        FOREIGN KEY (prestamo_original_id)
            REFERENCES prestamos (prestamo_id)
            ON DELETE RESTRICT,
    CONSTRAINT fk_reest_nuevo
        FOREIGN KEY (prestamo_nuevo_id)
            REFERENCES prestamos (prestamo_id)
            ON DELETE RESTRICT,

    INDEX idx_original (prestamo_original_id),
    INDEX idx_nuevo (prestamo_nuevo_id),
    INDEX idx_fecha (fecha_reestructuracion)
);

-- Comprobantes generados automáticamente
CREATE TABLE if not exists prestamo_comprobantes
(
    comprobante_id    INT AUTO_INCREMENT PRIMARY KEY,
    prestamo_id       INT                NOT NULL,
    amortizacion_id   INT,                         -- NULL si es comprobante de desembolso

    tipo_comprobante  varchar(30)        NOT NULL, -- 'desembolso', 'pago_regular', 'pago_extraordinario', 'cargo_moratorio', 'ajuste'
    -- tipo_comprobante  ENUM (
    --     'desembolso',
    --     'pago_regular',
    --     'pago_extraordinario',
    --     'cargo_moratorio',
    --     'ajuste'
    --     )                                NOT NULL,

    folio_comprobante VARCHAR(50) UNIQUE NOT NULL,
    monto             DECIMAL(10, 2)     NOT NULL,
    descripcion       TEXT,

    fecha_emision     DATETIME DEFAULT CURRENT_TIMESTAMP,
    ruta_pdf          VARCHAR(255),                -- PDF generado automáticamente

    CONSTRAINT fk_comp_prestamo
        FOREIGN KEY (prestamo_id)
            REFERENCES prestamos (prestamo_id)
            ON DELETE CASCADE,
    CONSTRAINT fk_comp_amortizacion
        FOREIGN KEY (amortizacion_id)
            REFERENCES prestamo_amortizacion (amortizacion_id)
            ON DELETE SET NULL,

    INDEX idx_folio (folio_comprobante),
    INDEX idx_prestamo_fecha (prestamo_id, fecha_emision)
);

create table if not exists publicaciones
(
    publicacion_id    int auto_increment primary key,
    titulo            varchar(100) not null,
    resumen           varchar(255),
    contenido         text         not null,
    tipo_publicacion  varchar(20)  not null,
    -- tipo_publicacion  enum ('noticia', 'gestion', 'aviso') not null,
    fecha_publicacion datetime     not null,
    fecha_expiracion  date default null,
    autor_id          int
);


CREATE TABLE IF NOT EXISTS colores_sistema
(
    id           tinyint primary key check ( id = 1) default 1,
    primario     varchar(7)                          default '#611232',
    secundario   varchar(7)                          default '#a57f2c',
    exito        varchar(7)                          default '#38b44a',
    info         varchar(7)                          default '#17a2b8',
    advertencia  varchar(7)                          default '#efb73e',
    peligro      varchar(7)                          default '#df382c',
    claro        varchar(7)                          default '#e9ecef',
    oscuro       varchar(7)                          default '#002f2a',
    blanco       varchar(7)                          default '#ffffff',
    cuerpo       varchar(7)                          default '#212529',
    fondo_cuerpo varchar(7)                          default '#f8f9fa'
);