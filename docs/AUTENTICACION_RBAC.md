# Sistema de Autenticación y RBAC

## Descripción General

Este documento describe la implementación completa de un sistema de autenticación y control de acceso basado en roles (RBAC - Role-Based Access Control) para la aplicación.

## Características

- ✅ Autenticación de usuarios con contraseñas hasheadas (bcrypt)
- ✅ Sistema de roles y permisos (RBAC)
- ✅ Middleware para proteger rutas
- ✅ Gestión de sesiones
- ✅ Auditoría de logins
- ✅ Integración con contenedor DI
- ✅ Permisos granulares

## Estructura de Carpetas

```
app/Module/Auth/
├── Entity/
│   ├── User.php          # Entidad de usuario
│   └── Role.php          # Entidad de rol
├── DTO/
│   ├── UserDTO.php       # DTO de usuario
│   └── RoleDTO.php       # DTO de rol
├── Repository/
│   ├── UserRepositoryInterface.php
│   ├── UserRepository.php
│   ├── RoleRepositoryInterface.php
│   └── RoleRepository.php
├── Service/
│   ├── AuthenticationService.php  # Servicio principal de auth
│   └── RoleService.php            # Servicio de gestión de roles
├── Middleware/
│   ├── BaseMiddleware.php
│   ├── AuthMiddleware.php
│   ├── RoleMiddleware.php
│   ├── PermissionMiddleware.php
│   └── MiddlewareFactory.php
├── Session/
│   └── SessionManager.php         # Gestor de sesiones
└── Exception/
    ├── AuthenticationException.php
    ├── UnauthorizedException.php
    ├── UserNotFoundException.php
    └── RoleNotFoundException.php
```

## Entidades Principales

### User (Usuario)

Representa un usuario del sistema con los siguiente atributos:
- `id`: ID único del usuario
- `email`: Email único del usuario
- `password`: Contraseña hasheada (bcrypt)
- `nombre`: Nombre del usuario
- `apellidos`: Apellidos del usuario
- `roles`: Array de roles asignados
- `activo`: Estado del usuario
- `createdAt`: Fecha de creación
- `updatedAt`: Fecha de última actualización
- `lastLogin`: Fecha del último login

**Métodos principales:**
```php
hasRole(string $role): bool                    // Verifica si tiene un rol
hasPermission(string $permission): bool        // Verifica si tiene un permiso
hasAnyRole(array $roleNames): bool            // Verifica si tiene alguno de los roles
hasAllRoles(array $roleNames): bool           // Verifica si tiene todos los roles
```

### Role (Rol)

Representa un rol del sistema con permisos asociados:
- `id`: ID único del rol
- `name`: Nombre único del rol
- `description`: Descripción del rol
- `permissions`: Array de permisos del rol

**Métodos principales:**
```php
hasPermission(string $permission): bool       // Verifica si el rol tiene un permiso
addPermission(string $permission): void       // Agrega un permiso al rol
removePermission(string $permission): void    // Remueve un permiso del rol
```

## Tablas de Base de Datos

### users
```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    last_login TIMESTAMP
);
```

### roles
```sql
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### user_roles
```sql
CREATE TABLE user_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    assigned_at TIMESTAMP,
    UNIQUE KEY (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
);
```

### role_permissions
```sql
CREATE TABLE role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    permission VARCHAR(255) NOT NULL,
    assigned_at TIMESTAMP,
    UNIQUE KEY (role_id, permission),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
);
```

### auth_logs (opcional)
```sql
CREATE TABLE auth_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    email VARCHAR(255),
    action VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    success BOOLEAN NOT NULL DEFAULT FALSE,
    error_message TEXT,
    created_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
```

## Servicios principales

### AuthenticationService

Servicio encargado de la autenticación y autorización.

```php
// Autenticar usuario
authenticate(string $email, string $password): bool

// Registrar nuevo usuario
register(string $email, string $password, string $nombre, string $apellidos): int

// Obtener usuario actual
getCurrentUser(): ?User

// Establecer usuario actual
setCurrentUser(?User $user): void

// Verificar autenticación
isAuthenticated(): bool

// Verificar roles
hasRole(string $role): bool
hasAnyRole(array $roles): bool
hasAllRoles(array $roles): bool

// Verificar permisos
hasPermission(string $permission): bool

// Obtener permisos del usuario
getCurrentUserPermissions(): array

// Cerrar sesión
logout(): void

// Cambiar contraseña
changePassword(int $userId, string $oldPassword, string $newPassword): bool

// Resetear contraseña (admin)
resetPassword(int $userId, string $newPassword): bool

// Validar email
isValidEmail(string $email): bool

// Verificar si email existe
emailExists(string $email): bool
```

### RoleService

Servicio para gestión de roles y permisos.

```php
// Obtener todos los roles
getAllRoles(): array

// Obtener rol por ID
getRoleById(int $id): ?Role

// Obtener rol por nombre
getRoleByName(string $name): ?Role

// Crear nuevo rol
createRole(string $name, string $description, array $permissions = []): int

