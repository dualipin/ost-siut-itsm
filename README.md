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

# Sistema de Préstamos - SIUT ITSM

## Descripción

Sistema completo de préstamos para agremiados que cumple con todos los requerimientos especificados:

- Solicitud de préstamos con recibo de nómina obligatorio
- Gestión administrativa para aprobar/rechazar solicitudes
- Sistema de pagarés con descarga, firma y subida
- Corrida financiera con simulación de pagos quincenales
- Validación del 70% máximo del salario quincenal
- Lista de espera para solicitudes no atendidas

## Instalación

1. **Ejecutar migración de base de datos:**

   ```bash
   php comandos/migrar-prestamos.php
   ```

2. **Configurar salarios quincenales:**
   - Acceder como administrador a: `aplicacion/prestamos/actualizar-salario.php`
   - Registrar el salario quincenal de cada miembro

## Funcionalidades

### Para Agremiados

1. **Solicitar Préstamo** (`aplicacion/prestamos/solicitar.php`)

   - Monto solicitado (máximo 70% del salario quincenal)
   - Plazo en meses (3, 6, 12, 18, 24)
   - Tipo de descuento: Quincenal, Aguinaldo, Prima Vacacional
   - Justificación obligatoria
   - Recibo de nómina obligatorio (PDF, JPG, PNG)

2. **Simulador** (`aplicacion/prestamos/simulador.php`)

   - Calcular pagos quincenales
   - Ver corrida financiera completa

3. **Gestión de Pagarés** (`aplicacion/prestamos/pagare.php`)

   - Descargar pagaré en PDF
   - Subir pagaré firmado
   - Activar préstamo

4. **Seguimiento** (`aplicacion/prestamos/index.php`)
   - Ver todas las solicitudes
   - Estados: Pendiente, Aprobado, Rechazado, Lista de Espera, etc.
   - Ver calendario de pagos

### Para Administradores/Finanzas

1. **Administrar Solicitudes** (`aplicacion/prestamos/administrar.php`)

   - Ver solicitudes pendientes
   - Aprobar con monto específico
   - Rechazar con motivo
   - Poner en lista de espera

2. **Gestionar Salarios** (`aplicacion/prestamos/actualizar-salario.php`)
   - Actualizar salarios quincenales
   - Ver montos máximos de préstamo

## Flujo del Sistema

### 1. Solicitud

- El agremiado llena el formulario con monto, plazo y justificación
- **Obligatorio:** Adjuntar recibo de nómina del período donde se hará el descuento
- Sistema valida que no exceda el 70% del salario quincenal
- Se crea solicitud con estado "Pendiente"

### 2. Evaluación Administrativa

- Administrador/Finanzas ve solicitudes pendientes
- Puede aprobar (con monto igual o menor al solicitado)
- Puede rechazar (con motivo)
- Puede poner en lista de espera para siguiente quincena

### 3. Pagaré (Solo si se aprueba)

- Sistema genera pagaré con corrida financiera
- Agremiado descarga, firma y sube el pagaré
- Préstamo se activa automáticamente

### 4. Corrida Financiera

- **Quincenal:** Pagos cada 15 días por el plazo especificado
- **Aguinaldo:** Pagos quincenales hasta diciembre del año actual
- **Prima Vacacional:** Pagos quincenales hasta julio del año siguiente

## Validaciones Implementadas

1. **Capacidad de Pago:**

   - Máximo 70% del salario quincenal
   - Considera préstamos activos existentes

2. **Archivos:**

   - Recibo de nómina: PDF, JPG, JPEG, PNG
   - Pagaré firmado: Solo PDF

3. **Estados del Préstamo:**
   - `pendiente`: Esperando evaluación
   - `aprobado`: Aprobado (estado transitorio)
   - `rechazado`: Rechazado con motivo
   - `lista_espera`: Para siguiente quincena
   - `pagare_pendiente`: Esperando pagaré firmado
   - `activo`: Préstamo activo con pagos programados
   - `pagado`: Préstamo completamente pagado

## Estructura de Base de Datos

### Tablas Principales

1. **solicitudes_prestamos**: Solicitudes con todos los datos
2. **pagos_prestamos**: Calendario de pagos generado automáticamente
3. **historial_prestamos**: Registro de todas las acciones

### Campos Importantes

- `salario_quincenal` en tabla `miembros`: Necesario para validaciones
- `tipo_descuento`: Determina cómo se calcula la corrida financiera
- `monto_aprobado`: Puede ser menor al solicitado

## Archivos del Sistema

### Controladores

- `aplicacion/prestamos/index.php` - Dashboard principal
- `aplicacion/prestamos/solicitar.php` - Formulario de solicitud
- `aplicacion/prestamos/procesar-solicitud.php` - Procesar nueva solicitud
- `aplicacion/prestamos/administrar.php` - Panel administrativo
- `aplicacion/prestamos/pagare.php` - Gestión de pagarés
- `aplicacion/prestamos/actualizar-salario.php` - Gestión de salarios
- `aplicacion/prestamos/api-pagos.php` - API para obtener pagos

### Servicios

- `src/Servicios/ServicioPrestamos.php` - Lógica principal
- `src/Servicios/ServicioMiembros.php` - Gestión de miembros

### Entidades

- `src/Entidades/SolicitudPrestamo.php` - Entidad principal
- `src/Entidades/PagoPrestamo.php` - Entidad de pagos
- `src/Entidades/Miembro.php` - Entidad de miembros

### Plantillas

- `aplicacion/plantillas/prestamos.latte` - Dashboard
- `aplicacion/plantillas/prestamos-solicitar.latte` - Formulario
- `aplicacion/plantillas/prestamos-administrar.latte` - Panel admin
- `aplicacion/plantillas/prestamos-pagare.latte` - Gestión pagarés
- `aplicacion/plantillas/prestamos-salarios.latte` - Gestión salarios

## Configuración Requerida

### Variables de Entorno (.env)

```
DB_HOST=localhost
DB_NAME=siut_itsm
DB_USER=root
DB_PASS=
```

### Permisos de Directorio

- `temp/recibos-nomina/` - Lectura/escritura
- `temp/pagares-firmados/` - Lectura/escritura

### Roles de Usuario

- `agremiado`: Puede solicitar préstamos
- `finanzas`: Puede administrar solicitudes
- `administrador`: Acceso completo + gestión de salarios

## Notas Importantes

1. **Salarios Obligatorios:** Los miembros deben tener salario quincenal registrado para poder solicitar préstamos.

2. **Recibo de Nómina:** Debe corresponder al tipo de descuento:

   - Quincenal: Último recibo quincenal
   - Aguinaldo: Recibo con aguinaldo del año pasado
   - Prima Vacacional: Recibo con prima vacacional del año pasado

3. **Corrida Financiera:** Siempre muestra pagos quincenales, incluso para aguinaldo y prima vacacional.

4. **Archivos:** Se almacenan en `temp/` - considerar mover a ubicación más segura en producción.

5. **Seguridad:** Validar que los usuarios solo accedan a sus propias solicitudes.

## Próximas Mejoras Sugeridas

1. Notificaciones por email
2. Reportes de préstamos
3. Dashboard con estadísticas
4. Integración con nómina
5. Recordatorios de pagos vencidos
6. Generación de PDF mejorada para pagarés

_Desarrollado con ❤️ para la comunidad sindical del ITSM_
