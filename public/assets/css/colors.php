<?php

declare(strict_types=1);

// 1. Evitar caché del navegador para ver cambios inmediatos durante desarrollo
use App\Bootstrap;
use App\Module\Sindicato\Repository\ColoresRepository;

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Content-Type: text/css; charset=UTF-8");

// ============================================================================
// FUNCIONES HELPER PARA CONVERSIÓN Y ACCESIBILIDAD
// ============================================================================

if (!function_exists("hexToRgb")) {
    /**
     * Convierte HEX a string RGB para CSS
     */
    function hexToRgb($hex): string
    {
        $rgb = hexToRgbArray($hex);
        return "{$rgb[0]}, {$rgb[1]}, {$rgb[2]}";
    }
}

if (!function_exists("hexToRgbArray")) {
    /**
     * Convierte HEX a array RGB [r, g, b]
     */
    function hexToRgbArray($hex): array
    {
        $hex = str_replace("#", "", $hex);
        if (strlen($hex) == 3) {
            $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
            $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
            $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }
        return [$r, $g, $b];
    }
}

if (!function_exists("rgbArrayToHex")) {
    /**
     * Convierte array RGB a HEX
     */
    function rgbArrayToHex($r, $g, $b): string
    {
        return sprintf("#%02x%02x%02x", $r, $g, $b);
    }
}

if (!function_exists("getLuminance")) {
    /**
     * Calcula la luminancia relativa según WCAG 2.0
     * https://www.w3.org/TR/WCAG20/#relativeluminancedef
     */
    function getLuminance($r, $g, $b): float
    {
        $r = $r / 255;
        $g = $g / 255;
        $b = $b / 255;

        $r = $r <= 0.03928 ? $r / 12.92 : pow(($r + 0.055) / 1.055, 2.4);
        $g = $g <= 0.03928 ? $g / 12.92 : pow(($g + 0.055) / 1.055, 2.4);
        $b = $b <= 0.03928 ? $b / 12.92 : pow(($b + 0.055) / 1.055, 2.4);

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }
}

if (!function_exists("getContrastRatio")) {
    /**
     * Calcula el ratio de contraste entre dos colores
     * Retorna un valor entre 1 y 21
     */
    function getContrastRatio($hex1, $hex2): float
    {
        [$r1, $g1, $b1] = hexToRgbArray($hex1);
        [$r2, $g2, $b2] = hexToRgbArray($hex2);

        $l1 = getLuminance($r1, $g1, $b1);
        $l2 = getLuminance($r2, $g2, $b2);

        $lighter = max($l1, $l2);
        $darker = min($l1, $l2);

        return ($lighter + 0.05) / ($darker + 0.05);
    }
}

if (!function_exists("getAccessibleTextColor")) {
    /**
     * Retorna blanco o negro según cuál tenga mejor contraste con el fondo
     * @param string $bgColor Color de fondo en HEX
     * @return string '#ffffff' o '#000000'
     */
    function getAccessibleTextColor($bgColor): string
    {
        $whiteContrast = getContrastRatio($bgColor, "#ffffff");
        $blackContrast = getContrastRatio($bgColor, "#000000");

        return $whiteContrast > $blackContrast ? "#ffffff" : "#000000";
    }
}

if (!function_exists("lightenDarkenColor")) {
    /**
     * Aclara u oscurece un color
     * @param string $hex Color base
     * @param int $percent Porcentaje positivo para aclarar, negativo para oscurecer
     */
    function lightenDarkenColor($hex, $percent): string
    {
        [$r, $g, $b] = hexToRgbArray($hex);

        $r = max(0, min(255, $r + ($r * $percent) / 100));
        $g = max(0, min(255, $g + ($g * $percent) / 100));
        $b = max(0, min(255, $b + ($b * $percent) / 100));

        return rgbArrayToHex(round($r), round($g), round($b));
    }
}

// ============================================================================
// CARGA DE CONFIGURACIÓN
// ============================================================================

require_once __DIR__ . "/../../../bootstrap.php";

$c = Bootstrap::buildContainer();

$repo = $c->get(ColoresRepository::class);

$colores = $repo->getColores();

$settings = [];

if ($colores) {
    $settings = [
        "primario" => $colores->primario,
        "secundario" => $colores->secundario,
        "exito" => $colores->exito,
        "info" => $colores->info,
        "advertencia" => $colores->advertencia,
        "peligro" => $colores->peligro,
        "claro" => $colores->claro,
        "oscuro" => $colores->oscuro,
        "blanco" => $colores->blanco,
        "cuerpo" => $colores->cuerpo,
        "fondo-cuerpo" => $colores->fondoCuerpo,
    ];
} else {
     ?>
  /* Si no hay colores configurados, se usarán los valores por defecto definidos en el CSS */
  <?php
}

