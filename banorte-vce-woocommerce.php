<?php
/**
 * Plugin Name: Banorte VCE para WooCommerce (Lightbox)
 * Description: Integra Banorte VCE (lightbox) con WooCommerce usando el microservicio Java en 127.0.0.1:8888 para cifrado (AES+RSA). Basado en tu flujo PHP funcional (checkout.php → wsCifrado.php → Payment.startPayment → authenticateV2).
 * Version: 1.1.4
 * Author: Tu Equipo
 * Requires Plugins: woocommerce
 */

if (!defined('ABSPATH')) { exit; }

// Asegurar WooCommerce
add_action('plugins_loaded', function() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function(){
            echo '<div class="notice notice-error"><p><strong>Banorte VCE:</strong> Requiere WooCommerce activo.</p></div>';
        });
        return;
    }

    // Cargar clases
    require_once __DIR__ . '/includes/class-wc-gateway-banorte-vce.php';
    require_once __DIR__ . '/includes/class-banorte-vce-ajax.php';
    require_once __DIR__ . '/includes/class-banorte-vce-router.php';

    // Registrar el método de pago
    add_filter('woocommerce_payment_gateways', function($methods){
        $methods[] = 'WC_Gateway_Banorte_VCE';
        return $methods;
    });

    // Router (interstitial pay.php y callback)
    Banorte_VCE_Router::bootstrap();

    // Ajax bootstrap (asegura hooks incluso en admin-ajax)
    Banorte_VCE_Ajax::bootstrap();
});

// Activos (JS) sólo cuando corresponde
add_action('wp_enqueue_scripts', function(){
    // Sólo en la pantalla de pago intermedio (interstitial)
    if (!empty($_GET['banorte_vce_pay']) && $_GET['banorte_vce_pay'] === '1') {
        wp_enqueue_script(
            'banorte-vce-frontend',
            plugins_url('assets/js/banorte-frontend.js', __FILE__),
            array(),
            '1.1.4',
            true
        );
    }
});
