<?php
header('Content-Type: text/css; charset=UTF-8');

$settings = $settings ?? []; // Ensure $settings is defined as an array

$primaryColor = $settings['primary_color'] ?? '#611232';  // Color primario: #611232
$secondaryColor = $settings['secondary_color'] ?? '#a57f2c';  // Color secundario: #a57f2c
$successColor = $settings['success_color'] ?? '#38b44a';
$infoColor = $settings['info_color'] ?? '#17a2b8';
$warningColor = $settings['warning_color'] ?? '#efb73e';
$dangerColor = $settings['danger_color'] ?? '#df382c';
$lightColor = $settings['light_color'] ?? '#e9ecef';
$darkColor = $settings['dark_color'] ?? '#002f2a';  // Color oscuro: #002f2a
?>

:root,
[data-bs-theme=light] {
--bs-blue: <?php echo $primaryColor; ?>;
--bs-indigo: <?php echo $secondaryColor; ?>;
--bs-purple: <?php echo $darkColor; ?>;
--bs-pink: <?php echo $dangerColor; ?>;
--bs-red: <?php echo $dangerColor; ?>;
--bs-orange: <?php echo $warningColor; ?>;
--bs-yellow: <?php echo $warningColor; ?>;
--bs-green: <?php echo $successColor; ?>;
--bs-teal: <?php echo $infoColor; ?>;
--bs-cyan: <?php echo $infoColor; ?>;
--bs-black: #000;
--bs-white: #fff;
--bs-gray: #868e96;
--bs-gray-dark: #333;
--bs-gray-100: #f8f9fa;
--bs-gray-200: #e9ecef;
--bs-gray-300: #dee2e6;
--bs-gray-400: #ced4da;
--bs-gray-500: #aea79f;
--bs-gray-600: #868e96;
--bs-gray-700: #495057;
--bs-gray-800: #333;
--bs-gray-900: #212529;
--bs-primary: <?php echo $primaryColor; ?>;
--bs-secondary: <?php echo $secondaryColor; ?>;
--bs-success: <?php echo $successColor; ?>;
--bs-info: <?php echo $infoColor; ?>;
--bs-warning: <?php echo $warningColor; ?>;
--bs-danger: <?php echo $dangerColor; ?>;
--bs-light: <?php echo $lightColor; ?>;
--bs-dark: <?php echo $darkColor; ?>;
--bs-primary-rgb: <?php echo hexToRgb($primaryColor); ?>;
--bs-secondary-rgb: <?php echo hexToRgb($secondaryColor); ?>;
--bs-success-rgb: <?php echo hexToRgb($successColor); ?>;
--bs-info-rgb: <?php echo hexToRgb($infoColor); ?>;
--bs-warning-rgb: <?php echo hexToRgb($warningColor); ?>;
--bs-danger-rgb: <?php echo hexToRgb($dangerColor); ?>;
--bs-light-rgb: <?php echo hexToRgb($lightColor); ?>;
--bs-dark-rgb: <?php echo hexToRgb($darkColor); ?>;

--bs-primary-text-emphasis: #5d220d;
--bs-secondary-text-emphasis: #464340;
--bs-success-text-emphasis: #16481e;
--bs-info-text-emphasis: #09414a;
--bs-warning-text-emphasis: #604919;
--bs-danger-text-emphasis: #591612;
--bs-light-text-emphasis: #495057;
--bs-dark-text-emphasis: #495057;
--bs-primary-bg-subtle: #fbddd2;
--bs-secondary-bg-subtle: #efedec;
--bs-success-bg-subtle: #d7f0db;
--bs-info-bg-subtle: #d1ecf1;
--bs-warning-bg-subtle: #fcf1d8;
--bs-danger-bg-subtle: #f9d7d5;
--bs-light-bg-subtle: #fcfcfd;
--bs-dark-bg-subtle: #ced4da;
--bs-primary-border-subtle: #f6bba6;
--bs-secondary-border-subtle: #dfdcd9;
--bs-success-border-subtle: #afe1b7;
--bs-info-border-subtle: #a2dae3;
--bs-warning-border-subtle: #f9e2b2;
--bs-danger-border-subtle: #f2afab;
--bs-light-border-subtle: #e9ecef;
--bs-dark-border-subtle: #aea79f;
--bs-white-rgb: 255, 255, 255;
--bs-black-rgb: 0, 0, 0;
--bs-font-sans-serif: Ubuntu, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
--bs-font-monospace: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
--bs-gradient: linear-gradient(180deg, rgba(255, 255, 255, 0.15), rgba(255, 255, 255, 0));
--bs-body-font-family: var(--bs-font-sans-serif);
--bs-body-font-size: 1rem;
--bs-body-font-weight: 400;
--bs-body-line-height: 1.5;
--bs-body-color: #333;
--bs-body-color-rgb: 51, 51, 51;
--bs-body-bg: #fff;
--bs-body-bg-rgb: 255, 255, 255;
--bs-emphasis-color: #000;
--bs-emphasis-color-rgb: 0, 0, 0;
--bs-secondary-color: rgba(51, 51, 51, 0.75);
--bs-secondary-color-rgb: 51, 51, 51;
--bs-secondary-bg: #e9ecef;
--bs-secondary-bg-rgb: 233, 236, 239;
--bs-tertiary-color: rgba(51, 51, 51, 0.5);
--bs-tertiary-color-rgb: 51, 51, 51;
--bs-tertiary-bg: #f8f9fa;
--bs-tertiary-bg-rgb: 248, 249, 250;
--bs-heading-color: inherit;
--bs-link-color: #e95420;
--bs-link-color-rgb: 233, 84, 32;
--bs-link-decoration: underline;
--bs-link-hover-color: #ba431a;
--bs-link-hover-color-rgb: 186, 67, 26;
--bs-code-color: #e83e8c;
--bs-highlight-color: #333;
--bs-highlight-bg: #fcf1d8;
--bs-border-width: 1px;
--bs-border-style: solid;
--bs-border-color: #dee2e6;
--bs-border-color-translucent: rgba(0, 0, 0, 0.175);
--bs-border-radius: 0.375rem;
--bs-border-radius-sm: 0.25rem;
--bs-border-radius-lg: 0.5rem;
--bs-border-radius-xl: 1rem;
--bs-border-radius-xxl: 2rem;
--bs-border-radius-2xl: var(--bs-border-radius-xxl);
--bs-border-radius-pill: 50rem;
--bs-box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
--bs-box-shadow-sm: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
--bs-box-shadow-lg: 0 1rem 3rem rgba(0, 0, 0, 0.175);
--bs-box-shadow-inset: inset 0 1px 2px rgba(0, 0, 0, 0.075);
--bs-focus-ring-width: 0.25rem;
--bs-focus-ring-opacity: 0.25;
--bs-focus-ring-color: rgba(233, 84, 32, 0.25);
--bs-form-valid-color: #38b44a;
--bs-form-valid-border-color: #38b44a;
--bs-form-invalid-color: #df382c;
--bs-form-invalid-border-color: #df382c;
}
<?php
function hexToRgb($hex)
{
    $hex = str_replace("#", "", $hex);
    if (strlen($hex) == 6) {
        list($r, $g, $b) = str_split($hex, 2);
        $r = hexdec($r);
        $g = hexdec($g);
        $b = hexdec($b);
    } elseif (strlen($hex) == 3) {
        list($r, $g, $b) = str_split($hex, 1);
        $r = hexdec($r . $r);
        $g = hexdec($g . $g);
        $b = hexdec($b . $b);
    } else {
        return "0, 0, 0"; // Devuelve un color por defecto si no es válido
    }
    return "$r, $g, $b";
}
