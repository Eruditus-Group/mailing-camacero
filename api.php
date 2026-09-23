<?php
/**
 * api.php — API del Boletín Diario CAMACERO (MySQL Hostinger)
 * Endpoints (POST, JSON): action=save|list|delete|clear
 */
require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
    );
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array('ok' => false, 'error' => 'DB: revise config.php (' . $e->getMessage() . ')'));
    exit;
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) $body = array();

$action = isset($body['action']) ? (string)$body['action'] : (isset($_GET['action']) ? (string)$_GET['action'] : '');

switch ($action) {
    case 'save': {
        $fecha = isset($body['fecha']) ? trim((string)$body['fecha']) : '';
        $iso   = isset($body['fecha_iso']) ? trim((string)$body['fecha_iso']) : date('Y-m-d');
        $hora  = isset($body['hora']) ? trim((string)$body['hora']) : '';
        $html  = isset($body['html']) ? (string)$body['html'] : '';
        if ($fecha === '' || $html === '') {
            http_response_code(400);
            echo json_encode(array('ok' => false, 'error' => 'faltan datos (fecha/html)'));
            exit;
        }
        $stmt = $pdo->prepare(
            'INSERT INTO boletines (fecha, fecha_iso, hora, html, actualizado_en)
             VALUES (:f, :iso, :h, :x, NOW())
             ON DUPLICATE KEY UPDATE hora = VALUES(hora), html = VALUES(html), actualizado_en = NOW()'
        );
        $stmt->execute(array(':f' => $fecha, ':iso' => $iso, ':h' => $hora, ':x' => $html));
        echo json_encode(array('ok' => true));
        exit;
    }

    case 'list': {
        $rows = $pdo->query('SELECT fecha, fecha_iso, hora, html FROM boletines ORDER BY fecha_iso DESC, actualizado_en DESC')
                    ->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(array('ok' => true, 'rows' => $rows));
        exit;
    }

    case 'delete': {
        $fecha = isset($body['fecha']) ? trim((string)$body['fecha']) : '';
        if ($fecha === '') {
            http_response_code(400);
            echo json_encode(array('ok' => false, 'error' => 'falta fecha'));
            exit;
        }
        $stmt = $pdo->prepare('DELETE FROM boletines WHERE fecha = :f');
        $stmt->execute(array(':f' => $fecha));
        echo json_encode(array('ok' => true));
        exit;
    }

    case 'clear': {
        $pdo->exec('DELETE FROM boletines');
        echo json_encode(array('ok' => true));
        exit;
    }

    default:
        http_response_code(400);
        echo json_encode(array('ok' => false, 'error' => 'acción no válida'));
        exit;
}