// ============================================================================
// DEFINICIÓN DE COLORES
// ============================================================================

// Colores temáticos
$primaryColor = $settings["primario"] ?? "#611232";
$secondaryColor = $settings["secundario"] ?? "#a57f2c";
$successColor = $settings["exito"] ?? "#38b44a";
$infoColor = $settings["info"] ?? "#17a2b8";
$warningColor = $settings["advertencia"] ?? "#efb73e";
$dangerColor = $settings["peligro"] ?? "#df382c";
$lightColor = $settings["claro"] ?? "#f8f9fa";
$darkColor = $settings["oscuro"] ?? "#212529";

// Colores de sistema (puedes hacerlos dinámicos también si lo deseas)
$whiteColor = $settings["blanco"] ?? "#ffffff";
$bodyColor = $settings["cuerpo"] ?? "#212529";
$bodyBg = $settings["fondo-cuerpo"] ?? "#ffffff";

// Definir array de colores para iterar
$themeColors = [
    "primary" => $primaryColor,
    "secondary" => $secondaryColor,
    "success" => $successColor,
    "info" => $infoColor,
    "warning" => $warningColor,
    "danger" => $dangerColor,
    "light" => $lightColor,
    "dark" => $darkColor,
];
?>
/* ============================================================================
CSS GENERADO DINÁMICAMENTE - BOOTSTRAP 5 THEME + LITERA OVERRIDE
============================================================================ */

:root, [data-bs-theme=light] {

/* ============================================================================
1. COLORES BASE
============================================================================ */

/* Colores Temáticos */
--bs-primary: <?= $primaryColor ?>;
--bs-secondary: <?= $secondaryColor ?>;
--bs-success: <?= $successColor ?>;
--bs-info: <?= $infoColor ?>;
--bs-warning: <?= $warningColor ?>;
--bs-danger: <?= $dangerColor ?>;
--bs-light: <?= $lightColor ?>;
--bs-dark: <?= $darkColor ?>;

/* Colores de Sistema */
--bs-white: <?= $whiteColor ?>;
--bs-body-color: <?= $bodyColor ?>;
--bs-body-bg: <?= $bodyBg ?>;

/* ============================================================================
2. VALORES RGB (Para opacidades y sombras)
============================================================================ */

<?php foreach ($themeColors as $name => $color): ?>
  --bs-<?= $name ?>-rgb: <?= hexToRgb($color) ?>;
<?php endforeach; ?>

--bs-white-rgb: <?= hexToRgb($whiteColor) ?>;
--bs-body-color-rgb: <?= hexToRgb($bodyColor) ?>;
--bs-body-bg-rgb: <?= hexToRgb($bodyBg) ?>;

/* ============================================================================
3. COLORES DE TEXTO ACCESIBLES SOBRE FONDOS DE COLOR
============================================================================ */

<?php foreach ($themeColors as $name => $color): ?>
  --bs-<?= $name ?>-text: <?= getAccessibleTextColor($color) ?>;
<?php endforeach; ?>

/* ============================================================================
4. COLORES DERIVADOS - TEXT EMPHASIS
Mezcla el color base con 60% de negro (más oscuro para texto)
============================================================================ */

<?php foreach ($themeColors as $name => $color): ?>
  --bs-<?= $name ?>-text-emphasis: color-mix(in srgb, var(--bs-<?= $name ?>), black 60%);
<?php endforeach; ?>

/* ============================================================================
5. COLORES DERIVADOS - BG SUBTLE
Mezcla el color base con 80% de blanco (muy claro para fondos)
============================================================================ */

<?php foreach ($themeColors as $name => $color): ?>
  --bs-<?= $name ?>-bg-subtle: color-mix(in srgb, var(--bs-<?= $name ?>), white 80%);
<?php endforeach; ?>

/* ============================================================================
6. COLORES DERIVADOS - BORDER SUBTLE
Mezcla el color base con 60% de blanco (suave para bordes)
============================================================================ */

<?php foreach ($themeColors as $name => $color): ?>
  --bs-<?= $name ?>-border-subtle: color-mix(in srgb, var(--bs-<?= $name ?>), white 60%);
<?php endforeach; ?>

/* ============================================================================
7. COLORES DE ENLACE
============================================================================ */

--bs-link-color: var(--bs-primary);
--bs-link-color-rgb: var(--bs-primary-rgb);
--bs-link-hover-color: color-mix(in srgb, var(--bs-primary), black 20%);
--bs-link-decoration: underline;
--bs-link-hover-decoration: underline;

/* ============================================================================
8. COLORES DE BORDE Y FONDO
============================================================================ */

--bs-border-color: #dee2e6;
--bs-border-color-translucent: rgba(0, 0, 0, 0.175);

/* ============================================================================
9. FOCUS RING (Borde al hacer foco en inputs)
============================================================================ */

--bs-focus-ring-width: 0.25rem;
--bs-focus-ring-opacity: 0.25;
--bs-focus-ring-color: rgba(var(--bs-primary-rgb), var(--bs-focus-ring-opacity));

/* ============================================================================
10. COLORES DE FORMULARIOS
============================================================================ */

--bs-form-valid-color: var(--bs-success);
--bs-form-valid-border-color: var(--bs-success);
--bs-form-invalid-color: var(--bs-danger);
--bs-form-invalid-border-color: var(--bs-danger);

/* ============================================================================
11. COLORES DE HEADINGS
============================================================================ */

--bs-heading-color: inherit;

/* ============================================================================
12. COLORES DE CÓDIGO
============================================================================ */

--bs-code-color: #d63384;
--bs-highlight-bg: #fff3cd;

}

