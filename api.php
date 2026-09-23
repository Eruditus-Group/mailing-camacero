<?php
/**
 * api.php — API del Boletín Diario CAMACERO (MySQL Hostinger)
 * Endpoints (POST, JSON): action=login|save|list|delete|clear
 * save/delete/clear requieren token emitido por login (Authorization: Bearer <token>).
 */
require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function issuerJson($code, $data) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function base64urlEncode($s) { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function base64urlDecode($s) { return base64_decode(strtr($s, '-_', '+/')); }

function signToken($user, $exp) {
    $payload = base64urlEncode($user . '|' . $exp);
    $sig = hash_hmac('sha256', $payload, AUTH_SECRET);
    return $payload . '.' . $sig;
}

function verifyToken($token) {
    $parts = explode('.', (string)$token);
    if (count($parts) !== 2) return false;
    $expected = hash_hmac('sha256', $parts[0], AUTH_SECRET);
    if (!hash_equals($expected, $parts[1])) return false;
    $decoded = base64urlDecode($parts[0]);
    $pos = strrpos($decoded, '|');
    if ($pos === false) return false;
    $exp = (int)substr($decoded, $pos + 1);
    $user = substr($decoded, 0, $pos);
    return ($user === ADMIN_USER && $exp > time());
}

function bearerToken() {
    $h = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    if ($h !== '' && preg_match('/Bearer\s+(\S+)/i', $h, $m)) return $m[1];
    foreach (getallheaders() as $k => $v) {
        if (strtolower($k) === 'authorization' && preg_match('/Bearer\s+(\S+)/i', $v, $m)) return $m[1];
    }
    return '';
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
    );
} catch (Exception $e) {
    issuerJson(500, array('ok' => false, 'error' => 'DB: revise config.php (' . $e->getMessage() . ')'));
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

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) $body = array();

$action = isset($body['action']) ? (string)$body['action'] : (isset($_GET['action']) ? (string)$_GET['action'] : '');

switch ($action) {
    case 'login': {
        $user = isset($body['user']) ? (string)$body['user'] : '';
        $pass = isset($body['pass']) ? (string)$body['pass'] : '';
        if ($user === '' || $pass === '') {
            issuerJson(400, array('ok' => false, 'error' => 'faltan usuario/contraseña'));
        }
        if (!hash_equals(ADMIN_USER, $user) || !password_verify($pass, ADMIN_PASS_HASH)) {
            issuerJson(401, array('ok' => false, 'error' => 'usuario o contraseña incorrectos'));
        }
        $exp = time() + 7 * 86400;
        issuerJson(200, array('ok' => true, 'token' => signToken($user, $exp), 'user' => $user));
    }

    case 'save': {
        if (!verifyToken(bearerToken())) issuerJson(401, array('ok' => false, 'error' => 'no autorizado: haz login'));
        $fecha = isset($body['fecha']) ? trim((string)$body['fecha']) : '';
        $iso   = isset($body['fecha_iso']) ? trim((string)$body['fecha_iso']) : date('Y-m-d');
        $hora  = isset($body['hora']) ? trim((string)$body['hora']) : '';
        $html  = isset($body['html']) ? (string)$body['html'] : '';
        if ($fecha === '' || $html === '') {
            issuerJson(400, array('ok' => false, 'error' => 'faltan datos (fecha/html)'));
        }
        $stmt = $pdo->prepare(
            'INSERT INTO boletines (fecha, fecha_iso, hora, html, actualizado_en)
             VALUES (:f, :iso, :h, :x, NOW())
             ON DUPLICATE KEY UPDATE hora = VALUES(hora), html = VALUES(html), actualizado_en = NOW()'
        );
        $stmt->execute(array(':f' => $fecha, ':iso' => $iso, ':h' => $hora, ':x' => $html));
        issuerJson(200, array('ok' => true));
    }

    case 'list': {
        $rows = $pdo->query('SELECT fecha, fecha_iso, hora, html FROM boletines ORDER BY fecha_iso DESC, actualizado_en DESC')
                    ->fetchAll(PDO::FETCH_ASSOC);
        issuerJson(200, array('ok' => true, 'rows' => $rows));
    }

    case 'delete': {
        if (!verifyToken(bearerToken())) issuerJson(401, array('ok' => false, 'error' => 'no autorizado: haz login'));
        $fecha = isset($body['fecha']) ? trim((string)$body['fecha']) : '';
        if ($fecha === '') {
            issuerJson(400, array('ok' => false, 'error' => 'falta fecha'));
        }
        $stmt = $pdo->prepare('DELETE FROM boletines WHERE fecha = :f');
        $stmt->execute(array(':f' => $fecha));
        issuerJson(200, array('ok' => true));
    }

    case 'clear': {
        if (!verifyToken(bearerToken())) issuerJson(401, array('ok' => false, 'error' => 'no autorizado: haz login'));
        $pdo->exec('DELETE FROM boletines');
        issuerJson(200, array('ok' => true));
    }

    default:
        issuerJson(400, array('ok' => false, 'error' => 'acción no válida'));
}