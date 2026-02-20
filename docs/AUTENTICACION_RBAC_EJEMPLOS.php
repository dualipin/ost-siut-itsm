<?php

/**
 * EJEMPLOS DE USO DEL SISTEMA DE AUTENTICACIÓN Y RBAC
 * 
 * Este archivo muestra cómo utilizar el sistema de autenticación
 * y RBAC implementado.
 */

// ============================================================================
// 1. AUTENTICACIÓN BÁSICA
// ============================================================================

use App\Bootstrap;
use App\Module\Auth\Service\AuthenticationService;
use App\Module\Auth\Session\SessionManager;

$container = Bootstrap::buildContainer();
$authService = $container->get(AuthenticationService::class);
$sessionManager = $container->get(SessionManager::class);

// Intentar autenticarse
$email = 'admin@ejemplo.com';
$password = 'password123';

if ($authService->authenticate($email, $password)) {
    // Guardar en sesión
    $user = $authService->getCurrentUser();
    $sessionManager->saveUserSession(
        $user->getId(),
        array_map(fn($r) => $r->getName(), $user->getRoles()),
        $authService->getCurrentUserPermissions()
    );
    
    echo "✓ Autenticado como: " . $user->getFullName() . "\n";
} else {
    echo "✗ Falló la autenticación\n";
}


// ============================================================================
// 2. VERIFICAR ROLES
// ============================================================================

if ($authService->hasRole('admin')) {
    echo "✓ El usuario es administrador\n";
}

if ($authService->hasAnyRole(['admin', 'gerente'])) {
    echo "✓ El usuario tiene rol de admin o gerente\n";
}


// ============================================================================
// 3. VERIFICAR PERMISOS
// ============================================================================

if ($authService->hasPermission('usuarios.crear')) {
    echo "✓ El usuario puede crear usuarios\n";
}

if ($authService->hasPermission('prestamos.ver')) {
    echo "✓ El usuario puede ver préstamos\n";
}

// Obtener todos los permisos del usuario
$permissions = $authService->getCurrentUserPermissions();
echo "Permisos del usuario: " . implode(', ', $permissions) . "\n";


// ============================================================================
// 4. USO DE MIDDLEWARE EN RUTAS
// ============================================================================

use App\Module\Auth\Middleware\MiddlewareFactory;

$middlewareFactory = $container->get(MiddlewareFactory::class);

// Middleware de autenticación simple
$authMiddleware = $middlewareFactory->createAuthMiddleware();
if (!$authMiddleware->execute()) {
    $exception = $authMiddleware->getLastException();
    header('HTTP/1.1 401 Unauthorized');
    die($exception->getMessage());
}

// Middleware de roles
$roleMiddleware = $middlewareFactory->createRoleMiddleware(['admin', 'gerente']);
if (!$roleMiddleware->execute()) {
    $exception = $roleMiddleware->getLastException();
    header('HTTP/1.1 403 Forbidden');
    die($exception->getMessage());
}

// Middleware de permisos (requiere ANY)
$permissionMiddleware = $middlewareFactory->createPermissionMiddleware([
    'prestamos.ver',
    'prestamos.crear'
]);
if (!$permissionMiddleware->execute()) {
    $exception = $permissionMiddleware->getLastException();
    header('HTTP/1.1 403 Forbidden');
    die($exception->getMessage());
}

// Middleware de permisos (requiere ALL)
$strictPermissionMiddleware = $middlewareFactory->createPermissionMiddleware(
    ['prestamos.crear', 'prestamos.editar'],
    requireAll: true
);
if (!$strictPermissionMiddleware->execute()) {
    $exception = $strictPermissionMiddleware->getLastException();
    header('HTTP/1.1 403 Forbidden');
    die($exception->getMessage());
}


// ============================================================================
// 5. GESTIÓN DE USUARIOS
// ============================================================================

use App\Module\Auth\Repository\UserRepositoryInterface;

$userRepository = $container->get(UserRepositoryInterface::class);

// Obtener usuario por ID
$user = $userRepository->findById(1);