// Actualizar rol
updateRole(int $id, string $name, string $description, array $permissions = []): bool

// Eliminar rol
deleteRole(int $id): bool

// Asignar permiso a rol
assignPermissionToRole(int $roleId, string $permission): bool

// Remover permiso de rol
removePermissionFromRole(int $roleId, string $permission): bool

// Obtener todos los permisos disponibles
getAllPermissions(): array

// Obtener permisos predefinidos
static getPredefinedPermissions(): array

// Obtener roles predefinidos
static getPredefinedRoles(): array
```

## Middleware

### AuthMiddleware
Requiere autenticación válida. Si no está autenticado, retorna 401.

```php
$authMiddleware = $middlewareFactory->createAuthMiddleware();
if (!$authMiddleware->execute()) {
    header('HTTP/1.1 401 Unauthorized');
    die($authMiddleware->getLastException()->getMessage());
}
```

### RoleMiddleware
Requiere que el usuario tenga al menos uno de los roles especificados.

```php
$roleMiddleware = $middlewareFactory->createRoleMiddleware(['admin', 'gerente']);
if (!$roleMiddleware->execute()) {
    header('HTTP/1.1 403 Forbidden');
    die($roleMiddleware->getLastException()->getMessage());
}
```

### PermissionMiddleware
Requiere que el usuario tenga un permiso específico.

```php
// Requiere ANY de los permisos
$permissionMiddleware = $middlewareFactory->createPermissionMiddleware([
    'prestamos.ver',
    'prestamos.crear'
]);

// Requiere ALL de los permisos
$permissionMiddleware = $middlewareFactory->createPermissionMiddleware(
    ['prestamos.crear', 'prestamos.editar'],
    requireAll: true
);

if (!$permissionMiddleware->execute()) {
    header('HTTP/1.1 403 Forbidden');
    die($permissionMiddleware->getLastException()->getMessage());
}
```

## Sesiones

### SessionManager

Gestiona las sesiones de usuario.

```php
// Guardar información de usuario en sesión
saveUserSession(int $userId, array $roles = [], array $permissions = []): void

// Obtener ID del usuario autenticado
getUserId(): ?int

// Obtener roles del usuario
getUserRoles(): array

// Obtener permisos del usuario
getUserPermissions(): array

// Verificar si hay sesión válida
isSessionValid(): bool

// Verificar autenticación
isAuthenticated(): bool

// Renovar sesión (actualizar timestamp)
renewSession(): void

// Cerrar sesión
destroySession(): void

// Cierre completo de sesión
logout(): void

// Establecer timeout
setSessionTimeout(int $seconds): void
```

## Permisos Predefinidos

El sistema incluye los siguientes permisos predefinidos:

### Gestión de Usuarios
- `usuarios.ver` - Ver usuarios
- `usuarios.crear` - Crear usuarios
- `usuarios.editar` - Editar usuarios
- `usuarios.eliminar` - Eliminar usuarios
- `usuarios.cambiar-contraseña` - Cambiar contraseña

### Gestión de Roles
- `roles.ver` - Ver roles
- `roles.crear` - Crear roles
- `roles.editar` - Editar roles
- `roles.eliminar` - Eliminar roles

### Gestión de Préstamos
- `prestamos.ver` - Ver préstamos
- `prestamos.crear` - Crear préstamos
- `prestamos.editar` - Editar préstamos
- `prestamos.eliminar` - Eliminar préstamos
- `prestamos.aprobar` - Aprobar préstamos
- `prestamos.rechazar` - Rechazar préstamos

### Gestión de Finanzas
- `finanzas.ver` - Ver finanzas
- `finanzas.reportes` - Acceder a reportes
- `finanzas.exportar` - Exportar datos

### Gestión de Transparencia
- `transparencia.ver` - Ver transparencia
- `transparencia.crear` - Crear archivos
- `transparencia.editar` - Editar archivos

### Sistema
- `sistema.administracion` - Acceso de administración
- `sistema.logs` - Ver logs
- `sistema.configuracion` - Configurar sistema

## Roles Predefinidos

El sistema incluye los siguientes roles predefinidos:

### admin
- Descripción: Administrador del sistema con acceso completo
- Permisos: Todos

### gerente
- Descripción: Gerente con acceso a gestión de préstamos y reportes
- Permisos: 
  - `prestamos.ver`
  - `prestamos.crear`
  - `prestamos.editar`
  - `prestamos.aprobar`
  - `finanzas.ver`
  - `finanzas.reportes`
  - `usuarios.ver`

### empleado
- Descripción: Empleado con acceso a vista de préstamos
- Permisos:
  - `prestamos.ver`

### usuario
- Descripción: Usuario estándar del sistema
- Permisos: Ninguno

## Configuración Inicial

### 1. Ejecutar migraciones y seeding

```bash
php commands/setup-auth.php
```

Este comando:
1. Crea todas las tablas necesarias
2. Inserta los roles predefinidos
3. Crea usuarios de ejemplo:
   - admin@ejemplo.com (contraseña: password123)
   - gerente@ejemplo.com (contraseña: password123)
   - empleado@ejemplo.com (contraseña: password123)

### 2. Registración en el contenedor DI

Los servicios están registrados automáticamente en:
- `config/repositories.php` - Repositorios
- `config/services.php` - Servicios

## Ejemplo de Uso en Ruta Protegida

```php
<?php
// archivo.php

