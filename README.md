# SIUT-ITSM - Sitio Web del Sindicato

Sitio web oficial del **Sindicato Único de Trabajadores del Instituto Tecnológico Superior de Macuspana (SIUT-ITSM)**.

---

## 📌 Descripción

Aplicación web desarrollada en **PHP 8.3+** que ofrece a los agremiados:

- Acceso a información sindical y noticias.
- Gestión de trámites y consultas.
- Descarga de documentos y generación de PDF.
- Panel de usuario con permisos y roles.

---

## ✨ Características principales

- Sistema de autenticación y gestión de usuarios.
- Módulos de publicaciones, préstamos, transparencia, mensajería y configuración.
- Integración con correo (PHPMailer) para notificaciones y recuperación de contraseña.
- Plantillas Latte para renderizado de vistas.
- Soporte para importación de datos (direcciones, usuarios) y carga de archivos.

---

## 🧰 Tecnologías

- **PHP 8.3+**
- **Latte** (templating)
- **PHP-DI** (inyección de dependencias)
- **Monolog** (logging)
- **PHPMailer** (envío de correos)
- **dompdf** (generación de PDF)
- **Dotenv** (configuración de entorno)
- **Pest** (tests)

---

## ✅ Requisitos previos

- PHP 8.3 o superior
- Extensiones PHP: `pdo`, `intl`, `zip`
- Composer
- Servidor web (Apache/Nginx) o el servidor embebido de PHP
- Base de datos MySQL / MariaDB

---

## 🚀 Instalación rápida

1. Clonar el repositorio:

```bash
git clone https://github.com/mrtnsnchz/ost-siut-itsm.git
cd ost-siut-itsm
```

2. Instalar dependencias:

```bash
composer install
```

3. Configurar variables de entorno:

```bash
cp .env.example .env
```

Edita `.env` para ajustar las credenciales de la base de datos, correo y URL base.

4. Importar la base de datos (opcional):

- Usar `database.sql` para la estructura y datos iniciales.
- Usar `database.seed.sql` para carga de datos de ejemplo.

---

## ▶️ Ejecutar en modo desarrollo

Puedes usar el servidor embebido de PHP:

```bash
composer run servidor
```

Luego abre: `http://localhost:8888`

---

## 🧪 Tests

Se usan pruebas con **Pest**.

```bash
vendor/bin/pest
```

---

## 🗂️ Estructura del proyecto

- `app/` – Código fuente (módulos, controladores, servicios, infraestructura).
- `public/` – Punto de entrada público (vistas, assets, rutas).
- `config/` – Configuraciones y definiciones de DI.
- `commands/` – Scripts CLI (importación, minificación, etc.).
- `tests/` – Pruebas unitarias y de integración.
- `templates/` – Plantillas Latte compartidas.

---

## 🤝 Contribuir

1. Crea un fork y una rama propia.
2. Realiza cambios con tests.
3. Abre un Pull Request describiendo tus cambios.

---

## 📄 Licencia

Este proyecto está bajo la licencia **MIT**.

---

## ✉️ Contacto

Para dudas o soporte, contacta al equipo del sindicato o al autor del repositorio.
