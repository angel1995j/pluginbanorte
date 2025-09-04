<?php
if (!defined('ABSPATH')) { exit; }

class Banorte_VCE_Router {

    public static function maybe_render_pay_page() {
        if (!get_query_var('banorte_vce_pay')) { return; }

        $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
        $key      = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';

        if (!$order_id || !$key) {
            wp_die('Parámetros inválidos.');
        }

        $order = wc_get_order($order_id);
        if (!$order || $order->get_order_key() !== $key) {
            wp_die('Pedido no válido.');
        }

        // Cargar ajuste de gateway
        $gateways = WC()->payment_gateways()->payment_gateways();
        /** @var WC_Gateway_Banorte_VCE $gw */
        $gw = isset($gateways['banorte_vce']) ? $gateways['banorte_vce'] : null;
        if (!$gw || 'yes' !== $gw->enabled) {
            wp_die('Gateway Banorte desactivado.');
        }

        $endpointJs = strtoupper($gw->mode) === 'PRD' ? $gw->endpointJs_PRD : $gw->endpointJs_AUT;

        $amount = number_format((float)$order->get_total(), 2, '.', '');
        $controlNumber = 'REF' . $order->get_id(); // o $order->get_order_number()

        $data = [
            'merchantId'   => $gw->affiliateId,
            'name'         => $gw->user,
            'password'     => $gw->password,
            'mode'         => strtoupper($gw->mode), // AUT | PRD (Payment.setEnv siempre 'pro')
            'controlNumber'=> $controlNumber,
            'terminalId'   => $gw->terminalId,
            'amount'       => $amount,
            'merchantName' => $gw->merchantName,
            'merchantCity' => $gw->merchantCity,
            'lang'         => $gw->lang,
        ];

        // Pasamos variables a la vista
        $vars = [
            'endpointJs'   => $endpointJs,
            'order'        => $order,
            'order_id'     => $order_id,
            'order_key'    => $key,
            'payload'      => $data,
            'ajax_url'     => admin_url('admin-ajax.php'),
            'return_ok'    => $order->get_checkout_order_received_url(),
            'return_cancel'=> $order->get_cancel_order_url(),
            'return_retry' => $order->get_checkout_payment_url(),
        ];

        self::render_template('pay.php', $vars);
        exit;
    }

    private static function render_template($file, $vars=[]) {
        $path = BANORTE_VCE_PATH . 'templates/' . $file;
        if (!file_exists($path)) { wp_die('Vista no encontrada.'); }
        extract($vars);
        include $path;
    }
}