/* ============================================================================
COMPONENTES - BOTONES
============================================================================ */

<?php foreach ($themeColors as $name => $color): ?>
  .btn-<?= $name ?> {
  --bs-btn-color: var(--bs-<?= $name ?>-text);
  --bs-btn-bg: var(--bs-<?= $name ?>);
  --bs-btn-border-color: var(--bs-<?= $name ?>);
  --bs-btn-hover-color: var(--bs-<?= $name ?>-text);
  --bs-btn-hover-bg: color-mix(in srgb, var(--bs-<?= $name ?>), black 15%);
  --bs-btn-hover-border-color: color-mix(in srgb, var(--bs-<?= $name ?>), black 20%);
  --bs-btn-focus-shadow-rgb: var(--bs-<?= $name ?>-rgb);
  --bs-btn-active-color: var(--bs-<?= $name ?>-text);
  --bs-btn-active-bg: color-mix(in srgb, var(--bs-<?= $name ?>), black 20%);
  --bs-btn-active-border-color: color-mix(in srgb, var(--bs-<?= $name ?>), black 25%);
  --bs-btn-active-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
  --bs-btn-disabled-color: var(--bs-<?= $name ?>-text);
  --bs-btn-disabled-bg: var(--bs-<?= $name ?>);
  --bs-btn-disabled-border-color: var(--bs-<?= $name ?>);
  }

<?php endforeach; ?>

/* Botones Outline */
<?php foreach ($themeColors as $name => $color): ?>
  .btn-outline-<?= $name ?> {
  --bs-btn-color: var(--bs-<?= $name ?>);
  --bs-btn-border-color: var(--bs-<?= $name ?>);
  --bs-btn-hover-color: var(--bs-<?= $name ?>-text);
  --bs-btn-hover-bg: var(--bs-<?= $name ?>);
  --bs-btn-hover-border-color: var(--bs-<?= $name ?>);
  --bs-btn-focus-shadow-rgb: var(--bs-<?= $name ?>-rgb);
  --bs-btn-active-color: var(--bs-<?= $name ?>-text);
  --bs-btn-active-bg: var(--bs-<?= $name ?>);
  --bs-btn-active-border-color: var(--bs-<?= $name ?>);
  --bs-btn-active-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
  --bs-btn-disabled-color: var(--bs-<?= $name ?>);
  --bs-btn-disabled-bg: transparent;
  --bs-gradient: none;
  }

<?php endforeach; ?>

/* ============================================================================
COMPONENTES - ALERTAS
============================================================================ */

<?php foreach ($themeColors as $name => $color): ?>
  .alert-<?= $name ?> {
  --bs-alert-color: var(--bs-<?= $name ?>-text-emphasis);
  --bs-alert-bg: var(--bs-<?= $name ?>-bg-subtle);
  --bs-alert-border-color: var(--bs-<?= $name ?>-border-subtle);
  --bs-alert-link-color: var(--bs-<?= $name ?>-text-emphasis);
  }

<?php endforeach; ?>

/* ============================================================================
COMPONENTES - BADGES
============================================================================ */

<?php foreach ($themeColors as $name => $color): ?>
  .badge.bg-<?= $name ?> {
  color: var(--bs-<?= $name ?>-text) !important;
  }

<?php endforeach; ?>

/* ============================================================================
COMPONENTES - TABLAS
============================================================================ */

