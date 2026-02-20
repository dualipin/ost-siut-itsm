# Referencia Rápida - Sistema de Autenticación y RBAC

## Instalación

```bash
php commands/setup-auth.php
```

## Acceso en Archivos PHP

```php
$container = Bootstrap::buildContainer();
$authService = $container->get(AuthenticationService::class);
$roleService = $container->get(RoleService::class);
$sessionManager = $container->get(SessionManager::class);
$middlewareFactory = $container->get(MiddlewareFactory::class);
```

## Autenticación

```php
// Login
$authService->authenticate('email@ejemplo.com', 'password');

// Obtener usuario actual
$user = $authService->getCurrentUser();

// Verificar si está autenticado
$authService->isAuthenticated();

// Logout
$authService->logout();
```

## Verificar Permisos

```php
// Un rol específico
$authService->hasRole('admin');

// Alguno de los roles
$authService->hasAnyRole(['admin', 'gerente']);

// Todos los roles
$authService->hasAllRoles(['admin', 'gerente']);

// Un permiso específico
$authService->hasPermission('usuarios.crear');

// Obtener todos los permisos
$authService->getCurrentUserPermissions();
```

## Middleware en Rutas

```php
// Ruta protegida básica
$authMiddleware = $middlewareFactory->createAuthMiddleware();
if (!$authMiddleware->execute()) {
    header('HTTP/1.1 401 Unauthorized');
    exit;
}

// Requiere rol específico
$roleMiddleware = $middlewareFactory->createRoleMiddleware(['admin']);
if (!$roleMiddleware->execute()) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

// Requiere permiso
$permMiddleware = $middlewareFactory->createPermissionMiddleware(['usuarios.crear']);
if (!$permMiddleware->execute()) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}
```

## Sesiones

```php
// Guardar sesión
$sessionManager->saveUserSession($userId, $roles, $permissions);

// Verificar autenticación
$sessionManager->isAuthenticated();

// Obtener usuario de sesión
$userId = $sessionManager->getUserId();

// Renovar sesión
$sessionManager->renewSession();

// Cerrar sesión
$sessionManager->logout();
```

## Gestión de Usuarios

```php
// Obtener usuario
$user = $userRepository->findById(1);
$user = $userRepository->findByEmail('email@ejemplo.com');

// Obtener todos
$users = $userRepository->findAll();

// Crear usuario
$userId = $authService->register('email@ejemplo.com', 'password', 'Nombre', 'Apellidos');

// Cambiar contraseña
$authService->changePassword($userId, 'oldPassword', 'newPassword');

// Resetear contraseña (admin)
$authService->resetPassword($userId, 'newPassword');

// Asignar rol
$userRepository->assignRole($userId, $roleId);

// Remover rol
$userRepository->removeRole($userId, $roleId);
```

## Gestión de Roles

```php
// Obtener roles
$roles = $roleService->getAllRoles();
$role = $roleService->getRoleById(1);
$role = $roleService->getRoleByName('admin');

// Crear rol
$roleId = $roleService->createRole('editor', 'Editor de contenido', ['posts.crear', 'posts.editar']);

// Actualizar rol
$roleService->updateRole(1, 'admin', 'Administrador', ['usuarios.*']);

// Eliminar rol
$roleService->deleteRole(1);

// Asignar permiso
$roleService->assignPermissionToRole(1, 'usuarios.ver');

// Remover permiso
$roleService->removePermissionFromRole(1, 'usuarios.ver');

// Obtener permisos disponibles
$permissions = $roleService->getAllPermissions();
```

## Permisos del Sistema

```
usuarios.ver               - Ver usuarios
usuarios.crear             - Crear usuarios
usuarios.editar            - Editar usuarios
usuarios.eliminar          - Eliminar usuarios
usuarios.cambiar-contraseña - Cambiar contraseña

roles.ver                  - Ver roles
roles.crear                - Crear roles
roles.editar               - Editar roles
roles.eliminar             - Eliminar roles

prestamos.ver              - Ver préstamos
prestamos.crear            - Crear préstamos
prestamos.editar           - Editar préstamos
prestamos.eliminar         - Eliminar préstamos
prestamos.aprobar          - Aprobar préstamos
prestamos.rechazar         - Rechazar préstamos

finanzas.ver               - Ver finanzas
finanzas.reportes          - Ver reportes
finanzas.exportar          - Exportar datos

transparencia.ver          - Ver transparencia
transparencia.crear        - Crear archivos
transparencia.editar       - Editar archivos

sistema.administracion     - Panel de administración
sistema.logs               - Ver logs
sistema.configuracion      - Configurar sistema
```

