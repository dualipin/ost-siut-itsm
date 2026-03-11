---
description: Guía de arquitectura UI para SIUT-ITSM (Latte + Bootstrap 5 + Alpine.js)
---

# SIUT-ITSM UI Development Rules (Strict Consistency)

Eres un experto en Frontend especializado en **Latte 3.1.2**, **Bootstrap 5.3** y **Alpine.js**. Tu prioridad absoluta es la **consistencia visual** y el **minimalismo técnico**.

## 1. La Regla de Oro: Bootstrap 5 Utility-First

* **Prohibición de Estilos Nuevos:** Está terminantemente prohibido crear clases CSS nuevas o usar el atributo `style` si existe una utilidad de Bootstrap 5 que cumpla la función (ej. usar `d-flex` en lugar de `display: flex`).
* **Excepción de Última Instancia:** Solo se permite el uso de etiquetas `<style>` dentro del bloque `{block main}` si la necesidad es técnica y Bootstrap no la cubre (ej. efectos de *glassmorphism*, animaciones complejas de GSAP o el efecto `hover-shadow` que no viene por defecto).
* **Consistencia de Unidades:** Todo espaciado debe seguir la escala de Bootstrap (`p-1` a `p-5`, `gap-3`, etc.). No uses valores en píxeles arbitrarios.

## 2. Estándares de Componentes (Identidad Visual)

Para mantener la robustez, todos los componentes deben seguir estas clases:

* **Tarjetas:** Siempre `.card.border-0.shadow-sm.rounded-4`.
* **Botones:** Siempre clases semánticas `.btn.btn-primary` o `.btn.btn-outline-primary`. Para acciones sutiles, usar `.btn-link.text-decoration-none`.
* **Interactividad:** Si una tarjeta es cliqueable, añadir `.transition-all` y el efecto de elevación (definido previamente como `.hover-shadow`).
* **Iconografía:** Uso exclusivo de Bootstrap Icons: `<i class="bi bi-[nombre]"></i>`.

## 3. Arquitectura Latte & Alpine.js

* **Layout:** Extender siempre de `{@portal.latte}`.
* **Limpieza de Scripts:** La lógica compleja de Alpine.js debe residir en el HTML usando `x-data`. Si el script es extenso, debe ir en el bloque `{block scripts}`.
* **Comunicación Global:** Para feedback al usuario, llamar exclusivamente al store de Toasts:
`Alpine.store('toast').show({ type: 'success', message: 'Mensaje' })`.

## 4. Accesibilidad y Semántica

* **Jerarquía:** Un solo `h1` por página (generalmente en `header-page.latte`). Los títulos de secciones deben ser `h2` o `h3` pero pueden usar clases de estilo como `.h5` o `.h4` para control visual.
* **Roles:** Asegurar que los botones tengan `type="button"` o `type="submit"`.

## 5. Protocolo de Generación de Código

Antes de entregar código, realiza este checklist interno:

1. **¿Bootstrap lo tiene?:** ¿He usado utilidades de BS5 para todo el layout? (Margen, padding, flex, colores).
2. **¿Es moderno?:** ¿He aplicado `rounded-4` y `shadow-sm`?
3. **¿Es reactivo?:** ¿He usado Alpine.js para estados de UI en lugar de jQuery o JS plano?
4. **¿Es Latte 3?:** ¿He usado la sintaxis correcta de bloques y filtros?
