<?php
require __DIR__ . '/inc.php';
if (!empty($_SESSION['uid'])) { header('Location: index.php'); exit; }

$err = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  if (($_SESSION['fallos'] ?? 0) >= 5) { sleep(3); } // frena la fuerza bruta básica
  $st = db()->prepare('SELECT id, usuario, password_hash FROM usuarios WHERE usuario = ?');
  $st->execute([trim($_POST['usuario'] ?? '')]);
  $u = $st->fetch();
  if ($u && password_verify($_POST['password'] ?? '', $u['password_hash'])) {
    session_regenerate_id(true);
    $_SESSION['uid'] = $u['id'];
    $_SESSION['usuario'] = $u['usuario'];
    $_SESSION['fallos'] = 0;
    header('Location: index.php'); exit;
  }
  $_SESSION['fallos'] = ($_SESSION['fallos'] ?? 0) + 1;
  $err[] = 'Usuario o contraseña incorrectos.';
}
head_html('Iniciar sesión', false); ?>
<section class="login">
  <h1>Inventario de equipos</h1>
  <p>Inicia sesión para gestionar los equipos de la red.</p>
  <?php errores_html($err); ?>
  <form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <label>Usuario <input name="usuario" required autofocus></label>
    <label>Contraseña <input name="password" type="password" required></label>
    <button class="primary">Iniciar sesión</button>
  </form>
</section>
<?php foot_html();
