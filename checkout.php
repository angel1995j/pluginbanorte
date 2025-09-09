<?php
// checkout.php - VERSIÓN FUNCIONAL COMPLETA
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Cargar WordPress core para tener acceso a funciones
if (!function_exists('get_option')) {
    require_once ABSPATH . 'wp-load.php';
}

// Cargar configuración
$config_file = dirname(__FILE__) . '/config.php';
if (file_exists($config_file)) {
    $config = require $config_file;
} else {
    die('Error: Archivo de configuración no encontrado.');
}

// Datos de la orden
$order_id = $_GET['order_id'] ?? 'temp_' . time();
$amount = isset($_GET['amount']) ? floatval($_GET['amount']) : 1.00;

// Preparar datos para Banorte
$order_data = [
    'merchantId'    => $config['affiliateId'],
    'name'          => $config['user'],
    'password'      => $config['password'],
    'mode'          => strtoupper($config['mode']),
    'controlNumber' => 'ORDER_' . $order_id,
    'terminalId'    => $config['terminalId'],
    'amount'        => number_format($amount, 2, '.', ''),
    'merchantName'  => $config['merchantName'],
    'merchantCity'  => $config['merchantCity'],
    'lang'          => $config['lang'],
    'email'         => 'cliente@ejemplo.com',
    'returnURL'     => $config['returnUrl'] . '?order_id=' . $order_id
];

// Para debug
$order_debug = $order_data;
$order_debug['password'] = '*******'; // Ocultar password
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Pago con Banorte - Lightbox</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- jQuery de Banorte -->
    <script src="<?= $config['jqueryJs'] ?>"></script>
    
    <!-- Lightbox de Banorte -->
    <script src="<?= $config['endpointJs_AUT'] ?>"></script>
    
    <style>
        body { 
            font-family: Arial, sans-serif; 
            padding: 20px; 
            background: #f5f5f5; 
            text-align: center; 
        }
        .container { 
            max-width: 600px; 
            margin: 0 auto; 
            background: white; 
            padding: 30px; 
            border-radius: 10px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
        }
        h1 { 
            color: #2c3e50; 
            margin-bottom: 20px; 
        }
        .btn-pagar { 
            background: #3498db; 
            color: white; 
            padding: 15px 30px; 
            border: none; 
            border-radius: 5px; 
            font-size: 18px; 
            cursor: pointer; 
            margin: 20px 0; 
        }
        .btn-pagar:hover { 
            background: #2980b9; 
        }
        .info-box { 
            background: #f8f9fa; 
            padding: 15px; 
            border-radius: 5px; 
            margin: 15px 0; 
            text-align: left; 
        }
        .debug-info { 
            display: none; 
            background: #fff3cd; 
            padding: 15px; 
            border-radius: 5px; 
            margin: 15px 0; 
            text-align: left; 
            font-size: 12px; 
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>💰 Pago con Banorte</h1>
        
        <div class="info-box">
            <strong>Orden:</strong> #<?= $order_id ?><br>
            <strong>Monto:</strong> $<?= number_format($amount, 2) ?><br>
            <strong>Comercio:</strong> <?= $config['merchantName'] ?><br>
            <strong>Ambiente:</strong> <?= $config['mode'] ?>
        </div>

        <button class="btn-pagar" onclick="iniciarPagoBanorte()">
            🚀 Pagar con Banorte
        </button>

        <p>Serás redirigido al portal seguro de Banorte para completar tu pago.</p>

        <div class="debug-info" id="debugInfo">
            <strong>Debug Information:</strong><br>
            <pre><?= json_encode($order_debug, JSON_PRETTY_PRINT) ?></pre>
        </div>

        <button onclick="document.getElementById('debugInfo').style.display='block'">
            Mostrar Info Debug
        </button>
    </div>

    <script>
    // Función para iniciar el pago con Banorte
    function iniciarPagoBanorte() {
        console.log('Iniciando pago con Banorte...');
        
        // Verificar que la librería de Banorte esté cargada
        if (typeof Payment === 'undefined') {
            alert('Error: La librería de Banorte no se cargó correctamente. Recarga la página.');
            return;
        }

        if (typeof Payment.startPayment === 'undefined') {
            alert('Error: La función startPayment no está disponible. Verifica la versión del SDK.');
            return;
        }

        // Preparar datos para cifrado
        const orderData = <?= json_encode($order_data) ?>;
        
        console.log('Datos de la orden:', orderData);
        
        // Mostrar loading
        const btn = document.querySelector('.btn-pagar');
        const originalText = btn.innerHTML;
        btn.innerHTML = '⏳ Procesando...';
        btn.disabled = true;

        // Enviar datos al servidor para cifrado
        fetch('wsCifrado.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                base64: btoa(JSON.stringify(orderData))
            })
        })
        .then(response => response.json())
        .then(data => {
            console.log('Respuesta de cifrado:', data);
            
            if (data.code === '200' && data.data && data.data[0]) {
                const paramsString = data.data[0];
                console.log('Parámetros cifrados:', paramsString);
                
                // Iniciar el pago con Banorte
                Payment.startPayment({
                    Params: paramsString,
                    onSuccess: function(response) {
                        console.log('Pago exitoso:', response);
                        alert('✅ Pago realizado con éxito!');
                        // Redirigir a página de éxito
                        window.location.href = '<?= $config['returnUrl'] ?>?status=success&order_id=<?= $order_id ?>';
                    },
                    onError: function(error) {
                        console.error('Error en pago:', error);
                        alert('❌ Error en el pago: ' + (error.message || 'Error desconocido'));
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    },
                    onCancel: function() {
                        console.log('Pago cancelado por el usuario');
                        alert('⚠️ Pago cancelado');
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    },
                    onClosed: function() {
                        console.log('Ventana cerrada');
                    }
                });
                
            } else {
                throw new Error(data.message || 'Error en el servidor de cifrado');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al procesar el pago: ' + error.message);
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    }

    // Verificar automáticamente si las librerías se cargaron
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM cargado, verificando librerías...');
        
        if (typeof Payment !== 'undefined') {
            console.log('✅ Librería Banorte cargada correctamente');
            console.log('Payment object:', Payment);
        } else {
            console.error('❌ Librería Banorte NO cargada');
        }
        
        if (typeof jQuery !== 'undefined') {
            console.log('✅ jQuery cargado correctamente');
        } else {
            console.error('❌ jQuery NO cargado');
        }
    });

    // Función para probar el modal de Banorte
    function testModal() {
        if (typeof Payment === 'undefined') {
            alert('La librería de Banorte no está cargada');
            return;
        }
        
        // Test simple del modal
        alert('La librería de Banorte está funcionando. Ahora se abrirá el lightbox de pago.');
    }
    </script>
</body>
</html>