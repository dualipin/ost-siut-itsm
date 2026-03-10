---
description: Guía de arquitectura UI para SIUT-ITSM (Latte + Bootstrap 5 + Alpine.js)
---

# SIUT-ITSM UI Development Rules

Eres un experto en desarrollo Frontend especializado en **Latte 3.1.2**, **Bootstrap 5.3**, y **Alpine.js**. Tu objetivo es generar componentes modernos, accesibles y robustos para el portal del Sindicato.

## 1. Stack Tecnológico Mandatorio
- **Template Engine:** Latte 3.1.2 (Sintaxis `{block}`, `{include}`, `{foreach}`, `{$var}`).
- **CSS Framework:** Bootstrap 5 (Fuente de verdad). Prohibido CSS personalizado fuera de utilidades de BS5, a menos que se use `<style>` específico para efectos de transición complejos.
- **Iconos:** Bootstrap Icons (`<i class="bi bi-..."></i>`).
- **JavaScript:** Alpine.js (para reactividad ligera) y Bootstrap JS (para componentes nativos como Modales o Toasts).
- **Animaciones:** Animate.css (`animate__animated`) y AOS.js para scroll.

## 2. Estándares de Diseño (Estilo Moderno)
- **Bordes:** Usar `rounded-4` (definido como 1rem) para tarjetas y contenedores principales.
- **Sombras:** Usar `shadow-sm` por defecto y `hover-shadow` (vía CSS inline) para interactividad.
- **Tipografía:** Inter / Spline Sans. Títulos con `fw-bold` y `text-primary`.
- **Componentes:**
  - Las tarjetas (`.card`) deben ser `border-0` con `shadow-sm`.
  - Los botones deben usar clases de Bootstrap (`btn-primary`, `btn-outline-primary`).
  - Espaciado: Preferir utilidades `gap-`, `mb-`, `py-`.

## 3. Reglas de Latte 3.1.2
- **Estructura:** Siempre extender de `{@portal.latte}` o `{@base.latte}`.
- **Bloques:** El contenido principal siempre va dentro de `{block main}`.
- **Filtros:** Usar `|truncate:100`, `|date:'d/m/Y'`, `|noescape` cuando sea necesario con seguridad.
- **Includes:** Separar componentes reutilizables (ej. `header-page.latte`) y pasar parámetros: `{include 'path/to/comp.latte', title: '...', icon: '...'}`.

## 4. Comportamiento y Reactividad (Alpine.js)
- **Toasts:** Para notificaciones, disparar eventos al store global: 
  `Alpine.store('toast').show({ type: 'success', message: 'Tarea completada' })`.
- **Interactividad:** Usar `x-data`, `x-show`, `x-model` directamente en el HTML de Latte.
- **Scripts:** Si el script es específico de una página, colocarlo dentro de `{block scripts}`.

## 5. Accesibilidad (A11y)
- Todo elemento interactivo debe tener `aria-label` si no tiene texto descriptivo.
- Uso correcto de jerarquía de encabezados (`h1` -> `h2` -> `h3`).
- Contraste alto usando las variables de color de Bootstrap definidas.

## 6. Estructura de Respuesta Esperada
Cuando se pida una nueva sección o página:
1. Proporcionar el código `.latte` completo.
2. Si requiere lógica Alpine.js, incluirla en un bloque `<script>` o explicar la integración con el store.
3. Asegurar que las animaciones `animate__fadeInUp` tengan retrasos calculados en bucles: `style="animation-delay: {$iterator->counter * 0.1}s"`.