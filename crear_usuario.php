<?php
// Uso: php crear_usuario.php <usuario> <clave>   (solo por consola)
if (php_sapi_name() !== 'cli') { exit('Solo CLI'); }
require '/var/www/html/config.php';
[$_, $u, $p] = array_pad($argv, 3, null);
if (!$u || !$p) { exit("Uso: php crear_usuario.php usuario clave\n"); }
$pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS,
  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$st = $pdo->prepare('INSERT INTO usuarios (usuario, password_hash) VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)');
$st->execute([$u, password_hash($p, PASSWORD_DEFAULT)]);
echo "Usuario '$u' listo.\n";
