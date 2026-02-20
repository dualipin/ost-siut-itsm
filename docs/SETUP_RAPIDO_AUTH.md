# Guía Rápida de Implementación

## Instalación Rápida

### 1. Ejecutar el setup

```bash
cd c:\Users\dualipin\Projects\ost-siut-itsm
php commands/setup-auth.php
```

Esto creará:
- Tablas en base de datos
- Roles predefinidos (admin, gerente, empleado, usuario)
- 3 usuarios de ejemplo

**Credenciales de ejemplo:**
- Email: `admin@ejemplo.com` | Contraseña: `password123`
- Email: `gerente@ejemplo.com` | Contraseña: `password123`
- Email: `empleado@ejemplo.com` | Contraseña: `password123`

### 2. Crear archivo de login

```php
<?php
// cuentas/login.php

require_once __DIR__ . '/../bootstrap.php';

use App\Bootstrap;
use App\Module\Auth\Service\AuthenticationService;
use App\Module\Auth\Session\SessionManager;

$container = Bootstrap::buildContainer();
$authService = $container->get(AuthenticationService::class);
$sessionManager = $container->get(SessionManager::class);

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($authService->authenticate($email, $password)) {
        $user = $authService->getCurrentUser();
        
        // Guardar en sesión
        $sessionManager->saveUserSession(
            $user->getId(),
            array_map(fn($r) => $r->getName(), $user->getRoles()),
            $authService->getCurrentUserPermissions()
        );

        header('Location: /portal/');
        exit;
    } else {
        $error = 'Email o contraseña incorrectos';
    }
}

// Mostrar formulario...
?>
```

### 3. Proteger rutas

```php
<?php
// portal/index.php

require_once __DIR__ . '/../bootstrap.php';

use App\Bootstrap;
use App\Module\Auth\Middleware\MiddlewareFactory;

$container = Bootstrap::buildContainer();
$middlewareFactory = $container->get(\App\Module\Auth\Middleware\MiddlewareFactory::class);

// Requiere autenticación
$authMiddleware = $middlewareFactory->createAuthMiddleware();
if (!$authMiddleware->execute()) {
    header('Location: /cuentas/login.php');
    exit;
}

// Obtener usuario actual
$authService = $container->get(\App\Module\Auth\Service\AuthenticationService::class);
$user = $authService->getCurrentUser();

echo "Bienvenido " . $user->getFullName();
?>
```

### 4. Proteger con roles

```php
<?php
// portal/administracion.php

use App\Module\Auth\Middleware\MiddlewareFactory;

$middlewareFactory = $container->get(MiddlewareFactory::class);

// Solo admin y gerente
$roleMiddleware = $middlewareFactory->createRoleMiddleware(['admin', 'gerente']);
if (!$roleMiddleware->execute()) {
    header('HTTP/1.1 403 Forbidden');
    die('Acceso denegado');
}

// Contenido de administración...
?>
```

### 5. Proteger con permisos

```php
<?php
// portal/prestamos/crear.php

// Requiere permiso de crear préstamos
$permissionMiddleware = $middlewareFactory->createPermissionMiddleware([
    'prestamos.crear'
]);

if (!$permissionMiddleware->execute()) {
    header('HTTP/1.1 403 Forbidden');
    die('No tiene permisos para crear préstamos');
}

// Código para crear préstamo...
?>
```

## Estructura de Carpetas Creadas

```
app/Module/Auth/
├── Entity/                    # Entidades del dominio
├── DTO/                       # Data Transfer Objects
├── Repository/                # Repositorios (DB)
├── Service/                   # Servicios principales
├── Middleware/                # Middleware de protección
├── Session/                   # Gestión de sesiones
└── Exception/                 # Excepciones personalizadas

app/Infrastructure/Database/
├── MigrationRunner.php        # Ejecutor de migraciones
└── AuthSeeder.php             # Seeder de datos iniciales

migrations/
└── 001_create_auth_tables.sql # Script SQL

commands/
└── setup-auth.php             # Comando de instalación
```

## Permisos Disponibles

```
usuarios.ver
usuarios.crear
usuarios.editar
usuarios.eliminar
usuarios.cambiar-contraseña

roles.ver
roles.crear
roles.editar
roles.eliminar

prestamos.ver
prestamos.crear
prestamos.editar
prestamos.eliminar
prestamos.aprobar
prestamos.rechazar

finanzas.ver
finanzas.reportes
finanzas.exportar

transparencia.ver
transparencia.crear
transparencia.editar

sistema.administracion
sistema.logs
sistema.configuracion
```

## Roles Disponibles

| Rol | Descripción | Permisos |
|-----|-------------|----------|
| admin | Administrador total | Todos |
| gerente | Gerente de préstamos | Ver, crear, editar, aprobar préstamos + reportes |
| empleado | Empleado | Ver préstamos |
| usuario | Usuario estándar | Ninguno |

## API Rápida

```php
// Autenticación
$authService->authenticate($email, $password): bool
$authService->isAuthenticated(): bool
$authService->getCurrentUser(): ?User
$authService->logout(): void

// Verificar permisos
$authService->hasRole('admin'): bool
$authService->hasPermission('prestamos.ver'): bool

// Middleware
$middlewareFactory->createAuthMiddleware()
$middlewareFactory->createRoleMiddleware(['admin'])
$middlewareFactory->createPermissionMiddleware(['prestamos.ver'])

// Gestión de roles
$roleService->getAllRoles(): array
$roleService->createRole($name, $description, $permissions): int
$roleService->updateRole($id, $name, $description, $permissions): bool

// Sesiones
$sessionManager->saveUserSession($userId, $roles, $permissions)
$sessionManager->isAuthenticated(): bool
$sessionManager->getUserId(): ?int
$sessionManager->logout(): void
```

## Troubleshooting

### "Base de datos no tiene las tablas"
Ejecutar: `php commands/setup-auth.php`

### "Error de sesión"
Verificar que las sesiones estén habilitadas en php.ini:
```ini
session.save_path = "/ruta/a/tmp"
```

### "Usuario siempre no autenticado"
Verificar que la sesión se esté guardando correctamente:
```php
$sessionManager->saveUserSession($userId, $roles, $permissions);
```

### "Middleware siempre falla"
Asegurarse de llamar a `execute()` antes de verificar:
```php
if (!$middleware->execute()) {
    // Falló
}
```

## Siguiente Paso

Leer documentación completa: [AUTENTICACION_RBAC.md](AUTENTICACION_RBAC.md)
Ver ejemplos de uso: [AUTENTICACION_RBAC_EJEMPLOS.php](AUTENTICACION_RBAC_EJEMPLOS.php)
