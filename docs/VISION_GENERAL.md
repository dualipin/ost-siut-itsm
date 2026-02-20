# 📖 Visión General del Sistema de Autenticación y RBAC

## 🎯 Objetivo

Implementar un sistema profesional, robusto y flexible de autenticación y control de acceso basado en roles (RBAC) que permita:

1. ✅ **Autenticación** - Verificación de identidad de usuarios
2. ✅ **Autorización** - Control de qué pueden hacer los usuarios
3. ✅ **Auditoría** - Registro de accesos y cambios
4. ✅ **Flexibilidad** - Fácil customización de roles y permisos

## 📊 Diagrama de Arquitectura

```
┌─────────────────────────────────────────────────────────────┐
│                     USUARIO                                  │
└──────────────────────────┬──────────────────────────────────┘
                          │
                          ▼
            ┌─────────────────────────┐
            │   SESSION MANAGER       │
            │ (Gestión de sesiones)   │
            └──────────┬──────────────┘
                      │
         ┌────────────┼────────────┐
         ▼            ▼            ▼
    MIDDLEWARE    MIDDLEWARE    MIDDLEWARE
    (Auth)        (Role)        (Permission)
         │            │            │
         └────────────┼────────────┘
                      │
                      ▼
        ┌──────────────────────────┐
        │ AUTHENTICATION SERVICE   │
        │ - authenticate()         │
        │ - hasRole()              │
        │ - hasPermission()        │
        └──────────┬───────────────┘
                   │
         ┌─────────┼─────────┐
         │         │         │
         ▼         ▼         ▼
    USER REPO  ROLE REPO  ROLE SERVICE
    (BD)       (BD)       (Gestión)
         │         │         │
         └─────────┼─────────┘
                   │
                   ▼
        ┌──────────────────────┐
        │    BASE DE DATOS     │
        │ ┌──────────────────┐ │
        │ │ users            │ │
        │ │ roles            │ │
        │ │ user_roles       │ │
        │ │ role_permissions │ │
        │ │ auth_logs        │ │
        │ └──────────────────┘ │
        └──────────────────────┘
```

## 🔄 Flujo de Autenticación

```
1. USUARIO INGRESA CREDENCIALES
   ↓
2. POST a login.php
   ↓
3. AuthenticationService::authenticate()
   ├─ Buscar usuario por email
   ├─ Verificar contraseña (password_verify)
   ├─ Verificar si está activo
   └─ Actualizar last_login
   ↓
4. SessionManager::saveUserSession()
   ├─ Guardar user_id en sesión
   ├─ Guardar roles en sesión
   └─ Guardar permisos en sesión
   ↓
5. REDIRECCIONAR A PORTAL
```

## 🔐 Flujo de Autorización

```
1. USUARIO ACCEDE A RUTA PROTEGIDA
   ↓
2. MIDDLEWARE VERIFICA AUTENTICACIÓN
   ├─ ¿Existe usuario en sesión?
   └─ ¿Sesión no está expirada?
   ↓
3. MIDDLEWARE VERIFICA ROL (si aplica)
   ├─ ¿Usuario tiene rol requerido?
   └─ ¿Tiene al menos uno de los roles?
   ↓
4. MIDDLEWARE VERIFICA PERMISO (si aplica)
   ├─ ¿Usuario tiene permiso requerido?
   └─ ¿Tiene todos los permisos (AND)? o ¿alguno (OR)?
   ↓
5. TODO OK?
   ├─ SÍ → EJECUTAR CÓDIGO DE LA RUTA
   └─ NO → RETORNAR 401/403
```

## 🏗️ Arquitectura de Capas

### Capa de Presentación (Templates/Views)
```
└─ Templates (Latte)
   ├─ Verificar con @if {$authService->hasPermission()}
   └─ Mostrar/ocultar elementos
```

### Capa de Middleware (Protección)
```
├─ AuthMiddleware - Requiere autenticación
├─ RoleMiddleware - Requiere rol
└─ PermissionMiddleware - Requiere permiso
```

### Capa de Servicios (Lógica)
```
├─ AuthenticationService - Login/logout
├─ RoleService - Gestión de roles
└─ SessionManager - Gestión de sesiones
```

### Capa de Repositorios (Datos)
```
├─ UserRepository - Operaciones CRUD usuarios
└─ RoleRepository - Operaciones CRUD roles
```

### Capa de Persistencia (Base de Datos)
```
├─ users
├─ roles
├─ user_roles
├─ role_permissions
└─ auth_logs
```

## 📝 Componentes Principales

