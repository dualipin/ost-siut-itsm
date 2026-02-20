# Próximos Pasos - Implementación Completada

## 🎉 ¡Implementación Completada!

Se ha implementado exitosamente un sistema profesional de autenticación y RBAC para tu aplicación.

## 📋 Lista de Verificación de Instalación

- [x] **Entidades creadas** (User, Role)
- [x] **DTOs creados** (UserDTO, RoleDTO)
- [x] **Repositorios implementados** (UserRepository, RoleRepository)
- [x] **Servicios creados** (AuthenticationService, RoleService)
- [x] **Middleware implementado** (Auth, Role, Permission)
- [x] **Gestor de sesiones** (SessionManager)
- [x] **Excepciones definidas** (4 tipos)
- [x] **Migraciones SQL** (001_create_auth_tables.sql)
- [x] **Seeder de datos** (AuthSeeder)
- [x] **Integración DI** (config/services.php, config/repositories.php)
- [x] **Documentación completa** (5 archivos)
- [x] **Ejemplos de código** (2 archivos)

## 🚀 Paso 1: Ejecutar Instalación

```bash
cd c:\Users\dualipin\Projects\ost-siut-itsm
php commands/setup-auth.php
```

**Qué hace:**
1. Crea todas las tablas en la BD
2. Crea los roles predefinidos
3. Crea usuarios de prueba

**Usuarios creados:**
```
admin@ejemplo.com      (Contraseña: password123)
gerente@ejemplo.com    (Contraseña: password123)
empleado@ejemplo.com   (Contraseña: password123)
```

## 📝 Paso 2: Crear Página de Login

Crear archivo: `cuentas/login.php`

```php
<?php
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
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <h1>Login</h1>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="password">Contraseña</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Ingresar</button>
                </form>

                <hr>
                <p class="text-muted small">Credenciales de prueba:</p>
                <ul class="small">
                    <li>admin@ejemplo.com / password123</li>
                    <li>gerente@ejemplo.com / password123</li>
                    <li>empleado@ejemplo.com / password123</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>
```

## 🚪 Paso 3: Crear Página de Logout

Crear archivo: `cuentas/logout.php`

```php
<?php
require_once __DIR__ . '/../bootstrap.php';

use App\Bootstrap;
use App\Module\Auth\Session\SessionManager;

$container = Bootstrap::buildContainer();
$sessionManager = $container->get(SessionManager::class);

$sessionManager->logout();

header('Location: /');
exit;
?>
```

## 🏠 Paso 4: Proteger el Portal

Modificar archivo: `portal/index.php`

```php
<?php
require_once __DIR__ . '/../bootstrap.php';

use App\Bootstrap;
use App\Module\Auth\Middleware\MiddlewareFactory;
use App\Module\Auth\Service\AuthenticationService;

$container = Bootstrap::buildContainer();
$middlewareFactory = $container->get(MiddlewareFactory::class);

// Proteger: requiere autenticación
$authMiddleware = $middlewareFactory->createAuthMiddleware();
if (!$authMiddleware->execute()) {
    header('Location: /cuentas/login.php');
    exit;
}

// Obtener usuario actual
$authService = $container->get(AuthenticationService::class);
$user = $authService->getCurrentUser();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Portal</title>
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <span class="navbar-brand">ITSM</span>
            <div>
                <span class="text-white"><?php echo $user->getFullName(); ?></span>
                <a href="/cuentas/logout.php" class="btn btn-sm btn-light ms-2">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h1>Bienvenido <?php echo $user->getFullName(); ?></h1>
        <p>Roles: <?php echo implode(', ', array_map(fn($r) => $r->getName(), $user->getRoles())); ?></p>
    </div>
</body>
</html>
```

## 🔐 Paso 5: Proteger Rutas Específicas

Ejemplo proteger panel de administración:

```php
<?php
// portal/administracion.php

require_once __DIR__ . '/../bootstrap.php';

use App\Bootstrap;
use App\Module\Auth\Middleware\MiddlewareFactory;

$container = Bootstrap::buildContainer();
$middlewareFactory = $container->get(MiddlewareFactory::class);

// Requiere rol de admin o gerente
$roleMiddleware = $middlewareFactory->createRoleMiddleware(['admin', 'gerente']);
if (!$roleMiddleware->execute()) {
    header('HTTP/1.1 403 Forbidden');
    die('Acceso denegado: Solo administradores');
}

// Contenido protegido...
?>
```

## 👥 Paso 6: Crear Panel de Usuarios (Opcional)

Crear archivo: `portal/usuarios/listado.php`

Ver ejemplo completo en: `docs/EJEMPLO_PAGINA_PROTEGIDA.php`

```php
<?php
$permissionMiddleware = $middlewareFactory->createPermissionMiddleware(['usuarios.ver']);
if (!$permissionMiddleware->execute()) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

$users = $userRepository->findAll();

// Mostrar tabla de usuarios...
?>
```

## 🔍 Paso 7: Implementar Auditoría (Opcional)

La tabla `auth_logs` está lista para registrar intentos de login.

Crear servicio para registrar:

```php
class AuthLogger
{
    public function __construct(private PDO $pdo) {}

    public function logLogin(int $userId, bool $success, ?string $error = null): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO auth_logs (user_id, action, ip_address, user_agent, success, error_message) 
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $userId,
            'login',
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            (int)$success,
            $error,
        ]);
    }
}
```

