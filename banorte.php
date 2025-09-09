<?php
/**
 * Plugin Name: Banorte Payment Gateway
 * Plugin URI: https://github.com/angel1995j/pluginbanorte
 * Description: Plugin de pago con Banorte - Lightbox
 * Version: 1.0.0
 * Author: Angel
 */

if (!defined('ABSPATH')) {
    exit;
}

// DEFINIR CONSTANTES PRIMERO - antes de cualquier otra cosa
define('BANORTE_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('BANORTE_PLUGIN_URL', plugin_dir_url(__FILE__));

// Función helper para detectar páginas de pago
function is_checkout_pay_page() {
    global $wp;
    return (is_checkout() && !empty($wp->query_vars['order-pay']));
}

// Incluir el gateway directamente
add_action('plugins_loaded', 'init_banorte_gateway', 0);

function init_banorte_gateway() {
    // Verificar que WooCommerce esté activo
    if (!class_exists('WC_Payment_Gateway')) {
        add_action('admin_notices', 'banorte_woocommerce_missing_notice');
        return;
    }
    
    // Incluir la clase del gateway
    require_once BANORTE_PLUGIN_PATH . 'includes/banorte-payment-gateway.php';
}

function banorte_woocommerce_missing_notice() {
    echo '<div class="error"><p>';
    echo sprintf(
        __('Banorte Gateway requiere que WooCommerce esté instalado y activado. %s', 'banorte'),
        '<a href="' . admin_url('plugin-install.php?tab=search&s=woocommerce&plugin-search-input=Search+Plugins') . '">' . __('Instalar WooCommerce', 'banorte') . '</a>'
    );
    echo '</p></div>';
}

// Clase principal simplificada
class BanortePaymentGateway {
    
    public static function init() {
        // Enqueue scripts
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
        
        // Hook de activación
        register_activation_hook(__FILE__, array(__CLASS__, 'install'));
    }
    
    public static function enqueue_scripts() {
        // Solo cargar en páginas de checkout y order-pay
        if (is_checkout() || is_wc_endpoint_url('order-pay')) {
            // Cargar jQuery de Banorte
            wp_enqueue_script('banorte-jquery', 'https://multicobros.banorte.com/orquestador/resources/js/jquery-3.3.1.js', array(), '3.3.1', true);
            
            // Cargar checkoutV2.js de Banorte
            wp_enqueue_script('banorte-checkoutv2', 'https://multicobros.banorte.com/orquestador/lightbox/checkoutV2.js', array('banorte-jquery'), '1.0.0', true);
            
            // Cargar nuestro script de integración
            wp_enqueue_script('banorte-checkout', BANORTE_PLUGIN_URL . 'assets/js/banorte-checkout.js', array('banorte-jquery', 'banorte-checkoutv2'), '1.0.0', true);
            
            // Localizar scripts con datos necesarios
            self::localize_scripts();
        }
    }
    
    private static function localize_scripts() {
        global $wp, $woocommerce;
        
        $order_id = null;
        $order_data = array();

        // Intentar obtener el ID de la orden desde múltiples fuentes
        if (is_checkout_pay_page() && !empty($wp->query_vars['order-pay'])) {
            // Página de pago de orden
            $order_id = absint($wp->query_vars['order-pay']);
        } elseif (isset($_GET['order_id'])) {
            // Desde parámetro GET
            $order_id = absint($_GET['order_id']);
        } elseif (WC()->session && WC()->session->get('order_awaiting_payment')) {
            // Desde sesión de WooCommerce
            $order_id = absint(WC()->session->get('order_awaiting_payment'));
        }

        // Si estamos en el proceso de checkout, obtener el order_id temporal
        if (!$order_id && is_checkout() && !is_wc_endpoint_url('order-received')) {
            // Crear un order_id temporal para redirección
            $order_id = 'temp_' . time();
        }

        // Preparar datos de la orden si existe
        if ($order_id && is_numeric($order_id)) {
            $order = wc_get_order($order_id);
            if ($order) {
                $order_data = array(
                    'id' => $order_id,
                    'total' => $order->get_total(),
                    'currency' => $order->get_currency(),
                    'status' => $order->get_status()
                );
            }
        }

        // Datos para localización
        $localize_data = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('banorte_nonce'),
            'order_id' => $order_id,
            'order_data' => $order_data,
            'checkout_url' => BANORTE_PLUGIN_URL . 'includes/checkout.php',
            'callback_url' => BANORTE_PLUGIN_URL . 'includes/callback.php',
            'java_service_url' => 'http://127.0.0.1:8888',
            'is_checkout' => is_checkout(),
            'is_order_pay' => is_checkout_pay_page(),
            'current_url' => $_SERVER['REQUEST_URI']
        );

        wp_localize_script('banorte-checkout', 'banorte_ajax', $localize_data);
    }
    
    public static function install() {
        // Configuración por defecto
        $default_options = array(
            'banorte_environment' => 'AUT',
            'banorte_merchant_id' => '9709884',
            'banorte_terminal_id' => '97098841',
            'banorte_merchant_name' => 'INSCRIP MARATHON MTY',
            'banorte_merchant_city' => 'Monterrey',
            'banorte_user' => 'ANGIE',
            'banorte_password' => 'Mar?toN2!5'
        );
        
        foreach ($default_options as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
        
        // Crear directorios necesarios
        if (!file_exists(BANORTE_PLUGIN_PATH . 'includes')) {
            wp_mkdir_p(BANORTE_PLUGIN_PATH . 'includes');
        }
        if (!file_exists(BANORTE_PLUGIN_PATH . 'assets/js')) {
            wp_mkdir_p(BANORTE_PLUGIN_PATH . 'assets/js');
        }
        if (!file_exists(BANORTE_PLUGIN_PATH . 'vendor')) {
            wp_mkdir_p(BANORTE_PLUGIN_PATH . 'vendor');
        }
    }
}

// Inicializar
BanortePaymentGateway::init();

// Shortcode para diagnóstico
function banorte_diagnostic_shortcode() {
    global $wp, $woocommerce;
    
    ob_start();
    ?>
    <div style="border:1px solid #ccc; padding:20px; margin:20px 0; background:#f9f9f9;">
        <h3>Banorte Diagnostic Information</h3>
        
        <h4>System Info:</h4>
        <p><strong>WooCommerce Active:</strong> <?php echo class_exists('WC_Payment_Gateway') ? 'Yes' : 'No'; ?></p>
        <p><strong>Gateway Class Exists:</strong> <?php echo class_exists('Banorte_WC_Gateway') ? 'Yes' : 'No'; ?></p>
        <p><strong>Plugin Path:</strong> <?php echo BANORTE_PLUGIN_PATH; ?></p>
        <p><strong>Plugin URL:</strong> <?php echo BANORTE_PLUGIN_URL; ?></p>
        
        <h4>Order ID Detection:</h4>
        <p><strong>URL order-pay:</strong> <?php echo !empty($wp->query_vars['order-pay']) ? $wp->query_vars['order-pay'] : 'Not found'; ?></p>
        <p><strong>GET order_id:</strong> <?php echo isset($_GET['order_id']) ? $_GET['order_id'] : 'Not found'; ?></p>
        <p><strong>Session order_awaiting_payment:</strong> <?php echo (WC()->session && WC()->session->get('order_awaiting_payment')) ? WC()->session->get('order_awaiting_payment') : 'Not found'; ?></p>
        
        <h4>Current Page Info:</h4>
        <p><strong>is_checkout():</strong> <?php echo is_checkout() ? 'Yes' : 'No'; ?></p>
        <p><strong>is_order_pay_page():</strong> <?php echo is_checkout_pay_page() ? 'Yes' : 'No'; ?></p>
        <p><strong>Current URL:</strong> <?php echo $_SERVER['REQUEST_URI']; ?></p>
        
        <h4>Available Payment Gateways:</h4>
        <?php if (class_exists('WC_Payment_Gateway')) : ?>
            <ul>
            <?php 
            $gateways = WC()->payment_gateways->payment_gateways();
            foreach ($gateways as $id => $gateway) : ?>
                <li><strong><?php echo $id; ?>:</strong> <?php echo $gateway->enabled; ?></li>
            <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>WooCommerce not available</p>
        <?php endif; ?>
        
        <h4>JavaScript Localization Data:</h4>
        <pre><?php 
        $localize_data = array(
            'order_id' => !empty($wp->query_vars['order-pay']) ? $wp->query_vars['order-pay'] : (isset($_GET['order_id']) ? $_GET['order_id'] : 'Not found'),
            'is_checkout' => is_checkout(),
            'is_order_pay' => is_checkout_pay_page(),
            'current_url' => $_SERVER['REQUEST_URI']
        );
        echo json_encode($localize_data, JSON_PRETTY_PRINT); 
        ?></pre>
        
        <h4>Session Data:</h4>
        <pre><?php 
        if (WC()->session) {
            $session_data = WC()->session->get_session_data();
            echo "Session exists. Data length: " . strlen($session_data);
        } else {
            echo "No WooCommerce session";
        }
        ?></pre>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('banorte_diagnostic', 'banorte_diagnostic_shortcode');

// Shortcode para debug avanzado
function banorte_debug_info() {
    global $wp, $woocommerce;
    
    ob_start();
    ?>
    <div style="border:1px solid #ccc; padding:20px; margin:20px 0; background:#fff3cd;">
        <h3>🛠️ Banorte Advanced Debug</h3>
        
        <h4>🔍 Order ID Detection Tests:</h4>
        <?php
        $tests = array(
            'URL order-pay' => !empty($wp->query_vars['order-pay']) ? $wp->query_vars['order-pay'] : 'Not found',
            'GET order_id' => isset($_GET['order_id']) ? $_GET['order_id'] : 'Not found',
            'Session order_awaiting_payment' => (WC()->session && WC()->session->get('order_awaiting_payment')) ? WC()->session->get('order_awaiting_payment') : 'Not found',
            'is_checkout()' => is_checkout() ? 'Yes' : 'No',
            'is_order_pay_page()' => is_checkout_pay_page() ? 'Yes' : 'No'
        );
        
        foreach ($tests as $test => $result) {
            echo "<p><strong>{$test}:</strong> {$result}</p>";
        }
        ?>
        
        <h4>📋 WooCommerce Status:</h4>
        <p><strong>WC Version:</strong> <?php echo defined('WC_VERSION') ? WC_VERSION : 'Not available'; ?></p>
        <p><strong>Cart Contents:</strong> <?php echo (WC()->cart && WC()->cart->get_cart_contents_count()) ? WC()->cart->get_cart_contents_count() . ' items' : 'Empty'; ?></p>
        
        <h4>🔧 Plugin Constants:</h4>
        <p><strong>BANORTE_PLUGIN_PATH:</strong> <?php echo BANORTE_PLUGIN_PATH; ?></p>
        <p><strong>BANORTE_PLUGIN_URL:</strong> <?php echo BANORTE_PLUGIN_URL; ?></p>
        
        <h4>🌐 Server Environment:</h4>
        <p><strong>PHP Version:</strong> <?php echo phpversion(); ?></p>
        <p><strong>WordPress Version:</strong> <?php echo get_bloginfo('version'); ?></p>
        <p><strong>SSL Enabled:</strong> <?php echo is_ssl() ? 'Yes' : 'No'; ?></p>
        
        <h4>📊 JavaScript Ready Test:</h4>
        <button onclick="testBanorteJS()" style="padding:10px 15px; background:#0073aa; color:white; border:none; border-radius:4px; cursor:pointer;">
            Test JavaScript Integration
        </button>
        <div id="banorte-js-test" style="margin-top:10px; padding:10px; border:1px solid #ddd; display:none;"></div>
        
        <script>
        function testBanorteJS() {
            var testDiv = document.getElementById('banorte-js-test');
            testDiv.innerHTML = '<p>Testing JavaScript integration...</p>';
            testDiv.style.display = 'block';
            
            try {
                // Test jQuery
                if (typeof jQuery !== 'undefined') {
                    testDiv.innerHTML += '<p>✅ jQuery is loaded</p>';
                } else {
                    testDiv.innerHTML += '<p>❌ jQuery is NOT loaded</p>';
                }
                
                // Test Banorte AJAX object
                if (typeof banorte_ajax !== 'undefined') {
                    testDiv.innerHTML += '<p>✅ banorte_ajax object is available</p>';
                    testDiv.innerHTML += '<pre>Order ID: ' + (banorte_ajax.order_id || 'Not found') + '</pre>';
                } else {
                    testDiv.innerHTML += '<p>❌ banorte_ajax object is NOT available</p>';
                }
                
                // Test Payment library
                if (typeof Payment !== 'undefined') {
                    testDiv.innerHTML += '<p>✅ Banorte Payment library is loaded</p>';
                } else {
                    testDiv.innerHTML += '<p>❌ Banorte Payment library is NOT loaded</p>';
                }
                
            } catch (error) {
                testDiv.innerHTML += '<p>❌ JavaScript Error: ' + error.message + '</p>';
            }
        }
        </script>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('banorte_debug', 'banorte_debug_info');

// Función para crear un order_id de prueba
function banorte_create_test_order() {
    if (!current_user_can('manage_options')) {
        return '⚠️ Admin access required';
    }
    
    ob_start();
    ?>
    <div style="border:1px solid #28a745; padding:20px; margin:20px 0; background:#d4edda;">
        <h3>🧪 Banorte Test Order Creator</h3>
        
        <form method="post">
            <input type="hidden" name="banorte_create_test" value="1">
            <button type="submit" style="padding:10px 15px; background:#28a745; color:white; border:none; border-radius:4px; cursor:pointer;">
                Create Test Order
            </button>
        </form>
        
        <?php
        if (isset($_POST['banorte_create_test']) && class_exists('WC_Order')) {
            try {
                // Crear una orden de prueba
                $order = wc_create_order();
                $order->add_product(wc_get_product(wc_get_product_ids_by_sku('')[0] ?? 0), 1);
                $order->set_total(100.00);
                $order->save();
                
                echo '<div style="margin-top:15px; padding:10px; background:#c3e6cb; border:1px solid #28a745;">';
                echo '<p>✅ Test order created successfully!</p>';
                echo '<p><strong>Order ID:</strong> ' . $order->get_id() . '</p>';
                echo '<p><strong>Order Total:</strong> $' . $order->get_total() . '</p>';
                echo '<p><strong>Order Status:</strong> ' . $order->get_status() . '</p>';
                echo '<p><a href="' . $order->get_checkout_payment_url() . '" style="color:#155724;">Go to Payment Page</a></p>';
                echo '</div>';
                
            } catch (Exception $e) {
                echo '<div style="margin-top:15px; padding:10px; background:#f8d7da; border:1px solid #dc3545;">';
                echo '<p>❌ Error creating test order: ' . $e->getMessage() . '</p>';
                echo '</div>';
            }
        }
        ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('banorte_test_order', 'banorte_create_test_order');