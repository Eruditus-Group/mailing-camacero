<?php
/**
 * ver-boletin.php — Página PÚBLICA de cada boletín guardado (sin login).
 * Uso:
 *   ver-boletin.php?fecha=2026-09-23   -> muestra el boletín de esa fecha
 *   ver-boletin.php                    -> listado de todos los boletines publicados
 */
require __DIR__ . '/config.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
    );
} catch (Exception $e) {
    http_response_code(500);
    echo '<p>Error de conexión a la base de datos.</p>';
    exit;
}

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS boletines (
      fecha VARCHAR(80) NOT NULL,
      fecha_iso DATE NOT NULL,
      hora VARCHAR(20) NOT NULL DEFAULT '',
      html LONGTEXT NOT NULL,
      actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (fecha),
      KEY idx_iso (fecha_iso)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$fecha = isset($_GET['fecha']) ? trim((string)$_GET['fecha']) : '';

if ($fecha !== '') {
    $stmt = $pdo->prepare('SELECT fecha, hora, html FROM boletines WHERE fecha_iso = :f LIMIT 1');
    $stmt->execute(array(':f' => $fecha));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo $row['html'];
        exit;
    }
    http_response_code(404);
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Boletín no encontrado</title></head>
          <body style="font-family:system-ui;background:#eef1f4;color:#1a2a3a;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;">
          <div style="text-align:center;"><h1>404</h1><p>No existe un boletín publicado con la fecha <b>' . htmlspecialchars($fecha) . '</b>.</p>
          <a href="ver-boletin.php">← Ver todos los boletines</a></div></body></html>';
    exit;
}

$rows = $pdo->query('SELECT fecha, fecha_iso, hora FROM boletines ORDER BY fecha_iso DESC, actualizado_en DESC')
            ->fetchAll(PDO::FETCH_ASSOC);

echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Boletines CAMACERO — Publicados</title>
<style>
  body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;background:#eef1f4;color:#1a2a3a;margin:0;padding:32px 16px;}
  main{max-width:640px;margin:0 auto;}
  h1{font-size:20px;color:#1a2a3a;border-bottom:3px solid #DD6420;padding-bottom:10px;}
  h1 span{color:#DD6420;}
  ul{list-style:none;padding:0;}
  li{background:#fff;border-radius:10px;box-shadow:0 1px 3px rgba(26,42,58,.1);margin-bottom:10px;}
  a{display:block;padding:14px 18px;color:#1a2a3a;text-decoration:none;font-weight:700;}
  a:hover{background:#f5f8fb;}
  .hint{color:#8b98a8;font-size:12px;display:block;}
  .none{background:#fff;border-radius:10px;padding:20px;text-align:center;color:#8b98a8;}
</style></head><body><main>
<h1>Boletín Diario <span>CAMACERO</span> — Publicados</h1>';

if (!$rows) {
    echo '<div class="none">Aún no hay boletines publicados.</div>';
} else {
    echo '<ul>';
    foreach ($rows as $r) {
        $u = 'ver-boletin.php?fecha=' . urlencode($r['fecha_iso']);
        $d = $r['fecha'];
        $h = $r['hora'];
        echo '<li><a href="' . $u . '">' . htmlspecialchars($d) . '<span class="hint">' . htmlspecialchars($h) . ' · Abrir en ventana</span></a></li>';
    }
    echo '</ul>';
}

echo '</main></body></html>';