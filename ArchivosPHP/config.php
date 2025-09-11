<?php
// config.php

return [
    // ===== Datos del comercio =====
    // merchantId asignado por Banorte
    'affiliateId'   => '9709884',
    // terminal asociada al merchant
    'terminalId'    => '97098841',
    'merchantName'  => 'INSCRIP MARATHON MTY',
    'merchantCity'  => 'Monterrey',
    'lang'          => 'ES',

    // ===== Usuario del orquestador (motor) =====
    // Credenciales de ejecuci�n para el lightbox (ambiente indicado en 'mode')
    'user'          => 'ANGIE',
    'password'      => 'Mar?toN2!5', // 

    // ===== Ambiente =====
    // 'AUT' para pruebas (UAT) o 'PRD' para producci�n
    'mode'          => 'AUT',

    // ===== Scripts del lightbox por ambiente =====
    // Si Banorte te dio URLs distintas para AUT/PRD, col�calas aqu�.
    // Si usan la misma, d�jalas iguales.
    'jqueryJs'      => 'https://multicobros.banorte.com/orquestador/resources/js/jquery-3.3.1.js',
    'endpointJs_AUT'=> 'https://multicobros.banorte.com/orquestador/lightbox/checkoutV2.js',
    'endpointJs_PRD'=> 'https://multicobros.banorte.com/orquestador/lightbox/checkoutV2.js',

    // ===== URL de retorno de tu sitio =====
    'returnUrl'     => 'https://maratonmonterrey.mx/prueba/banorte/callback.php',

    // ===== Certificado p�blico de Multicobros (RSA) =====
    // Ruta absoluta/relativa al .cer vigente
    'certPath'      => __DIR__ . '/multicobros.cer',

    // ===== Microservicio Java de cifrado (local) =====
    'javaServiceUrl'=> 'http://127.0.0.1:8888',

    // ===== Debug opcional =====
    'debug'         => true,
];