// Obtener usuario por email
$user = $userRepository->findByEmail('admin@ejemplo.com');

// Obtener todos los usuarios
$allUsers = $userRepository->findAll();

// Asignar un rol a un usuario
$userRepository->assignRole(1, 1); // Usuario 1 asignado al rol 1

// Remover un rol de un usuario
$userRepository->removeRole(1, 1);


// ============================================================================
// 6. GESTIÓN DE ROLES Y PERMISOS
// ============================================================================

use App\Module\Auth\Service\RoleService;

$roleService = $container->get(RoleService::class);

// Obtener todos los roles
$roles = $roleService->getAllRoles();

// Obtener un rol por nombre
$adminRole = $roleService->getRoleByName('admin');

// Crear un nuevo rol
$newRoleId = $roleService->createRole(
    name: 'supervisor',
    description: 'Supervisor de préstamos',
    permissions: ['prestamos.ver', 'prestamos.editar']
);

// Actualizar un rol
$roleService->updateRole(
    id: $newRoleId,
    name: 'supervisor',
    description: 'Supervisor con acceso completo',
    permissions: ['prestamos.ver', 'prestamos.editar', 'prestamos.aprobar']
);

// Asignar permiso a un rol
$roleService->assignPermissionToRole($newRoleId, 'finanzas.ver');

// Remover permiso de un rol
$roleService->removePermissionFromRole($newRoleId, 'finanzas.ver');

// Obtener todos los permisos disponibles
$availablePermissions = $roleService->getAllPermissions();

// Obtener permisos predefinidos del sistema
$predefinedPermissions = RoleService::getPredefinedPermissions();

// Obtener roles predefinidos
$predefinedRoles = RoleService::getPredefinedRoles();


// ============================================================================
// 7. SESIONES
// ============================================================================

// Verificar si hay una sesión activa
if ($sessionManager->isAuthenticated()) {
    $userId = $sessionManager->getUserId();
    $roles = $sessionManager->getUserRoles();
    $permissions = $sessionManager->getUserPermissions();
}

// Renovar la sesión (actualizar timestamp)
$sessionManager->renewSession();

// Cerrar sesión
$sessionManager->logout();


// ============================================================================
// 8. EJEMPLO DE RUTA PROTEGIDA (en un archivo PHP)
// ============================================================================

/*
<?php
// portal/usuarios/administrar.php

require_once __DIR__ . '/../../bootstrap.php';

use App\Bootstrap;
use App\Module\Auth\Middleware\MiddlewareFactory;

$container = Bootstrap::buildContainer();
$middlewareFactory = $container->get(MiddlewareFactory::class);

// Proteger la ruta: requiere autenticación Y rol de admin o gerente
$authMiddleware = $middlewareFactory->createAuthMiddleware();
$roleMiddleware = $middlewareFactory->createRoleMiddleware(['admin', 'gerente']);

if (!$authMiddleware->execute()) {
    header('HTTP/1.1 401 Unauthorized');
    die($authMiddleware->getLastException()->getMessage());
}

if (!$roleMiddleware->execute()) {
    header('HTTP/1.1 403 Forbidden');
    die($roleMiddleware->getLastException()->getMessage());
}

// Código de la página...
echo "Bienvenido al panel de administración!";
*/


// ============================================================================
// 9. EJEMPLO DE RUTA CON PERMISOS ESPECÍFICOS
// ============================================================================

/*
<?php
// portal/prestamos/crear.php

require_once __DIR__ . '/../../bootstrap.php';

use App\Bootstrap;
use App\Module\Auth\Middleware\MiddlewareFactory;

$container = Bootstrap::buildContainer();
$middlewareFactory = $container->get(MiddlewareFactory::class);

// Proteger la ruta: requiere el permiso de crear préstamos
$permissionMiddleware = $middlewareFactory->createPermissionMiddleware([
    'prestamos.crear'
]);

if (!$permissionMiddleware->execute()) {
    header('HTTP/1.1 403 Forbidden');
    die($permissionMiddleware->getLastException()->getMessage());
}

// Código para crear préstamo...
*/
