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
    INDEX idx_correo (correo) -- índice para búsquedas rápidas por correo
);

CREATE TABLE IF NOT EXISTS miembros
(
    id               INT AUTO_INCREMENT PRIMARY KEY,
    nombre           VARCHAR(100) NOT NULL,
    apellidos        VARCHAR(255) NOT NULL,
    direccion        VARCHAR(255),
    telefono         VARCHAR(50),
    categoria        VARCHAR(100),
    departamento     VARCHAR(100),
    nss              VARCHAR(15) UNIQUE,
    curp             VARCHAR(20) UNIQUE,
    fecha_ingreso    DATE DEFAULT NULL,
    fecha_nacimiento DATE DEFAULT NULL,
    fk_usuario       INT,
    CONSTRAINT fk_miembro_usuario
        FOREIGN KEY (fk_usuario)
            REFERENCES usuarios (id)
            ON DELETE SET NULL,
    INDEX idx_fk_usuario (fk_usuario), -- índice para relaciones
    INDEX idx_nombre (nombre),         -- índice si buscas miembros por nombre
    INDEX idx_apellidos (apellidos)    -- índice si buscas miembros por apellidos
);


CREATE TABLE IF NOT EXISTS publicaciones
(
    id         INT AUTO_INCREMENT PRIMARY KEY,
    titulo     VARCHAR(255) NOT NULL,
    resumen    VARCHAR(500),
    contenido  TEXT         NOT NULL,
    imagen     VARCHAR(255),
    tipo       VARCHAR(50)           DEFAULT 'noticia',
    importante BOOLEAN               DEFAULT FALSE,
    expiracion DATE,
    fecha      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fk_miembro INT          REFERENCES miembros (id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS solicitudes_prestamos
(
    id         INT AUTO_INCREMENT PRIMARY KEY,
    monto      DECIMAL(10, 2) NOT NULL,
    plazo      INT            NOT NULL,
    fk_miembro INT REFERENCES miembros (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS prestamos_aprobados
(
    id             INT AUTO_INCREMENT PRIMARY KEY,
    monto          DECIMAL(10, 2) NOT NULL,
    plazo          INT            NOT NULL,
    tasa           DECIMAL(5, 2)  NOT NULL,
    fecha_aprobado DATE           NOT NULL,
    fk_solicitud   INT REFERENCES solicitudes_prestamos (id) ON DELETE CASCADE,
    fk_aprobador   INT            REFERENCES miembros (id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS prestamos_rechazados
(
    id            INT AUTO_INCREMENT PRIMARY KEY,
    motivo        VARCHAR(255) NOT NULL,
    fecha_rechazo DATE         NOT NULL,
    fk_solicitud  INT REFERENCES solicitudes_prestamos (id) ON DELETE CASCADE,
    fk_rechazador INT          REFERENCES miembros (id) ON DELETE SET NULL
);