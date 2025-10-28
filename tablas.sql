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

CREATE TABLE IF NOT EXISTS historial_prestamos
(
    id           INT AUTO_INCREMENT PRIMARY KEY,
    accion       VARCHAR(100) NOT NULL,
    descripcion  TEXT,
    fecha        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fk_solicitud INT          NOT NULL,
    fk_usuario   INT          NULL,
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

ALTER TABLE propuestas_contenido CHANGE tipo tipo ENUM ('salarial','no_salarial','comentario') NOT NULL;