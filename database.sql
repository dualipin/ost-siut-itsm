create table usuarios
(
    usuario_id            BINARY(16) PRIMARY KEY,

    -- auth
    email                 varchar(100)                                                     not null unique,
    password_hash         varchar(255)                                                     not null,
    rol                   enum ('admin', 'finanzas', 'agremiado', 'no_agremiado', 'lider') not null,
    activo                BOOLEAN        DEFAULT TRUE,

    -- info personal
    curp                  VARCHAR(20),
    nombre                VARCHAR(100)                                                     NOT NULL,
    apellidos             VARCHAR(255)                                                     NOT NULL,
    fecha_nacimiento      DATE           DEFAULT NULL,
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
    salario_quincenal     DECIMAL(12, 2) DEFAULT 0,
    fecha_ingreso_laboral DATE           DEFAULT NULL,

    -- sesion
    ultimo_ingreso        DATETIME       DEFAULT NULL,
    fecha_creacion        DATETIME       DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion   DATETIME       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fecha_eliminacion     DATETIME       DEFAULT NULL
);


create table publicaciones
(
    publicacion_id    int auto_increment primary key,
    titulo            varchar(100)                         not null,
    resumen           varchar(255),
    contenido         text                                 not null,
    tipo_publicacion  enum ('noticia', 'gestion', 'aviso') not null,
    fecha_publicacion datetime                             not null,
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

insert into colores_sistema (id)
values (1)
on duplicate key update id=id;