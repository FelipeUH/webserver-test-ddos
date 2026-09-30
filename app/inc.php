<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

session_set_cookie_params([
  'lifetime' => 0, 'path' => '/', 'httponly' => true,
  'samesite' => 'Strict', 'secure' => !empty($_SERVER['HTTPS']),
]);
session_start();

const TIPOS = ['Servidor', 'Router', 'Switch', 'Firewall', 'PC', 'Otro'];

function db(): PDO {
  static $pdo = null;
  if (!$pdo) {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]);
  }
  return $pdo;
}

function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function csrf_token(): string {
  if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
  return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void {
  if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(403); exit('Solicitud no válida (token CSRF).');
  }
}

function require_login(): void {
  if (empty($_SESSION['uid'])) { header('Location: login.php'); exit; }
}

/** Valida los datos de un equipo. Devuelve [datos, errores]. */
function validar_equipo(array $in): array {
  $d = [
    'nombre'    => trim($in['nombre'] ?? ''),
    'tipo'      => $in['tipo'] ?? '',
    'ip'        => trim($in['ip'] ?? ''),
    'ubicacion' => trim($in['ubicacion'] ?? ''),
  ];
  $err = [];
  if ($d['nombre'] === '' || mb_strlen($d['nombre']) > 100) $err[] = 'El nombre es obligatorio (máx. 100 caracteres).';
  if (!in_array($d['tipo'], TIPOS, true))                    $err[] = 'Elige un tipo de equipo de la lista.';
  if (!filter_var($d['ip'], FILTER_VALIDATE_IP))              $err[] = 'La dirección IP no es válida.';
  if (mb_strlen($d['ubicacion']) > 100)                       $err[] = 'La ubicación admite máx. 100 caracteres.';
  return [$d, $err];
}

function head_html(string $titulo, bool $nav = true): void { ?>
<!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($titulo) ?> · Inventario de equipos</title>
<link rel="stylesheet" href="style.css">
</head><body>
<?php if ($nav): ?>
<header class="bar">
  <strong>Inventario de equipos</strong>
  <form method="post" action="logout.php" class="inline">
    <span><?= e($_SESSION['usuario'] ?? '') ?></span>
    <?= csrf_field() ?><button class="link">Cerrar sesión</button>
  </form>
</header>
<?php endif; ?>
<main>
<?php }

function foot_html(): void { echo "</main></body></html>"; }

function errores_html(array $err): void {
  if ($err) { echo '<ul class="errores" role="alert">'; foreach ($err as $m) echo '<li>' . e($m) . '</li>'; echo '</ul>'; }
}

function form_equipo(array $d, string $boton): void { ?>
  <label>Nombre <input name="nombre" maxlength="100" required value="<?= e($d['nombre'] ?? '') ?>"></label>
  <label>Tipo
    <select name="tipo" required>
      <?php foreach (TIPOS as $t): ?>
        <option <?= ($d['tipo'] ?? '') === $t ? 'selected' : '' ?>><?= e($t) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Dirección IP <input name="ip" required placeholder="10.18.30.20" value="<?= e($d['ip'] ?? '') ?>"></label>
  <label>Ubicación <input name="ubicacion" maxlength="100" value="<?= e($d['ubicacion'] ?? '') ?>"></label>
  <button class="primary"><?= e($boton) ?></button>
<?php }
