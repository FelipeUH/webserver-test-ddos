<?php
require __DIR__ . '/inc.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
csrf_check();                                           // DELETE
db()->prepare('DELETE FROM equipos WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
$_SESSION['msg'] = 'Equipo eliminado.';
header('Location: index.php');
