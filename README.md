# SIUT-ITSM - Sitio Web del Sindicato

Sitio web oficial del **Sindicato Único de Trabajadores del Instituto Tecnológico Superior de Macuspana (SIUT-ITSM)**.

## Descripción

Este proyecto es una aplicación web desarrollada en PHP que proporciona una plataforma digital para el sindicato SIUT-ITSM. El sitio permite a los agremiados acceder a información sindical, gestionar trámites, consultar noticias y mantenerse informados sobre las actividades del sindicato.

## Características

- **Portal de información sindical** - Misión, visión y objetivos del sindicato
- **Sistema de gestiones** - Trámites y solicitudes en línea
- **Noticias y avisos** - Comunicados y actualizaciones importantes
- **Transparencia** - Información institucional y contacto
- **Área privada** - Panel para miembros registrados
- **Sistema de préstamos** - Gestión de préstamos para agremiados

## Tecnologías Utilizadas

- **PHP 8+** - Lenguaje de programación principal
- **Latte** - Motor de plantillas
- **MySQL** - Base de datos
- **Bootstrap** - Framework CSS
- **Composer** - Gestor de dependencias
- **PHPMailer** - Envío de correos electrónicos

## Requisitos del Sistema

- PHP 8.0 o superior
- MySQL 5.7 o superior
- Composer
- Servidor web (Apache/Nginx)
- Extensiones PHP requeridas:
  - PDO
  - OpenSSL
  - Extensiones estándar de PHP

## Instalación

### 1. Clonar el repositorio

```bash
git clone https://github.com/dualipin/ost-siut-itsm.git
cd ost-siut-itsm
```

### 2. Instalar dependencias

```bash
composer install
```

### 3. Configurar el entorno

Copiar el archivo de ejemplo de configuración:

```bash
cp .env.example .env
```

Editar el archivo `.env` con tus configuraciones:

```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=siut_db
DB_USER=tu_usuario
DB_PASS=tu_contraseña

MAIL_USERNAME=tu_correo@gmail.com
MAIL_NAME=SIUTITSM
MAIL_PASSWORD=tu_contraseña_app
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587

APP_ENV=production
```

### 4. Configurar la base de datos

Crear la base de datos e importar el esquema:

```bash
mysql -u tu_usuario -p tu_base_datos < tablas.sql
```

### 5. Configurar el servidor web

Asegúrate de que el directorio raíz del proyecto sea accesible desde tu servidor web y que el archivo `.htaccess` esté funcionando correctamente.

## Estructura del Proyecto

```
├── src/                    # Código fuente PHP
│   ├── Configuracion/      # Configuraciones del sistema
│   ├── Entidades/          # Entidades del dominio
│   ├── Fabricas/           # Patrones Factory
│   ├── Manejadores/        # Manejadores de lógica
│   ├── Modelos/            # Modelos de datos
│   ├── Servicios/          # Servicios de aplicación
│   └── Utilidades/         # Utilidades y helpers
├── plantillas/             # Plantillas Latte
├── aplicacion/             # Área privada de la aplicación
├── assets/                 # Recursos estáticos (CSS, JS, imágenes)
├── comandos/               # Scripts de comandos
├── *.php                   # Páginas públicas del sitio
└── composer.json           # Dependencias de Composer
```

## Uso

### Páginas Públicas

- **Inicio** (`/`) - Página principal con información general
- **Acerca de** (`/acerca-de.php`) - Información del sindicato
- **Noticias** (`/noticias.php`) - Últimas noticias y comunicados
- **Gestiones** (`/gestiones.php`) - Información sobre trámites
- **Transparencia** (`/transparencia.php`) - Información institucional
- **Contacto** (`/contacto.php`) - Información de contacto

### Área Privada

- **Login** (`/aplicacion/`) - Acceso para miembros
- **Perfil** (`/aplicacion/perfil.php`) - Gestión de perfil personal
- **Préstamos** (`/aplicacion/prestamos/`) - Sistema de préstamos
- **Buzón** (`/aplicacion/buzon.php`) - Mensajería interna

## Desarrollo

### Minificar Assets

Para optimizar los archivos CSS y JS:

```bash
composer run minificar
```

### Variables de Entorno

El proyecto utiliza variables de entorno para la configuración. Asegúrate de configurar:

- Credenciales de base de datos
- Configuración de correo electrónico
- Entorno de ejecución (dev/production)

## Contribuir

1. Fork el repositorio
2. Crea una rama para tu feature (`git checkout -b feature/nueva-funcionalidad`)
3. Commit tus cambios (`git commit -am 'Agregar nueva funcionalidad'`)
4. Push a la rama (`git push origin feature/nueva-funcionalidad`)
5. Crea un Pull Request

## Licencia

Este proyecto está desarrollado para el Sindicato Único de Trabajadores del ITSM.

## Contacto

**Sindicato SIUT-ITSM**
- Email: Sindicato_SIUTITSM@outlook.com  
- Teléfono: 936 106 61 69

## Autor

- **Martin Sanchez** - [dualipin](https://github.com/dualipin)

---

*Desarrollado con ❤️ para la comunidad sindical del ITSM*
