<?php
require __DIR__ . '/inc.php';
require_login();

$err = []; $msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {           // CREATE
  csrf_check();
  [$d, $err] = validar_equipo($_POST);
  if (!$err) {
    db()->prepare('INSERT INTO equipos (nombre, tipo, ip, ubicacion) VALUES (?,?,?,?)')
        ->execute([$d['nombre'], $d['tipo'], $d['ip'], $d['ubicacion']]);
    $_SESSION['msg'] = 'Equipo guardado.';
    header('Location: index.php'); exit;
  }
}
$msg = $_SESSION['msg'] ?? ''; unset($_SESSION['msg']);
$equipos = db()->query('SELECT * FROM equipos ORDER BY id DESC')->fetchAll();  // READ

head_html('Equipos'); ?>
<?php if ($msg): ?><p class="ok" role="status"><?= e($msg) ?></p><?php endif; ?>
<section>
  <h2>Registrar equipo</h2>
  <?php errores_html($err); ?>
  <form method="post" class="grid"><?= csrf_field() ?><?php form_equipo($_POST ?: [], 'Guardar equipo'); ?></form>
</section>
<section>
  <h2>Equipos registrados (<?= count($equipos) ?>)</h2>
  <?php if (!$equipos): ?>
    <p>Aún no hay equipos. Registra el primero con el formulario de arriba.</p>
  <?php else: ?>
  <div class="tabla"><table>
    <thead><tr><th>Nombre</th><th>Tipo</th><th>IP</th><th>Ubicación</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($equipos as $q): ?>
      <tr>
        <td><?= e($q['nombre']) ?></td><td><?= e($q['tipo']) ?></td>
        <td><?= e($q['ip']) ?></td><td><?= e($q['ubicacion']) ?></td>
        <td class="acciones">
          <a href="editar.php?id=<?= (int)$q['id'] ?>">Editar</a>
          <form method="post" action="eliminar.php" class="inline"
                onsubmit="return confirm('¿Eliminar este equipo?')">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$q['id'] ?>">
            <button class="link danger">Eliminar</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
<?php foot_html();
