<?php
// config.php

// Verificar si las constantes de WordPress están definidas
$wp_loaded = defined('ABSPATH');

if (!$wp_loaded) {
    // Si no estamos en contexto de WordPress, calcular paths manualmente
    $current_dir = dirname(__FILE__);
    $wp_content_dir = dirname(dirname(dirname($current_dir))); // Subir 3 niveles para llegar a wp-content
    
    // Calcular BANORTE_PLUGIN_PATH
    $banorte_plugin_path = $current_dir . '/';
    
    // Calcular BANORTE_PLUGIN_URL relativo
    $server_name = $_SERVER['SERVER_NAME'] ?? 'localhost';
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
    $protocol = $is_https ? 'https://' : 'http://';
    
    // Calcular la ruta relativa desde el documento root
    $doc_root = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $relative_path = str_replace($doc_root, '', $banorte_plugin_path);
    
    $banorte_plugin_url = $protocol . $server_name . $relative_path;
    
    define('BANORTE_PLUGIN_PATH', $banorte_plugin_path);
    define('BANORTE_PLUGIN_URL', $banorte_plugin_url);
    
} else {
    // Estamos en contexto de WordPress, usar las constantes normales
    if (!defined('BANORTE_PLUGIN_PATH')) {
        define('BANORTE_PLUGIN_PATH', plugin_dir_path(__FILE__));
    }
    if (!defined('BANORTE_PLUGIN_URL')) {
        define('BANORTE_PLUGIN_URL', plugin_dir_url(__FILE__));
    }
}

return [
    // ===== Datos del comercio =====
    'affiliateId'   => '9709884',
    'terminalId'    => '97098841',
    'merchantName'  => 'INSCRIP MARATHON MTY',
    'merchantCity'  => 'Monterrey',
    'lang'          => 'ES',

    // ===== Usuario del orquestador =====
    'user'          => 'ANGIE',
    'password'      => 'Mar?toN2!5',

    // ===== Ambiente =====
    'mode'          => 'AUT',

    // ===== Scripts del lightbox =====
    'jqueryJs'      => 'https://multicobros.banorte.com/orquestador/resources/js/jquery-3.3.1.js',
    'endpointJs_AUT'=> 'https://multicobros.banorte.com/orquestador/lightbox/checkoutV2.js',
    'endpointJs_PRD'=> 'https://multicobros.banorte.com/orquestador/lightbox/checkoutV2.js',

    // ===== URL de retorno =====
    'returnUrl'     => BANORTE_PLUGIN_URL . 'callback.php',

    // ===== Certificado público =====
    'certPath'      => BANORTE_PLUGIN_PATH . 'vendor/multicobros.cer',

    // ===== Microservicio Java .jar =====
    'javaServiceUrl'=> 'http://127.0.0.1:8888',

    // ===== Debug =====
    'debug'         => true,
];