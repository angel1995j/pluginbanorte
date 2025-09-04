<?php
if (!defined('ABSPATH')) { exit; }

class Banorte_VCE_AJAX {

    public static function init() {
        add_action('wp_ajax_nopriv_banorte_vce_cifrar', [__CLASS__, 'cifrar']);
        add_action('wp_ajax_banorte_vce_cifrar', [__CLASS__, 'cifrar']);

        add_action('wp_ajax_nopriv_banorte_vce_capture', [__CLASS__, 'capture']);
        add_action('wp_ajax_banorte_vce_capture', [__CLASS__, 'capture']);
    }

    /**
     * POST admin-ajax.php?action=banorte_vce_cifrar&order_id=..&order_key=..
     * Devuelve json del JAR ({responseId, code, message, data:["sub1:::sub2"]})
     */
    public static function cifrar() {
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $order_key= isset($_POST['order_key']) ? sanitize_text_field($_POST['order_key']) : '';

        if (!$order_id || !$order_key) {
            wp_send_json(['responseId'=>'900','code'=>'400','message'=>'Faltan parámetros'], 400);
        }

        $order = wc_get_order($order_id);
        if (!$order || $order->get_order_key() !== $order_key) {
            wp_send_json(['responseId'=>'900','code'=>'403','message'=>'Pedido no autorizado'], 403);
        }

        // Cargar gateway y settings
        $gateways = WC()->payment_gateways()->payment_gateways();
        /** @var WC_Gateway_Banorte_VCE $gw */
        $gw = isset($gateways['banorte_vce']) ? $gateways['banorte_vce'] : null;
        if (!$gw || 'yes' !== $gw->enabled) {
            wp_send_json(['responseId'=>'900','code'=>'503','message'=>'Gateway desactivado'], 503);
        }

        $amount = number_format((float)$order->get_total(), 2, '.', '');
        $controlNumber = 'REF' . $order->get_id();

        $payload = [
            'merchantId'   => $gw->affiliateId,
            'name'         => $gw->user,
            'password'     => $gw->password,
            'mode'         => strtoupper($gw->mode),
            'controlNumber'=> $controlNumber,
            'terminalId'   => $gw->terminalId,
            'amount'       => $amount,
            'merchantName' => $gw->merchantName,
            'merchantCity' => $gw->merchantCity,
            'lang'         => $gw->lang,
        ];

        // Base64(JSON(payload)) y pedir cifrado al JAR local
        $json = wp_json_encode($payload, JSON_UNESCAPED_SLASHES);
        $base64 = base64_encode($json);

        $url = 'http://127.0.0.1:8888/wsCifrado';
        $args = [
            'method'  => 'POST',
            'timeout' => 20,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode(['base64' => $base64], JSON_UNESCAPED_SLASHES),
        ];

        $res = wp_remote_post($url, $args);
        if (is_wp_error($res)) {
            wp_send_json(['responseId'=>'900','code'=>'500','message'=>'Error al contactar el componente de cifrado: '.$res->get_error_message()], 500);
        }

        $code = wp_remote_retrieve_response_code($res);
        $body = wp_remote_retrieve_body($res);
        if ($code !== 200) {
            wp_send_json(['responseId'=>'900','code'=>(string)$code,'message'=>'Falla cifrado','raw'=>$body], 500);
        }

        // Devuelve respuesta del JAR tal cual
        @header('Content-Type: application/json; charset=UTF-8');
        echo $body;
        wp_die();
    }

    /**
     * POST admin-ajax.php?action=banorte_vce_capture
     * Campos: order_id, order_key, status(A|E|C), code?, message?, control?
     */
    public static function capture() {
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $order_key= isset($_POST['order_key']) ? sanitize_text_field($_POST['order_key']) : '';
        $status   = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $code     = isset($_POST['code']) ? sanitize_text_field($_POST['code']) : '';
        $message  = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : '';
        $control  = isset($_POST['control']) ? sanitize_text_field($_POST['control']) : '';

        if (!$order_id || !$order_key || !$status) {
            wp_send_json_error(['msg' => 'Parámetros incompletos'], 400);
        }

        $order = wc_get_order($order_id);
        if (!$order || $order->get_order_key() !== $order_key) {
            wp_send_json_error(['msg' => 'Pedido no autorizado'], 403);
        }

        if ($status === 'A') {
            if (!empty($control)) {
                $order->update_meta_data('_banorte_control', $control);
            }
            $order->payment_complete($control ?: '');
            $order->add_order_note('Pago aprobado Banorte. Control: ' . ($control ?: 'N/D'));
            wp_send_json_success();
        } elseif ($status === 'C') {
            $order->update_status('cancelled', 'Pago cancelado por el usuario (Banorte).');
            wp_send_json_success();
        } else {
            $note = 'Pago rechazado Banorte. ' . trim($code . ' ' . $message);
            $order->update_status('failed', $note);
            wp_send_json_success();
        }
    }
}

Banorte_VCE_AJAX::init();
