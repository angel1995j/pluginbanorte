<?php
/**
 * Plugin Name: Banorte VCE para WooCommerce
 * Description: Pasarela de pago Banorte (Lightbox VCE) con cifrado vía microservicio Java local (puerto 8888).
 * Author: Maratón Monterrey
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: banorte-vce
 */

if (!defined('ABSPATH')) { exit; }

define('BANORTE_VCE_VERSION', '1.0.0');
define('BANORTE_VCE_PATH', plugin_dir_path(__FILE__));
define('BANORTE_VCE_URL', plugin_dir_url(__FILE__));

/**
 * Carga archivos del plugin
 */
add_action('plugins_loaded', function () {
    if (!class_exists('WC_Payment_Gateway')) {
        add_action('admin_notices', function(){
            echo '<div class="notice notice-error"><p><strong>Banorte VCE</strong> requiere WooCommerce activo.</p></div>';
        });
        return;
    }

    require_once BANORTE_VCE_PATH . 'includes/class-wc-gateway-banorte-vce.php';
    require_once BANORTE_VCE_PATH . 'includes/class-banorte-vce-router.php';
    require_once BANORTE_VCE_PATH . 'includes/class-banorte-vce-ajax.php';

    // Registrar pasarela
    add_filter('woocommerce_payment_gateways', function($methods){
        $methods[] = 'WC_Gateway_Banorte_VCE';
        return $methods;
    });
});

/**
 * Query var para la página de pago (intermedia de lightbox)
 */
add_filter('query_vars', function($vars){
    $vars[] = 'banorte_vce_pay';
    return $vars;
});

/**
 * Cargar scripts en frontend
 * - Forzamos checkoutV2.js en <head> (sin defer/async)
 * - Evitamos optimizaciones que rompen el global window.Payment
 * - También aplicamos cuando la página contenga el shortcode [banorte_vce_pay]
 */
add_action('wp_enqueue_scripts', function () {
    // ¿Debemos cargar en esta vista?
    $show = false;

    if (function_exists('is_checkout') && is_checkout()) {
        $show = true;
    }
    if (isset($_GET['pay_for_order'])) {
        $show = true;
    }
    if (!$show && is_singular()) {
        global $post;
        if ($post && has_shortcode($post->post_content, 'banorte_vce_pay')) {
            $show = true;
        }
    }
    if (!$show) return;

    $settings = get_option('woocommerce_banorte_vce_settings', []);
    $mode     = strtoupper($settings['environment'] ?? 'PRD');
    $aut_url  = trim($settings['endpoint_js_aut'] ?? '');
    $prd_url  = trim($settings['endpoint_js_prd'] ?? '');
    $lb_url   = ($mode === 'PRD') ? ($prd_url ?: $aut_url) : ($aut_url ?: $prd_url);

    if (empty($lb_url)) {
        // URL por defecto (según lo que te funciona en /prueba)
        $lb_url = 'https://multicobros.banorte.com/orquestador/lightbox/checkoutV2.js';
    }

    // jQuery de WP
    wp_enqueue_script('jquery');

    // Lightbox (en <head> -> in_footer=false; sin deps para que cargue lo antes posible)
    // IMPORTANTE: dejamos version null para no forzar cache-busting raro
    wp_enqueue_script('banorte-lightbox', $lb_url, [], null, false);

    // Nuestro JS de frontend (usa jQuery y el global Payment)
    wp_register_script(
        'banorte-frontend',
        plugins_url('assets/js/banorte-frontend.js', __FILE__),
        ['jquery'],
        BANORTE_VCE_VERSION,
        true // footer
    );

    // Variables disponibles en window.BANORTE_VCE (también las expone el frontend)
    wp_localize_script('banorte-frontend', 'BANORTE_VCE', [
        'lightboxSrc' => $lb_url,
        'env'         => 'pro', // Banorte pidió 'pro' siempre
    ]);

    wp_enqueue_script('banorte-frontend');
}, 1);

/**
 * Asegurar que el tag del lightbox no sea "optimizado" por minificadores/defer/rocket loader
 * y que quede en <head> sin async/defer
 */
add_filter('script_loader_tag', function ($tag, $handle, $src) {
    if ($handle === 'banorte-lightbox') {
        // Construimos manualmente la etiqueta <script> con atributos anti-optimización
        $attrs = sprintf(
            'id="banorte-lightbox" src="%s" data-cfasync="false" data-no-minify="1" data-no-defer="1" data-no-optimization="1" crossorigin="anonymous"',
            esc_url($src)
        );
        return '<script ' . $attrs . '></script>';
    }
    return $tag;
}, 10, 3);

/**
 * Render de la página de pago si corresponde
 */
add_action('template_redirect', ['Banorte_VCE_Router','maybe_render_pay_page']);

/**
 * Reescritura al activar/desactivar
 */
register_activation_hook(__FILE__, function(){
    flush_rewrite_rules();
});
register_deactivation_hook(__FILE__, function(){
    flush_rewrite_rules();
});
