<?php

/**
 * EJEMPLO DE PÁGINA PROTEGIDA COMPLETA
 * 
 * Este archivo muestra cómo implementar una página protegida
 * que requiere autenticación, rol específico y permisos.
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Bootstrap;
use App\Module\Auth\Middleware\MiddlewareFactory;
use App\Module\Auth\Service\AuthenticationService;
use App\Module\Auth\Session\SessionManager;
use App\Module\Auth\Repository\UserRepositoryInterface;

// ============================================================================
// INICIALIZACIÓN
// ============================================================================

$container = Bootstrap::buildContainer();
$middlewareFactory = $container->get(MiddlewareFactory::class);
$authService = $container->get(AuthenticationService::class);
$sessionManager = $container->get(SessionManager::class);
$userRepository = $container->get(UserRepositoryInterface::class);

// ============================================================================
// PROTECCIÓN DE LA RUTA
// ============================================================================

// 1. Requiere autenticación (si no está autenticado, error 401)
$authMiddleware = $middlewareFactory->createAuthMiddleware();
if (!$authMiddleware->execute()) {
    http_response_code(401);
    header('Location: /cuentas/login.php');
    exit;
}

// 2. Requiere rol de admin o gerente (si no tiene el rol, error 403)
$roleMiddleware = $middlewareFactory->createRoleMiddleware(['admin', 'gerente']);
if (!$roleMiddleware->execute()) {
    http_response_code(403);
    die($roleMiddleware->getLastException()->getMessage());
}

// 3. Requiere permiso específico (si no tiene permisos, error 403)
$permissionMiddleware = $middlewareFactory->createPermissionMiddleware([
    'usuarios.ver'
]);
if (!$permissionMiddleware->execute()) {
    http_response_code(403);
    die($permissionMiddleware->getLastException()->getMessage());
}

// ============================================================================
// RENOVAR SESIÓN
// ============================================================================

$sessionManager->renewSession();

// ============================================================================
// OBTENER DATOS DEL USUARIO AUTENTICADO
// ============================================================================

$user = $authService->getCurrentUser();
$userFullName = $user->getFullName();
$userEmail = $user->getEmail();
$userRoles = array_map(fn($r) => $r->getName(), $user->getRoles());
$userPermissions = $authService->getCurrentUserPermissions();

// ============================================================================
// LÓGICA DE NEGOCIO DE LA PÁGINA
// ============================================================================

$users = [];
$message = null;
$error = null;

// Obtener lista de usuarios (solo si tiene permiso)
if ($authService->hasPermission('usuarios.ver')) {
    $users = $userRepository->findAll();
}

// Procesar formulario de creación de usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Verificar permisos para crear usuario
    if ($_POST['action'] === 'create' && $authService->hasPermission('usuarios.crear')) {
        try {
            $email = $_POST['email'] ?? '';
            $nombre = $_POST['nombre'] ?? '';
            $apellidos = $_POST['apellidos'] ?? '';

            if (empty($email) || empty($nombre) || empty($apellidos)) {
                $error = 'Todos los campos son requeridos';
            } elseif ($authService->emailExists($email)) {
                $error = 'El email ya está registrado';
            } else {
                $newUserId = $authService->register(
                    $email,
                    'password123', // Contraseña temporal
                    $nombre,
                    $apellidos
                );
                $message = 'Usuario creado exitosamente con ID: ' . $newUserId;
                $users = $userRepository->findAll();
            }
        } catch (\Exception $e) {
            $error = 'Error al crear usuario: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'create' && !$authService->hasPermission('usuarios.crear')) {
        $error = 'No tiene permisos para crear usuarios';
    }

    // Procesar otras acciones...
}

// ============================================================================
// PREPARAR DATOS PARA TEMPLATE
// ============================================================================

$templateData = [
    'user' => [
        'fullName' => $userFullName,
        'email' => $userEmail,
        'roles' => $userRoles,
        'permissions' => $userPermissions,
    ],
    'users' => $users,
    'message' => $message,
    'error' => $error,
    'canCreateUsers' => $authService->hasPermission('usuarios.crear'),
    'canEditUsers' => $authService->hasPermission('usuarios.editar'),
    'canDeleteUsers' => $authService->hasPermission('usuarios.eliminar'),
    'isAdmin' => $authService->hasRole('admin'),
    'isGerente' => $authService->hasRole('gerente'),
];

// ============================================================================
// RENDERIZAR TEMPLATE (EJEMPLO CON LATTE)
// ============================================================================

// $renderer = $container->get(\App\Infrastructure\Templating\RendererInterface::class);
// $renderer->render('portal/usuarios/listado.latte', $templateData);

// ============================================================================
// O RENDERIZAR CON HTML DIRECTO (PARA ESTE EJEMPLO)
// ============================================================================

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administración de Usuarios</title>
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="/">ITSM</a>
            <div class="d-flex align-items-center gap-3">
                <span class="text-white">
                    <?php echo htmlspecialchars($userFullName); ?>
                </span>
                <a href="/cuentas/logout.php" class="btn btn-sm btn-outline-light">
                    Cerrar Sesión
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <!-- Mensajes -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Información del usuario -->
        <div class="row mb-4">
            <div class="col-md-6">
                <h2>Bienvenido, <?php echo htmlspecialchars($userFullName); ?></h2>
                <p class="text-muted">
                    Roles: <?php echo implode(', ', $userRoles); ?>
                </p>
            </div>
        </div>

        <!-- Formulario de creación (si tiene permisos) -->
        <?php if ($templateData['canCreateUsers']): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Crear Nuevo Usuario</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="create">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" id="email" name="email" 
                                       class="form-control" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="nombre" class="form-label">Nombre</label>
                                <input type="text" id="nombre" name="nombre" 
                                       class="form-control" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="apellidos" class="form-label">Apellidos</label>
                                <input type="text" id="apellidos" name="apellidos" 
                                       class="form-control" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            Crear Usuario
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Tabla de usuarios -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Lista de Usuarios</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th>Nombre</th>
                            <th>Apellidos</th>
                            <th>Activo</th>
                            <th>Último Login</th>
                            <?php if ($templateData['canEditUsers'] || $templateData['canDeleteUsers']): ?>
                                <th>Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($u->getEmail()); ?></td>
                                <td><?php echo htmlspecialchars($u->getNombre()); ?></td>
                                <td><?php echo htmlspecialchars($u->getApellidos()); ?></td>
                                <td>
                                    <span class="badge <?php 
                                        echo $u->isActivo() ? 'bg-success' : 'bg-danger'; 
                                    ?>">
                                        <?php echo $u->isActivo() ? 'Sí' : 'No'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $lastLogin = $u->getLastLogin();
                                    echo $lastLogin ? $lastLogin->format('d/m/Y H:i') : 'Nunca';
                                    ?>
                                </td>
                                <?php if ($templateData['canEditUsers'] || $templateData['canDeleteUsers']): ?>
                                    <td>
                                        <?php if ($templateData['canEditUsers']): ?>
                                            <a href="/portal/usuarios/editar.php?id=<?php 
                                                echo $u->getId(); 
                                            ?>" class="btn btn-sm btn-primary">
                                                Editar
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($templateData['canDeleteUsers']): ?>
                                            <button class="btn btn-sm btn-danger" 
                                                    onclick="confirm('¿Eliminar?') && fetch('/api/usuarios/<?php 
                                                        echo $u->getId(); 
                                                    ?>', {method: 'DELETE'})">
                                                Eliminar
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Footer con info de debugging (solo para admin) -->
        <?php if ($templateData['isAdmin']): ?>
            <hr class="my-5">
            <div class="card bg-light">
                <div class="card-header">
                    <h6 class="mb-0">Debug Info (Solo Admin)</h6>
                </div>
                <div class="card-body">
                    <p><strong>Permisos:</strong></p>
                    <ul class="small">
                        <?php foreach ($userPermissions as $perm): ?>
                            <li><?php echo htmlspecialchars($perm); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="/assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
