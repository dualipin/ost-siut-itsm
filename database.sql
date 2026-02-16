create table usuarios
(
    usuario_id        int auto_increment primary key,
    email             varchar(100)                                                     not null unique,
    password_hash     varchar(255)                                                     not null,
    rol               enum ('admin', 'finanzas', 'agremiado', 'no_agremiado', 'lider') not null,
    nombre            VARCHAR(100)                                                     NOT NULL,
    apellidos         VARCHAR(255)                                                     NOT NULL,
    direccion         VARCHAR(255),
    telefono          VARCHAR(50),
    categoria         VARCHAR(100),
    departamento      VARCHAR(100),
    nss               VARCHAR(15),
    curp              VARCHAR(20),
    fecha_ingreso     DATE           DEFAULT NULL,
    fecha_nacimiento  DATE           DEFAULT NULL,
    salario_quincenal DECIMAL(12, 2) DEFAULT 0,
    activo            BOOLEAN        DEFAULT TRUE
);


create table publicaciones
(
    publicacion_id    int auto_increment primary key,
    titulo            varchar(100) not null,
    resumen           varchar(255),
    contenido         text         not null,
    tipo_publicacion   enum('noticia', 'gestion', 'aviso') not null,
    fecha_publicacion datetime     not null,
    fecha_expiracion  date default null,
    autor_id          int
);