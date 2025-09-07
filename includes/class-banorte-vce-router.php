<?php
if (!defined('ABSPATH')) { exit; }

class Banorte_VCE_Router {

    public static function bootstrap() {
        add_action('template_redirect', array(__CLASS__, 'maybe_render_interstitial'));
    }

    public static function maybe_render_interstitial() {
        if (empty($_GET['banorte_vce_pay']) || $_GET['banorte_vce_pay'] !== '1') {
            return;
        }

        $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
        $key      = isset($_GET['key']) ? wc_clean(wp_unslash($_GET['key'])) : '';
        $order    = wc_get_order($order_id);

        if (!$order || $order->get_order_key() !== $key) {
            wp_die('Pedido inválido.');
        }

        // Preparar datos para la vista
        $gateway = self::get_gateway();
        if (!$gateway) {
            wp_die('Gateway Banorte no disponible.');
        }

        $payload = get_transient('banorte_vce_payload_' . $order_id);
        if (!$payload) {
            // Re-armar mínimo (fallback)
            $payload = array(
                'order_id'  => $order->get_id(),
                'order_key' => $order->get_order_key(),
                'amount'    => (float) $order->get_total(),
                'currency'  => $order->get_currency(),
                'return_ok' => $gateway->get_return_url($order),
                'return_cancel' => wc_get_checkout_url(),
            );
        }

        $endpointJs = ($gateway->environment === 'AUT') ? $gateway->endpointJs_AUT : $gateway->endpointJs_PRD;

        // Variables para la plantilla

        $start_cfg = array(
            'user'         => isset($gateway->user) ? $gateway->user : '',
            'password'     => isset($gateway->password) ? $gateway->password : '',
            'affiliateId'  => !empty($gateway->affiliateId) ? $gateway->affiliateId : (isset($gateway->merchantId) ? $gateway->merchantId : ''),
            'terminalId'   => isset($gateway->terminalId) ? $gateway->terminalId : '',
            'merchantName' => isset($gateway->merchantName) ? $gateway->merchantName : '',
            'merchantCity' => isset($gateway->merchantCity) ? $gateway->merchantCity : '',
            'lang'         => isset($gateway->lang) ? $gateway->lang : 'ES',
            'mode'         => isset($gateway->environment) ? $gateway->environment : 'PRD',
        );

        $vars = array(
            'endpointJs'   => $endpointJs,
            'order'        => $order,
            'order_id'     => $order->get_id(),
            'order_key'    => $order->get_order_key(),
            'payload'      => $payload,
            'ajax_url'     => admin_url('admin-ajax.php?action=banorte_vce_encrypt&order_id=' . $order->get_id() . '&key=' . rawurlencode($order->get_order_key())),
            'return_ok'    => $gateway->get_return_url($order),
            'return_cancel'=> wc_get_checkout_url(),
        );

        // Renderizar plantilla simple (sin header/footer del theme)
        self::load_template($vars);
        exit;
    }

    protected static function get_gateway() {
        if (!function_exists('WC')) return null;
        $gateways = WC()->payment_gateways()->get_available_payment_gateways();
        return isset($gateways['banorte_vce']) ? $gateways['banorte_vce'] : null;
    }

    protected static function load_template($vars) {
        extract($vars);
        include plugin_dir_path(__FILE__) . '../templates/pay.php';
    }
}
