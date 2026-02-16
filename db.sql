CREATE TABLE IF NOT EXISTS usuarios
(
    usuario_id        varchar(36) PRIMARY KEY,
    curp              VARCHAR(20)  NOT NULL UNIQUE,
    correo            VARCHAR(255) NOT NULL UNIQUE,
    contra            VARCHAR(255) NOT NULL,
    rol               VARCHAR(50)  NOT NULL DEFAULT 'agremiado',
    nombre            VARCHAR(100) NOT NULL,
    apellidos         VARCHAR(255) NOT NULL,
    direccion         VARCHAR(255),
    telefono          VARCHAR(50),
    categoria         VARCHAR(100),
    departamento      VARCHAR(100),
    nss               VARCHAR(15),
    fecha_nacimiento  DATE                  DEFAULT NULL,
    salario_quincenal DECIMAL(10, 2)        DEFAULT NULL,
    activo            BOOLEAN               DEFAULT TRUE,
    agremiado         boolean               default true,
    actualizado       datetime              default null,
    creado            datetime              default current_timestamp,
    ultimo_ingreso    datetime              DEFAULT NULL,
    INDEX idx_correo (correo),
    INDEX idx_nombre (nombre),
    INDEX idx_apellidos (apellidos)
);


create table if not exists documentos_usuarios
(
    usuario_id            varchar(36) primary key references usuarios (usuario_id) on delete cascade,
    afiliacion            varchar(255) not null,
    comprobante_domicilio varchar(255) not null,
    ine                   varchar(255) not null,
    comprobante_pago      varchar(255) not null,
    foto_perfil           varchar(255) not null,
    actualizado           datetime default current_timestamp on update current_timestamp
);


create table if not exists tipos_acervos
(
    tipo_id int auto_increment primary key,
    tipo    varchar(100) not null unique
);


insert into tipos_acervos (tipo)
values ('gestoría'),
       ('gremiales'),
       ('tramites ante autoridades'),
       ('minutas y acuerdos');


create table if not exists acervos
(
    acervo_id     int auto_increment primary key,
    tipo_id       int          references tipos_acervos (tipo_id) on delete set null,
    nombre        varchar(255) not null,
    descripcion   text,
    ruta          varchar(255) not null,
    mime          varchar(100) not null,
    privado       boolean               default false,
    fecha_emision date         not null,
    fecha_subida  timestamp    not null default current_timestamp,
    usuario_id    varchar(36)  references usuarios (usuario_id) on delete set null
);

create table if not exists acervos_visitas
(
    acervo_visita_id int auto_increment primary key,
    acervo_id        int         not null,
    usuario_id       varchar(36) null,
    fecha_visita     timestamp   not null default current_timestamp,
    constraint fk_acervo_visitas
        foreign key (acervo_id)
            references acervos (acervo_id)
            on delete cascade,
    constraint fk_acervo_visitas_usuarios
        foreign key (usuario_id)
            references usuarios (usuario_id)
            on delete set null
);


create table if not exists tipos_publicaciones
(
    tipo_id int auto_increment primary key,
    tipo    varchar(100) not null unique
);


insert into tipos_publicaciones (tipo)
values ('noticia'),
       ('avisos'),
       ('gestiones');


CREATE TABLE IF NOT EXISTS publicaciones
(
    publicacion_id INT AUTO_INCREMENT PRIMARY KEY,
    titulo         VARCHAR(255) NOT NULL,
    resumen        VARCHAR(255),
    contenido      LONGTEXT     NOT NULL,
    imagen         VARCHAR(255),
    tipo           INT          REFERENCES tipos_publicaciones (tipo_id) ON DELETE SET NULL,
    importante     BOOLEAN               DEFAULT FALSE,
    expiracion     DATE                  DEFAULT NULL,
    fecha          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    usuario_id     varchar(36)  REFERENCES usuarios (usuario_id) ON DELETE SET NULL
);


CREATE TABLE IF NOT EXISTS visitas_pagina
(
    visita_pagina_id INT AUTO_INCREMENT PRIMARY KEY,
    direccion_ip     VARCHAR(45)  NOT NULL,
    fecha_visita     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    pagina           VARCHAR(255) NOT NULL,
    usuario_id       varchar(36)  NULL,
    constraint fk_visitas_pagina_usuarios
        foreign key (usuario_id)
            references usuarios (usuario_id)
            on delete set null
);


create table if not exists sindicatos(
    sindicato_id int auto_increment primary key,
    nombre       varchar(255) not null,
    direccion    varchar(255),
    telefono     varchar(50),
    correo       varchar(255),
    creado       datetime     default current_timestamp,
    actualizado  datetime     default null on update current_timestamp
);


CREATE TABLE IF NOT EXISTS colores_sistema
(
    id          tinyint primary key check ( id = 1) default 1,
    primario    varchar(7)                          default '#611232',
    secundario  varchar(7)                          default '#a57f2c',
    exito       varchar(7)                          default '#38b44a',
    info        varchar(7)                          default '#17a2b8',
    advertencia varchar(7)                          default '#efb73e',
    peligro     varchar(7)                          default '#df382c',
    claro       varchar(7)                          default '#e9ecef',
    oscuro      varchar(7)                          default '#002f2a'
);

insert into colores_sistema (id)
values (1)
on duplicate key update id=id;