### 1. **User (Entidad)**
```
└─ Propiedades
   ├─ id
   ├─ email
   ├─ password (hasheada)
   ├─ nombre
   ├─ apellidos
   ├─ roles[] (colección)
   ├─ activo
   ├─ created_at
   ├─ updated_at
   └─ last_login

└─ Métodos
   ├─ hasRole(string)
   ├─ hasPermission(string)
   ├─ hasAnyRole(array)
   ├─ hasAllRoles(array)
   └─ updateLastLogin()
```

### 2. **Role (Entidad)**
```
└─ Propiedades
   ├─ id
   ├─ name
   ├─ description
   ├─ permissions[] (colección)
   ├─ created_at
   └─ updated_at

└─ Métodos
   ├─ hasPermission(string)
   ├─ addPermission(string)
   ├─ removePermission(string)
   └─ setPermissions(array)
```

### 3. **AuthenticationService**
```
└─ Funciones principales
   ├─ authenticate(email, password) → bool
   ├─ getCurrentUser() → User|null
   ├─ isAuthenticated() → bool
   ├─ hasRole(role) → bool
   ├─ hasPermission(permission) → bool
   ├─ logout() → void
   ├─ register(...) → int
   ├─ changePassword(...) → bool
   └─ resetPassword(...) → bool
```

### 4. **SessionManager**
```
└─ Funciones principales
   ├─ saveUserSession(userId, roles, permissions)
   ├─ getUserId() → int|null
   ├─ isAuthenticated() → bool
   ├─ isSessionValid() → bool
   ├─ renewSession() → void
   ├─ destroySession() → void
   └─ logout() → void
```

### 5. **Middleware**
```
├─ AuthMiddleware
│  └─ Verifica: usuario autenticado
│
├─ RoleMiddleware
│  └─ Verifica: usuario tiene rol(es)
│
└─ PermissionMiddleware
   └─ Verifica: usuario tiene permiso(s)
```

## 🗂️ Estructura de Carpetas

```
app/Module/Auth/
├── Entity/
│   ├── User.php
│   └── Role.php
├── DTO/
│   ├── UserDTO.php
│   └── RoleDTO.php
├── Repository/
│   ├── UserRepositoryInterface.php
│   ├── UserRepository.php
│   ├── RoleRepositoryInterface.php
│   └── RoleRepository.php
├── Service/
│   ├── AuthenticationService.php
│   └── RoleService.php
├── Middleware/
│   ├── BaseMiddleware.php
│   ├── AuthMiddleware.php
│   ├── RoleMiddleware.php
│   ├── PermissionMiddleware.php
│   └── MiddlewareFactory.php
├── Session/
│   └── SessionManager.php
└── Exception/
    ├── AuthenticationException.php
    ├── UnauthorizedException.php
    ├── UserNotFoundException.php
    └── RoleNotFoundException.php

app/Infrastructure/
└── Database/
    ├── MigrationRunner.php
    └── AuthSeeder.php

migrations/
└── 001_create_auth_tables.sql

commands/
└── setup-auth.php

docs/
├── AUTENTICACION_RBAC.md
├── REFERENCIA_RAPIDA_AUTH.md
├── SETUP_RAPIDO_AUTH.md
├── AUTENTICACION_RBAC_EJEMPLOS.php
└── EJEMPLO_PAGINA_PROTEGIDA.php
```

## 🔐 Medidas de Seguridad

1. **Bcrypt Hashing** (costo 12)
   - Contraseñas son hasheadas de forma irreversible
   - Resistente a fuerza bruta

2. **Session Management**
   - Timeout configurable (default: 1 hora)
   - Validación de sesión en cada petición
   - Renovación de timestamp

3. **Validación**
   - Email se valida contra base de datos
   - Formato de email validado
   - Estados de usuario verificados

4. **Control de Acceso Granular**
   - Permisos a nivel de acción
   - Verificación en múltiples niveles
   - Bloqueo por defecto

5. **Auditoría**
   - Registro de intentos de login
   - IP y User-Agent capturados
   - Marcado de éxito/error

## 📊 Matriz de Permisos

```
USUARIOS
├─ usuarios.ver
├─ usuarios.crear
├─ usuarios.editar
├─ usuarios.eliminar
└─ usuarios.cambiar-contraseña

ROLES
├─ roles.ver
├─ roles.crear
├─ roles.editar
└─ roles.eliminar

PRESTAMOS
├─ prestamos.ver
├─ prestamos.crear
├─ prestamos.editar
├─ prestamos.eliminar
├─ prestamos.aprobar
└─ prestamos.rechazar

FINANZAS
├─ finanzas.ver
├─ finanzas.reportes
└─ finanzas.exportar

TRANSPARENCIA
├─ transparencia.ver
├─ transparencia.crear
└─ transparencia.editar

SISTEMA
├─ sistema.administracion
├─ sistema.logs
└─ sistema.configuracion
```

