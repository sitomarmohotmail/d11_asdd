<?php
// Endpoint de escritura para el editor inline del dashboard ASDD.
// Solo acepta ficheros NNN-*.md en specs/ — sin path traversal.
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['ok' => false, 'error' => 'Method not allowed']));
}

$data    = json_decode(file_get_contents('php://input'), true);
$file    = $data['file']    ?? '';
$content = $data['content'] ?? '';

if (!preg_match('/^\d{3}-[a-z0-9_-]+\.md$/i', $file)) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'error' => 'Nombre de fichero no válido']));
}

$path = '/var/www/d11assd/specs/' . $file;

if (!file_exists($path)) {
    http_response_code(404);
    exit(json_encode(['ok' => false, 'error' => 'Fichero no encontrado']));
}

if (file_put_contents($path, $content) === false) {
    http_response_code(500);
    exit(json_encode(['ok' => false, 'error' => 'Error al escribir. Comprueba permisos de www-data sobre specs/']));
}

exec('bash /var/www/d11assd/scripts/gen-specs-json.sh > /dev/null 2>&1 &');

exit(json_encode(['ok' => true]));
