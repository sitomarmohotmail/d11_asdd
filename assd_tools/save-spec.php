<?php
// Endpoint de escritura para el editor inline del dashboard ASDD.
// Solo acepta ficheros NNN-*.md en specs/ — sin path traversal.
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['ok' => false, 'error' => 'Method not allowed']));
}

$cfg_file = __DIR__ . '/config.json';
if (!file_exists($cfg_file)) {
    http_response_code(500);
    exit(json_encode(['ok' => false, 'error' => 'assd_tools/config.json no encontrado']));
}
$cfg      = json_decode(file_get_contents($cfg_file), true);
$specs_dir = realpath(__DIR__ . '/' . ($cfg['specs_dir'] ?? '../specs'));
$gen_script = realpath(__DIR__ . '/../scripts/gen-specs-json.sh');

$data    = json_decode(file_get_contents('php://input'), true);
$file    = $data['file']    ?? '';
$content = $data['content'] ?? '';

if (!preg_match('/^\d{3}-[a-z0-9_-]+\.md$/i', $file)) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'error' => 'Nombre de fichero no válido']));
}

$path = $specs_dir . '/' . $file;

if (!file_exists($path)) {
    http_response_code(404);
    exit(json_encode(['ok' => false, 'error' => 'Fichero no encontrado']));
}

if (file_put_contents($path, $content) === false) {
    http_response_code(500);
    exit(json_encode(['ok' => false, 'error' => 'Error al escribir. Comprueba permisos de www-data sobre specs/']));
}

if ($gen_script) {
    exec('bash ' . escapeshellarg($gen_script) . ' > /dev/null 2>&1 &');
}

exit(json_encode(['ok' => true]));