## 👥 Matriz de Roles

| Rol | Usuarios | Roles | Préstamos | Finanzas | Transparencia | Sistema |
|-----|----------|-------|-----------|----------|---------------|---------|
| admin | TODO | TODO | TODO | TODO | TODO | TODO |
| gerente | - | - | VER,CREAR,EDITAR,APROBAR | VER,REPORTES | - | - |
| empleado | - | - | VER | - | - | - |
| usuario | - | - | - | - | - | - |

## 🔄 Ciclo de Vida de una Sesión

```
LOGIN
  ↓
SessionManager::saveUserSession()
  │
  ├─ $_SESSION['auth_user_id'] = userId
  ├─ $_SESSION['auth_user_roles'] = [roles]
  ├─ $_SESSION['auth_user_permissions'] = [permisos]
  └─ $_SESSION['auth_session_created'] = timestamp
  ↓
USUARIO NAVEGA
  ↓
SessionManager::renewSession()
  │
  └─ $_SESSION['auth_session_created'] = nuevo_timestamp
  ↓
VERIFICA TIMEOUT (3600 segundos)
  │
  ├─ SI VÁLIDA → continuar
  └─ SI EXPIRADA → SessionManager::destroySession()
  ↓
LOGOUT ó SESIÓN EXPIRADA
  ↓
SessionManager::logout()
  │
  ├─ unset($_SESSION['auth_user_id'])
  ├─ unset($_SESSION['auth_user_roles'])
  ├─ unset($_SESSION['auth_user_permissions'])
  └─ session_destroy()
  ↓
FIN
```

## 🧪 Escenarios de Prueba

### Test 1: Login Exitoso
```
1. Usuario: admin@ejemplo.com
2. Contraseña: password123
3. Esperado: Redirección a /portal/
```

### Test 2: Login Fallido - Email Incorrecto
```
1. Usuario: inexistente@ejemplo.com
2. Contraseña: password123
3. Esperado: Mensaje de error
```

### Test 3: Login Fallido - Contraseña Incorrecta
```
1. Usuario: admin@ejemplo.com
2. Contraseña: incorrecta
3. Esperado: Mensaje de error
```

### Test 4: Usuario No Autenticado
```
1. Intento acceso a /portal/
2. Sin sesión válida
3. Esperado: Redirección a /cuentas/login.php
```

### Test 5: Rol Insuficiente
```
1. Login como empleado
2. Acceso a /portal/administracion (requiere admin/gerente)
3. Esperado: Error 403
```

### Test 6: Permiso Insuficiente
```
1. Login como empleado
2. Acceso a crear usuarios (requiere usuarios.crear)
3. Esperado: Error 403
```

### Test 7: Sesión Expirada
```
1. Login 
2. Esperar > 1 hora
3. Acceso a ruta protegida
4. Esperado: Redirección a login
```

## 📈 Escalabilidad

El sistema está diseñado para:

- ✅ Múltiples usuarios simultáneos
- ✅ Cientos de roles y permisos
- ✅ Miles de usuarios
- ✅ Altos volúmenes de auditoría

Limitaciones:
- Control de acceso a nivel global (no por fila)
- Sesiones en memoria (no distribuidas)

## 🔧 Extensibilidad

### Agregar Nuevo Permiso
```php
// En RoleService
'nueva_seccion.nueva_accion'
```

### Agregar Nuevo Rol
```php
$roleService->createRole('mi_rol', 'Descripción', ['perm1', 'perm2']);
```

### Crear Middleware Personalizado
```php
class CustomMiddleware extends BaseMiddleware {
    public function execute(): bool { ... }
}
```

### Agregar Lógica Personalizada
```php
class CustomAuthService {
    public function __construct(
        private AuthenticationService $authService
    ) {}
}
```

## 🎓 Conceptos Clave

### RBAC (Role-Based Access Control)
- Los usuarios tienen **roles**
- Los roles tienen **permisos**
- Los permisos controlan **acciones**

### Autenticación vs Autorización
- **Autenticación**: ¿Eres quién dices ser?
- **Autorización**: ¿Puedes hacer esto?

### Sesión
- Almacena estado del usuario
- Persiste durante la sesión
- Se valida en cada petición

### Permiso
- Nivel más granular de control
- Permite acciones específicas
- Agrupado en roles

## 📚 Referencias

- [OWASP AuthenAuth](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html)
- [OWASP Authorization](https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html)
- [PHP password_hash](https://www.php.net/manual/es/function.password-hash.php)
- [Session Security](https://www.php.net/manual/es/session.security.php)

---

**Sistema implementado y listo para usar** ✅
