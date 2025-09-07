<?php
if (!defined('ABSPATH')) { exit; }

class WC_Gateway_Banorte_VCE extends WC_Payment_Gateway {

    // Public properties used elsewhere
    public $enabled;
    public $environment;
    public $java_url;
    public $cert_path;
    public $merchantId;
    public $affiliateId;
    public $user;
    public $password;
    public $branch;
    public $terminalId;
    public $merchantName;
    public $merchantCity;
    public $lang;
    public $endpointJs_AUT;
    public $endpointJs_PRD;

    public function __construct() {
        $this->id                 = 'banorte_vce';
        $this->icon               = '';
        $this->method_title       = 'Banorte VCE';
        $this->method_description = 'Cobros con Banorte Lightbox (VCE) usando microservicio Java/WS local.';
        $this->has_fields         = false;
        $this->supports           = array('products');

        $this->init_form_fields();
        $this->init_settings();

        // Settings
        $this->title            = $this->get_option('title', 'Tarjeta (Banorte VCE)');
        $this->description      = $this->get_option('description', 'Paga con Banorte VCE (Lightbox).');
        $this->enabled          = $this->get_option('enabled', 'yes');
        $this->environment      = $this->get_option('environment', 'PRD'); // AUT / PRD
        $this->java_url         = $this->get_option('java_url', 'http://127.0.0.1:8888/wsCifrado');
        $this->cert_path        = $this->get_option('cert_path', plugin_dir_path(__FILE__) . '../cert/multicobros.cer');

        $this->merchantId       = $this->get_option('merchantId', '');
        $this->affiliateId      = $this->get_option('affiliateId', '');
        $this->user             = $this->get_option('user', '');
        $this->password         = $this->get_option('password', '');
        $this->branch           = $this->get_option('branch', '');
        $this->terminalId       = $this->get_option('terminalId', '');
        $this->merchantName     = $this->get_option('merchantName', '');
        $this->merchantCity     = $this->get_option('merchantCity', '');
        $this->lang             = $this->get_option('lang', 'ES');

        $this->endpointJs_AUT   = $this->get_option('endpointJs_AUT', 'https://multicobros.banorte.com/orquestador/lightbox/checkoutV2.js');
        $this->endpointJs_PRD   = $this->get_option('endpointJs_PRD', 'https://multicobros.banorte.com/orquestador/lightbox/checkoutV2.js');

        // Hooks
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        // Ajax (also registered in global bootstrap as backup)
        add_action('wp_ajax_banorte_vce_encrypt', array($this, 'ajax_encrypt'));
        add_action('wp_ajax_nopriv_banorte_vce_encrypt', array($this, 'ajax_encrypt'));

        // WooCommerce API callback
        add_action('woocommerce_api_banorte_vce_callback', array($this, 'handle_callback'));
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => 'Habilitar',
                'label'   => 'Activar Banorte VCE',
                'type'    => 'checkbox',
                'default' => 'yes'
            ),
            'title' => array(
                'title'       => 'Título',
                'type'        => 'text',
                'default'     => 'Tarjeta (Banorte VCE)',
            ),
            'description' => array(
                'title'       => 'Descripción',
                'type'        => 'textarea',
                'default'     => 'Paga con Banorte VCE (Lightbox).'
            ),
            'environment' => array(
                'title'       => 'Entorno',
                'type'        => 'select',
                'options'     => array('AUT' => 'Autorización/Pruebas', 'PRD' => 'Producción'),
                'default'     => 'PRD'
            ),
            'java_url' => array(
                'title'       => 'Endpoint de cifrado (Java o PHP)',
                'type'        => 'text',
                'default'     => 'http://127.0.0.1:8888/wsCifrado',
                'desc_tip'    => true,
                'description' => 'URL completa del servicio de cifrado. Ej: http://127.0.0.1:8888/wsCifrado (JAR) o https://tu-dominio/wsCifrado.php (proxy PHP).'
            ),
            'cert_path' => array(
                'title'       => 'Certificado (.cer) — ruta o URL',
                'type'        => 'text',
                'default'     => plugin_dir_path(__FILE__) . '../cert/multicobros.cer',
                'description' => 'Ruta absoluta (p.ej. /home/xxx/public_html/.../multicobros.cer), relativa a WP (wp-content/uploads/multicobros.cer) o URL https://.../multicobros.cer'
            ),
            'merchantId' => array('title'=>'Merchant ID','type'=>'text','default'=>''),
            'affiliateId'=> array('title'=>'Affiliate ID','type'=>'text','default'=>''),
            'user'       => array('title'=>'Usuario orquestador','type'=>'text','default'=>''),
            'password'   => array('title'=>'Password orquestador','type'=>'password','default'=>''),
            'branch'     => array('title'=>'Sucursal/Branch','type'=>'text','default'=>''),
            'terminalId' => array('title'=>'Terminal ID','type'=>'text','default'=>''),
            'merchantName'=>array('title'=>'Nombre comercio','type'=>'text','default'=>''),
            'merchantCity'=>array('title'=>'Ciudad comercio','type'=>'text','default'=>''),
            'lang'       => array('title'=>'Idioma','type'=>'text','default'=>'ES'),
            'endpointJs_AUT' => array(
                'title'   => 'checkoutV2.js (AUT)',
                'type'    => 'text',
                'default' => 'https://multicobros.banorte.com/orquestador/lightbox/checkoutV2.js'
            ),
            'endpointJs_PRD' => array(
                'title'   => 'checkoutV2.js (PRD)',
                'type'    => 'text',
                'default' => 'https://multicobros.banorte.com/orquestador/lightbox/checkoutV2.js'
            )
        );
    }

    public function admin_options() {
        echo '<h2>Banorte VCE (Lightbox)</h2>';
        echo '<p>Usa tu microservicio Java/WS para cifrar y abrir el lightbox.</p>';
        echo '<table class="form-table">';
        $this->generate_settings_html();
        echo '</table>';
    }

    public function is_available() {
        return 'yes' === $this->enabled;
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            wc_add_notice('Pedido no válido.', 'error');
            return array('result' => 'fail');
        }

        $payload = array(
            'order_id'      => $order->get_id(),
            'order_key'     => $order->get_order_key(),
            'amount'        => (float) $order->get_total(),
            'currency'      => $order->get_currency(),
            'customer_email'=> $order->get_billing_email(),
            'merchantId'    => $this->merchantId,
            'affiliateId'   => $this->affiliateId,
            'user'          => $this->user,
            'branch'        => $this->branch,
            'terminalId'    => $this->terminalId,
            'merchantName'  => $this->merchantName,
            'merchantCity'  => $this->merchantCity,
            'lang'          => $this->lang,
            'environment'   => $this->environment,
            'return_ok'     => $this->get_return_url($order),
            'return_cancel' => wc_get_checkout_url(),
        );

        $payload = apply_filters('banorte_vce_payload', $payload, $order);
        set_transient('banorte_vce_payload_' . $order->get_id(), $payload, MINUTE_IN_SECONDS * 10);
        update_post_meta($order->get_id(), '_banorte_vce_payload', $payload);

        $redirect = add_query_arg(array(
            'banorte_vce_pay' => '1',
            'order_id'        => $order->get_id(),
            'key'             => $order->get_order_key()
        ), home_url('/'));

        $order->update_status('pending', 'Esperando confirmación de pago Banorte VCE.');

        return array(
            'result'   => 'success',
            'redirect' => $redirect
        );
    }

    public function ajax_encrypt() {
        // Validar order
        $order_id = isset($_REQUEST['order_id']) ? absint($_REQUEST['order_id']) : 0;
        $key      = isset($_REQUEST['key']) ? wc_clean(wp_unslash($_REQUEST['key'])) : '';

        $order = $order_id ? wc_get_order($order_id) : false;
        if (!$order && $key && function_exists('wc_get_order_id_by_order_key')) {
            $resolved_id = wc_get_order_id_by_order_key($key);
            if ($resolved_id) {
                $order_id = absint($resolved_id);
                $order = wc_get_order($order_id);
            }
        }
        if (!$order) {
            wp_send_json_error(array('msg' => 'Pedido no encontrado'), 400);
        }

        $strict = apply_filters('banorte_vce_strict_key_check', true, $order_id);
        if ($strict && $key && $order->get_order_key() !== $key) {
            wp_send_json_error(array('msg' => 'Pedido inválido (key mismatch)'), 400);
        }

        // Payload: transient -> meta
        $payload = get_transient('banorte_vce_payload_' . $order_id);
        if (!$payload) {
            $payload = get_post_meta($order_id, '_banorte_vce_payload', true);
        }
        if (!$payload || !is_array($payload)) {
            wp_send_json_error(array('msg' => 'Payload expirado o ausente'), 410);
        }

        $json   = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $base64 = base64_encode($json);

        $endpoint = trim($this->java_url);

        // Si el endpoint es un PHP (wrapper), usar JSON {"base64": "..."}
        if (preg_match('#\.php($|\?)#i', $endpoint)) {
            $resp = wp_remote_post($endpoint, array(
                'timeout' => 25,
                'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
                'body'    => wp_json_encode(array('base64' => $base64), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ));
            if (is_wp_error($resp)) {
                wp_send_json_error(array('msg' => 'Error llamando wsCifrado.php', 'err' => $resp->get_error_message(), 'code' => 0), 502);
            }
            $code = (int) wp_remote_retrieve_response_code($resp);
            $body = wp_remote_retrieve_body($resp);
            if ($code >= 400 || !$body) {
                wp_send_json_error(array('msg' => 'wsCifrado error', 'code' => $code, 'err' => substr($body, 0, 500)), 502);
            }
            $j = json_decode($body, true);
            $params = '';
            if (is_array($j) && isset($j['data'][0]) && is_string($j['data'][0])) {
                $params = $j['data'][0];
            } elseif (is_array($j) && isset($j['data'][0]) && is_array($j['data'][0])) {
                $inner = $j['data'][0];
                if (isset($inner['params']) && is_string($inner['params'])) { $params = $inner['params']; }
                if (!$params && isset($inner['data']) && is_string($inner['data'])) { $params = $inner['data']; }
            }
            if ($params && strpos($params, ':::') !== false) {
                $params = preg_replace('/^null:::/', '', $params);
            }
            if (!$params || strpos($params, ':::') === false) {
                wp_send_json_error(array('msg' => 'Respuesta del wsCifrado inesperada', 'raw' => substr($body, 0, 500)), 502);
            }
            list($sub1, $sub2) = explode(':::', $params, 2);
            $lightboxSrc = ($this->environment === 'AUT') ? $this->endpointJs_AUT : $this->endpointJs_PRD;
            wp_send_json_success(array(
                'mode'        => 'sub1sub2',
                'sub1'        => trim($sub1),
                'sub2'        => trim($sub2),
                'lightboxSrc' => $lightboxSrc
            ));
        }

        // JAVA: requiere el .cer
        $cert_path = trim($this->cert_path);
        $cert_pem  = '';

        if (preg_match('#^https?://#i', $cert_path)) {
            $resp_c = wp_remote_get($cert_path, array('timeout' => 15));
            if (is_wp_error($resp_c)) {
                wp_send_json_error(array('msg' => 'No se pudo descargar el certificado', 'error' => $resp_c->get_error_message()), 500);
            }
            $code_c = (int) wp_remote_retrieve_response_code($resp_c);
            $body_c = wp_remote_retrieve_body($resp_c);
            if ($code_c >= 400 || empty($body_c)) {
                wp_send_json_error(array('msg' => 'Certificado no descargado', 'code' => $code_c), 500);
            }
            $cert_pem = $body_c;
        } else {
            if (!file_exists($cert_path)) {
                $maybe = trailingslashit(ABSPATH) . ltrim($cert_path, '/\\');
                if (file_exists($maybe)) {
                    $cert_path = $maybe;
                }
            }
            if (!file_exists($cert_path)) {
                wp_send_json_error(array('msg' => 'Certificado no encontrado: ' . $cert_path), 500);
            }
            $cert_pem = file_get_contents($cert_path);
        }

        if (!$cert_pem) {
            wp_send_json_error(array('msg' => 'Contenido de certificado vacío'), 500);
        }

        // Llamar JAR
        $ch = curl_init($endpoint);
        $post = array(
            'base64'        => $base64,
            'pubKeyStrCert' => $cert_pem,
            'rsaPublicKey'  => $cert_pem
        );
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $post,
            CURLOPT_TIMEOUT        => 25,
        ));
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp === false || $code >= 400) {
            wp_send_json_error(array('msg' => 'Error cifrando en Java', 'err' => $err, 'code' => $code), 502);
        }

        $sub1 = '';
        $sub2 = '';
        if (strpos($resp, ':::') !== false) {
            list($sub1, $sub2) = explode(':::', $resp, 2);
        } else {
            $decoded = json_decode($resp, true);
            if (is_array($decoded) && isset($decoded['token'], $decoded['post_url'])) {
                wp_send_json_success(array(
                    'mode'     => 'token_post',
                    'token'    => $decoded['token'],
                    'post_url' => $decoded['post_url']
                ));
            }
        }

        if (!$sub1 || !$sub2) {
            wp_send_json_error(array('msg' => 'Respuesta del cifrado inesperada', 'raw' => substr($resp, 0, 500)), 500);
        }

        $lightboxSrc = ($this->environment === 'AUT') ? $this->endpointJs_AUT : $this->endpointJs_PRD;
        wp_send_json_success(array(
            'mode'        => 'sub1sub2',
            'sub1'        => trim($sub1),
            'sub2'        => trim($sub2),
            'lightboxSrc' => $lightboxSrc
        ));
    }

    public function handle_callback() {
        $order_id = isset($_REQUEST['order_id']) ? absint($_REQUEST['order_id']) : 0;
        $status   = isset($_REQUEST['status']) ? sanitize_text_field($_REQUEST['status']) : '';
        $order    = wc_get_order($order_id);

        if ($order) {
            switch (strtoupper($status)) {
                case 'A':
                    $order->payment_complete();
                    $order->add_order_note('Banorte: Pago aprobado (A).');
                    break;
                case 'D':
                case 'R':
                case 'T':
                default:
                    $order->update_status('failed', 'Banorte: Pago no aprobado (' . $status . ').');
                    break;
            }
        }

        if ($order && $order->is_paid()) {
            wp_safe_redirect($this->get_return_url($order));
        } else {
            wc_add_notice('Pago cancelado o rechazado.', 'error');
            wp_safe_redirect(wc_get_checkout_url());
        }
        exit;
    }
}
