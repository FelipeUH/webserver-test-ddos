<?php
require __DIR__ . '/inc.php';
require_login();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$st = db()->prepare('SELECT * FROM equipos WHERE id = ?');
$st->execute([$id]);
$q = $st->fetch();
if (!$q) { http_response_code(404); exit('Equipo no encontrado.'); }

$err = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {           // UPDATE
  csrf_check();
  [$d, $err] = validar_equipo($_POST);
  if (!$err) {
    db()->prepare('UPDATE equipos SET nombre=?, tipo=?, ip=?, ubicacion=? WHERE id=?')
        ->execute([$d['nombre'], $d['tipo'], $d['ip'], $d['ubicacion'], $id]);
    $_SESSION['msg'] = 'Cambios guardados.';
    header('Location: index.php'); exit;
  }
  $q = $d;
}
head_html('Editar equipo'); ?>
<section>
  <h2>Editar equipo</h2>
  <?php errores_html($err); ?>
  <form method="post" class="grid">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
    <?php form_equipo($q, 'Guardar cambios'); ?>
  </form>
  <p><a href="index.php">Volver al listado</a></p>
</section>
<?php foot_html();
