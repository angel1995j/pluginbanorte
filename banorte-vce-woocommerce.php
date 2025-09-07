<?php
/**
 * Plugin Name: Banorte VCE Payment Gateway
 * Description: Pasarela de pago Banorte Ventana de Comercio Electrónico para WooCommerce
 * Version: 1.0.0
 * Author: Tu Nombre
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', 'init_banorte_vce_gateway');
function init_banorte_vce_gateway() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    class WC_Banorte_VCE_Gateway extends WC_Payment_Gateway {
        public function __construct() {
            $this->id = 'banorte_vce';
            $this->icon = '';
            $this->has_fields = false;
            $this->method_title = 'Banorte VCE';
            $this->method_description = 'Paga con tarjeta de crédito/débito mediante Banorte';

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->merchant_id = $this->get_option('merchant_id');
            $this->terminal_id = $this->get_option('terminal_id');
            $this->user = $this->get_option('user');
            $this->password = $this->get_option('password');
            $this->mode = $this->get_option('mode');
            $this->merchant_name = $this->get_option('merchant_name');
            $this->merchant_city = $this->get_option('merchant_city');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
            add_action('woocommerce_api_banorte_vce_response', array($this, 'handle_response'));
        }

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'Habilitar/Deshabilitar',
                    'type' => 'checkbox',
                    'label' => 'Habilitar Banorte VCE',
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => 'Título',
                    'type' => 'text',
                    'description' => 'Título que el usuario verá durante el checkout',
                    'default' => 'Tarjeta de Crédito/Débito (Banorte)',
                    'desc_tip' => true
                ),
                'description' => array(
                    'title' => 'Descripción',
                    'type' => 'textarea',
                    'description' => 'Descripción del método de pago',
                    'default' => 'Paga de forma segura con tu tarjeta Banorte'
                ),
                'merchant_id' => array(
                    'title' => 'Merchant ID',
                    'type' => 'text',
                    'description' => 'ID de comercio proporcionado por Banorte'
                ),
                'terminal_id' => array(
                    'title' => 'Terminal ID',
                    'type' => 'text',
                    'description' => 'ID de terminal proporcionado por Banorte'
                ),
                'user' => array(
                    'title' => 'Usuario',
                    'type' => 'text',
                    'description' => 'Usuario para autenticación'
                ),
                'password' => array(
                    'title' => 'Contraseña',
                    'type' => 'password',
                    'description' => 'Contraseña para autenticación'
                ),
                'mode' => array(
                    'title' => 'Modo',
                    'type' => 'select',
                    'options' => array(
                        'AUT' => 'Pruebas (AUT)',
                        'PRD' => 'Producción (PRD)'
                    ),
                    'default' => 'AUT'
                ),
                'merchant_name' => array(
                    'title' => 'Nombre del Comercio',
                    'type' => 'text',
                    'default' => get_bloginfo('name')
                ),
                'merchant_city' => array(
                    'title' => 'Ciudad del Comercio',
                    'type' => 'text',
                    'default' => 'Ciudad'
                )
            );
        }

        public function enqueue_scripts() {
            if (is_checkout() && $this->is_available()) {
                $mode = $this->mode;
                $endpoint_js = ($mode === 'PRD') ? 
                    'https://multicobros.banorte.com/orquestador/lightbox/checkoutV2.js' :
                    'https://multicobros.banorte.com/orquestador/lightbox/checkoutV2.js';

                wp_enqueue_script('jquery');
                wp_enqueue_script('banorte-checkout', $endpoint_js, array('jquery'), null, true);
                wp_enqueue_script('banorte-vce-handler', plugin_dir_url(__FILE__) . 'assets/js/banorte-vce.js', array('jquery', 'banorte-checkout'), '1.0.0', true);
                
                wp_localize_script('banorte-vce-handler', 'banorte_vce_params', array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('banorte_vce_nonce'),
                    'checkout_url' => wc_get_checkout_url()
                ));
            }
        }

        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
            return array(
                'result' => 'success',
                'redirect' => $order->get_checkout_payment_url(true)
            );
        }

        public function receipt_page($order_id) {
            echo '<div id="banorte-vce-container">';
            echo '<p>Conectando con Banorte…</p>';
            echo '<div id="banorte-vce-messages"></div>';
            echo '</div>';
            
            // Incluir el template del lightbox
            include plugin_dir_path(__FILE__) . 'templates/lightbox-template.php';
            
            // Pasar datos a JavaScript
            wp_localize_script('banorte-vce-handler', 'banorte_order_data', array(
                'order_id' => $order_id,
                'amount' => WC()->cart->total,
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('banorte_process_payment_' . $order_id)
            ));
        }

        public function handle_response() {
            $status = $_GET['status'] ?? '';
            $order_id = $_GET['order_id'] ?? '';
            $control_number = $_GET['control'] ?? '';
            
            if ($order_id) {
                $order = wc_get_order($order_id);
                
                if ($status === 'A') {
                    $order->payment_complete($control_number);
                    $order->add_order_note('Pago aprobado por Banorte. Número de control: ' . $control_number);
                    wp_redirect($this->get_return_url($order));
                } else {
                    $order->update_status('failed', 'Pago rechazado por Banorte');
                    wc_add_notice('El pago fue rechazado. Por favor intenta con otro método.', 'error');
                    wp_redirect(wc_get_checkout_url());
                }
                exit;
            }
        }

        public function is_available() {
            return parent::is_available() && 
                   !empty($this->merchant_id) && 
                   !empty($this->terminal_id) && 
                   !empty($this->user) && 
                   !empty($this->password);
        }
    }

    function add_banorte_vce_gateway($methods) {
        $methods[] = 'WC_Banorte_VCE_Gateway';
        return $methods;
    }
    add_filter('woocommerce_payment_gateways', 'add_banorte_vce_gateway');
}