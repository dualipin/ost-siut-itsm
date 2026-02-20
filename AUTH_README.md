# 🔐 Sistema de Autenticación y RBAC

## 🚀 Instalación Rápida

```bash
php commands/setup-auth.php
```

Eso es todo. El sistema está listo.

## 📖 Documentación Completa

| Documento | Propósito |
|-----------|-----------|
| [PROXIMO_PASOS.md](PROXIMO_PASOS.md) | **LEER PRIMERO** - Pasos de instalación |
| [RESUMEN_FINAL.md](RESUMEN_FINAL.md) | Resumen ejecutivo |
| [VERIFICACION_FINAL.md](VERIFICACION_FINAL.md) | Checklist de verificación |
| [docs/INDICE.md](docs/INDICE.md) | Índice de toda la documentación |
| [docs/VISION_GENERAL.md](docs/VISION_GENERAL.md) | Arquitectura del sistema |
| [docs/AUTENTICACION_RBAC.md](docs/AUTENTICACION_RBAC.md) | Documentación técnica completa |
| [docs/REFERENCIA_RAPIDA_AUTH.md](docs/REFERENCIA_RAPIDA_AUTH.md) | API de referencia rápida |
| [docs/SETUP_RAPIDO_AUTH.md](docs/SETUP_RAPIDO_AUTH.md) | Guía de setup |
| [docs/AUTENTICACION_RBAC_EJEMPLOS.php](docs/AUTENTICACION_RBAC_EJEMPLOS.php) | Ejemplos de código |
| [docs/EJEMPLO_PAGINA_PROTEGIDA.php](docs/EJEMPLO_PAGINA_PROTEGIDA.php) | Página funcional de ejemplo |

## ✨ Características

- ✅ Autenticación con bcrypt
- ✅ RBAC (Roles y Permisos)
- ✅ Middleware de protección
- ✅ Gestión de sesiones
- ✅ Auditoría de logins
- ✅ 27 permisos predefinidos
- ✅ 4 roles predefinidos
- ✅ Totalmente integrado con DI Container

## 🎯 Uso Rápido

### Login
```php
$authService->authenticate('admin@ejemplo.com', 'password123');
$user = $authService->getCurrentUser();
```

### Verificar Permisos
```php
if ($authService->hasPermission('usuarios.crear')) {
    // Crear usuario
}
```

### Proteger Rutas
```php
$authMiddleware = $middlewareFactory->createAuthMiddleware();
if (!$authMiddleware->execute()) {
    header('Location: /cuentas/login.php');
    exit;
}
```

## 👥 Usuarios de Prueba

Después de `php commands/setup-auth.php`:

```
admin@ejemplo.com      / password123
gerente@ejemplo.com    / password123
empleado@ejemplo.com   / password123
```

## 📊 Estructura

```
app/Module/Auth/
├── Entity/          # Entidades (User, Role)
├── DTO/             # Data Transfer Objects
├── Repository/      # Acceso a Base de Datos
├── Service/         # Servicios de lógica
├── Middleware/      # Middleware de protección
├── Session/         # Gestión de sesiones
└── Exception/       # Excepciones personalizadas
```

## 🔑 Permisos Disponibles

```
usuarios.*              - Gestión de usuarios
roles.*                 - Gestión de roles
prestamos.*             - Gestión de préstamos
finanzas.*              - Gestión de finanzas
transparencia.*         - Gestión de transparencia
sistema.*               - Funciones del sistema
```

## 🛡️ Seguridad

- Hash bcrypt con costo 12
- Session timeout de 1 hora
- Validación en cada petición
- Auditoría de logins
- Control granular de acceso

## 📚 Próximos Pasos

1. [Lee esto primero](PROXIMO_PASOS.md)
2. Ejecuta: `php commands/setup-auth.php`
3. Crea archivo `/cuentas/login.php`
4. Protege tus rutas
5. Disfruta

## 🆘 Ayuda

- Error en instalación → [docs/SETUP_RAPIDO_AUTH.md](docs/SETUP_RAPIDO_AUTH.md)
- Cómo implementar → [PROXIMO_PASOS.md](PROXIMO_PASOS.md)
- Ejemplos de código → [docs/AUTENTICACION_RBAC_EJEMPLOS.php](docs/AUTENTICACION_RBAC_EJEMPLOS.php)
- Documentación técnica → [docs/AUTENTICACION_RBAC.md](docs/AUTENTICACION_RBAC.md)

---

**✅ Sistema listo para usar**