require_once __DIR__ . '/bootstrap.php';

use App\Bootstrap;
use App\Module\Auth\Middleware\MiddlewareFactory;

$container = Bootstrap::buildContainer();
$middlewareFactory = $container->get(\App\Module\Auth\Middleware\MiddlewareFactory::class);

// Proteger: requiere autenticación
$authMiddleware = $middlewareFactory->createAuthMiddleware();
if (!$authMiddleware->execute()) {
    header('HTTP/1.1 401 Unauthorized');
    die($authMiddleware->getLastException()->getMessage());
}

// Proteger: requiere rol admin o gerente
$roleMiddleware = $middlewareFactory->createRoleMiddleware(['admin', 'gerente']);
if (!$roleMiddleware->execute()) {
    header('HTTP/1.1 403 Forbidden');
    die($roleMiddleware->getLastException()->getMessage());
}

// Proteger: requiere permiso específico
$permissionMiddleware = $middlewareFactory->createPermissionMiddleware(['prestamos.crear']);
if (!$permissionMiddleware->execute()) {
    header('HTTP/1.1 403 Forbidden');
    die($permissionMiddleware->getLastException()->getMessage());
}

// Resto del código de la ruta...
?>
```

## Flujo de Autenticación Típico

```
1. Usuario ingresa email y contraseña en login.php
   ↓
2. Se llama a AuthenticationService::authenticate()
   ↓
3. Se verifica email y contraseña
   ↓
4. Se obtiene el usuario con sus roles y permisos
   ↓
5. Se guarda información en sesión (SessionManager)
   ↓
6. Se redirige al usuario a la página protegida
   ↓
7. En cada ruta protegida se valida:
   - Existencia de sesión válida
   - Roles requeridos (si aplica)
   - Permisos requeridos (si aplica)
   ↓
8. Si todo es válido, se ejecuta la ruta
   ↓
9. En caso de error, se retorna 401 (Unauthorized) o 403 (Forbidden)
```

## Seguridad

El sistema implementa las siguientes medidas de seguridad:

1. **Hash de contraseñas**: Utiliza bcrypt con costo 12
2. **Validación de email**: Verifica que el formato sea válido
3. **Timeout de sesión**: Las sesiones expiran después de 1 hora
4. **Auditoría**: Registra todos los intentos de login en `auth_logs`
5. **Control de acceso granular**: Permisos a nivel de acción individual
6. **Validación de rol/permiso**: Verificación en cada acceso

## Exception Handling

El sistema define las siguientes excepciones:

- `AuthenticationException` - Error en autenticación (401)
- `UnauthorizedException` - Acceso denegado (403)
- `UserNotFoundException` - Usuario no encontrado (404)
- `RoleNotFoundException` - Rol no encontrado (404)

## Extensión del Sistema

### Agregar un nuevo permiso

```php
// En RoleService::getPredefinedPermissions()
'nueva_seccion.nueva_accion'
```

### Crear un nuevo rol

```php
$roleService->createRole(
    'nuevo_rol',
    'Descripción del rol',
    ['permiso1', 'permiso2']
);
```

### Crear un nuevo usuario

```php
$userId = $authService->register(
    'email@ejemplo.com',
    'password',
    'Nombre',
    'Apellidos'
);

// Asignar rol
$userRepository->assignRole($userId, $roleId);
```

### Crear middleware personalizado

```php
class CustomMiddleware extends BaseMiddleware
{
    public function execute(): bool
    {
        // Lógica personalizada
        if (!$this->someCustomCheck()) {
            return $this->deny('Acceso denegado');
        }
        return true;
    }
}
```

## Testing

Para probar el sistema:

1. Ejecutar setup: `php commands/setup-auth.php`
2. Acceder a una ruta protegida
3. Intentar ingresar con diferentes usuarios:
   - admin@ejemplo.com (acceso total)
   - gerente@ejemplo.com (acceso limitado)
   - empleado@ejemplo.com (acceso muy limitado)

## Notas Importantes

1. El timeout de sesión está configurado a 1 hora (3600 segundos). Se puede cambiar en `SessionManager`.
2. Las contraseñas de ejemplo son `password123` según se especifica en `AuthSeeder`.
3. Se recomienda cambiar las contraseñas predefinidas en producción.
4. Los permisos se pueden personalizar según los requerimientos de la aplicación.
5. Se recomienda habilitar HTTPS en producción para proteger las sesiones.

## Referencias

- [PHP password_hash](https://www.php.net/manual/es/function.password-hash.php)
- [RBAC - Wikipedia](https://en.wikipedia.org/wiki/Role-based_access_control)
- [OWASP Authentication](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html)
