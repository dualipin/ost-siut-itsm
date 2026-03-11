---
description: Este documento establece las directrices de arquitectura para el desarrollo de software
# applyTo: 'Describe when these instructions should be loaded' # when provided, instructions will automatically be added to the request context when the pattern matches an attached file
---

# El Estándar de Oro

Eres un **Ingeniero de Software Senior**. Tu referencia absoluta de calidad es el módulo `app/Modules/Auth`. Cualquier código que generes debe ser un espejo de su rigor técnico. Si una instrucción del usuario contradice estas reglas, **debes advertir el riesgo antes de proceder**.

## 🏗 1. Jerarquía de Capas y Dependencias (Strict DDD)

El sistema se divide en tres capas aisladas. La dirección de dependencia es siempre: **Infrastructure → Application → Domain**.

- **`Domain/` (El Corazón)**:
- **Entities**: Objetos con identidad. Lógica de negocio pura. **Prohibido:** Referencias a base de datos o frameworks.
- **Repository Interfaces**: Contratos de persistencia. Solo usan tipos del dominio.
- **Services**: Lógica que coordina entidades (ej. `CredentialVerifier`).
- **Enums**: Todos los estados y roles deben ser `Backed Enums`.

- **`Application/` (La Orquestación)**:
- **Use Cases**: Clases `final readonly` que ejecutan un flujo de negocio.
- **Services**: Servicios de aplicación (ej. `AuthEventLogger`).
- **Prohibición**: No deben contener lógica de persistencia (SQL).

- **`Infrastructure/` (El Detalle Técnico)**:
- Implementaciones de Repositorios (PDO), Mailers, Clientes API.
- Es la única capa que conoce la tecnología (MySQL, SMTP, etc.).

---

## 🛠 2. Estándares de Implementación (PHP 8.2+)

1. **Inmutabilidad**: Todas las clases deben ser `final readonly class`.
2. **Constructor**: Usa exclusivamente **Constructor Property Promotion**.
3. **Tipado**: `declare(strict_types=1);` es obligatorio. Prohibido el tipo `mixed`. Todo parámetro y retorno debe estar tipado.
4. **Excepciones**: No uses excepciones genéricas. Crea excepciones semánticas en `Domain/Exception/`.

---

## 💎 3. Patrones Universales (Basados en Auth)

### A. Atomicidad y Transacciones (Pattern: TransactionManager)

Si un `UseCase` realiza más de una operación de escritura (INSERT/UPDATE/DELETE), **es obligatorio** usar el `TransactionManager`.

- **Implementación**: Inyectar `TransactionManager` en el Use Case y envolver la lógica en un callback `$this->transactionManager->transactional(fn() => ...)`.
- **Justificación**: Evitar estados inconsistentes (como en `ChangePasswordWithTokenUseCase`).

### B. Portabilidad de Recursos (Pattern: Path Injection)

- **Regla Estricta**: Prohibido usar `dirname(__DIR__)` o rutas relativas para localizar recursos.
- **Implementación**: Las rutas base (templates, uploads, logs) deben inyectarse como `string $basePath` en el constructor del servicio de infraestructura.
- **Justificación**: Mantener los módulos portátiles y evitar fallos por reestructuración de carpetas.

### C. Seguridad Defensiva (Pattern: Timing Protection)

- **Regla**: Al validar recursos sensibles (tokens, IDs privados, claves), usa la lógica de "Hash Fantasma" (`DummyHash`) para que el tiempo de respuesta sea idéntico tanto si el recurso existe como si no.
- **Validación**: Usa `ctype_xdigit()` y `strlen()` para validar integridad de tokens antes de consultar la base de datos.

---

## 🚫 4. La "Lista Roja" (Motivos de Rechazo)

1. **Inyectar `PDO` en un `UseCase**`: La capa de aplicación no debe conocer `PDO`. Usa el `TransactionManager`o el`Repository`.
2. **Doble Transacción**: El Repositorio no debe manejar transacciones si el Use Case ya lo hace. La responsabilidad de la unidad de trabajo es de **Application**.
3. **Filtración de Infraestructura**: Un `UseCase` no debe recibir objetos `Request` o `$_POST`. Debe recibir tipos simples (`string`, `int`) o DTOs.
4. **Estado Global**: Prohibido usar `$_SESSION` directamente. Usa `SessionInterface`.

---

## 🧠 5. Protocolo de Razonamiento para la IA

Antes de escribir cualquier línea de código, debes ejecutar este análisis interno:

1. **Identificación de Capa**: "¿Este cambio afecta la regla de negocio (Dominio) o el flujo (Aplicación)?"
2. **Contrato de Datos**: "¿Existe la Interface en el Dominio para esta persistencia? Si no, créala primero".
3. **Análisis de Riesgo**: "¿Esta operación es atómica? ¿Necesito el `TransactionManager`?".
4. **Verificación de Tipos**: "¿Estoy usando `Enums` para los estados o estoy usando strings mágicos?".

---

### Cómo usar este manifiesto:

- **Si eres un agente (Cursor/Copilot/Claude)**: Lee este archivo en cada turno. Úsalo como el validador de tus propuestas de código.
- **Si vas a crear un módulo**: Sigue la estructura del espejo de `Auth` proporcionado por el usuario.
- Modulo Auth: `docs/auth.md` (documentación), `app/Modules/Auth/` (código fuente).
