<?php
// test-debug.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test Debug</h1>";
echo "<p>PHP Version: " . phpversion() . "</p>";

// Test config file
$config_file = dirname(__FILE__) . '/config.php';
echo "<p>Config file: " . $config_file . "</p>";
echo "<p>Config exists: " . (file_exists($config_file) ? 'YES' : 'NO') . "</p>";

if (file_exists($config_file)) {
    $config = require $config_file;
    echo "<h2>Config Content:</h2>";
    echo "<pre>" . htmlspecialchars(print_r($config, true)) . "</pre>";
    
    echo "<h2>Constants:</h2>";
    echo "<p>BANORTE_PLUGIN_PATH: " . (defined('BANORTE_PLUGIN_PATH') ? BANORTE_PLUGIN_PATH : 'NOT DEFINED') . "</p>";
    echo "<p>BANORTE_PLUGIN_URL: " . (defined('BANORTE_PLUGIN_URL') ? BANORTE_PLUGIN_URL : 'NOT DEFINED') . "</p>";
}

// Test server variables
echo "<h2>Server Info:</h2>";
echo "<p>DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'NOT SET') . "</p>";
echo "<p>SERVER_NAME: " . ($_SERVER['SERVER_NAME'] ?? 'NOT SET') . "</p>";
echo "<p>HTTPS: " . ($_SERVER['HTTPS'] ?? 'NOT SET') . "</p>";

// Test file paths
echo "<h2>File Path Tests:</h2>";
$vendor_dir = dirname(__FILE__) . '/../vendor/';
echo "<p>Vendor dir: " . $vendor_dir . "</p>";
echo "<p>Vendor exists: " . (file_exists($vendor_dir) ? 'YES' : 'NO') . "</p>";

if (file_exists($vendor_dir)) {
    $cert_file = $vendor_dir . 'multicobros.cer';
    echo "<p>Cert file: " . $cert_file . "</p>";
    echo "<p>Cert exists: " . (file_exists($cert_file) ? 'YES' : 'NO') . "</p>";
}

echo "<p>Test completed at: " . date('Y-m-d H:i:s') . "</p>";
?>