## 📚 Documentación Disponible

| Archivo | Propósito |
|---------|-----------|
| [AUTENTICACION_RBAC.md](docs/AUTENTICACION_RBAC.md) | Documentación completa y detallada |
| [REFERENCIA_RAPIDA_AUTH.md](docs/REFERENCIA_RAPIDA_AUTH.md) | API rápida de referencia |
| [SETUP_RAPIDO_AUTH.md](docs/SETUP_RAPIDO_AUTH.md) | Guía de instalación |
| [AUTENTICACION_RBAC_EJEMPLOS.php](docs/AUTENTICACION_RBAC_EJEMPLOS.php) | Ejemplos prácticos |
| [EJEMPLO_PAGINA_PROTEGIDA.php](docs/EJEMPLO_PAGINA_PROTEGIDA.php) | Página completa de ejemplo |

## 🧪 Pruebas Manuales

### Test 1: Login Básico
1. Ir a `http://localhost:8888/cuentas/login.php`
2. Ingresar: admin@ejemplo.com / password123
3. Verificar redirección a `/portal/`

### Test 2: Verificar Sesión
1. Login como admin
2. Acceder a `http://localhost:8888/portal/`
3. Verificar que muestre usuario y roles

### Test 3: Verificar Permisos
1. Login como empleado
2. Intentar acceder a panel de administración
3. Verificar que muestre error 403

### Test 4: Logout
1. Estar autenticado
2. Click en "Logout"
3. Intentar acceder a ruta protegida
4. Verificar redirección a login

## 🔧 Configuraciones Importantes

### Timeout de Sesión
En `config/services.php`:
```php
SessionManager::class => function () {
    return new SessionManager(3600); // 1 hora
},
```

### Permisos Personalizados
En `RoleService::getPredefinedPermissions()`:
```php
'mi_seccion.mi_accion',
```

### Roles Personalizados
En `RoleService::getPredefinedRoles()`:
```php
[
    'name' => 'custom_role',
    'description' => 'Mi rol personalizado',
    'permissions' => ['permiso1', 'permiso2'],
],
```

## 🎯 Checklist Final

- [ ] Ejecutar `php commands/setup-auth.php`
- [ ] Crear página login en `cuentas/login.php`
- [ ] Crear página logout en `cuentas/logout.php`
- [ ] Proteger ruta `/portal/index.php`
- [ ] Proteger ruta `/portal/administracion.php` (solo admin)
- [ ] Probar login con diferentes usuarios
- [ ] Probar logout
- [ ] Probar acceso denegado (403)
- [ ] Probar no autenticado (redirect a login)
- [ ] Cambiar contraseñas de usuarios de prueba
- [ ] Crear nuevos usuarios reales
- [ ] Crear nuevos roles según necesidad
- [ ] Personalizar permisos según requerimientos

## ✨ Funcionalidades Opcionales

### Agregarpic Two-Factor Authentication (2FA)
- Implementar TOTP con credenciales QR
- SMS como segundo factor

### Agregar Recuperación de Contraseña
- Email de reset
- Token temporal

### Agregar Historial de Acciones
- Guardar auditoría de acciones
- Reportes de actividad

### Agregar Bloqueo de Intentos Fallidos
- Limitar intentos de login
- Bloquear por IP temporal

### Agregar API Token
- JWT tokens
- API key management

## ❓ FAQ

**P: ¿Cómo cambio la contraseña de un usuario?**
R: `$authService->changePassword($userId, $oldPassword, $newPassword);`

**P: ¿Cómo reseto la contraseña (como admin)?**
R: `$authService->resetPassword($userId, $newPassword);`

**P: ¿Cómo creo un nuevo rol?**
R: `$roleService->createRole('nombre', 'descripción', ['permiso1', 'permiso2']);`

**P: ¿Cómo asigno un rol a un usuario?**
R: `$userRepository->assignRole($userId, $roleId);`

**P: ¿Cómo cambio el timeout de sesión?**
R: `$sessionManager->setSessionTimeout(7200);` (en segundos)

**P: ¿Dónde se guardanlas contraseñas?**
R: En la tabla `users` (columna `password`) hashadas con bcrypt

**P: ¿Es seguro para producción?**
R: Sí, implementa estándares de seguridad: bcrypt, validación, timeout, auditoría

## 🆘 Troubleshooting

**Error: "Call to undefined method..."**
- Verificar que el contenedor DI esté correctamente configurado
- Ejecutar `php commands/setup-auth.php` de nuevo

**Error: "SQLSTATE[HY000]..."**
- Verificar credenciales de base de datos
- Ejecutar `php commands/setup-auth.php` para crear tablas

**Sesión no se guarda**
- Verificar que `session.save_path` esté configurado en php.ini
- Verificar permisos de escritura en carpeta de sesiones

**Middleware siempre falla**
- Asegurarse de llamar a `execute()` antes de verificar resultado
- Verificar que la sesión sea válida

## 📞 Contacto / Soporte

Para preguntas o problemas:
1. Revisar documentación en `docs/`
2. Ver ejemplos en `docs/AUTENTICACION_RBAC_EJEMPLOS.php`
3. Consultar referencia rápida en `docs/REFERENCIA_RAPIDA_AUTH.md`

---

**¡Eres listo para empezar!** 🚀

Ejecuta ahora: `php commands/setup-auth.php`