<?php foreach ($themeColors as $name => $color): ?>
  .table-<?= $name ?> {
  --bs-table-color: var(--bs-body-color);
  --bs-table-bg: var(--bs-<?= $name ?>-bg-subtle);
  --bs-table-border-color: var(--bs-<?= $name ?>-border-subtle);
  --bs-table-striped-bg: color-mix(in srgb, var(--bs-<?= $name ?>-bg-subtle), black 5%);
  --bs-table-striped-color: var(--bs-body-color);
  --bs-table-active-bg: color-mix(in srgb, var(--bs-<?= $name ?>-bg-subtle), black 10%);
  --bs-table-active-color: var(--bs-body-color);
  --bs-table-hover-bg: color-mix(in srgb, var(--bs-<?= $name ?>-bg-subtle), black 7.5%);
  --bs-table-hover-color: var(--bs-body-color);
  }

<?php endforeach; ?>

/* ============================================================================
COMPONENTES - TOASTS
============================================================================ */

<?php foreach ($themeColors as $name => $color): ?>
  .toast.bg-<?= $name ?> {
  color: var(--bs-<?= $name ?>-text);
  }

  .toast.bg-<?= $name ?> .btn-close {
  filter: <?= getAccessibleTextColor($color) === "#ffffff"
      ? "invert(1) grayscale(100%) brightness(200%)"
      : "none" ?>;
  }

<?php endforeach; ?>

/* ============================================================================
COMPONENTES - LIST GROUPS
============================================================================ */

<?php foreach ($themeColors as $name => $color): ?>
  .list-group-item-<?= $name ?> {
  --bs-list-group-color: var(--bs-<?= $name ?>-text-emphasis);
  --bs-list-group-bg: var(--bs-<?= $name ?>-bg-subtle);
  --bs-list-group-border-color: var(--bs-<?= $name ?>-border-subtle);
  --bs-list-group-action-hover-color: var(--bs-<?= $name ?>-text-emphasis);
  --bs-list-group-action-hover-bg: color-mix(in srgb, var(--bs-<?= $name ?>-bg-subtle), black 5%);
  --bs-list-group-action-active-color: var(--bs-<?= $name ?>-text-emphasis);
  --bs-list-group-action-active-bg: var(--bs-<?= $name ?>-bg-subtle);
  --bs-list-group-active-color: var(--bs-<?= $name ?>-text);
  --bs-list-group-active-bg: var(--bs-<?= $name ?>);
  --bs-list-group-active-border-color: var(--bs-<?= $name ?>);
  }

<?php endforeach; ?>

/* ============================================================================
COMPONENTES - PROGRESS BARS
============================================================================ */

<?php foreach ($themeColors as $name => $color): ?>
  .progress-bar.bg-<?= $name ?> {
  color: var(--bs-<?= $name ?>-text);
  }

<?php endforeach; ?>

/* ============================================================================
COMPONENTES - NAVS Y TABS
============================================================================ */

.nav-pills .nav-link.active {
background-color: var(--bs-primary);
color: var(--bs-primary-text);
}

/* ============================================================================
COMPONENTES - PAGINATION
============================================================================ */

.page-link {
--bs-pagination-color: var(--bs-link-color);
--bs-pagination-hover-color: var(--bs-link-hover-color);
--bs-pagination-focus-color: var(--bs-link-hover-color);
--bs-pagination-active-bg: var(--bs-primary);
--bs-pagination-active-border-color: var(--bs-primary);
}

/* ============================================================================
UTILIDADES - COLORES DE TEXTO
============================================================================ */

<?php foreach ($themeColors as $name => $color): ?>
  .text-<?= $name ?> {
  --bs-text-opacity: 1;
  color: rgba(var(--bs-<?= $name ?>-rgb), var(--bs-text-opacity)) !important;
  }

  .text-bg-<?= $name ?> {
  color: var(--bs-<?= $name ?>-text) !important;
  background-color: RGBA(var(--bs-<?= $name ?>-rgb), var(--bs-bg-opacity, 1)) !important;
  }

<?php endforeach; ?>

/* ============================================================================
UTILIDADES - FONDOS
============================================================================ */

<?php foreach ($themeColors as $name => $color): ?>
  .bg-<?= $name ?> {
  --bs-bg-opacity: 1;
  background-color: rgba(var(--bs-<?= $name ?>-rgb), var(--bs-bg-opacity)) !important;
  }

<?php endforeach; ?>

/* ============================================================================
UTILIDADES - BORDES
============================================================================ */

<?php foreach ($themeColors as $name => $color): ?>
  .border-<?= $name ?> {
  --bs-border-opacity: 1;
  border-color: rgba(var(--bs-<?= $name ?>-rgb), var(--bs-border-opacity)) !important;
  }

<?php endforeach; ?>

/* ============================================================================
FIN DEL CSS DINÁMICO
============================================================================ */