## Roles Predefinidos

| Rol | Permisos |
|-----|----------|
| admin | Todos |
| gerente | prestamos.*, finanzas.ver, finanzas.reportes, usuarios.ver |
| empleado | prestamos.ver |
| usuario | Ninguno |

## Excepciones

```php
use App\Module\Auth\Exception\AuthenticationException;
use App\Module\Auth\Exception\UnauthorizedException;
use App\Module\Auth\Exception\UserNotFoundException;
use App\Module\Auth\Exception\RoleNotFoundException;
```

## Entidades Principales

### User
```php
$user->getId()
$user->getEmail()
$user->getNombre()
$user->getApellidos()
$user->getFullName()
$user->getRoles()
$user->isActivo()
$user->getLastLogin()
$user->hasRole('admin')
$user->hasPermission('usuarios.crear')
```

### Role
```php
$role->getId()
$role->getName()
$role->getDescription()
$role->getPermissions()
$role->hasPermission('usuarios.ver')
```

## Template (Latte)

```latte
{if $authService->isAuthenticated()}
    <p>Bienvenido {$authService->getCurrentUser()->getFullName()}</p>
{/if}

{if $authService->hasRole('admin')}
    <a href="/admin">Panel de Admin</a>
{/if}

{if $authService->hasPermission('usuarios.crear')}
    <button>Crear Usuario</button>
{/if}
```

## URLs de Ejemplo

```
/cuentas/login.php              - Login
/cuentas/logout.php             - Logout
/cuentas/registro.php           - Registro

/portal/                         - Dashboard (requiere auth)
/portal/usuarios/listado.php    - Lista de usuarios (requiere usuarios.ver)
/portal/usuarios/crear.php      - Crear usuario (requiere usuarios.crear)
/portal/usuarios/editar.php     - Editar usuario (requiere usuarios.editar)

/portal/roles/listado.php       - Lista de roles (requiere roles.ver)
/portal/roles/crear.php         - Crear rol (requiere roles.crear)

/portal/prestamos/listado.php   - Lista (requiere prestamos.ver)
/portal/prestamos/crear.php     - Crear (requiere prestamos.crear)
```

## Configuración

Archivo: `config/services.php` y `config/repositories.php`

Servicios registrados:
- `AuthenticationService::class`
- `RoleService::class`
- `SessionManager::class`
- `MiddlewareFactory::class`
- `UserRepositoryInterface::class`
- `RoleRepositoryInterface::class`

## Variables de Sesión

```php
$_SESSION['auth_user_id']         // ID del usuario
$_SESSION['auth_user_roles']      // Roles (array)
$_SESSION['auth_user_permissions'] // Permisos (array)
$_SESSION['auth_session_created'] // Timestamp creación
```

## Troubleshooting

```php
// Ver contenido de sesión
var_dump($_SESSION);

// Verificar usuario actual
var_dump($authService->getCurrentUser());

// Ver permisos del usuario
var_dump($authService->getCurrentUserPermissions());

// Ver roles del usuario
var_dump($authService->getCurrentUser()->getRoles());

// Verificar estado de sesión
var_dump($sessionManager->isSessionValid());
```

## Seguridad

- ✅ Contraseñas hasheadas con bcrypt (cost: 12)
- ✅ Timeout de sesión: 1 hora (configurable)
- ✅ Validación de email
- ✅ Control de acceso granular
- ✅ Auditoría de login (tabla auth_logs)

## Cambiar Timeout de Sesión

```php
$sessionManager->setSessionTimeout(7200); // 2 horas
```

## Debugging

Solo admins pueden ver la siguiente información de debug:

```php
if ($authService->hasRole('admin')) {
    // Mostrar permisos
    // Mostrar logs
    // Mostrar configuración
}
```
