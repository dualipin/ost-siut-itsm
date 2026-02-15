CREATE TABLE IF NOT EXISTS visitas_pagina
(
    id    INT AUTO_INCREMENT PRIMARY KEY,
    fecha TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS usuarios
(
    id     INT AUTO_INCREMENT PRIMARY KEY,
    correo VARCHAR(255) NOT NULL UNIQUE,
    contra VARCHAR(255) NOT NULL,
    rol    VARCHAR(50)  NOT NULL DEFAULT 'agremiado',
    INDEX idx_correo (correo)
);

CREATE TABLE IF NOT EXISTS miembros
(
    id                INT AUTO_INCREMENT PRIMARY KEY,
    nombre            VARCHAR(100) NOT NULL,
    apellidos         VARCHAR(255) NOT NULL,
    direccion         VARCHAR(255),
    telefono          VARCHAR(50),
    categoria         VARCHAR(100),
    departamento      VARCHAR(100),
    nss               VARCHAR(15),
    curp              VARCHAR(20),
    fecha_ingreso     DATE           DEFAULT NULL,
    fecha_nacimiento  DATE           DEFAULT NULL,
    salario_quincenal DECIMAL(10, 2) DEFAULT NULL,
    fk_usuario        INT,
    CONSTRAINT fk_miembro_usuario
        FOREIGN KEY (fk_usuario)
            REFERENCES usuarios (id)
            ON DELETE SET NULL,
    INDEX idx_fk_usuario (fk_usuario), -- índice para relaciones
    INDEX idx_nombre (nombre),         -- índice si buscas miembros por nombre
    INDEX idx_apellidos (apellidos)    -- índice si buscas miembros por apellidos
);

ALTER TABLE miembros
    ADD salario_quincenal DECIMAL(12, 2) DEFAULT 0;

alter table miembros
    add column activo boolean default true;


CREATE TABLE IF NOT EXISTS documentos_miembros
(
    id           INT AUTO_INCREMENT PRIMARY KEY,
    nombre       VARCHAR(255) NOT NULL,
    tipo         VARCHAR(100),
    ruta         VARCHAR(255) NOT NULL,
    fecha_subida TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    miembro_id   INT,
    CONSTRAINT fk_documento_miembro_miembros_miembro_id
        FOREIGN KEY (miembro_id)
            REFERENCES miembros (id)
            ON DELETE CASCADE
);


CREATE TABLE IF NOT EXISTS publicaciones
(
    id         INT AUTO_INCREMENT PRIMARY KEY,
    titulo     VARCHAR(255) NOT NULL,
    resumen    VARCHAR(255),
    contenido  LONGTEXT     NOT NULL,
    imagen     VARCHAR(255),
    tipo       VARCHAR(50)           DEFAULT 'noticia',
    importante BOOLEAN               DEFAULT FALSE,
    expiracion DATE                  DEFAULT NULL,
    fecha      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fk_miembro INT          REFERENCES miembros (id) ON DELETE SET NULL
);

ALTER TABLE publicaciones
    CHANGE COLUMN contenido contenido LONGTEXT NOT NULL;

CREATE TABLE IF NOT EXISTS solicitudes_prestamos
(
    id               INT AUTO_INCREMENT PRIMARY KEY,
    monto_solicitado DECIMAL(10, 2)                                      NOT NULL,
    monto_aprobado   DECIMAL(10, 2)                                                                                      DEFAULT NULL,
    plazo_meses      INT                                                 NOT NULL,
    tipo_descuento   ENUM ('quincenal', 'aguinaldo', 'prima_vacacional') NOT NULL,
    justificacion    TEXT,
    recibo_nomina    VARCHAR(255)                                        NOT NULL,
    estado           ENUM ('pendiente', 'aprobado', 'rechazado', 'lista_espera', 'pagare_pendiente', 'activo', 'pagado') DEFAULT 'pendiente',
    fecha_solicitud  TIMESTAMP                                                                                           DEFAULT CURRENT_TIMESTAMP,
    fecha_respuesta  TIMESTAMP                                           NULL,
    motivo_rechazo   TEXT                                                NULL,
    pagare_firmado   VARCHAR(255)                                        NULL,
    fecha_pagare     TIMESTAMP                                           NULL,
    tasa_interes     DECIMAL(5, 2)                                                                                       DEFAULT 0.00,
    fk_miembro       INT                                                 NOT NULL,
    fk_aprobador     INT                                                 NULL,
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
);

CREATE TABLE IF NOT EXISTS pagos_prestamos
(
    id               INT AUTO_INCREMENT PRIMARY KEY,
    numero_pago      INT            NOT NULL,
    monto_pago       DECIMAL(10, 2) NOT NULL,
    fecha_programada DATE           NOT NULL,
    fecha_pago       DATE           NULL,
    estado           ENUM ('pendiente', 'pagado', 'vencido') DEFAULT 'pendiente',
    fk_solicitud     INT            NOT NULL,
    CONSTRAINT fk_pago_solicitud
        FOREIGN KEY (fk_solicitud)
            REFERENCES solicitudes_prestamos (id)
            ON DELETE CASCADE,
    INDEX idx_fk_solicitud (fk_solicitud),
    INDEX idx_fecha_programada (fecha_programada),
    INDEX idx_estado (estado)
);


CREATE TABLE IF NOT EXISTS documentos_gestoria
(
    id           INT AUTO_INCREMENT PRIMARY KEY,
    titulo       VARCHAR(255) NOT NULL,
    contenido    LONGTEXT     NOT NULL,
    fecha_subida TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    adjunto      VARCHAR(255)
);

ALTER TABLE documentos_gestoria
    ADD COLUMN fecha_documento DATE;
ALTER TABLE documentos_gestoria
    ADD COLUMN privado BOOLEAN DEFAULT FALSE;

CREATE TABLE IF NOT EXISTS documentos_gremiales
(
    id              INT AUTO_INCREMENT PRIMARY KEY,
    titulo          VARCHAR(255) NOT NULL,
    contenido       LONGTEXT     NOT NULL,
    fecha_subida    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    adjunto         VARCHAR(255),
    privado         BOOLEAN               DEFAULT FALSE,
    fecha_documento DATE
);

CREATE TABLE IF NOT EXISTS documentos_minutas
(
    id              INT AUTO_INCREMENT PRIMARY KEY,
    titulo          VARCHAR(255) NOT NULL,
    contenido       LONGTEXT     NOT NULL,
    fecha_subida    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    adjunto         VARCHAR(255),
    privado         BOOLEAN               DEFAULT FALSE,
    fecha_documento DATE
);

CREATE TABLE IF NOT EXISTS documentos_tramites_ante_autoridades
(
    id              INT AUTO_INCREMENT PRIMARY KEY,
    titulo          VARCHAR(255) NOT NULL,
    contenido       LONGTEXT     NOT NULL,
    fecha_subida    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    adjunto         VARCHAR(255),
    privado         BOOLEAN               DEFAULT FALSE,
    fecha_documento DATE
);

CREATE TABLE IF NOT EXISTS propuestas
(
    id         INT AUTO_INCREMENT PRIMARY KEY,
    miembro_id INT       NOT NULL,
    fecha      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_propuesta_miembro
        FOREIGN KEY (miembro_id)
            REFERENCES miembros (id)
            ON DELETE CASCADE

);

CREATE TABLE IF NOT EXISTS propuestas_contenido
(
    id           INT AUTO_INCREMENT PRIMARY KEY,
    propuesta_id INT                             NOT NULL,
    contenido    TEXT                            NOT NULL,
    tipo         ENUM ('salarial','no_salarial') NOT NULL,
    CONSTRAINT fk_contenido_propuesta
        FOREIGN KEY (propuesta_id)
            REFERENCES propuestas (id)
            ON DELETE CASCADE
);

ALTER TABLE propuestas_contenido
    CHANGE tipo tipo ENUM ('salarial','no_salarial','comentario') NOT NULL;
-- Tabla específica para gestiones
CREATE TABLE IF NOT EXISTS gestiones
(
    id          INT AUTO_INCREMENT PRIMARY KEY,
    titulo      VARCHAR(255) NOT NULL,
    descripcion TEXT,
    fecha       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    estado      VARCHAR(50)           DEFAULT 'pendiente',
    adjunto     VARCHAR(255)
);

-- Tabla para formatos
CREATE TABLE IF NOT EXISTS formatos
(
    id           INT AUTO_INCREMENT PRIMARY KEY,
    titulo       VARCHAR(255) NOT NULL,
    descripcion  TEXT,
    archivo      VARCHAR(255) NOT NULL,
    fecha_subida TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Tabla para acervos bibliográficos
CREATE TABLE IF NOT EXISTS acervos_bibliograficos
(
    id           INT AUTO_INCREMENT PRIMARY KEY,
    titulo       VARCHAR(255) NOT NULL,
    autor        VARCHAR(255),
    descripcion  TEXT,
    tipo         VARCHAR(100),
    archivo      VARCHAR(255),
    fecha_subida TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS eventos
(
    id             INT AUTO_INCREMENT PRIMARY KEY,
    titulo         VARCHAR(255) NOT NULL,
    descripcion    TEXT,
    fecha_evento   DATETIME     NOT NULL,
    fecha_creacion TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS participantes
(
    id      INT AUTO_INCREMENT PRIMARY KEY,
    nombre  VARCHAR(255) NOT NULL,
    genero  VARCHAR(50),
    area    VARCHAR(255),
    evento  VARCHAR(255),
    cancion text
);

ALTER TABLE participantes
    ADD COLUMN agremiado BOOLEAN DEFAULT FALSE;

ALTER TABLE participantes
    ADD COLUMN miembro_id INT NULL;

ALTER TABLE participantes
    ADD COLUMN evento_id INT NULL;

ALTER TABLE participantes
    ADD COLUMN fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;


CREATE TABLE IF NOT EXISTS dudas_transparencia
(
    id     INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    correo VARCHAR(255) NOT NULL,
    duda   TEXT         NOT NULL,
    fecha  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS dudas_transparencia_respuestas
(
    id        INT AUTO_INCREMENT PRIMARY KEY,
    duda_id   INT       NOT NULL,
    respuesta TEXT      NOT NULL,
    fecha     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_respuesta_duda
        FOREIGN KEY (duda_id)
            REFERENCES dudas_transparencia (id)
            ON DELETE CASCADE
);


create table if not exists documentos_agremiados
(
    id                    int auto_increment primary key,
    miembro_id            int          not null,
    afiliacion            varchar(255) not null,
    comprobante_domicilio varchar(255) not null,
    ine                   varchar(255) not null,
    comprobante_pago      varchar(255) not null,
    fecha_subida          timestamp    not null default current_timestamp,
    constraint fk_documento_agremiado_miembro
        foreign key (miembro_id)
            references miembros (id)
            on delete cascade
);

alter table documentos_agremiados
    add column perfil varchar(255);


create table if not exists buzon
(
    id         int auto_increment primary key,
    miembro_id int       not null,
    mensaje    text      not null,
    fecha      timestamp not null default current_timestamp,
    constraint fk_buzon_miembro
        foreign key (miembro_id)
            references miembros (id)
            on delete cascade
);

create table if not exists buzon_respuestas
(
    id        int auto_increment primary key,
    buzon_id  int       not null,
    respuesta text      not null,
    fecha     timestamp not null default current_timestamp,
    constraint fk_respuesta_buzon
        foreign key (buzon_id)
            references buzon (id)
            on delete cascade
);


CREATE TABLE IF NOT EXISTS movimientos_finanzas
(
    id          INT AUTO_INCREMENT PRIMARY KEY,
    tipo        ENUM ('ingreso','egreso') NOT NULL,
    concepto    VARCHAR(255)              NOT NULL,
    monto       DECIMAL(10, 2)            NOT NULL,
    fecha       DATE                      NOT NULL,
    fk_prestamo INT                       NULL,
    fk_pago     INT                       NULL,
    observacion TEXT                      NULL,
    INDEX idx_fecha (fecha),
    INDEX idx_tipo (tipo),
    CONSTRAINT fk_mov_prestamo FOREIGN KEY (fk_prestamo) REFERENCES solicitudes_prestamos (id) ON DELETE SET NULL,
    CONSTRAINT fk_mov_pago FOREIGN KEY (fk_pago) REFERENCES pagos_prestamos (id) ON DELETE SET NULL
);


drop table if exists colores_sistema;


CREATE TABLE IF NOT EXISTS colores_sistema
(
    id             tinyint primary key check ( id = 1) default 1,
    primario       varchar(7)                          default '#611232',
    secundario     varchar(7)                          default '#a57f2c',
    exito          varchar(7)                          default '#38b44a',
    info           varchar(7)                          default '#17a2b8',
    advertencia    varchar(7)                          default '#efb73e',
    peligro        varchar(7)                          default '#df382c',
    claro          varchar(7)                          default '#e9ecef',
    oscuro         varchar(7)                          default '#002f2a',
    blanco         varchar(7)                          default '#ffffff',
    cuerpo         varchar(7)                          default '#212529',
    fondo_cuerpo   varchar(7)                          default '#f8f9fa'

);

insert into colores_sistema (id)
values (1)
on duplicate key update id=id;