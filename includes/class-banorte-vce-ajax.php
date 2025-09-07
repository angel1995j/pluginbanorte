<?php
if (!defined('ABSPATH')) { exit; }

class Banorte_VCE_Ajax {

    public static function bootstrap() {
        add_action('wp_ajax_banorte_vce_encrypt', array(__CLASS__, 'handle_encrypt'));
        add_action('wp_ajax_nopriv_banorte_vce_encrypt', array(__CLASS__, 'handle_encrypt'));
    }

    /**
     * Localiza una instancia del gateway. Si no existe, intenta instanciarla.
     */
    protected static function get_gateway() {
        // 1) Si WC está disponible, intenta obtener el gateway registrado
        if (function_exists('WC')) {
            $pms = WC()->payment_gateways();
            if ($pms && method_exists($pms, 'get_available_payment_gateways')) {
                $gateways = $pms->get_available_payment_gateways();
                if (isset($gateways['banorte_vce'])) return $gateways['banorte_vce'];
            }
        }
        // 2) Cargar manualmente la clase e instanciar
        if (!class_exists('WC_Gateway_Banorte_VCE')) {
            $file = dirname(__FILE__) . '/class-wc-gateway-banorte-vce.php';
            if (file_exists($file)) require_once $file;
        }
        if (class_exists('WC_Gateway_Banorte_VCE')) {
            return new WC_Gateway_Banorte_VCE();
        }
        return null;
    }

    public static function handle_encrypt() {
        $gw = self::get_gateway();
        if (!$gw || !method_exists($gw, 'ajax_encrypt')) {
            wp_send_json_error(array('msg' => 'Gateway no disponible'), 500);
        }
        // Delegar la lógica al método del gateway
        $gw->ajax_encrypt();
    }
}
