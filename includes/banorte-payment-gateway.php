<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WC_Payment_Gateway')) {
    return;
}

class Banorte_WC_Gateway extends WC_Payment_Gateway {
    
    // Declarar todas las propiedades para evitar warnings en PHP 8.2+
    public $environment;
    public $merchant_id;
    public $terminal_id;
    public $merchant_name;
    public $merchant_city;
    public $banorte_user;
    public $banorte_password;
    public $debug_mode;
    
    public function __construct() {
        $this->id = 'banorte';
        $this->icon = '';
        $this->has_fields = false;
        $this->method_title = 'Banorte';
        $this->method_description = 'Procesamiento de pagos mediante Banorte Lightbox (usando .jar de Java para cifrado)';
        
        // Cargar configuración
        $this->init_form_fields();
        $this->init_settings();
        
        // Variables de configuración - ASIGNAR VALORES CORRECTAMENTE
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->environment = $this->get_option('environment', 'AUT');
        $this->merchant_id = $this->get_option('merchant_id', '9709884');
        $this->terminal_id = $this->get_option('terminal_id', '97098841');
        $this->merchant_name = $this->get_option('merchant_name', 'INSCRIP MARATHON MTY');
        $this->merchant_city = $this->get_option('merchant_city', 'Monterrey');
        $this->banorte_user = $this->get_option('banorte_user', 'ANGIE');
        $this->banorte_password = $this->get_option('banorte_password', 'Mar?toN2!5');
        $this->debug_mode = $this->get_option('debug_mode', 'yes');
        
        // Hook para guardar configuración
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        
        // Añadir descripción del método de pago
        add_action('woocommerce_after_checkout_form', array($this, 'add_payment_description'));
        
        // Hook para procesar el retorno de Banorte
        add_action('woocommerce_api_banorte_return', array($this, 'handle_banorte_return'));
        
        // Debug info
        add_action('admin_notices', array($this, 'admin_debug_info'));
    }
    
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Habilitar/Deshabilitar', 'banorte'),
                'type' => 'checkbox',
                'label' => __('Habilitar Banorte', 'banorte'),
                'default' => 'yes',
                'description' => __('Activar o desactivar el método de pago Banorte', 'banorte'),
                'desc_tip' => true
            ),
            'title' => array(
                'title' => __('Título', 'banorte'),
                'type' => 'text',
                'description' => __('Título que el usuario verá durante el checkout', 'banorte'),
                'default' => __('Tarjeta de Crédito/Débito (Banorte)', 'banorte'),
                'desc_tip' => true
            ),
            'description' => array(
                'title' => __('Descripción', 'banorte'),
                'type' => 'textarea',
                'description' => __('Descripción que el usuario verá durante el checkout', 'banorte'),
                'default' => __('Paga de forma segura con tu tarjeta de crédito o débito mediante Banorte. Serás redirigido al portal seguro de Banorte para completar tu pago.', 'banorte'),
                'desc_tip' => true
            ),
            
            'environment_settings' => array(
                'title' => __('Configuración de Ambiente', 'banorte'),
                'type' => 'title',
                'description' => __('Configuración del ambiente de Banorte', 'banorte'),
            ),
            'environment' => array(
                'title' => __('Ambiente', 'banorte'),
                'type' => 'select',
                'options' => array(
                    'AUT' => __('Pruebas (AUT)', 'banorte'),
                    'PRD' => __('Producción (PRD)', 'banorte')
                ),
                'default' => 'AUT',
                'description' => __('Selecciona el ambiente de trabajo', 'banorte'),
                'desc_tip' => true
            ),
            
            'merchant_settings' => array(
                'title' => __('Datos del Comercio', 'banorte'),
                'type' => 'title',
                'description' => __('Información proporcionada por Banorte', 'banorte'),
            ),
            'merchant_id' => array(
                'title' => __('Merchant ID', 'banorte'),
                'type' => 'text',
                'description' => __('ID del comercio proporcionado por Banorte', 'banorte'),
                'default' => '9709884',
                'desc_tip' => true
            ),
            'terminal_id' => array(
                'title' => __('Terminal ID', 'banorte'),
                'type' => 'text',
                'description' => __('ID del terminal proporcionado por Banorte', 'banorte'),
                'default' => '97098841',
                'desc_tip' => true
            ),
            'merchant_name' => array(
                'title' => __('Nombre del Comercio', 'banorte'),
                'type' => 'text',
                'description' => __('Nombre que aparecerá en los estados de cuenta', 'banorte'),
                'default' => 'INSCRIP MARATHON MTY',
                'desc_tip' => true
            ),
            'merchant_city' => array(
                'title' => __('Ciudad del Comercio', 'banorte'),
                'type' => 'text',
                'description' => __('Ciudad donde se encuentra el comercio', 'banorte'),
                'default' => 'Monterrey',
                'desc_tip' => true
            ),
            
            'credentials_settings' => array(
                'title' => __('Credenciales', 'banorte'),
                'type' => 'title',
                'description' => __('Credenciales de acceso al sistema Banorte', 'banorte'),
            ),
            'banorte_user' => array(
                'title' => __('Usuario Banorte', 'banorte'),
                'type' => 'text',
                'description' => __('Usuario proporcionado por Banorte', 'banorte'),
                'default' => 'ANGIE',
                'desc_tip' => true
            ),
            'banorte_password' => array(
                'title' => __('Contraseña Banorte', 'banorte'),
                'type' => 'password',
                'description' => __('Contraseña proporcionada por Banorte', 'banorte'),
                'default' => 'Mar?toN2!5',
                'desc_tip' => true
            ),
            
            'advanced_settings' => array(
                'title' => __('Configuración Avanzada', 'banorte'),
                'type' => 'title',
                'description' => __('Configuración avanzada del gateway', 'banorte'),
            ),
            'debug_mode' => array(
                'title' => __('Modo Debug', 'banorte'),
                'type' => 'checkbox',
                'label' => __('Habilitar modo debug', 'banorte'),
                'default' => 'yes',
                'description' => __('Activar logs de depuración para troubleshooting', 'banorte'),
                'desc_tip' => true
            )
        );
    }
    
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            wc_add_notice(__('Error: No se pudo encontrar la orden.', 'banorte'), 'error');
            return array('result' => 'failure');
        }
        
        // Marcar como pendiente de pago
        $order->update_status('pending', __('Esperando pago con Banorte', 'banorte'));
        
        // Guardar metadata importante
        $order->update_meta_data('_banorte_payment_initiated', time());
        $order->update_meta_data('_banorte_environment', $this->environment);
        $order->save();
        
        // Vaciar carrito
        WC()->cart->empty_cart();
        
        // URL de retorno para Banorte
        $return_url = WC()->api_request_url('banorte_return');
        
        // Construir URL de checkout de Banorte
        $checkout_url = add_query_arg(array(
            'order_id' => $order_id,
            'return_url' => urlencode($return_url),
            'merchant_id' => $this->merchant_id,
            'terminal_id' => $this->terminal_id
        ), BANORTE_PLUGIN_URL . 'checkout.php');

        if ($this->debug_mode === 'yes') {
            error_log('Banorte Payment Initiated - Order: ' . $order_id . ' - Redirect: ' . $checkout_url);
        }

        return array(
            'result' => 'success',
            'redirect' => $checkout_url
        );
    }
    
    public function payment_fields() {
        if ($this->description) {
            echo wpautop(wptexturize($this->description));
        }
        
        // Mostrar información adicional si está en modo debug
        if ($this->debug_mode === 'yes' && current_user_can('manage_options')) {
            echo '<div style="border:1px solid #ffba00; background:#fff8e5; padding:10px; margin:10px 0; border-radius:4px;">';
            echo '<strong>🔧 Modo Debug Activado (Solo visible para administradores)</strong><br>';
            echo 'Merchant ID: ' . esc_html($this->merchant_id) . '<br>';
            echo 'Terminal ID: ' . esc_html($this->terminal_id) . '<br>';
            echo 'Ambiente: ' . esc_html($this->environment) . '<br>';
            echo 'URL de Checkout: ' . BANORTE_PLUGIN_URL . 'checkout.php';
            echo '</div>';
        }
    }
    
    public function add_payment_description() {
        echo '<div id="banorte-payment-description" style="display: none; margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px; border-left: 4px solid #0073aa;">';
        echo '<p><strong>💡 Información importante sobre Banorte:</strong></p>';
        echo '<p>Al seleccionar Banorte, serás redirigido al portal seguro de Banorte para completar tu pago. No se almacena información de tu tarjeta en nuestro sistema.</p>';
        echo '<p>Este proceso utiliza cifrado seguro mediante servicio Java para proteger tus datos.</p>';
        echo '<p><strong>⚠️ No cierres la ventana del navegador durante el proceso de pago.</strong></p>';
        echo '</div>';
        
        echo '<script>
        jQuery(document).ready(function($) {
            // Mostrar/ocultar información de Banorte
            function toggleBanorteInfo() {
                if ($("input[name=\'payment_method\']:checked").val() === "banorte") {
                    $("#banorte-payment-description").slideDown(300);
                } else {
                    $("#banorte-payment-description").slideUp(300);
                }
            }
            
            // Inicializar
            toggleBanorteInfo();
            
            // Escuchar cambios
            $("input[name=\'payment_method\']").change(function() {
                toggleBanorteInfo();
            });
        });
        </script>';
    }
    
    public function handle_banorte_return() {
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $auth_code = isset($_GET['auth_code']) ? sanitize_text_field($_GET['auth_code']) : '';
        $error_message = isset($_GET['error_message']) ? sanitize_text_field($_GET['error_message']) : '';
        
        if ($order_id && $order = wc_get_order($order_id)) {
            switch ($status) {
                case 'A': // Aprobado
                    $order->payment_complete($auth_code);
                    $order->add_order_note(__('Pago aprobado por Banorte. Código de autorización: ', 'banorte') . $auth_code);
                    wp_redirect($this->get_return_url($order));
                    break;
                    
                case 'D': // Declinado
                    $order->update_status('failed', __('Pago declinado por Banorte. ', 'banorte') . $error_message);
                    wc_add_notice(__('Pago declinado: ', 'banorte') . $error_message, 'error');
                    wp_redirect(wc_get_checkout_url());
                    break;
                    
                case 'E': // Error
                    $order->update_status('failed', __('Error en el pago con Banorte. ', 'banorte') . $error_message);
                    wc_add_notice(__('Error en el pago: ', 'banorte') . $error_message, 'error');
                    wp_redirect(wc_get_checkout_url());
                    break;
                    
                case 'C': // Cancelado
                    $order->update_status('cancelled', __('Pago cancelado por el usuario.', 'banorte'));
                    wc_add_notice(__('Pago cancelado.', 'banorte'), 'notice');
                    wp_redirect(wc_get_checkout_url());
                    break;
                    
                default:
                    $order->update_status('on-hold', __('Estado de pago desconocido de Banorte.', 'banorte'));
                    wc_add_notice(__('Estado de pago desconocido. Por favor contacte al soporte.', 'banorte'), 'error');
                    wp_redirect(wc_get_checkout_url());
                    break;
            }
        } else {
            wc_add_notice(__('Error: No se pudo procesar el retorno del pago.', 'banorte'), 'error');
            wp_redirect(wc_get_checkout_url());
        }
        
        exit;
    }
    
    public function admin_debug_info() {
        global $current_screen;
        
        // Solo mostrar en la página de configuración de Banorte
        if ($current_screen->id === 'woocommerce_page_wc-settings' && isset($_GET['section']) && $_GET['section'] === 'banorte') {
            echo '<div class="notice notice-info">';
            echo '<p><strong>ℹ️ Información de Debug Banorte:</strong></p>';
            echo '<ul>';
            echo '<li>Plugin Path: ' . BANORTE_PLUGIN_PATH . '</li>';
            echo '<li>Plugin URL: ' . BANORTE_PLUGIN_URL . '</li>';
            echo '<li>Checkout File: ' . BANORTE_PLUGIN_PATH . 'checkout.php</li>';
            echo '<li>Checkout URL: ' . BANORTE_PLUGIN_URL . 'checkout.php</li>';
            echo '<li>Java Service: http://127.0.0.1:8888</li>';
            echo '</ul>';
            echo '</div>';
        }
    }
    
    // Función importante: verificar disponibilidad
    public function is_available() {
        $is_available = ($this->enabled === 'yes');
        
        // Verificar requisitos adicionales
        if ($is_available) {
            // Verificar que tengamos los datos mínimos requeridos
            if (empty($this->merchant_id) || empty($this->terminal_id)) {
                $is_available = false;
            }
            
            // En producción, requerir credenciales completas
            if ($this->environment === 'PRD' && (empty($this->banorte_user) || empty($this->banorte_password))) {
                $is_available = false;
            }
        }
        
        return apply_filters('woocommerce_valid_' . $this->id . '_settings', $is_available);
    }
    
    // Validar campos antes de guardar
    public function validate_fields() {
        if ($this->environment === 'PRD') {
            if (empty($this->merchant_id) || empty($this->terminal_id)) {
                WC_Admin_Settings::add_error(__('Error: Merchant ID y Terminal ID son requeridos para producción.', 'banorte'));
                return false;
            }
            
            if (empty($this->banorte_user) || empty($this->banorte_password)) {
                WC_Admin_Settings::add_error(__('Error: Usuario y contraseña de Banorte son requeridos para producción.', 'banorte'));
                return false;
            }
        }
        
        return true;
    }
}

// ✅ REGISTRAR EL GATEWAY
add_filter('woocommerce_payment_gateways', 'register_banorte_gateway');

function register_banorte_gateway($gateways) {
    $gateways[] = 'Banorte_WC_Gateway';
    return $gateways;
}