=== Banorte VCE para WooCommerce ===
Conecta WooCommerce con Banorte Lightbox (VCE). Requiere microservicio Java escuchando en 127.0.0.1:8888/wsCifrado.

== Instalación ==
1. Sube la carpeta 'banorte-vce-woocommerce' a /wp-content/plugins/
2. Activa el plugin en WP-Admin.
3. WooCommerce > Ajustes > Pagos > Banorte VCE: Activa y configura credenciales.
4. Asegúrate de tener el servicio Java corriendo:
   ExecStart=/usr/bin/java -Dserver.address=127.0.0.1 -Dserver.port=8888 -jar /ruta/CifradoComponent-1.0.jar
5. ¡Listo!
